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
            $status_label = (1 === (int) $status) ? 'Activa' : 'Inactiva';
            $start_date = $sub_data['subscription_start'] ?? '-';

            echo '<tr class="woocommerce-orders-table__row woocommerce-orders-table__row--status-completed order">';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number" data-title="ID">' . esc_html($sub_id) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="Plan">' . esc_html($plan_name) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status" data-title="Estado">' . esc_html($status_label) . '</td>';
            echo '<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total" data-title="Inicio">' . esc_html($start_date) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }
}
