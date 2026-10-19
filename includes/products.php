<?php
defined('ABSPATH') || exit;

function simcloud_wc_config($product) {
    return $product instanceof WC_Product && is_array($product->get_meta('_simcloud_wc_config')) ? $product->get_meta('_simcloud_wc_config') : [];
}

function simcloud_wc_import_data_products($catalogue) {
    return simcloud_wc_import_products('data', $catalogue);
}

function simcloud_wc_import_vas_products($catalogue) {
    return simcloud_wc_import_products('vas', $catalogue);
}

function simcloud_wc_import_key($config) {
    $service = $config['service'] ?? '';
    $id = (int) ($config['product_id'] ?? 0);
    if ($id < 1 || !in_array($service, ['data', 'vas'], true)) {
        return '';
    }
    return $service . ':' . $id . ($service === 'vas' ? ':' . (string) simcloud_wc_cents($config['amount'] ?? '') : '');
}

function simcloud_wc_import_products($service, $catalogue) {
    if (!current_user_can('manage_woocommerce') || !current_user_can('edit_products') || !current_user_can('upload_files')) {
        return new WP_Error('simcloud_import_permission', __('Product setup requires WooCommerce management, product editing and media upload permissions.', 'simcloud-woocommerce'));
    }
    if (get_woocommerce_currency() !== 'ZAR') {
        return new WP_Error('simcloud_import_currency', __('Product import requires a ZAR store.', 'simcloud-woocommerce'));
    }
    if (!in_array($service, ['data', 'vas'], true) || !is_array($catalogue) || !isset($catalogue['products']) || !is_array($catalogue['products'])) {
        return new WP_Error('simcloud_import_catalogue', __('No valid catalogue was returned. No products were imported.', 'simcloud-woocommerce'));
    }
    global $wpdb;
    // Connection-scoped lock serializes product creation and shared-logo imports.
    $lock = 'simcloud_products_' . md5(DB_NAME . $wpdb->prefix);
    if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') {
        return new WP_Error('simcloud_import_busy', __('Another product import is running, or its database lock is unavailable. Try again later.', 'simcloud-woocommerce'));
    }
    $result = ['created' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0, 'images_added' => 0, 'image_warnings' => 0];
    try {
        $existing = [];
        $ids = get_posts(['post_type' => 'product', 'post_status' => array_keys(get_post_stati()), 'numberposts' => -1, 'fields' => 'ids',
            'meta_query' => ['relation' => 'OR', ['key' => '_simcloud_wc_config', 'compare' => 'EXISTS'],
                ['key' => '_simcloud_wc_data_product_id', 'compare' => 'EXISTS'], ['key' => '_simcloud_wc_import_key', 'compare' => 'EXISTS']]]);
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $keys = [simcloud_wc_import_key(simcloud_wc_config($product)), (string) $product->get_meta('_simcloud_wc_import_key')];
            $old_id = (int) $product->get_meta('_simcloud_wc_data_product_id');
            if ($old_id > 0) {
                $keys[] = 'data:' . $old_id;
            }
            foreach (array_filter($keys) as $key) {
                $existing[$key][$id] = $product;
            }
        }
        $id_counts = [];
        foreach ($catalogue['products'] as $entry) {
            $id = is_array($entry) ? simcloud_wc_text($entry['product_id'] ?? '') : '';
            $id_counts[$id] = ($id_counts[$id] ?? 0) + 1;
        }
        $networks = ['mtn' => 'MTN', 'vodacom' => 'Vodacom', 'cellc' => 'Cell C', 'heita' => 'Telkom'];
        foreach ($catalogue['products'] as $entry) {
            $id = is_array($entry) ? simcloud_wc_text($entry['product_id'] ?? '') : '';
            $description = is_array($entry) ? simcloud_wc_text($entry[$service === 'data' ? 'description' : 'voucher_type'] ?? '') : '';
            if (!preg_match('/^[1-9][0-9]*$/D', $id) || strlen($id) > 10 || $id_counts[$id] !== 1 || $description === '') {
                $result['skipped']++;
                continue;
            }
            $amounts = $service === 'data' ? [$entry['amount'] ?? ''] : ($entry['available_amounts'] ?? []);
            if (!is_array($amounts) || !$amounts) {
                $result['skipped']++;
                continue;
            }
            $seen_amounts = [];
            foreach ($amounts as $amount) {
                $config = ['service' => $service, 'network' => $service === 'data' ? simcloud_wc_network($entry['network'] ?? '') : '',
                    'amount' => simcloud_wc_text($amount), 'product_id' => (int) $id];
                if (is_wp_error(simcloud_wc_validate_config($config))) {
                    $result['skipped']++;
                    continue;
                }
                $cents = simcloud_wc_cents($config['amount']);
                if (isset($seen_amounts[$cents])) {
                    continue;
                }
                $seen_amounts[$cents] = true;
                $config['amount'] = simcloud_wc_decimal($cents);
                $key = simcloud_wc_import_key($config);
                if (isset($existing[$key])) {
                    $result['existing']++;
                    foreach ($existing[$key] as $product) {
                        // Existing copy/prices/state stay intact; only fill an empty image on an active mapping.
                        if ($product->get_status() !== 'trash' && simcloud_wc_import_key(simcloud_wc_config($product)) === $key && !$product->get_image_id('edit')) {
                            simcloud_wc_import_image($product, $config, $entry, $result, true);
                        }
                    }
                    continue;
                }
                try {
                    $product = new WC_Product_Simple();
                    $product->set_name($service === 'data' ? $networks[$config['network']] . ' — ' . $description : $description . ' — R' . $config['amount']);
                    $product->set_description($description);
                    $product->set_short_description($service === 'data' ? simcloud_wc_text($entry['groupname'] ?? '') : '');
                    $product->set_status('publish');
                    $product->set_regular_price($config['amount']);
                    $product->set_sku('simcloud-' . $service . '-' . $id . ($service === 'vas' ? '-' . $cents : ''));
                    $product->set_virtual(true);
                    $product->set_downloadable(false);
                    $product->update_meta_data('_simcloud_wc_config', $config);
                    $product->update_meta_data('_simcloud_wc_import_key', $key);
                    if ($service === 'data') {
                        $product->update_meta_data('_simcloud_wc_data_product_id', (int) $id);
                    }
                    $image = simcloud_wc_import_image($product, $config, $entry, $result, false);
                    if (!$product->save()) {
                        throw new RuntimeException('Product save returned no ID.');
                    }
                    $existing[$key][$product->get_id()] = $product;
                    $result['created']++;
                    if ($image) {
                        $result['images_added']++;
                    }
                } catch (Throwable $error) {
                    $result['failed']++;
                    wc_get_logger()->error('Catalogue product import save failed.', ['source' => 'simcloud-woocommerce', 'service' => $service,
                        'provider_product_id' => (int) $id, 'error_type' => get_class($error),
                        'error_code' => $error instanceof WC_Data_Exception ? sanitize_key($error->getErrorCode()) : (int) $error->getCode()]);
                }
            }
        }
        wc_get_logger()->info('Catalogue product import finished.', ['source' => 'simcloud-woocommerce', 'service' => $service] + $result);
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
    return $result;
}

function simcloud_wc_import_image($product, $config, $entry, &$result, $save) {
    try {
        $image = simcloud_wc_set_default_image($product, $config, $entry);
        if (is_wp_error($image)) {
            $result['image_warnings']++;
            wc_get_logger()->warning('Default product logo could not be added.', ['source' => 'simcloud-woocommerce',
                'product_id' => $product->get_id(), 'provider_product_id' => $config['product_id'], 'reason' => $image->get_error_code()]);
        } elseif ($image) {
            if ($save && !$product->save()) {
                throw new RuntimeException('Image save returned no product ID.');
            }
            if ($save) {
                $result['images_added']++;
            }
            return $image;
        }
    } catch (Throwable $error) {
        $result['image_warnings']++;
        wc_get_logger()->error('Default product image save failed.', ['source' => 'simcloud-woocommerce', 'product_id' => $product->get_id(), 'error_type' => get_class($error)]);
    }
    return 0;
}

function simcloud_wc_validate_config($config, $catalogue_check = true) {
    $service = $config['service'] ?? '';
    $cents = simcloud_wc_cents($config['amount'] ?? '');
    if (!in_array($service, ['airtime', 'data', 'vas', 'electricity'], true) || $cents === null || $cents < 1) {
        return new WP_Error('simcloud_product', __('Select a valid SIMcloud service and provider amount.', 'simcloud-woocommerce'));
    }
    if ($service === 'airtime' && ($cents < 200 || $cents > 99900)) {
        return new WP_Error('simcloud_amount', __('Airtime must be between R2 and R999.', 'simcloud-woocommerce'));
    }
    if ($service === 'electricity' && ($cents < 5500 || $cents > 100000 || $cents % 100 !== 0)) {
        return new WP_Error('simcloud_amount', __('Electricity must be whole rand between R55 and R1000.', 'simcloud-woocommerce'));
    }
    if (in_array($service, ['airtime', 'data'], true) && !in_array($config['network'] ?? '', ['mtn', 'vodacom', 'cellc', 'heita'], true)) {
        return new WP_Error('simcloud_network', __('Select a supported mobile network.', 'simcloud-woocommerce'));
    }
    if ($catalogue_check && in_array($service, ['data', 'vas'], true)) {
        $catalogue = simcloud_wc_catalogue($service);
        if (is_wp_error($catalogue)) {
            return $catalogue;
        }
        $matches = [];
        foreach ($catalogue['products'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($service === 'data' && simcloud_wc_network($entry['network'] ?? '') === ($config['network'] ?? '')
                && simcloud_wc_cents($entry['amount'] ?? '') === $cents) {
                $matches[] = $entry;
            }
            if ($service === 'vas' && (int) ($entry['product_id'] ?? 0) === (int) ($config['product_id'] ?? 0)) {
                foreach ((array) ($entry['available_amounts'] ?? []) as $amount) {
                    if (simcloud_wc_cents($amount) === $cents) {
                        $matches[] = $entry;
                        break;
                    }
                }
            }
        }
        if (count($matches) !== 1 || ($service === 'data' && (int) ($matches[0]['product_id'] ?? 0) !== (int) ($config['product_id'] ?? 0))) {
            return new WP_Error('simcloud_bundle', __('The selected bundle or voucher denomination is unavailable or ambiguous. Refresh the catalogue and update the product.', 'simcloud-woocommerce'));
        }
    }
    return true;
}

add_action('woocommerce_product_options_general_product_data', function () {
    global $product_object;
    $config = simcloud_wc_config($product_object);
    echo '<div class="options_group"><p class="form-field"><strong>' . esc_html__('SIMcloud prepaid fulfilment', 'simcloud-woocommerce') . '</strong></p>';
    woocommerce_wp_select(['id' => 'simcloud_wc_service', 'label' => __('Service', 'simcloud-woocommerce'), 'value' => $config['service'] ?? '',
        'options' => ['' => __('Not a prepaid product', 'simcloud-woocommerce'), 'airtime' => 'Airtime', 'data' => 'Data', 'vas' => 'VAS voucher', 'electricity' => 'Electricity']]);
    woocommerce_wp_select(['id' => 'simcloud_wc_network', 'label' => __('Mobile network', 'simcloud-woocommerce'), 'value' => $config['network'] ?? '',
        'options' => ['' => '—', 'mtn' => 'MTN', 'vodacom' => 'Vodacom', 'cellc' => 'Cell C', 'heita' => 'Telkom']]);
    woocommerce_wp_text_input(['id' => 'simcloud_wc_amount', 'label' => __('Provider amount (ZAR)', 'simcloud-woocommerce'), 'value' => $config['amount'] ?? '',
        'description' => __('Face value/bundle amount, independent of retail price, tax and coupons. Electricity includes the SIMcloud fee.', 'simcloud-woocommerce'), 'desc_tip' => true]);
    $options = ['' => __('Choose for data / VAS', 'simcloud-woocommerce')];
    foreach (['data', 'vas'] as $service) {
        $catalogue = get_transient('simcloud_wc_catalogue_' . $service);
        foreach ((array) ($catalogue['products'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = (int) ($entry['product_id'] ?? 0);
            $label = $service === 'data' ? ($entry['description'] ?? '') . ' / ' . ($entry['network_name'] ?? '') . ' / R' . ($entry['amount'] ?? '') : ($entry['voucher_type'] ?? '');
            $options[$service . ':' . $id] = strtoupper($service) . ' — ' . $label;
        }
    }
    $selected = isset($config['product_id']) ? ($config['service'] ?? '') . ':' . $config['product_id'] : '';
    if ($selected !== '' && !isset($options[$selected])) {
        $options[$selected] = $selected . ' — ' . __('refresh catalogue', 'simcloud-woocommerce');
    }
    woocommerce_wp_select(['id' => 'simcloud_wc_product', 'label' => __('Catalogue product', 'simcloud-woocommerce'), 'value' => $selected, 'options' => $options,
        'description' => __('Refresh catalogues in WooCommerce → SIMcloud. For VAS, enter an available denomination above.', 'simcloud-woocommerce'), 'desc_tip' => true]);
    echo '</div>';
});

add_action('woocommerce_admin_process_product_object', function ($product) {
    if (!isset($_POST['simcloud_wc_service']) || !current_user_can('edit_post', $product->get_id())) {
        return;
    }
    // WooCommerce verifies the product-save nonce before this hook.
    $service = sanitize_key(wp_unslash($_POST['simcloud_wc_service']));
    if ($service === '') {
        $product->delete_meta_data('_simcloud_wc_config');
        return;
    }
    $config = ['service' => $service, 'network' => sanitize_key(wp_unslash($_POST['simcloud_wc_network'] ?? '')),
        'amount' => sanitize_text_field(wp_unslash($_POST['simcloud_wc_amount'] ?? ''))];
    $choice = explode(':', sanitize_text_field(wp_unslash($_POST['simcloud_wc_product'] ?? '')));
    if (in_array($service, ['data', 'vas'], true) && ($choice[0] ?? '') === $service) {
        $config['product_id'] = absint($choice[1] ?? 0);
    }
    $valid = simcloud_wc_validate_config($config);
    if (!$product->is_type('simple') || is_wp_error($valid)) {
        WC_Admin_Meta_Boxes::add_error(is_wp_error($valid) ? $valid->get_error_message() : __('SIMcloud requires a simple product.', 'simcloud-woocommerce'));
        return;
    }
    $config['amount'] = simcloud_wc_decimal(simcloud_wc_cents($config['amount']));
    $product->set_virtual(true);
    $product->set_downloadable(false);
    $product->update_meta_data('_simcloud_wc_config', $config);
    if (!$product->get_image_id('edit') && in_array($service, ['airtime', 'data', 'vas'], true)) {
        $image = simcloud_wc_set_default_image($product, $config);
        if (is_wp_error($image)) {
            WC_Admin_Meta_Boxes::add_error($image->get_error_message());
        }
    }
});

add_filter('woocommerce_is_purchasable', function ($purchasable, $product) {
    return simcloud_wc_config($product) ? ($purchasable && simcloud_wc_live_ready() && $product->is_type('simple')) : $purchasable;
}, 10, 2);

add_filter('woocommerce_quantity_input_args', function ($args, $product) {
    if (simcloud_wc_config($product)) {
        $args['min_value'] = 1;
        $args['max_value'] = 1;
    }
    return $args;
}, 10, 2);

add_filter('woocommerce_product_add_to_cart_url', function ($url, $product) {
    return simcloud_wc_config($product) ? $product->get_permalink() : $url;
}, 10, 2);
add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    return simcloud_wc_config($product) ? __('Choose prepaid details', 'simcloud-woocommerce') : $text;
}, 10, 2);
add_filter('woocommerce_product_supports', function ($supports, $feature, $product) {
    return $feature === 'ajax_add_to_cart' && simcloud_wc_config($product) ? false : $supports;
}, 10, 3);
add_filter('woocommerce_product_has_options', function ($has_options, $product) {
    return simcloud_wc_config($product) ? true : $has_options;
}, 10, 2);
