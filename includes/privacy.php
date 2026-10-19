<?php
defined('ABSPATH') || exit;

add_action('admin_init', function () {
    if (function_exists('wp_add_privacy_policy_content')) {
        wp_add_privacy_policy_content('SIMcloud for WooCommerce', wp_kses_post(__('<p>When you purchase prepaid products, we send the chosen network, prepaid amount or product, a purchase reference, and (where applicable) your recipient number and electricity meter number to SIMcloud for validation and fulfilment. Voucher codes and electricity tokens are stored with restricted order records. Payment details are handled by our payment gateway and are not sent to SIMcloud by this plugin. Contact this store about delivery and refunds. Store administrators should add verified SIMcloud terms/privacy links and their chosen order-retention period to this notice.</p>', 'simcloud-woocommerce')));
    }
});

add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
    $exporters['simcloud-wc'] = ['exporter_friendly_name' => 'SIMcloud prepaid purchases', 'callback' => 'simcloud_wc_export'];
    return $exporters;
});

function simcloud_wc_export($email, $page = 1) {
    $orders = wc_get_orders(['billing_email' => $email, 'limit' => 20, 'page' => max(1, (int) $page), 'orderby' => 'ID', 'order' => 'ASC']);
    $data = [];
    foreach ($orders as $order) {
        foreach (simcloud_wc_rows($order->get_id()) as $row) {
            $s = simcloud_wc_order_snapshot($order, $row['item_id']);
            $values = ['Order' => (string) $order->get_id(), 'Service' => $s['service'] ?? '', 'Recipient' => $s['recipient'] ?? '',
                'Meter' => $s['meter'] ?? '', 'State' => $row['state'], 'Reference' => $row['reference']];
            $fields = [];
            foreach ($values as $name => $value) {
                $fields[] = ['name' => $name, 'value' => (string) $value];
            }
            $data[] = ['group_id' => 'simcloud-wc', 'group_label' => __('SIMcloud purchases', 'simcloud-woocommerce'),
                'item_id' => 'simcloud-wc-' . $row['id'], 'data' => $fields];
        }
    }
    return ['data' => $data, 'done' => count($orders) < 20];
}

add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
    $erasers['simcloud-wc'] = ['eraser_friendly_name' => 'SIMcloud prepaid purchases', 'callback' => function ($email, $page = 1) {
        $export = simcloud_wc_export($email, $page);
        return ['items_removed' => false, 'items_retained' => !empty($export['data']), 'messages' => !empty($export['data'])
            ? [__('Prepaid purchase records were retained for reconciliation. Review unresolved delivery and your accounting retention obligations before deleting order snapshots or redeemable codes.', 'simcloud-woocommerce')] : [], 'done' => $export['done']];
    }];
    return $erasers;
});
