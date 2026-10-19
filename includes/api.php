<?php
defined('ABSPATH') || exit;

function simcloud_wc_token() {
    return defined('SIMCLOUD_WC_API_TOKEN') ? trim((string) SIMCLOUD_WC_API_TOKEN) : trim((string) get_option('simcloud_wc_token', ''));
}

function simcloud_wc_live_ready() {
    if ((defined('SIMCLOUD_WC_DISABLE_SUBMISSIONS') && SIMCLOUD_WC_DISABLE_SUBMISSIONS) || wp_get_environment_type() !== 'production') {
        return false;
    }
    return get_option('simcloud_wc_enabled') === 'yes'
        && hash_equals((string) get_option('simcloud_wc_bound_site', ''), hash('sha256', home_url('/')))
        && simcloud_wc_token() !== '' && !get_option('simcloud_wc_auth_error');
}

function simcloud_wc_api($service, $method = 'GET', $data = []) {
    if (!in_array($service, ['airtime', 'data', 'vas', 'electricity', 'balance', 'network'], true) || !in_array($method, ['GET', 'POST'], true)) {
        return new WP_Error('simcloud_endpoint', __('Unsupported SIMcloud operation.', 'simcloud-woocommerce'));
    }
    if (simcloud_wc_token() === '') {
        return new WP_Error('simcloud_token', __('Configure the SIMcloud API token first.', 'simcloud-woocommerce'));
    }
    $url = 'https://simcloud.co.za/api/' . $service . '.php';
    $args = ['method' => $method, 'timeout' => 35, 'redirection' => 0, 'sslverify' => true,
        'headers' => ['Authorization' => 'Bearer ' . simcloud_wc_token(), 'Accept' => 'application/json']];
    if ($method === 'GET') {
        $url = add_query_arg($data, $url);
    } else {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = wp_json_encode($data);
    }
    $response = wp_remote_request($url, $args);
    if (is_wp_error($response)) {
        // Do not retain transport messages: third-party transports may include URLs or headers.
        return new WP_Error('simcloud_transport', __('SIMcloud connection failed; the outcome may be unknown.', 'simcloud-woocommerce'));
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code === 401) {
        update_option('simcloud_wc_auth_error', 'yes', false);
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($code < 200 || $code > 299 || !is_array($body)) {
        /* translators: %d: HTTP response status code. */
        return new WP_Error('simcloud_response', sprintf(__('SIMcloud returned HTTP %d or an invalid response. Check the merchant account.', 'simcloud-woocommerce'), $code), ['http_status' => $code]);
    }
    return ['code' => $code, 'body' => $body];
}

function simcloud_wc_cents($value) {
    if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string) $value) || strlen((string) $value) > 12) {
        return null;
    }
    $parts = explode('.', (string) $value);
    return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
}

function simcloud_wc_decimal($cents) {
    return sprintf('%d.%02d', intdiv((int) $cents, 100), (int) $cents % 100);
}

function simcloud_wc_text($value) {
    return is_scalar($value) ? sanitize_text_field((string) $value) : '';
}

function simcloud_wc_phone($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $number = preg_replace('/[\s()-]+/', '', (string) $value);
    if (preg_match('/^0[6-8]\d{8}$/D', $number)) {
        $number = '+27' . substr($number, 1);
    } elseif (preg_match('/^27[6-8]\d{8}$/D', $number)) {
        $number = '+' . $number;
    }
    return preg_match('/^\+27[6-8]\d{8}$/D', $number) ? $number : '';
}

function simcloud_wc_network($value) {
    if (!is_scalar($value)) {
        return '';
    }
    $key = strtolower(str_replace([' ', '-', '_'], '', (string) $value));
    $key = preg_replace('/^(?:pd|p)/', '', $key);
    $map = ['mtn' => 'mtn', 'vodacom' => 'vodacom', 'cellc' => 'cellc', 'telkom' => 'heita', 'heita' => 'heita'];
    return $map[$key] ?? '';
}

function simcloud_wc_catalogue($service, $refresh = false) {
    if (!in_array($service, ['data', 'vas'], true)) {
        return new WP_Error('simcloud_catalogue', __('Unsupported catalogue.', 'simcloud-woocommerce'));
    }
    $key = 'simcloud_wc_catalogue_' . $service;
    $cached = $refresh ? false : get_transient($key);
    if (is_array($cached)) {
        return $cached;
    }
    $response = simcloud_wc_api($service, 'GET', $service === 'data' ? ['products' => 1] : []);
    if (is_wp_error($response)) {
        return $response;
    }
    $body = $response['body'];
    if (($body['status'] ?? '') !== 'success' || !isset($body['products']) || !is_array($body['products'])) {
        return new WP_Error('simcloud_catalogue', __('SIMcloud catalogue is unavailable.', 'simcloud-woocommerce'));
    }
    $catalogue = ['products' => $body['products'], 'updated_at' => time()];
    set_transient($key, $catalogue, 15 * MINUTE_IN_SECONDS);
    return $catalogue;
}

function simcloud_wc_log($row, $event) {
    // Only controlled event labels and local/provider identifiers enter operational logs.
    wc_get_logger()->warning($event, ['source' => 'simcloud-woocommerce', 'order_id' => (int) $row['order_id'],
        'fulfilment_id' => (int) $row['id'], 'provider_id' => (int) $row['provider_id']]);
}
