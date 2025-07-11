<?php
  $payment_elements = [
    [
      'label'      => form_label('API key'),
      'element'    => form_input(['name' => "payment_params[option][api_key]", 'value' => @$payment_option->api_key, 'type' => 'text', 'class' => $class_element]),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('API URL'),
      'element'    => form_input(['name' => "payment_params[option][api_url]", 'value' => @$payment_option->api_url, 'type' => 'text', 'class' => $class_element]),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('Currency Code'),
      'element'    => form_input(['name' => "payment_params[option][currency]", 'value' => @$payment_option->currency, 'type' => 'text', 'class' => $class_element]),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
  ];
  echo render_elements_form($payment_elements);
?>