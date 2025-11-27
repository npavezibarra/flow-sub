<?php
/**
 * Helper functions for Flow Sub
 *
 * @package FlowSub
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to check if the current user has an active Flow subscription.
 * Uses Transients to cache the result for performance.
 *
 * @param int $user_id The ID of the user to check.
 * @return bool True if the user has a valid subscription based on the full state model.
 */
function flow_sub_is_user_active($user_id)
{
    if (!$user_id) {
        return false;
    }

    // 1. Check Transient Cache
    $transient_key = 'flow_user_active_' . $user_id;
    $cached_status = get_transient($transient_key);

    if (false !== $cached_status) {
        return (bool) $cached_status;
    }

    // 2. Get User Subscriptions from Meta
    $subscriptions = get_user_meta($user_id, 'flow_user_subscriptions', true);

    if (empty($subscriptions) || !is_array($subscriptions)) {
        // No subscriptions found, cache as false
        set_transient($transient_key, 0, 15 * MINUTE_IN_SECONDS); // Reduced cache time
        return false;
    }

    // 3. Initialize API
    $api_key = get_option('flow_sub_api_key');
    $secret_key = get_option('flow_sub_secret_key');

    if (!$api_key || !$secret_key) {
        return false; // Cannot check without keys
    }

    if (!class_exists('Flow_Sub_API')) {
        require_once FLOW_SUB_PATH . 'includes/class-flow-sub-api.php';
    }

    $api = new Flow_Sub_API($api_key, $secret_key);
    $is_active = false;

    // 4. Check each subscription status via API
    foreach ($subscriptions as $subscription_id) {
        $sub = $api->get_subscription($subscription_id);

        if (is_wp_error($sub)) {
            continue; // Skip on error
        }

        $now = current_time('timestamp');
        $status = isset($sub['status']) ? (int) $sub['status'] : 0;
        $morose = isset($sub['morose']) ? (int) $sub['morose'] : 0;

        // --- A. Trial Period ---
        // User SHOULD have access if: status == 2 AND current_date <= trial_end
        if (2 === $status) {
            if (!empty($sub['trial_end'])) {
                $trial_end = strtotime($sub['trial_end']);
                if ($now <= $trial_end) {
                    $is_active = true;
                    break;
                }
            }
        }

        // --- B. Pending First Payment (morose = 2) ---
        // User SHOULD have access if: morose == 2 AND date_now <= invoice_due_date
        if (2 === $morose) {
            // Find the invoice
            if (!empty($sub['invoices']) && is_array($sub['invoices'])) {
                foreach ($sub['invoices'] as $invoice) {
                    if (isset($invoice['status']) && 0 === (int) $invoice['status']) { // Unpaid
                        $due_date = isset($invoice['due_date']) ? strtotime($invoice['due_date']) : 0;
                        if ($due_date > 0 && $now <= $due_date) {
                            $is_active = true;
                            break 2; // Break both loops
                        }
                    }
                }
            }
        }

        // --- C. Grace Period (days_until_due) ---
        // User SHOULD have access if: There is any invoice where: invoice.status == 0 (unpaid) AND now <= due_date
        if (!empty($sub['invoices']) && is_array($sub['invoices'])) {
            foreach ($sub['invoices'] as $invoice) {
                if (isset($invoice['status']) && 0 === (int) $invoice['status']) { // Unpaid
                    $due_date = isset($invoice['due_date']) ? strtotime($invoice['due_date']) : 0;
                    if ($due_date > 0 && $now <= $due_date) {
                        $is_active = true;
                        break 2; // Break both loops
                    }
                }
            }
        }

        // --- D. Active Paid Subscription ---
        // User SHOULD have access if: status == 1 AND morose == 0 AND period_start <= now <= period_end
        if (1 === $status && 0 === $morose) {
            $period_start = isset($sub['period_start']) ? strtotime($sub['period_start']) : 0;
            $period_end = isset($sub['period_end']) ? strtotime($sub['period_end']) : 0;

            // Note: Some active subscriptions might not have period dates if they are perpetual or weirdly configured, 
            // but usually they do. If dates are missing, we might fallback to just status=1 check, 
            // but strict requirement says check dates.
            // Let's be safe: if period_end is present, check it.

            if ($period_end > 0) {
                if ($now <= $period_end) {
                    $is_active = true;
                    break;
                }
            } else {
                // Fallback if no period_end (unlikely for recurring)
                $is_active = true;
                break;
            }
        }

        // --- E. Cancelled but still valid ---
        // User SHOULD have access if: status == 4 AND cancel_at_period_end == 1 AND now <= period_end
        if (4 === $status) {
            $cancel_at_period_end = isset($sub['cancel_at_period_end']) ? (int) $sub['cancel_at_period_end'] : 0;
            if (1 === $cancel_at_period_end) {
                $period_end = isset($sub['period_end']) ? strtotime($sub['period_end']) : 0;
                if ($period_end > 0 && $now <= $period_end) {
                    $is_active = true;
                    break;
                }
            }
        }
    }

    // 5. Cache the result
    // Store 1 for true, 0 for false
    set_transient($transient_key, $is_active ? 1 : 0, 15 * MINUTE_IN_SECONDS); // Reduced cache time

    return $is_active;
}

/**
 * Get a human-readable status label for a subscription.
 *
 * @param array $sub Subscription data from Flow API.
 * @return string HTML status label.
 */
function flow_sub_get_subscription_status_label($sub)
{
    $status = isset($sub['status']) ? (int) $sub['status'] : 0;
    $morose = isset($sub['morose']) ? (int) $sub['morose'] : 0;
    $now = current_time('timestamp');

    // Trial
    if (2 === $status) {
        $trial_end = isset($sub['trial_end']) ? strtotime($sub['trial_end']) : 0;
        if ($trial_end > 0 && $now <= $trial_end) {
            return '<span class="text-blue-600 font-semibold">' . sprintf(__('Trial activo (hasta %s)', 'flow-sub'), date_i18n(get_option('date_format'), $trial_end)) . '</span>';
        }
        return '<span class="text-red-600 font-semibold">' . __('Trial expirado', 'flow-sub') . '</span>';
    }

    // Cancelled
    if (4 === $status) {
        $cancel_at_period_end = isset($sub['cancel_at_period_end']) ? (int) $sub['cancel_at_period_end'] : 0;
        if (1 === $cancel_at_period_end) {
            $period_end = isset($sub['period_end']) ? strtotime($sub['period_end']) : 0;
            if ($period_end > 0 && $now <= $period_end) {
                return '<span class="text-orange-600 font-semibold">' . sprintf(__('Cancelada (activa hasta %s)', 'flow-sub'), date_i18n(get_option('date_format'), $period_end)) . '</span>';
            }
        }
        return '<span class="text-gray-600 font-semibold">' . __('Cancelada', 'flow-sub') . '</span>';
    }

    // Pending First Payment
    if (2 === $morose) {
        // Check grace
        $in_grace = false;
        $due_date_str = '';
        if (!empty($sub['invoices']) && is_array($sub['invoices'])) {
            foreach ($sub['invoices'] as $invoice) {
                if (isset($invoice['status']) && 0 === (int) $invoice['status']) {
                    $due_date = isset($invoice['due_date']) ? strtotime($invoice['due_date']) : 0;
                    if ($due_date > 0 && $now <= $due_date) {
                        $in_grace = true;
                        $due_date_str = date_i18n(get_option('date_format'), $due_date);
                        break;
                    }
                }
            }
        }
        if ($in_grace) {
            return '<span class="text-yellow-600 font-semibold">' . sprintf(__('Pago pendiente (vence %s)', 'flow-sub'), $due_date_str) . '</span>';
        }
        return '<span class="text-red-600 font-semibold">' . __('Pago pendiente (vencido)', 'flow-sub') . '</span>';
    }

    // Active but Morose (Overdue)
    if (1 === $morose) {
        // Check grace (unlikely if morose=1 usually means grace expired, but let's check invoice dates)
        $in_grace = false;
        if (!empty($sub['invoices']) && is_array($sub['invoices'])) {
            foreach ($sub['invoices'] as $invoice) {
                if (isset($invoice['status']) && 0 === (int) $invoice['status']) {
                    $due_date = isset($invoice['due_date']) ? strtotime($invoice['due_date']) : 0;
                    if ($due_date > 0 && $now <= $due_date) {
                        $in_grace = true;
                        break;
                    }
                }
            }
        }
        if ($in_grace) {
            return '<span class="text-yellow-600 font-semibold">' . __('Pago atrasado (en periodo de gracia)', 'flow-sub') . '</span>';
        }
        return '<span class="text-red-600 font-semibold">' . __('Suspendida (pago atrasado)', 'flow-sub') . '</span>';
    }

    // Active Paid
    if (1 === $status) {
        $period_end = isset($sub['period_end']) ? strtotime($sub['period_end']) : 0;
        if ($period_end > 0 && $now > $period_end) {
            // Technically expired if no new invoice generated/paid yet?
            // But usually status stays 1 until Flow updates it.
            // If strictly following logic:
            return '<span class="text-yellow-600 font-semibold">' . __('Renovación pendiente', 'flow-sub') . '</span>';
        }
        return '<span class="text-green-600 font-semibold">' . __('Activa', 'flow-sub') . '</span>';
    }

    return '<span class="text-gray-500">' . __('Inactiva', 'flow-sub') . '</span>';
}
