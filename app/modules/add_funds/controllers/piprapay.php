<?php
defined('BASEPATH') or exit('No direct script access allowed');

class piprapay extends MX_Controller
{
    public $tb_users;
    public $tb_transaction_logs;
    public $tb_payments;
    public $tb_payments_bonuses;
    public $payment_type;
    public $payment_id;
    public $payment_lib;
    public $api_key;
    public $api_url;
    public $currency;
    public $take_fee_from_user;

    public function __construct($payment = "")
    {
        parent::__construct();
        $this->load->model('add_funds_model', 'model');

        $this->tb_users = USERS;
        $this->payment_type = 'piprapay';
        $this->tb_transaction_logs = TRANSACTION_LOGS;
        $this->tb_payments = PAYMENTS_METHOD;
        $this->tb_payments_bonuses = PAYMENTS_BONUSES;
        $this->currency_code = get_option("currency_code", "USD");
        
        if (!$payment) {
            $payment = $this->model->get('id, type, name, params', $this->tb_payments, ['type' => $this->payment_type]);
        }

        $this->payment_id = $payment->id;
        $params = $payment->params;
        $option = get_value($params, 'option');
        $this->take_fee_from_user = get_value($params, 'take_fee_from_user');
        
        // Get piprapay configuration
        $this->api_key = get_value($option, 'api_key');
        $this->api_url = get_value($option, 'api_url');
        $this->currency = get_value($option, 'currency');

        $this->load->library("piprapayapi");
        $this->payment_lib = new piprapayapi($this->api_key, $this->api_url);
    }

    public function index()
    {
        redirect(cn("add_funds"));
    }

    public function create_payment($data_payment = "")
    {
        _is_ajax($data_payment['module']);
        $amount = $data_payment['amount'];

        // Validate amount
        if (!$amount || $amount <= 0) {
            _validation('error', lang('invalid_amount'));
        }

        // Validate API credentials
        if (!$this->api_key || !$this->api_url || !$this->currency) {
            _validation('error', lang('payment_gateway_not_configured'));
        }

        // Get user details
        $user = $this->model->get('*', $this->tb_users, ['id' => session('uid')]);
        if (!$user) {
            _validation('error', lang('user_not_found'));
        }

        // Prepare payment data
        $unique_id = uniqid();
        
        $data = [
            "full_name"    => $user->first_name . ' ' . $user->last_name,
            "email_mobile" => $user->email,
            "amount"       => $amount,
            "currency"      => $this->currency,
            "metadata"     => [
                "invoice_id"   => $unique_id,
                "user_id"     => $user->id,
                "description"  => lang('deposit_to') . get_option('website_name'),
                "currency"    => $this->currency_code,
                "amount_usd"  => $amount
            ],
            "redirect_url" => cn("add_funds/piprapay/complete"),
            "return_type"  => 'GET',
            "cancel_url"   => cn("add_funds/unsuccess"),
            "webhook_url"  => cn("add_funds/piprapay/complete"),
        ];

        try {
            // Create transaction log
            $transaction_data = [
                "ids"            => ids(),
                "uid"            => $user->id,
                "type"           => $this->payment_type,
                "transaction_id" => $unique_id,
                "amount"         => $amount,
                "status"         => 0,
                "created"        => NOW,
            ];
            $this->db->insert($this->tb_transaction_logs, $transaction_data);
            $transaction_id = $this->db->insert_id();
            set_session("transaction_id", $transaction_id);

            // Initiate payment
            $paymentUrl = $this->payment_lib->createCharge($data);
            
            ms([
                'status' => 'success', 
                'redirect_url' => $paymentUrl
            ]);

        } catch (Exception $e) {
            _validation('error', $e->getMessage());
        }
    }

    public function complete()
    {
        $rawData = file_get_contents("php://input");
        if (empty($rawData)) {
            redirect(cn("add_funds"));
        }

        $data = json_decode($rawData, true);


          $headers = getallheaders();
        
          $received_api_key = '';
        
          if (isset($headers['mh-piprapay-api-key'])) {
              $received_api_key = $headers['mh-piprapay-api-key'];
          } elseif (isset($headers['Mh-Piprapay-Api-Key'])) {
              $received_api_key = $headers['Mh-Piprapay-Api-Key'];
          } elseif (isset($_SERVER['HTTP_MH_PIPRAPAY_API_KEY'])) {
              $received_api_key = $_SERVER['HTTP_MH_PIPRAPAY_API_KEY']; // fallback if needed
          }
        
          if ($received_api_key !== $this->api_key) {
            throw new Exception('Missing api in webhook');
          }

        if (empty($data['pp_id'])) {
            throw new Exception('Missing pp_id in webhook');
        }

        $result = $this->payment_lib->verifyPayment($data['pp_id']);
        $transaction = $this->model->get('*', $this->tb_transaction_logs, [
            'transaction_id' => $result['metadata']['invoice_id'],
            'status' => 0
        ]);

        if ($transaction) {
            $this->processPaymentResult($result, $transaction->id);
        }

        http_response_code(200);
        exit;
    }

    private function processPaymentResult($result, $transaction_id)
    {
        if (isset($result['status']) && $result['status'] === 'completed') {
            $amount = $result['metadata']['amount_usd'];

            $update_data = [
                "transaction_id" => $result['transaction_id'],
                "amount"         => $amount,
                "txn_fee"        => 0,
                "payer_email"    => $result['customer_email_mobile'],
                "status"         => 1,
            ];

            $this->db->update($this->tb_transaction_logs, $update_data, ['id' => $transaction_id]);

            // Update user balance
            $transaction = $this->model->get('*', $this->tb_transaction_logs, ['id' => $transaction_id]);
            $this->model->add_funds_bonus_email($transaction, $this->payment_id);

            redirect(cn("add_funds/success"));
        } else {
            redirect(cn("add_funds/unsuccess"));
        }
    }
}