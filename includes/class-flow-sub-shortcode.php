<?php
/**
 * Shortcode Class for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Flow_Sub_Shortcode
 */
class Flow_Sub_Shortcode
{

    /**
     * Initialize the class.
     */
    public function __construct()
    {
        add_shortcode('flow_subscribe', array($this, 'render_shortcode'));
        add_action('init', array($this, 'handle_subscription'));
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'plan' => '',
            ),
            $atts,
            'flow_subscribe'
        );

        if (empty($atts['plan'])) {
            return '';
        }

        if (isset($_GET['flow_subscribed']) && '1' === $_GET['flow_subscribed'] && isset($_GET['flow_plan_id']) && $_GET['flow_plan_id'] === $atts['plan']) {
            return '<div class="flow-subscription-success-message" style="background-color: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">' .
                esc_html__('Hemos enviado un correo electrónico a tu bandeja con un enlace para que completes tu información de pago. Este mensaje proviene de nuestro proveedor de servicios, Flow, bajo la dirección info@flow.cl. Al finalizar, tu suscripción al sitio El Villegas se activará de forma automática.', 'flow-sub') .
                '</div>';
        }

        $is_subscribed = false;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $api_key = get_option('flow_sub_api_key');
            $secret_key = get_option('flow_sub_secret_key');

            if ($api_key && $secret_key) {
                $cache_key = 'flow_user_subs_' . $user_id;
                $cached_data = get_transient($cache_key);

                if (false === $cached_data) {
                    require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
                    $api = new Flow_Sub_API($api_key, $secret_key);
                    $subs = get_user_meta($user_id, 'flow_user_subscriptions', true);
                    $cached_data = array();
                    if (is_array($subs)) {
                        foreach ($subs as $sub_id) {
                            $sub_data = $api->get_subscription($sub_id);
                            if (!is_wp_error($sub_data)) {
                                $cached_data[$sub_id] = $sub_data;
                            }
                        }
                        set_transient($cache_key, $cached_data, HOUR_IN_SECONDS);
                    }
                }

                if (is_array($cached_data)) {
                    foreach ($cached_data as $sub) {
                        if (isset($sub['planId']) && $sub['planId'] == $atts['plan'] && isset($sub['status']) && 1 === (int) $sub['status']) {
                            $is_subscribed = true;
                            break;
                        }
                    }
                }
            }
        }

        ob_start();
        ?>
        <style>
            .minimal-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 16px;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.2s ease-in-out;
                border: 1px solid transparent;
                cursor: pointer;
                margin-top: 4px;
            }

            .btn-primary {
                color: #065f46;
                border-color: #065f46;
                background-color: transparent;
            }

            .btn-primary:hover {
                background-color: #ecfdf5;
                box-shadow: 0 2px 4px rgba(6, 95, 70, 0.2);
                color: #065f46;
            }
        </style>
        <?php

        if ($is_subscribed) {
            echo '<div class="flow-subscribed-badge" style="display: inline-flex; align-items: center; font-weight: 600; color: #155724;">';
            echo '<span style="margin-right: 5px;">✅</span> ' . esc_html__('Suscrito', 'flow-sub');
            echo '</div>';
        } else {
            ?>
            <form method="post"
                onsubmit="var btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = '<?php echo esc_js(__('Procesando...', 'flow-sub')); ?>';">
                <?php wp_nonce_field('flow_subscribe_action', 'flow_subscribe_nonce'); ?>
                <input type="hidden" name="flow_plan_id" value="<?php echo esc_attr($atts['plan']); ?>" />
                <input type="hidden" name="action" value="flow_subscribe" />
                <button type="submit" class="minimal-button btn-primary"><?php esc_html_e('SUSCRIBIR', 'flow-sub'); ?></button>
            </form>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Handle the subscription request.
     */
    public function handle_subscription()
    {
        if (!isset($_POST['action']) || 'flow_subscribe' !== $_POST['action']) {
            return;
        }

        if (!isset($_POST['flow_subscribe_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['flow_subscribe_nonce'])), 'flow_subscribe_action')) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_die(esc_html__('You must be logged in to subscribe.', 'flow-sub'));
        }

        $plan_id = isset($_POST['flow_plan_id']) ? sanitize_text_field(wp_unslash($_POST['flow_plan_id'])) : '';

        if (empty($plan_id)) {
            wp_die(esc_html__('Invalid plan ID.', 'flow-sub'));
        }

        $user = wp_get_current_user();
        $api_key = get_option('flow_sub_api_key');
        $secret_key = get_option('flow_sub_secret_key');

        if (!$api_key || !$secret_key) {
            wp_die(esc_html__('Flow API credentials not configured.', 'flow-sub'));
        }

        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
        $api = new Flow_Sub_API($api_key, $secret_key);

        // Check for existing active subscriptions to the same plan
        $existing_subs = get_user_meta($user->ID, 'flow_user_subscriptions', true);
        if (is_array($existing_subs) && !empty($existing_subs)) {
            foreach ($existing_subs as $sub_id) {
                $sub_details = $api->get_subscription($sub_id);
                if (
                    !is_wp_error($sub_details) &&
                    isset($sub_details['status']) &&
                    1 === (int) $sub_details['status'] &&
                    isset($sub_details['planId']) &&
                    $sub_details['planId'] === $plan_id
                ) {
                    wp_die(esc_html__('Ya tienes una suscripción activa para este plan.', 'flow-sub'));
                }
            }
        }

        // 1. Create or Get Customer
        // Ideally we should check if user already has a customerId stored in meta
        $customer_id = get_user_meta($user->ID, 'flow_customer_id', true);

        if (!$customer_id) {
            $customer_data = array(
                'name' => $user->display_name,
                'email' => $user->user_email,
                'externalId' => $user->ID,
            );

            $customer_response = $api->create_customer($customer_data);

            if (is_wp_error($customer_response)) {
                wp_die(esc_html($customer_response->get_error_message()));
            }

            if (isset($customer_response['customerId'])) {
                $customer_id = $customer_response['customerId'];
                update_user_meta($user->ID, 'flow_customer_id', $customer_id);
            } else {
                // Handle case where customer might already exist or other error
                $error_message = $customer_response['message'] ?? '';

                // Check for "customer exists" error (externalId conflict)
                // The error message is typically "Internal Server Error - There is a customer with this externalId: X"
                if (stripos($error_message, 'externalId') !== false) {
                    error_log('Flow Sub Debug: Customer exists error detected. Attempting recovery for externalId: ' . $user->ID);

                    // Try to find the customer by listing them
                    $customers_response = $api->get_customers(array('externalId' => $user->ID));
                    error_log('Flow Sub Debug: get_customers response: ' . print_r($customers_response, true));

                    $found_customer_id = null;

                    if (!is_wp_error($customers_response) && isset($customers_response['data']) && is_array($customers_response['data'])) {
                        foreach ($customers_response['data'] as $customer) {
                            if (isset($customer['externalId']) && (string) $customer['externalId'] === (string) $user->ID) {
                                $found_customer_id = $customer['customerId'];
                                error_log('Flow Sub Debug: Found customer in data wrapper: ' . $found_customer_id);
                                break;
                            }
                        }
                    }

                    // Fallback for simple array response
                    if (!$found_customer_id && !is_wp_error($customers_response) && is_array($customers_response)) {
                        foreach ($customers_response as $customer) {
                            if (isset($customer['externalId']) && (string) $customer['externalId'] === (string) $user->ID) {
                                $found_customer_id = $customer['customerId'];
                                error_log('Flow Sub Debug: Found customer in simple array: ' . $found_customer_id);
                                break;
                            }
                        }
                    }

                    if ($found_customer_id) {
                        $customer_id = $found_customer_id;
                        update_user_meta($user->ID, 'flow_customer_id', $found_customer_id);
                        error_log('Flow Sub Debug: Recovered customer ID: ' . $customer_id);
                    } else {
                        error_log('Flow Sub Debug: Could not find customer with externalId ' . $user->ID . ' in list.');
                        // If we couldn't find it even though it says it exists, fail with the original message
                        wp_die(esc_html('Error creating customer: ' . $error_message));
                    }
                } else {
                    if (!empty($error_message)) {
                        wp_die(esc_html('Error creating customer: ' . $error_message));
                    }
                    wp_die(esc_html__('Failed to create customer.', 'flow-sub'));
                }
            }
        }

        // 2. Create Subscription
        $subscription_data = array(
            'customerId' => $customer_id,
            'planId' => $plan_id,
        );

        $subscription_response = $api->create_subscription($subscription_data);

        // Check if failure is due to invalid customer (e.g. deleted in Flow but exists in WP)
        // Flow API usually returns code 400 or 404 and a message like "The customerId ... does not exist"
        // We will check if it's an error and try to recover if it seems related to customer.
        if (is_wp_error($subscription_response) || (isset($subscription_response['code']) && 200 !== (int) $subscription_response['code'])) {

            $error_msg = is_wp_error($subscription_response) ? $subscription_response->get_error_message() : ($subscription_response['message'] ?? '');

            // Check for "customer exists" error (externalId conflict)
            if (stripos($error_msg, 'externalId') !== false) {
                // Try to find the customer by listing them
                // We assume get_customers might support filtering or we filter manually
                $customers_response = $api->get_customers(array('externalId' => $user->ID)); // Try filtering

                $found_customer_id = null;

                if (!is_wp_error($customers_response) && isset($customers_response['data']) && is_array($customers_response['data'])) {
                    foreach ($customers_response['data'] as $customer) {
                        if (isset($customer['externalId']) && (string) $customer['externalId'] === (string) $user->ID) {
                            $found_customer_id = $customer['customerId'];
                            break;
                        }
                    }
                }

                // If not found in 'data' (maybe response structure is different or filter didn't work and we need to paginate? 
                // For now, let's assume standard list response structure or simple list)
                if (!$found_customer_id && !is_wp_error($customers_response) && is_array($customers_response)) {
                    // Handle case where response is just the array of customers (no 'data' wrapper)
                    foreach ($customers_response as $customer) {
                        if (isset($customer['externalId']) && (string) $customer['externalId'] === (string) $user->ID) {
                            $found_customer_id = $customer['customerId'];
                            break;
                        }
                    }
                }

                if ($found_customer_id) {
                    update_user_meta($user->ID, 'flow_customer_id', $found_customer_id);
                    $subscription_data['customerId'] = $found_customer_id;
                    $subscription_response = $api->create_subscription($subscription_data);
                }
            } elseif (stripos($error_msg, 'customer') !== false || stripos($error_msg, 'cliente') !== false) {
                // Retry creating customer (previous logic for "not found" which implies ID mismatch)
                $customer_data = array(
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'externalId' => $user->ID,
                );

                $customer_response = $api->create_customer($customer_data);

                if (!is_wp_error($customer_response) && isset($customer_response['customerId'])) {
                    $customer_id = $customer_response['customerId'];
                    update_user_meta($user->ID, 'flow_customer_id', $customer_id);

                    // Retry subscription with new customer ID
                    $subscription_data['customerId'] = $customer_id;
                    $subscription_response = $api->create_subscription($subscription_data);
                }
            }
        }

        if (is_wp_error($subscription_response)) {
            error_log('Flow Sub Error: ' . $subscription_response->get_error_message());
            wp_die(esc_html($subscription_response->get_error_message()));
        }

        // Check for API error in response body (code != 200)
        if (isset($subscription_response['code']) && 200 !== (int) $subscription_response['code']) {
            wp_die(esc_html('Error creating subscription: ' . ($subscription_response['message'] ?? 'Unknown error')));
        }

        $redirect_url = '';
        if (isset($subscription_response['url'])) {
            $redirect_url = $subscription_response['url'];
        } elseif (isset($subscription_response['paymentUrl'])) {
            $redirect_url = $subscription_response['paymentUrl'];
        } elseif (isset($subscription_response['payment_url'])) {
            $redirect_url = $subscription_response['payment_url'];
        } elseif (isset($subscription_response['checkoutUrl'])) {
            $redirect_url = $subscription_response['checkoutUrl'];
        }

        // Check inside invoices if not found
        if (empty($redirect_url) && !empty($subscription_response['invoices']) && is_array($subscription_response['invoices'])) {
            foreach ($subscription_response['invoices'] as $invoice) {
                if (!empty($invoice['paymentUrl'])) {
                    $redirect_url = $invoice['paymentUrl'];
                    break;
                }
                if (!empty($invoice['url'])) {
                    $redirect_url = $invoice['url'];
                    break;
                }
            }
        }

        if (!empty($redirect_url)) {
            // Redirect to payment URL
            wp_redirect($redirect_url);
            exit;
        }

        // If no payment URL, we assume the subscription was created and Flow sent an email.

        // Store subscription ID in user meta for listing in My Account
        if (isset($subscription_response['subscriptionId'])) {
            $subs = get_user_meta($user->ID, 'flow_user_subscriptions', true);
            if (!is_array($subs)) {
                $subs = array();
            }
            if (!in_array($subscription_response['subscriptionId'], $subs, true)) {
                $subs[] = $subscription_response['subscriptionId'];
                update_user_meta($user->ID, 'flow_user_subscriptions', $subs);
                // Invalidate cache
                delete_transient('flow_user_subs_' . $user->ID);
            }
        }

        // Redirect back to the page with a success flag.
        wp_redirect(add_query_arg(array('flow_subscribed' => '1', 'flow_plan_id' => $plan_id), wp_get_referer()));
        exit;
    }
}
