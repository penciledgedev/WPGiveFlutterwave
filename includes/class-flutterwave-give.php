<?php

if (! defined('ABSPATH')) {
    exit;
}

class Flutterwave_Give
{

    public function __construct()
    {
        add_action('give_gateway_flutterwave', array($this, 'process_payment'));
    }

    public function process_payment($purchase_data)
    {
        // Retrieve the public and secret keys from settings
        $public_key = give_get_option('flutterwave_public_key');
        $secret_key = give_get_option('flutterwave_secret_key');

        // Payment processing logic (simplified for example)
        // You would integrate the Flutterwave API here

        // For this example, we'll assume payment is successful
        give_set_payment_transaction_id($purchase_data['payment_id'], 'FLW-TRANSACTION-ID');
        give_update_payment_status($purchase_data['payment_id'], 'complete');

        // Redirect to success page
        give_send_to_success_page();
    }
}
