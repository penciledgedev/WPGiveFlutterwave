<?php

/**
 * Plugin Name: Flutterwave for GiveWP
 * Description: A Flutterwave payment gateway integration for GiveWP.
 * Version: 1.0.0
 * Author: Penciledge LLC
 * Author URI: https://penciledge.net
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Define constants
define('FLUTTERWAVE_GIVE_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include necessary files
require_once FLUTTERWAVE_GIVE_PLUGIN_DIR . 'includes/class-flutterwave-give.php';

// Initialize the plugin
add_action('plugins_loaded', 'flutterwave_give_initialize');

function flutterwave_give_initialize()
{
    if (class_exists('Give')) {
        // Load the Flutterwave gateway class
        new Flutterwave_Give();
    }
}

// Add the settings to the GiveWP payment gateways
add_filter('give_payment_gateways', 'flutterwave_add_payment_gateway');

function flutterwave_add_payment_gateway($gateways) {
    $gateways['flutterwave'] = array(
        'admin_label'    => __('Flutterwave', 'flutterwave-give'),
        'checkout_label' => __('Flutterwave (Mobile Money)', 'flutterwave-give'),
    );
    return $gateways;
}

// Register the gateway settings fields
add_filter('give_get_sections_gateways', 'flutterwave_add_gateway_section');
function flutterwave_add_gateway_section($sections)
{
    $sections['flutterwave'] = __('Flutterwave', 'flutterwave-give');
    return $sections;
}

add_filter('give_get_settings_gateways', 'flutterwave_add_gateway_settings');
function flutterwave_add_gateway_settings($settings)
{
    $current_section = give_get_current_setting_section();

    if ('flutterwave' === $current_section) {
        $settings = array(
            array(
                'id'   => 'give_title_gateway_flw',
                'type' => 'title',
                'title' => __('Flutterwave Settings', 'flutterwave-give'),
            ),
            array(
                'name'    => __('Public Key', 'flutterwave-give'),
                'desc'    => __('Enter your Flutterwave public key.', 'flutterwave-give'),
                'id'      => 'flutterwave_public_key',
                'type'    => 'text',
            ),
            array(
                'name'    => __('Secret Key', 'flutterwave-give'),
                'desc'    => __('Enter your Flutterwave secret key.', 'flutterwave-give'),
                'id'      => 'flutterwave_secret_key',
                'type'    => 'text',
            ),
            array(
                'name'    => __('Enable Mobile Money', 'flutterwave-give'),
                'desc'    => __('Enable the Mobile Money payment option for Flutterwave.', 'flutterwave-give'),
                'id'      => 'flutterwave_enable_mobile_money',
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
            array(
                'type' => 'sectionend',
                'id'   => 'give_title_gateway_flw',
            ),
        );
    }

    return $settings;
}

// Redirect to Flutterwave checkout page for Mobile Money
add_action('give_gateway_flutterwave', 'flutterwave_redirect_to_checkout');
function flutterwave_redirect_to_checkout($purchase_data)
{
    $payment_id = give_insert_payment($purchase_data);

    if (! $payment_id) {
        // If payment ID is not created, throw an error
        give_record_gateway_error(__('Payment Error', 'flutterwave-give'), sprintf(__('Payment creation failed before redirecting to Flutterwave. Payment data: %s', 'flutterwave-give'), json_encode($purchase_data)));
        give_send_back_to_checkout('?payment-mode=flutterwave');
        return;
    }

    // Mark the payment as pending
    give_update_payment_status($payment_id, 'pending');

    // Retrieve Flutterwave keys
    $public_key = give_get_option('flutterwave_public_key');
    $redirect_url = add_query_arg('payment-id', $payment_id, give_get_success_page_uri());

    // Flutterwave payment request data
    $flutterwave_data = array(
        'tx_ref'       => $payment_id,
        'amount'       => $purchase_data['price'],
        'currency'     => give_get_currency(),
        'redirect_url' => $redirect_url,
        'customer'     => array(
            'email' => $purchase_data['user_email'],
            'name'  => $purchase_data['user_info']['first_name'] . ' ' . $purchase_data['user_info']['last_name'],
        ),
        'payment_options' => 'mobilemoney',
    );

    // Redirect to Flutterwave checkout
    $flutterwave_endpoint = 'https://api.flutterwave.com/v3/payments';
    $args = array(
        'body'    => wp_json_encode($flutterwave_data),
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $public_key,
        ),
        'timeout' => 60,
    );

    $response = wp_remote_post($flutterwave_endpoint, $args);

    if (is_wp_error($response)) {
        give_record_gateway_error(__('Flutterwave Error', 'flutterwave-give'), $response->get_error_message());
        give_send_back_to_checkout('?payment-mode=flutterwave');
        return;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body, true);

    if (isset($result['status']) && $result['status'] === 'success' && isset($result['data']['link'])) {
        // Redirect to Flutterwave payment link
        wp_redirect($result['data']['link']);
        exit;
    } else {
        give_record_gateway_error(__('Flutterwave Error', 'flutterwave-give'), __('Failed to get payment link from Flutterwave.', 'flutterwave-give'));
        give_send_back_to_checkout('?payment-mode=flutterwave');
    }
}
