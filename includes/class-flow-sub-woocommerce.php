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

        echo '<h3>' . esc_html__('Mis Suscripciones', 'flow-sub') . '</h3>';

        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span class="nobr">' . esc_html__('ID', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-date"><span class="nobr">' . esc_html__('Plan', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span class="nobr">' . esc_html__('Estado', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span class="nobr">' . esc_html__('Inicio', 'flow-sub') . '</span></th>';
        echo '<th class="woocommerce-orders-table__header woocommerce-orders-table__header-order-actions"><span class="nobr">' . esc_html__('Acciones', 'flow-sub') . '</span></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($subs as $sub_id) {
            $sub_data = $api->get_subscription($sub_id);

            if (is_wp_error($sub_data)) {
                continue;
            }

            $plan_name = $sub_data['plan_name'] ?? $sub_data['planId'] ?? '-';
            $status = $sub_data['status'] ?? 0;
            $start_date = $sub_data['subscription_start'] ?? '-';

            // Check for unpaid invoices
            $payment_url = '';
            $is_unpaid = false;
            if (!empty($sub_data['invoices']) && is_array($sub_data['invoices'])) {
                // Sort invoices by date desc to get the latest? Or just check any unpaid.
                // Usually we care about the latest one or any pending.
                foreach ($sub_data['invoices'] as $invoice) {
                    if (isset($invoice['status']) && 0 === (int) $invoice['status']) {
                        $is_unpaid = true;
                        $payment_url = $invoice['paymentUrl'] ?? $invoice['url'] ?? '';

                        // If paymentUrl is empty, try to construct it from token
                        if (empty($payment_url) && !empty($invoice['token'])) {
                            $payment_url = 'https://www.flow.cl/app/web/pay.php?token=' . $invoice['token'];
                        }

                        // If we found an unpaid invoice, we can stop and show this status.
                        // Ideally we pick the latest one if multiple exist.
                        break;
                    }
                }
            }

            $status_label = (1 === (int) $status) ? 'Activa' : 'Inactiva';
            if ($is_unpaid) {
                // Make the status label a link if we have a URL
                if (!empty($payment_url)) {
                    $status_label = '<a href="' . esc_url($payment_url) . '" target="_blank">' . esc_html__('Pendiente de pago', 'flow-sub') . '</a>';
                } else {
                    $status_label = 'Pendiente de pago';
                }
            }

            echo '<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-completed order">';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="ID">' . esc_html($sub_id) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="Plan">' . esc_html($plan_name) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="Estado">' . wp_kses_post($status_label) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="Inicio">' . esc_html($start_date) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions" data-title="Acciones">';
            if ($is_unpaid && !empty($payment_url)) {
                echo '<a href="' . esc_url($payment_url) . '" class="woocommerce-button button view" target="_blank">' . esc_html__('Pagar Ahora', 'flow-sub') . '</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}
