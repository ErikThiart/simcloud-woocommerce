<?php
defined('ABSPATH') || exit;

add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    $config = simcloud_wc_config($product);
    if (!$config) {
        return;
    }
    if ($config['service'] !== 'vas') {
        woocommerce_form_field('simcloud_wc_recipient', ['type' => 'tel', 'required' => true, 'label' => __('Recipient mobile number', 'simcloud-woocommerce'),
            'description' => __('Confirm the number carefully. Delivered prepaid value cannot be reversed.', 'simcloud-woocommerce')]);
    }
    if ($config['service'] === 'electricity') {
        woocommerce_form_field('simcloud_wc_meter', ['type' => 'text', 'required' => true, 'label' => __('Electricity meter number', 'simcloud-woocommerce'), 'custom_attributes' => ['maxlength' => 50]]);
        echo '<p>' . esc_html__('The configured provider amount includes the SIMcloud electricity fee. Retail price may differ. Tokens will appear in your order details.', 'simcloud-woocommerce') . '</p>';
    }
    if ($config['service'] === 'vas') {
        echo '<p>' . esc_html__('Your voucher code will appear in your order details after confirmed fulfilment.', 'simcloud-woocommerce') . '</p>';
    }
});

function simcloud_wc_purchase_input($config, $input) {
    $valid = simcloud_wc_validate_config($config);
    if (is_wp_error($valid)) {
        return $valid;
    }
    $snapshot = $config;
    $snapshot['currency'] = get_woocommerce_currency();
    if ($snapshot['currency'] !== 'ZAR') {
        return new WP_Error('simcloud_currency', __('SIMcloud products require ZAR checkout.', 'simcloud-woocommerce'));
    }
    if ($config['service'] !== 'vas') {
        $snapshot['recipient'] = simcloud_wc_phone($input['recipient'] ?? '');
        if ($snapshot['recipient'] === '') {
            return new WP_Error('simcloud_recipient', __('Enter a valid South African mobile number.', 'simcloud-woocommerce'));
        }
    }
    if (in_array($config['service'], ['airtime', 'data'], true)) {
        $lookup = simcloud_wc_api('network', 'GET', ['msisdn' => $snapshot['recipient']]);
        if (is_wp_error($lookup) || ($lookup['body']['status'] ?? '') !== 'success'
            || simcloud_wc_phone($lookup['body']['msisdn'] ?? '') !== $snapshot['recipient']
            || simcloud_wc_network($lookup['body']['network'] ?? '') !== $config['network']) {
            return new WP_Error('simcloud_recipient_network', __('The recipient network could not be confirmed for this product. Check the number and selected network.', 'simcloud-woocommerce'));
        }
    }
    if ($config['service'] === 'electricity') {
        $meter = $input['meter'] ?? '';
        $snapshot['meter'] = is_scalar($meter) ? trim((string) $meter) : '';
        if (!preg_match('/^[0-9A-Za-z]{1,50}$/D', $snapshot['meter'])) {
            return new WP_Error('simcloud_meter', __('Enter a valid electricity meter number.', 'simcloud-woocommerce'));
        }
        $check = simcloud_wc_api('electricity', 'GET', ['meter_number' => $snapshot['meter']]);
        if (is_wp_error($check) || ($check['body']['supported'] ?? null) !== true || ($check['body']['meter_number'] ?? '') !== $snapshot['meter']) {
            return new WP_Error('simcloud_meter', __('Meter support could not be confirmed. Check the meter or try again later.', 'simcloud-woocommerce'));
        }
    }
    return $snapshot;
}

add_filter('woocommerce_store_api_add_to_cart_data', function ($data, $request) {
    $extensions = $request->get_param('extensions');
    $input = is_array($extensions) && is_array($extensions['simcloud-wc'] ?? null) ? $extensions['simcloud-wc'] : [];
    $data['cart_item_data']['simcloud_wc_input'] = $input;
    return $data;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function ($data, $product_id, $variation_id, $quantity) {
    $config = simcloud_wc_config(wc_get_product($product_id));
    if (!$config) {
        return $data;
    }
    if (!simcloud_wc_live_ready() || (float) $quantity !== 1.0 || $variation_id) {
        throw new Exception(esc_html__('This prepaid purchase is unavailable or has an invalid quantity.', 'simcloud-woocommerce'));
    }
    $input = isset($data['simcloud_wc_input']) && is_array($data['simcloud_wc_input']) ? $data['simcloud_wc_input'] : [
        'recipient' => isset($_POST['simcloud_wc_recipient']) && is_scalar($_POST['simcloud_wc_recipient']) ? wc_clean(wp_unslash($_POST['simcloud_wc_recipient'])) : '',
        'meter' => isset($_POST['simcloud_wc_meter']) && is_scalar($_POST['simcloud_wc_meter']) ? wc_clean(wp_unslash($_POST['simcloud_wc_meter'])) : '',
    ];
    $snapshot = simcloud_wc_purchase_input($config, $input);
    if (is_wp_error($snapshot)) {
        throw new Exception(esc_html($snapshot->get_error_message()));
    }
    unset($data['simcloud_wc_input']);
    $data['simcloud_wc_snapshot'] = $snapshot;
    // A new line for every purchase prevents combining separate prepaid recipients.
    $data['simcloud_wc_line'] = wp_generate_uuid4();
    return $data;
}, 10, 4);

add_filter('woocommerce_get_item_data', function ($data, $cart_item) {
    $snapshot = $cart_item['simcloud_wc_snapshot'] ?? [];
    foreach (['recipient' => __('Recipient', 'simcloud-woocommerce'), 'meter' => __('Meter', 'simcloud-woocommerce')] as $key => $label) {
        if (!empty($snapshot[$key])) {
            $data[] = ['key' => $label, 'value' => $snapshot[$key]];
        }
    }
    return $data;
}, 10, 2);

function simcloud_wc_cart_errors() {
    $errors = [];
    $prepaid = 0;
    $regular = 0;
    foreach (WC()->cart->get_cart() as $line) {
        $snapshot = $line['simcloud_wc_snapshot'] ?? [];
        $product = $line['data'] ?? null;
        if (!$snapshot && !simcloud_wc_config($product)) {
            $regular++;
            continue;
        }
        $prepaid++;
        if (!$snapshot || !simcloud_wc_live_ready() || (float) $line['quantity'] !== 1.0 || !$product || !$product->is_type('simple')
            || get_woocommerce_currency() !== 'ZAR' || is_wp_error(simcloud_wc_validate_config($snapshot))) {
            $errors[] = __('A prepaid line is unavailable or invalid. Remove it and choose the product again.', 'simcloud-woocommerce');
        }
    }
    if ($prepaid && $regular) {
        $errors[] = __('Purchase prepaid products separately from other products.', 'simcloud-woocommerce');
    }
    return array_unique($errors);
}

add_action('woocommerce_check_cart_items', function () {
    foreach (simcloud_wc_cart_errors() as $error) {
        wc_add_notice($error, 'error');
    }
});
add_action('woocommerce_store_api_cart_errors', function ($errors) {
    foreach (simcloud_wc_cart_errors() as $error) {
        $errors->add('simcloud_cart', $error);
    }
});
add_filter('woocommerce_update_cart_validation', function ($valid, $key, $line, $quantity) {
    if (!empty($line['simcloud_wc_snapshot']) && !in_array((float) $quantity, [0.0, 1.0], true)) {
        wc_add_notice(__('Each prepaid line must have quantity one.', 'simcloud-woocommerce'), 'error');
        return false;
    }
    return $valid;
}, 10, 4);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $line, $order) {
    if (!empty($line['simcloud_wc_snapshot'])) {
        $snapshot = $line['simcloud_wc_snapshot'];
        $snapshot['retail_total'] = (string) $item->get_total();
        $item->add_meta_data('_simcloud_wc_snapshot', $snapshot, true);
    }
}, 10, 4);

add_action('woocommerce_init', function () {
    if (function_exists('woocommerce_store_api_register_endpoint_data')) {
        woocommerce_store_api_register_endpoint_data([
            'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
            'namespace' => 'simcloud-wc',
            'data_callback' => function ($item) {
                $s = $item['simcloud_wc_snapshot'] ?? [];
                return ['recipient' => (string) ($s['recipient'] ?? ''), 'meter' => (string) ($s['meter'] ?? '')];
            },
            'schema_callback' => function () {
                return ['recipient' => ['type' => 'string', 'readonly' => true], 'meter' => ['type' => 'string', 'readonly' => true]];
            },
            'schema_type' => ARRAY_A,
        ]);
    }
});
