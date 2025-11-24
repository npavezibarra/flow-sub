<?php
/**
 * API Class for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Flow_Sub_API
 */
class Flow_Sub_API
{

    /**
     * Flow API Base URL.
     *
     * @var string
     */
    private const API_URL = 'https://www.flow.cl/api';

    /**
     * API Key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Secret Key.
     *
     * @var string
     */
    private $secret_key;

    /**
     * Initialize the class.
     *
     * @param string $api_key    API Key.
     * @param string $secret_key Secret Key.
     */
    public function __construct($api_key, $secret_key)
    {
        $this->api_key = $this->decrypt_key($api_key);
        $this->secret_key = $this->decrypt_key($secret_key);
    }

    /**
     * Decrypt key.
     *
     * @param string $value The value to decrypt.
     * @return string Decrypted value.
     */
    private function decrypt_key($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Check if it looks like our encrypted format (base64 encoded string containing "::")
        if (strpos(base64_decode($value, true), '::') === false) {
            return $value; // Assume plain text (legacy)
        }

        $key = defined('AUTH_KEY') ? AUTH_KEY : 'flow-sub-secret-salt';
        list($encrypted_data, $iv) = explode('::', base64_decode($value), 2);

        if (empty($encrypted_data) || empty($iv)) {
            return $value; // Fallback
        }

        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);

        return $decrypted !== false ? $decrypted : $value;
    }

    /**
     * Make a GET request to the Flow API.
     *
     * @param string $endpoint API Endpoint.
     * @param array  $params   Request parameters.
     * @return array|WP_Error Response data or WP_Error.
     */
    public function get($endpoint, $params = array())
    {
        return $this->request($endpoint, $params, 'GET');
    }

    /**
     * Make a request to the Flow API.
     *
     * @param string $endpoint API Endpoint.
     * @param array  $params   Request parameters.
     * @param string $method   HTTP Method.
     * @return array|WP_Error Response data or WP_Error.
     */
    private function request($endpoint, $params, $method = 'GET')
    {
        $params['apiKey'] = $this->api_key;
        $params = $this->sign_params($params);
        $url = self::API_URL . '/' . ltrim($endpoint, '/');

        $args = array(
            'method' => $method,
            'timeout' => 20,
        );

        if ('GET' === $method) {
            $url = add_query_arg($params, $url);
        } else {
            $args['body'] = $params;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return new WP_Error('flow_api_error', __('Invalid response from Flow API.', 'flow-sub'));
        }

        return $data;
    }

    /**
     * Sign the parameters.
     *
     * @param array $params Request parameters.
     * @return array Signed parameters.
     */
    private function sign_params($params)
    {
        ksort($params);
        $to_sign = '';
        foreach ($params as $key => $value) {
            $to_sign .= $key . $value;
        }
        $params['s'] = hash_hmac('sha256', $to_sign, $this->secret_key);
        return $params;
    }

    /**
     * Make a POST request to the Flow API.
     *
     * @param string $endpoint API Endpoint.
     * @param array  $params   Request parameters.
     * @return array|WP_Error Response data or WP_Error.
     */
    public function post($endpoint, $params = array())
    {
        return $this->request($endpoint, $params, 'POST');
    }

    /**
     * Get plans.
     *
     * @return array|WP_Error List of plans or WP_Error.
     */
    public function get_plans()
    {
        return $this->get('plans/list');
    }

    /**
     * Create a customer.
     *
     * @param array $data Customer data (name, email, externalId).
     * @return array|WP_Error Customer data or WP_Error.
     */
    public function create_customer($data)
    {
        return $this->post('customer/create', $data);
    }

    /**
     * Get customers.
     *
     * @param array $params Query parameters (e.g., start, limit, filter).
     * @return array|WP_Error List of customers or WP_Error.
     */
    public function get_customers($params = array())
    {
        return $this->get('customer/list', $params);
    }

    /**
     * Create a subscription.
     *
     * @param array $data Subscription data (customerId, planId).
     * @return array|WP_Error Subscription data or WP_Error.
     */
    public function create_subscription($data)
    {
        return $this->post('subscription/create', $data);
    }

    /**
     * Get subscriptions.
     *
     * @param array $params Query parameters (e.g., customerId).
     * @return array|WP_Error List of subscriptions or WP_Error.
     */
    public function get_subscriptions($params = array())
    {
        return $this->get('subscription/list', $params);
    }

    /**
     * Get a single subscription.
     *
     * @param string $subscription_id Subscription ID.
     * @return array|WP_Error Subscription data or WP_Error.
     */
    public function get_subscription($subscription_id)
    {
        return $this->get('subscription/get', array('subscriptionId' => $subscription_id));
    }

    /**
     * Get a single invoice.
     *
     * @param int $invoice_id Invoice ID.
     * @return array|WP_Error Invoice data or WP_Error.
     */
    public function get_invoice($invoice_id)
    {
        return $this->get('invoice/get', array('invoiceId' => $invoice_id));
    }
    /**
     * Cancel a subscription.
     *
     * @param string $subscription_id Subscription ID.
     * @param int    $at_period_end   0 for immediate, 1 for end of period.
     * @return array|WP_Error Subscription data or WP_Error.
     */
    public function cancel_subscription($subscription_id, $at_period_end = 0)
    {
        return $this->post('subscription/cancel', array(
            'subscriptionId' => $subscription_id,
            'at_period_end' => $at_period_end,
        ));
    }
}
