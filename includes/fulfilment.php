<?php
defined('ABSPATH') || exit;

function simcloud_wc_order_snapshot($order, $item_id) {
    $item = $order ? $order->get_item($item_id) : false;
    $snapshot = $item ? $item->get_meta('_simcloud_wc_snapshot') : [];
    return is_array($snapshot) ? $snapshot : [];
}

function simcloud_wc_order_eligible($order) {
    return $order && $order->is_paid() && !$order->has_status(['cancelled', 'refunded', 'failed'])
        && (float) $order->get_total_refunded() === 0.0 && $order->get_currency() === 'ZAR'
        && $order->get_meta('_simcloud_wc_funds_confirmed') === 'yes';
}

function simcloud_wc_prepare_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    foreach ($order->get_items() as $item_id => $item) {
        if (simcloud_wc_order_snapshot($order, $item_id)) {
            $row = simcloud_wc_record($order_id, $item_id);
            if (!$row) {
                $order->add_order_note(__('SIMcloud could not persist the fulfilment record. No recharge was submitted.', 'simcloud-woocommerce'));
                continue;
            }
            if (simcloud_wc_order_eligible($order) && $row['state'] === 'awaiting_payment'
                && simcloud_wc_transition($row['id'], 'awaiting_payment', 'ready')) {
                simcloud_wc_schedule('simcloud_wc_submit', $row['id']);
            }
        }
    }
}

add_action('woocommerce_checkout_order_created', function ($order) { simcloud_wc_prepare_order($order->get_id()); });
add_action('woocommerce_store_api_checkout_order_processed', function ($order) { simcloud_wc_prepare_order($order->get_id()); });
add_action('woocommerce_payment_complete', function ($order_id) {
    $order = wc_get_order($order_id);
    $gateways = array_filter(array_map('trim', explode(',', (string) get_option('simcloud_wc_gateways', ''))));
    if ($order && in_array($order->get_payment_method(), $gateways, true)
        && !in_array($order->get_payment_method(), ['cod', 'bacs', 'cheque'], true)) {
        $order->update_meta_data('_simcloud_wc_funds_confirmed', 'yes');
        $order->save();
        simcloud_wc_prepare_order($order_id);
    }
});
add_filter('woocommerce_payment_complete_order_status', function ($status, $order_id, $order) {
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('_simcloud_wc_snapshot')) {
            return 'processing';
        }
    }
    return $status;
}, 10, 3);

function simcloud_wc_payload($snapshot, $reference) {
    $service = $snapshot['service'];
    $payload = ['amount' => (float) $snapshot['amount'], 'reference' => $reference];
    if (in_array($service, ['airtime', 'data'], true)) {
        $payload['msisdn'] = $snapshot['recipient'];
        $payload['network'] = ($service === 'data' ? 'pd-' : 'p-') . $snapshot['network'];
    } elseif ($service === 'vas') {
        $payload['product_id'] = (int) $snapshot['product_id'];
    } else {
        $payload = ['amount' => (int) $snapshot['amount'], 'client_reference' => $reference,
            'meter_number' => $snapshot['meter'], 'recipient' => $snapshot['recipient'], 'send_sms' => false];
    }
    return $payload;
}

function simcloud_wc_review($row, $message) {
    if (simcloud_wc_transition($row['id'], $row['state'], 'needs_review', ['message' => $message])) {
        simcloud_wc_log($row, $message);
        $order = wc_get_order($row['order_id']);
        if ($order) {
            /* translators: 1: WooCommerce item ID, 2: redacted review reason. */
            $order->add_order_note(sprintf(__('SIMcloud item %1$d needs review: %2$s', 'simcloud-woocommerce'), $row['item_id'], $message));
        }
    }
}

function simcloud_wc_ack_valid($body, $snapshot, $reference) {
    $service = $snapshot['service'];
    $id_field = in_array($service, ['airtime', 'data'], true) ? 'request_id' : 'order_id';
    if (($body['status'] ?? '') !== 'success' || !is_scalar($body[$id_field] ?? null) || !ctype_digit((string) $body[$id_field]) || (int) $body[$id_field] < 1
        || simcloud_wc_cents($body['amount'] ?? '') !== simcloud_wc_cents($snapshot['amount'])) {
        return false;
    }
    if ($service === 'electricity') {
        return ($body['submitted_reference'] ?? '') === $reference && is_string($body['client_reference'] ?? null) && $body['client_reference'] !== ''
            && ($body['meter_number'] ?? '') === $snapshot['meter'] && simcloud_wc_phone($body['recipient'] ?? '') === $snapshot['recipient'];
    }
    if (($body['service'] ?? '') !== $service || ($body['reference'] ?? '') !== $reference) {
        return false;
    }
    return $service === 'vas' ? (int) ($body['product_id'] ?? 0) === (int) $snapshot['product_id']
        : simcloud_wc_phone($body['msisdn'] ?? '') === $snapshot['recipient'] && simcloud_wc_network($body['network'] ?? '') === $snapshot['network'];
}

function simcloud_wc_submit($id) {
    $row = simcloud_wc_row($id);
    if (!$row || $row['state'] !== 'ready' || !simcloud_wc_live_ready()) {
        return;
    }
    $order = wc_get_order($row['order_id']);
    $snapshot = simcloud_wc_order_snapshot($order, $row['item_id']);
    $item = $order ? $order->get_item($row['item_id']) : false;
    if (!simcloud_wc_order_eligible($order) || !$item || (float) $item->get_quantity() !== 1.0 || !$snapshot
        || ($snapshot['currency'] ?? '') !== 'ZAR') {
        simcloud_wc_review($row, 'Order is no longer eligible for prepaid submission.');
        return;
    }
    // Recheck catalogue/network/meter before claiming an irreversible submission attempt.
    $valid = simcloud_wc_purchase_input($snapshot, ['recipient' => $snapshot['recipient'] ?? '', 'meter' => $snapshot['meter'] ?? '']);
    if (is_wp_error($valid)) {
        simcloud_wc_review($row, 'Pre-submission product or recipient validation failed.');
        return;
    }
    if (!simcloud_wc_transition($id, 'ready', 'submitting', ['submitted_at' => gmdate('Y-m-d H:i:s')])) {
        return;
    }
    $row = simcloud_wc_row($id);
    $order = wc_get_order($row['order_id']);
    if (!simcloud_wc_order_eligible($order) || !simcloud_wc_live_ready()) {
        simcloud_wc_review($row, 'Payment or fulfilment configuration changed before submission.');
        return;
    }
    $response = simcloud_wc_api($snapshot['service'], 'POST', simcloud_wc_payload($snapshot, $row['reference']));
    if (is_wp_error($response)) {
        simcloud_wc_review($row, $response->get_error_message());
        return;
    }
    $body = $response['body'];
    if ($response['code'] !== 201 || !simcloud_wc_ack_valid($body, $snapshot, $row['reference'])) {
        simcloud_wc_review($row, 'Create acknowledgement is missing or does not match the purchase.');
        return;
    }
    $provider_id = (int) ($body['request_id'] ?? $body['order_id']);
    $provider_reference = (string) ($body['client_reference'] ?? $body['transaction_id'] ?? '');
    if (!simcloud_wc_transition($id, 'submitting', 'queued', ['provider_id' => $provider_id, 'provider_reference' => $provider_reference])) {
        simcloud_wc_log($row, 'Provider accepted a request but local acknowledgement persistence failed. Reconcile before any new submission.');
        return;
    }
    simcloud_wc_schedule('simcloud_wc_poll', $id, 30);
}

function simcloud_wc_parse_status($body, $snapshot, $row) {
    $service = $snapshot['service'];
    $status = $body['status'] ?? '';
    if ($service === 'electricity') {
        if ($status === 'pending') {
            return ['state' => 'pending', 'result' => []];
        }
        $details = $body['transaction_details'] ?? [];
        if (!is_array($details) || ($details['meter_number'] ?? '') !== $snapshot['meter']
            || simcloud_wc_phone($details['recipient'] ?? '') !== $snapshot['recipient']) {
            return new WP_Error('simcloud_identity', 'Electricity response does not match the purchased meter and recipient.');
        }
    } elseif (!simcloud_wc_ack_valid(array_merge($body, ['status' => 'success']), $snapshot, $row['reference'])
        || (int) ($body['request_id'] ?? $body['order_id'] ?? 0) !== (int) $row['provider_id']) {
        return new WP_Error('simcloud_identity', 'Provider response does not match the purchased item.');
    }
    if (in_array($status, ['queued', 'pending'], true)) {
        return ['state' => 'pending', 'result' => []];
    }
    if (in_array($status, ['failed', 'cancelled'], true)) {
        return ['state' => 'failed', 'result' => []];
    }
    $success = in_array($service, ['vas', 'electricity'], true) ? 'success' : 'delivered';
    if ($status !== $success) {
        return new WP_Error('simcloud_status', 'Unrecognized provider status.');
    }
    $result = [];
    if ($service === 'vas') {
        foreach ((array) ($body['vouchers'] ?? []) as $voucher) {
            if (!is_array($voucher) || !is_scalar($voucher['voucher_code'] ?? null) || trim((string) $voucher['voucher_code']) === '') {
                return new WP_Error('simcloud_voucher', 'Completed VAS order is missing a voucher code.');
            }
            $result[] = ['code' => simcloud_wc_text($voucher['voucher_code']),
                'description' => simcloud_wc_text($voucher['voucher_type'] ?? 'Voucher'),
                'expiry' => simcloud_wc_text($voucher['expiry_date'] ?? '')];
        }
    } elseif ($service === 'electricity') {
        foreach ((array) ($body['recharge_pin_information'] ?? []) as $pin) {
            if (!is_array($pin) || !is_scalar($pin['pin'] ?? null) || trim((string) $pin['pin']) === '') {
                return new WP_Error('simcloud_pin', 'Completed electricity order is missing token information.');
            }
            $result[] = ['code' => simcloud_wc_text($pin['pin']), 'description' => simcloud_wc_text($pin['description'] ?? 'Electricity token'),
                'units' => simcloud_wc_text($pin['units'] ?? '')];
        }
    }
    if (in_array($service, ['vas', 'electricity'], true) && !$result) {
        return new WP_Error('simcloud_code', 'Provider marked the purchase complete without redeemable codes.');
    }
    return ['state' => 'delivered', 'result' => $result];
}

function simcloud_wc_poll($id) {
    $row = simcloud_wc_row($id);
    if (!$row || !in_array($row['state'], ['queued', 'pending', 'needs_review'], true) || (int) $row['provider_id'] < 1 || !simcloud_wc_live_ready()) {
        return;
    }
    $order = wc_get_order($row['order_id']);
    $snapshot = simcloud_wc_order_snapshot($order, $row['item_id']);
    if (!$snapshot) {
        simcloud_wc_review($row, 'Purchased item snapshot is unavailable.');
        return;
    }
    $field = in_array($snapshot['service'], ['airtime', 'data'], true) ? 'request_id' : 'order_id';
    $response = simcloud_wc_api($snapshot['service'], 'GET', [$field => (int) $row['provider_id']]);
    $parsed = is_wp_error($response) ? $response : simcloud_wc_parse_status($response['body'], $snapshot, $row);
    $polls = (int) $row['polls'] + 1;
    if (is_wp_error($parsed)) {
        $http = (int) (($parsed->get_error_data()['http_status'] ?? 0));
        if (!in_array($parsed->get_error_code(), ['simcloud_transport', 'simcloud_response'], true) || in_array($http, [400, 401, 404], true)) {
            simcloud_wc_review($row, $parsed->get_error_message());
            return;
        }
        $parsed = ['state' => 'pending', 'result' => []];
    }
    if ($parsed['state'] === 'pending' && ($polls >= 16 || strtotime(($row['submitted_at'] ?: $row['created_at']) . ' UTC') < time() - HOUR_IN_SECONDS)) {
        simcloud_wc_review($row, 'Delivery confirmation exceeded the polling window. Refresh status after investigation.');
        return;
    }
    $values = ['polls' => $polls, 'result' => wp_json_encode($parsed['result']), 'message' => ''];
    if (!simcloud_wc_transition($id, $row['state'], $parsed['state'], $values)) {
        return;
    }
    if ($parsed['state'] === 'pending') {
        $delay = [60, 120, 300][min($polls - 1, 2)];
        simcloud_wc_schedule('simcloud_wc_poll', $id, $delay);
        return;
    }
    /* translators: 1: WooCommerce item ID, 2: delivery state, 3: provider request ID. */
    $order->add_order_note(sprintf(__('SIMcloud item %1$d: %2$s (provider request %3$d).', 'simcloud-woocommerce'), $row['item_id'], $parsed['state'], $row['provider_id']));
    if ($parsed['state'] === 'failed') {
        simcloud_wc_log($row, 'Provider confirmed fulfilment failure. Merchant review required; no automatic refund or resubmission.');
    }
    $rows = simcloud_wc_rows($order->get_id());
    $all_delivered = count($rows) === count($order->get_items());
    foreach ($rows as $entry) {
        $all_delivered = $all_delivered && $entry['state'] === 'delivered';
    }
    if ($all_delivered && simcloud_wc_order_eligible($order)) {
        $order->update_status('completed', __('All SIMcloud items have confirmed delivery. Voucher/token details are available in the order.', 'simcloud-woocommerce'));
    }
}

function simcloud_wc_watchdog() {
    global $wpdb;
    $table = simcloud_wc_table();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE state IN ('submitting','ready','queued','pending') AND updated_at < %s ORDER BY id LIMIT 100", $table, gmdate('Y-m-d H:i:s', time() - 300)), ARRAY_A);
    foreach ($rows as $row) {
        if ($row['state'] === 'submitting') {
            simcloud_wc_review($row, 'Submission was interrupted. Reconcile before any new purchase.');
        } else {
            $hook = $row['state'] === 'ready' ? 'simcloud_wc_submit' : 'simcloud_wc_poll';
            if (!as_has_scheduled_action($hook, [(int) $row['id']], 'simcloud-wc')) {
                simcloud_wc_schedule($hook, $row['id']);
            }
        }
    }
}
