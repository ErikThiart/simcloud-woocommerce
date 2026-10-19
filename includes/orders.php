<?php
defined('ABSPATH') || exit;

function simcloud_wc_can_view($order) {
    if (!$order) {
        return false;
    }
    if (current_user_can('manage_woocommerce') || ($order->get_customer_id() > 0 && get_current_user_id() === $order->get_customer_id())) {
        return true;
    }
    $key = isset($_GET['key']) && is_scalar($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
    return $key !== '' && hash_equals($order->get_order_key(), $key);
}

function simcloud_wc_render_order($order, $admin = false) {
    if (!$order || !simcloud_wc_can_view($order)) {
        return;
    }
    $rows = simcloud_wc_rows($order->get_id());
    if (!$rows) {
        return;
    }
    echo '<section class="simcloud-wc-order"><h2>' . esc_html__('Prepaid fulfilment', 'simcloud-woocommerce') . '</h2>';
    foreach ($rows as $row) {
        $item = $order->get_item($row['item_id']);
        $snapshot = simcloud_wc_order_snapshot($order, $row['item_id']);
        echo '<div><h3>' . esc_html($item ? $item->get_name() : __('Prepaid item', 'simcloud-woocommerce')) . '</h3><p>' . esc_html__('Status: ', 'simcloud-woocommerce') . esc_html(str_replace('_', ' ', $row['state'])) . '</p>';
        foreach (['recipient' => __('Recipient: ', 'simcloud-woocommerce'), 'meter' => __('Meter: ', 'simcloud-woocommerce')] as $key => $label) {
            if (!empty($snapshot[$key])) {
                echo '<p>' . esc_html($label . $snapshot[$key]) . '</p>';
            }
        }
        $result = json_decode((string) ($row['result'] ?? ''), true);
        if ($row['state'] === 'delivered' && is_array($result)) {
            foreach ($result as $code) {
                echo '<p>' . esc_html($code['description'] ?? '') . ': <strong><code>' . esc_html($code['code'] ?? '') . '</code></strong>';
                if (!empty($code['units'])) {
                    echo ' — ' . esc_html($code['units']) . ' ' . esc_html__('units', 'simcloud-woocommerce');
                }
                if (!empty($code['expiry'])) {
                    echo ' — ' . esc_html__('Expiry: ', 'simcloud-woocommerce') . esc_html($code['expiry']);
                }
                echo '</p>';
            }
        }
        if ($admin && current_user_can('manage_woocommerce')) {
            /* translators: 1: opaque purchase reference, 2: provider ID, 3: UTC timestamp. */
            echo '<p>' . esc_html(sprintf(__('Local reference: %1$s | Provider ID: %2$d | Last update: %3$s UTC', 'simcloud-woocommerce'), $row['reference'], $row['provider_id'], $row['updated_at'])) . '</p><p>' . esc_html($row['message']) . '</p>';
        } elseif (in_array($row['state'], ['needs_review', 'failed'], true)) {
            echo '<p>' . esc_html__('Please contact the store about this purchase. Do not place a duplicate order while delivery is being investigated.', 'simcloud-woocommerce') . '</p>';
        }
        echo '</div>';
    }
    echo '</section>';
}
add_action('woocommerce_order_details_after_order_table', 'simcloud_wc_render_order');
add_action('woocommerce_admin_order_data_after_order_details', function ($order) { simcloud_wc_render_order($order, true); });

add_filter('woocommerce_order_actions', function ($actions, $order) {
    if (current_user_can('manage_woocommerce') && $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_simcloud_wc_snapshot')) {
                $actions['simcloud_wc_confirm_funds'] = __('SIMcloud: confirm received funds and queue', 'simcloud-woocommerce');
                $actions['simcloud_wc_refresh'] = __('SIMcloud: refresh identified provider requests', 'simcloud-woocommerce');
                break;
            }
        }
    }
    return $actions;
}, 10, 2);

add_action('woocommerce_order_action_simcloud_wc_confirm_funds', function ($order) {
    // WooCommerce verifies its order action nonce. This separate capability check also protects programmatic calls.
    if (!current_user_can('manage_woocommerce') || $order->has_status(['completed', 'cancelled', 'refunded', 'failed']) || (float) $order->get_total_refunded() > 0) {
        return;
    }
    $order->update_meta_data('_simcloud_wc_funds_confirmed', 'yes');
    if (!$order->get_date_paid()) {
        $order->set_date_paid(time());
    }
    $order->save();
    /* translators: %d: staff WordPress user ID. */
    $order->update_status('processing', sprintf(__('SIMcloud funds explicitly confirmed by staff user %d.', 'simcloud-woocommerce'), get_current_user_id()));
    simcloud_wc_prepare_order($order->get_id());
});

add_action('woocommerce_order_action_simcloud_wc_refresh', function ($order) {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    foreach (simcloud_wc_rows($order->get_id()) as $row) {
        if ((int) $row['provider_id'] > 0 && in_array($row['state'], ['queued', 'pending', 'needs_review'], true)) {
            simcloud_wc_schedule('simcloud_wc_poll', $row['id']);
        }
    }
    /* translators: %d: staff WordPress user ID. */
    $order->add_order_note(sprintf(__('SIMcloud read-only status refresh requested by staff user %d. No previous submission is repeated.', 'simcloud-woocommerce'), get_current_user_id()));
});

add_action('woocommerce_email_after_order_table', function ($order, $sent_to_admin, $plain_text) {
    if (!simcloud_wc_rows($order->get_id())) {
        return;
    }
    $message = __('Payment and prepaid delivery are separate. View this order securely for current delivery status and any voucher codes or electricity tokens.', 'simcloud-woocommerce');
    if ($plain_text) {
        echo "\n" . esc_html($message) . "\n" . esc_url_raw($order->get_checkout_order_received_url()) . "\n";
    } else {
        echo '<p>' . esc_html($message) . ' <a href="' . esc_url($order->get_checkout_order_received_url()) . '">' . esc_html__('View order', 'simcloud-woocommerce') . '</a></p>';
    }
}, 10, 3);
