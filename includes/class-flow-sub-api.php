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
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
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
     * Create a subscription.
     *
     * @param array $data Subscription data (customerId, planId).
     * @return array|WP_Error Subscription data or WP_Error.
     */
    public function create_subscription($data)
    {
        return $this->post('subscription/create', $data);
    }
}
