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

        if (isset($_GET['flow_subscribed']) && '1' === $_GET['flow_subscribed']) {
            return '<div class="flow-subscription-success-message" style="background-color: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px;">' .
                esc_html__('Hemos enviado un correo para que ingreses tu información de pago y suscribirte automáticamente al sitio EL VIllegas', 'flow-sub') .
                '</div>';
        }

        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('flow_subscribe_action', 'flow_subscribe_nonce'); ?>
            <input type="hidden" name="flow_plan_id" value="<?php echo esc_attr($atts['plan']); ?>" />
            <input type="hidden" name="action" value="flow_subscribe" />
            <button type="submit" class="button flow-subscribe-button"><?php esc_html_e('SUSCRIBIR', 'flow-sub'); ?></button>
        </form>
        <?php
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
                // For now, let's try to handle the specific error if Flow returns one, or just fail
                if (isset($customer_response['message'])) {
                    wp_die(esc_html('Error creating customer: ' . $customer_response['message']));
                }
                wp_die(esc_html__('Failed to create customer.', 'flow-sub'));
            }
        }

        // 2. Create Subscription
        $subscription_data = array(
            'customerId' => $customer_id,
            'planId' => $plan_id,
        );

        $subscription_response = $api->create_subscription($subscription_data);

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
            }
        }

        // Redirect back to the page with a success flag.
        wp_redirect(add_query_arg('flow_subscribed', '1', wp_get_referer()));
        exit;
    }
}
