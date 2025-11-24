<?php
/**
 * WooCommerce Integration Class for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Flow_Sub_WooCommerce
 */
class Flow_Sub_WooCommerce
{

    /**
     * Initialize the class.
     */
    public function __construct()
    {
        add_action('init', array($this, 'add_endpoint'));
        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_action('woocommerce_account_flow-subscriptions_endpoint', array($this, 'render_content'));
        add_action('template_redirect', array($this, 'handle_cancellation'));
    }

    /**
     * Add the endpoint.
     */
    public function add_endpoint()
    {
        add_rewrite_endpoint('flow-subscriptions', EP_ROOT | EP_PAGES);
    }

    /**
     * Add query vars.
     *
     * @param array $vars Query vars.
     * @return array Modified query vars.
     */
    public function add_query_vars($vars)
    {
        $vars[] = 'flow-subscriptions';
        return $vars;
    }

    /**
     * Add menu item to My Account.
     *
     * @param array $items Menu items.
     * @return array Modified menu items.
     */
    public function add_menu_item($items)
    {
        // Insert before 'customer-logout'
        $logout = $items['customer-logout'] ?? null;
        if ($logout) {
            unset($items['customer-logout']);
        }

        $items['flow-subscriptions'] = esc_html__('Suscripciones', 'flow-sub');

        if ($logout) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    /**
     * Render the content for the endpoint.
     */
    public function render_content()
    {
        $user_id = get_current_user_id();
        $subs = get_user_meta($user_id, 'flow_user_subscriptions', true);

        if (empty($subs) || !is_array($subs)) {
            echo '<div class="woocommerce-info">' . esc_html__('No tienes suscripciones activas.', 'flow-sub') . '</div>';
            return;
        }

        $api_key = get_option('flow_sub_api_key');
        $secret_key = get_option('flow_sub_secret_key');

        if (!$api_key || !$secret_key) {
            echo '<div class="woocommerce-error">' . esc_html__('Error de configuración del plugin.', 'flow-sub') . '</div>';
            return;
        }

        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
        $api = new Flow_Sub_API($api_key, $secret_key);

        // Add Tailwind and Custom Styles
        echo '<script src="https://cdn.tailwindcss.com"></script>';
        echo '<style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap");
            .flow-sub-wrapper {
                font-family: "Inter", sans-serif;
                color: #333;
            }
            .container-content {
                max-width: 1000px;
                margin: 0 auto;
                padding: 20px;
                background-color: #ffffff;
                border-radius: 12px;
                box-shadow: none;
            }
            .flow-sub-wrapper h3 {
                font-weight: 700;
                font-size: 1.75rem;
                color: #1a202c;
                margin-bottom: 24px;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 8px;
            }
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
            .btn-secondary {
                color: #4a5568;
                border-color: #cbd5e0;
                background-color: transparent;
                margin-left: 8px;
            }
            .btn-secondary:hover {
                background-color: #f7fafc;
                border-color: #a0aec0;
                color: #4a5568;
            }
            .status-link {
                color: #b78a1a;
                font-weight: 600;
                text-decoration: none;
                border-bottom: 1px dashed #f6e0b5;
            }
            .status-link:hover {
                color: #976f0c;
            }
            .account-orders-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
            }
            .account-orders-table thead th {
                padding: 12px 16px;
                border-bottom: 2px solid #e2e8f0;
                font-weight: 600;
                color: #4a5568;
                font-size: 0.875rem;
                text-transform: uppercase;
            }
            .account-orders-table tbody td {
                padding: 16px;
                border-bottom: 1px solid #edf2f7;
                vertical-align: middle;
                font-size: 0.9375rem;
            }
            @media (max-width: 768px) {
                .account-orders-table thead { display: none; }
                .account-orders-table tbody tr {
                    display: block;
                    border: 1px solid #e2e8f0;
                    margin-bottom: 16px;
                    border-radius: 8px;
                }
                .account-orders-table tbody td {
                    display: block;
                    text-align: right;
                    padding: 8px 16px;
                    border: none;
                    position: relative;
                }
                .account-orders-table tbody td::before {
                    content: attr(data-title);
                    font-weight: 600;
                    text-transform: uppercase;
                    font-size: 0.75rem;
                    color: #718096;
                    position: absolute;
                    left: 16px;
                }
                .woocommerce-orders-table__cell-order-actions {
                    text-align: center !important;
                    border-top: 1px solid #edf2f7;
                    padding-top: 12px !important;
                    padding-bottom: 12px !important;
                }
                .woocommerce-orders-table__cell-order-actions a {
                    margin-left: 0 !important;
                    margin-right: 8px;
                }
            }
        </style>';

        echo '<div class="flow-sub-wrapper container-content">';
        echo '<h3>' . esc_html__('Mis Suscripciones', 'flow-sub') . '</h3>';

        echo '<table class="account-orders-table woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr">' . esc_html__('Plan y ID', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr">' . esc_html__('Estado', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr">' . esc_html__('Inicio', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions"><span class="nobr">' . esc_html__('Acciones', 'flow-sub') . '</span></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $cache_key = 'flow_user_subs_' . $user_id;
        $cached_data = get_transient($cache_key);

        if (false === $cached_data) {
            $cached_data = array();
            foreach ($subs as $sub_id) {
                $sub_data = $api->get_subscription($sub_id);
                if (!is_wp_error($sub_data)) {
                    $cached_data[$sub_id] = $sub_data;
                }
            }
            set_transient($cache_key, $cached_data, HOUR_IN_SECONDS);
        }

        foreach ($subs as $sub_id) {
            $sub_data = $cached_data[$sub_id] ?? null;

            if (!$sub_data || is_wp_error($sub_data)) {
                continue;
            }

            $plan_name = $sub_data['plan_name'] ?? $sub_data['planId'] ?? '-';
            $status = $sub_data['status'] ?? 0;
            $start_date = $sub_data['subscription_start'] ?? '-';

            // Check for unpaid invoices
            $payment_url = '';
            $is_unpaid = false;
            if (!empty($sub_data['invoices']) && is_array($sub_data['invoices'])) {
                foreach ($sub_data['invoices'] as $invoice) {
                    if (isset($invoice['status']) && 0 === (int) $invoice['status']) {
                        $is_unpaid = true;
                        $invoice_details = $api->get_invoice($invoice['id']);
                        if (!is_wp_error($invoice_details)) {
                            $payment_url = $invoice_details['paymentLink'] ?? '';
                        }
                        if (empty($payment_url)) {
                            $payment_url = $invoice['paymentUrl'] ?? $invoice['url'] ?? '';
                            if (empty($payment_url) && !empty($invoice['token'])) {
                                $payment_url = 'https://www.flow.cl/app/web/pay.php?token=' . $invoice['token'];
                            }
                        }
                        break;
                    }
                }
            }

            $status_label = (1 === (int) $status) ? 'Activa' : 'Inactiva';

            if (4 === (int) $status) {
                $status_label = 'Cancelada';
            } elseif ($is_unpaid) {
                if (!empty($payment_url)) {
                    $status_label = '<a href="' . esc_url($payment_url) . '" target="_blank" class="status-link">' . esc_html__('Pendiente de pago', 'flow-sub') . '</a>';
                } else {
                    $status_label = 'Pendiente de pago';
                }
            }

            echo '<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-completed order">';

            // Plan y ID Column
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="Plan">';
            echo '<div class="font-semibold text-base mb-1">' . esc_html($plan_name) . '</div>';
            echo '<div class="text-xs text-gray-500">ID: ' . esc_html($sub_id) . '</div>';
            echo '</td>';

            // Estado Column
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="Estado">' . wp_kses_post($status_label) . '</td>';

            // Inicio Column
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="Inicio">' . esc_html($start_date) . '</td>';

            // Acciones Column
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="Acciones">';
            echo '<div class="flex flex-col md:flex-row md:justify-start items-center space-y-2 md:space-y-0">';

            if ($is_unpaid && !empty($payment_url) && 4 !== (int) $status) {
                echo '<a href="' . esc_url($payment_url) . '" class="minimal-button btn-primary" target="_blank">' . esc_html__('Pagar', 'flow-sub') . '</a>';
            }

            // Cancel button
            if ($status !== 4) { // 4 is Cancelled
                $cancel_url = wp_nonce_url(add_query_arg(array(
                    'action' => 'cancel_sub',
                    'sub_id' => $sub_id
                ), wc_get_endpoint_url('flow-subscriptions')), 'flow_cancel_sub');

                echo '<a href="' . esc_url($cancel_url) . '" class="minimal-button btn-secondary" onclick="return confirm(\'' . esc_js(__('¿Estás seguro de que deseas cancelar esta suscripción?', 'flow-sub')) . '\');" style="margin-left: 5px;">' . esc_html__('Cancelar', 'flow-sub') . '</a>';
            }

            if (!$is_unpaid && $status === 4) {
                echo '-';
            }

            echo '</div>'; // End flex container
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // End container-content
    }

    /**
     * Handle subscription cancellation.
     */
    public function handle_cancellation()
    {
        if (empty($_GET['action']) || 'cancel_sub' !== $_GET['action'] || empty($_GET['sub_id'])) {
            return;
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'flow_cancel_sub')) {
            wc_add_notice(__('Enlace de seguridad inválido.', 'flow-sub'), 'error');
            return;
        }

        $sub_id = sanitize_text_field($_GET['sub_id']);
        $user_id = get_current_user_id();
        $user_subs = get_user_meta($user_id, 'flow_user_subscriptions', true);

        if (!is_array($user_subs) || !in_array($sub_id, $user_subs)) {
            wc_add_notice(__('No tienes permiso para cancelar esta suscripción.', 'flow-sub'), 'error');
            return;
        }

        $api_key = get_option('flow_sub_api_key');
        $secret_key = get_option('flow_sub_secret_key');

        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
        $api = new Flow_Sub_API($api_key, $secret_key);

        $result = $api->cancel_subscription($sub_id);

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
        } else {
            wc_add_notice(__('Suscripción cancelada exitosamente.', 'flow-sub'), 'success');
            // Invalidate cache
            delete_transient('flow_user_subs_' . $user_id);
        }

        wp_safe_redirect(wc_get_endpoint_url('flow-subscriptions'));
        exit;
    }
}
