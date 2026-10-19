<?php
/** Synthetic provider and gateway. Load only in the isolated test installation. */
defined('ABSPATH') || exit;
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    return;
}

add_filter('pre_wp_mail', '__return_true');
$GLOBALS['simcloud_wc_test_calls'] = [];
$GLOBALS['simcloud_wc_test_mode'] = 'success';
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (!str_starts_with($url, 'https://simcloud.co.za/api/')) {
        return new WP_Error('test_offline', 'Outbound HTTP disabled in test installation.');
    }
    $service = basename((string) wp_parse_url($url, PHP_URL_PATH), '.php');
    $method = $args['method'] ?? 'GET';
    $payload = $method === 'POST' ? json_decode($args['body'], true) : [];
    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
    $GLOBALS['simcloud_wc_test_calls'][] = ['service' => $service, 'method' => $method, 'payload' => $payload, 'query' => $query];
    $mode = $GLOBALS['simcloud_wc_test_mode'];
    if ($mode === 'timeout' && $method === 'POST') {
        return new WP_Error('timeout', 'Synthetic timeout');
    }
    $code = $method === 'POST' ? 201 : 200;
    $body = ['status' => 'success'];
    if ($mode === 'unauthorized') {
        $code = 401;
        $body = ['status' => 'error'];
    } elseif ($service === 'balance') {
        $body['balance'] = 2000;
    } elseif ($service === 'network') {
        $body += ['msisdn' => $query['msisdn'] ?? '', 'network' => $mode === 'network_mismatch' ? 'vodacom' : 'mtn'];
    } elseif ($service === 'data' && !isset($query['request_id']) && $method === 'GET') {
        $body['products'] = $GLOBALS['simcloud_wc_test_data_products'] ?? [['product_id' => 7, 'network' => 'pd-mtn', 'network_name' => 'MTN', 'description' => 'Synthetic 1GB bundle', 'groupname' => 'Test', 'amount' => 25, 'sellvalue' => 25]];
    } elseif ($service === 'vas' && !isset($query['order_id']) && $method === 'GET') {
        $body['products'] = $GLOBALS['simcloud_wc_test_vas_products'] ?? [['product_id' => 12, 'voucher_type' => 'Synthetic store voucher', 'available_amounts' => [50, 100], 'default_amount' => 100]];
    } elseif ($service === 'electricity' && isset($query['meter_number'])) {
        $body += ['meter_number' => $query['meter_number'], 'supported' => $mode === 'meter_unknown' ? null : true];
    } elseif ($method === 'POST') {
        $id = (int) get_option('simcloud_wc_test_provider_sequence', 8000) + 1;
        update_option('simcloud_wc_test_provider_sequence', $id, false);
        $body += $payload;
        $body['service'] = $service;
        $body[in_array($service, ['airtime', 'data'], true) ? 'request_id' : 'order_id'] = $id;
        if ($service === 'electricity') {
            $body['submitted_reference'] = $payload['client_reference'];
            $body['client_reference'] = $payload['client_reference'] . '_1234567890';
        }
        update_option('simcloud_wc_test_provider_' . $id, $body, false);
        if ($mode === 'bad_ack') {
            unset($body['request_id'], $body['order_id']);
        }
        if ($mode === 'conflict') {
            $code = 409;
            $body = ['status' => 'error'];
        }
    } else {
        $id = (int) ($query['request_id'] ?? $query['order_id'] ?? 0);
        $original = get_option('simcloud_wc_test_provider_' . $id, []);
        $body = $original;
        $body['status'] = in_array($service, ['airtime', 'data'], true) ? 'delivered' : 'success';
        if ($service === 'vas') {
            $body['vouchers'] = [['voucher_type' => 'Synthetic store voucher', 'voucher_code' => 'TEST-NOT-REDEEMABLE-100', 'value' => 100, 'expiry_date' => '2030-01-01']];
        }
        if ($service === 'electricity') {
            $body = ['status' => 'success', 'transaction_details' => ['meter_number' => $original['meter_number'] ?? '', 'recipient' => $original['recipient'] ?? ''],
                'recharge_pin_information' => [['description' => 'Synthetic token', 'pin' => '0000-0000-0000-0000-0000', 'units' => '42.5']]];
        }
        if ($mode === 'pending') {
            $body['status'] = 'pending';
        } elseif ($mode === 'failed') {
            $body['status'] = 'failed';
        } elseif ($mode === 'missing_codes') {
            unset($body['vouchers'], $body['recharge_pin_information']);
        } elseif ($mode === 'mismatch') {
            $body['amount'] = 999;
            $body['transaction_details']['meter_number'] = 'WRONG';
        } elseif ($mode === 'not_found') {
            $code = 404;
            $body = ['status' => 'error'];
        } elseif ($mode === 'server_error') {
            $code = 503;
            $body = ['status' => 'error'];
        }
    }
    return ['headers' => [], 'body' => wp_json_encode($body), 'response' => ['code' => $code, 'message' => 'Synthetic provider'], 'cookies' => []];
}, PHP_INT_MAX, 3);

add_filter('woocommerce_payment_gateways', function ($gateways) {
    if (!class_exists('SIMcloud_WC_Test_Gateway')) {
        class SIMcloud_WC_Test_Gateway extends WC_Payment_Gateway {
            public function __construct() {
                $this->id = 'simcloud_test';
                $this->enabled = 'yes';
                $this->title = 'Synthetic payment (no money)';
                $this->method_title = $this->title;
                $this->description = 'Synthetic test payment; no money moves.';
                $this->method_description = $this->description;
                $this->has_fields = false;
            }
            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $order->payment_complete('synthetic-' . $order_id);
                return ['result' => 'success', 'redirect' => $this->get_return_url($order)];
            }
        }
    }
    $gateways[] = 'SIMcloud_WC_Test_Gateway';
    return $gateways;
});
