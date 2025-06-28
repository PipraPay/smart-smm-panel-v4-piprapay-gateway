<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Piprapayapi {
    
    private $apiKey;
    private $apiBaseURL;
    
    public function __construct($apiKey = null, $apiBaseURL = null) {
        $this->apiKey = $apiKey;
        $this->apiBaseURL = rtrim($apiBaseURL, '/');
    }

    public function createCharge($requestData)
    {
        $url = $this->apiBaseURL . '/api/create-charge';
        $response = $this->sendRequest($url, $requestData);

        if (!isset($response['status']) || $response['status'] !== true) {
            throw new Exception($response['message'] ?? 'Failed to create payment');
        }

        if (!isset($response['pp_url'])) {
            throw new Exception('No payment URL received from piprapay');
        }

        return $response['pp_url'];
    }

    public function verifyPayment($invoiceId)
    {
        $url = $this->apiBaseURL . '/api/verify-payments';
        $data = ['pp_id' => $invoiceId];
        $response = $this->sendRequest($url, $data);

        return $response;
    }

    private function sendRequest($url, $data)
    {
        $headers = [
            'accept: application/json',
            'content-type: application/json',
            'mh-piprapay-api-key: ' . $this->apiKey
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        //throw new Exception($response);

        return json_decode($response, true);
    }
}