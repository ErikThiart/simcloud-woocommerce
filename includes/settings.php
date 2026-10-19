<?php
defined('ABSPATH') || exit;

add_filter('plugin_action_links_' . plugin_basename(SIMCLOUD_WC_FILE), function ($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=simcloud-wc')) . '">' . esc_html__('Set up SIMcloud', 'simcloud-woocommerce') . '</a>');
    return $links;
});

add_action('admin_menu', function () {
    add_submenu_page('woocommerce', 'SIMcloud', 'SIMcloud', 'manage_woocommerce', 'simcloud-wc', 'simcloud_wc_settings_page');
});

add_action('admin_notices', function () {
    if (current_user_can('manage_woocommerce') && get_option('simcloud_wc_enabled') === 'yes' && !simcloud_wc_live_ready()) {
        echo '<div class="notice notice-warning"><p>' . esc_html__('SIMcloud submissions are paused. Check the API token, authentication, environment and site binding in WooCommerce → SIMcloud.', 'simcloud-woocommerce') . '</p></div>';
    }
});

function simcloud_wc_admin_guard($nonce_action) {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You cannot manage SIMcloud.', 'simcloud-woocommerce'), '', ['response' => 403]);
    }
    check_admin_referer($nonce_action);
}

function simcloud_wc_settings_page() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    echo '<div class="wrap"><h1>SIMcloud for WooCommerce</h1><p>' . esc_html__('Airtime, data, VAS vouchers and prepaid electricity. Each merchant uses their own funded SIMcloud account.', 'simcloud-woocommerce') . '</p>';
    $notice = get_transient('simcloud_wc_notice_' . get_current_user_id());
    if (is_string($notice) && $notice !== '') {
        echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        delete_transient('simcloud_wc_notice_' . get_current_user_id());
    }
    echo '<p>' . esc_html__('Quick start: enter your API token, then click Connect & set up products. This checks your account and loads data bundles, VAS denominations and bundled logos. Configure payments and deliberately enable live submissions when ready to sell.', 'simcloud-woocommerce') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="simcloud_wc_settings">';
    wp_nonce_field('simcloud_wc_settings');
    echo '<table class="form-table"><tr><th><label for="simcloud-token">' . esc_html__('Replace API token', 'simcloud-woocommerce') . '</label></th><td>';
    if (defined('SIMCLOUD_WC_API_TOKEN')) {
        echo esc_html__('Token is managed by SIMCLOUD_WC_API_TOKEN in server configuration.', 'simcloud-woocommerce');
    } else {
        echo '<input id="simcloud-token" type="password" autocomplete="new-password" name="token" value="" class="regular-text"><p>' . esc_html(simcloud_wc_token() ? __('Token configured. Leave blank to keep it.', 'simcloud-woocommerce') : __('No token configured.', 'simcloud-woocommerce')) . '</p>';
        echo '<label><input type="checkbox" name="remove_token" value="1"> ' . esc_html__('Remove saved token', 'simcloud-woocommerce') . '</label>';
    }
    echo '</td></tr><tr><th><label for="simcloud-gateways">' . esc_html__('Automatic payment gateways', 'simcloud-woocommerce') . '</label></th><td><input id="simcloud-gateways" class="regular-text" name="gateways" value="' . esc_attr(get_option('simcloud_wc_gateways', '')) . '"><p>' . esc_html__('Comma-separated gateway IDs. Only payment_complete from these gateways can authorize automatic fulfilment. COD, BACS and cheque are excluded. Use the order action to confirm funds manually.', 'simcloud-woocommerce') . '</p>';
    $ids = array_keys(WC()->payment_gateways()->payment_gateways());
    echo '<p>' . esc_html__('Installed IDs: ', 'simcloud-woocommerce') . esc_html(implode(', ', $ids)) . '</p></td></tr><tr><th>' . esc_html__('Live submissions', 'simcloud-woocommerce') . '</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked(get_option('simcloud_wc_enabled'), 'yes', false) . '> ' . esc_html__('Enable this site to spend my SIMcloud wallet after confirmed payment.', 'simcloud-woocommerce') . '</label><p>' . esc_html__('Saving this choice binds fulfilment to the current site URL. Staging/development environments and SIMCLOUD_WC_DISABLE_SUBMISSIONS block submissions. Pause before backups, restores or cloning.', 'simcloud-woocommerce') . '</p></td></tr></table>';
    echo '<button class="button button-primary" name="operation" value="setup">' . esc_html__('Connect & set up products', 'simcloud-woocommerce') . '</button> ';
    echo '<button class="button" name="operation" value="save">' . esc_html__('Save settings', 'simcloud-woocommerce') . '</button>';
    echo ' <button class="button" name="operation" value="balance">' . esc_html__('Check connection / balance', 'simcloud-woocommerce') . '</button> <button class="button" name="operation" value="catalogue">' . esc_html__('Refresh catalogues / import products', 'simcloud-woocommerce') . '</button></form>';
    echo '<p>' . esc_html__('Setup imports missing data bundles and one VAS product per available denomination, published at the API amount. Bundled logos fill empty product images. Existing prices, custom images, drafts and trash are preserved.', 'simcloud-woocommerce') . '</p>';
    $balance = get_option('simcloud_wc_balance', []);
    if (is_array($balance) && isset($balance['amount'], $balance['checked'])) {
        /* translators: 1: ZAR amount, 2: UTC timestamp. */
        echo '<p>' . esc_html(sprintf(__('Last known wallet balance: R%1$s. Checked %2$s UTC. This is not a funds reservation.', 'simcloud-woocommerce'), $balance['amount'], gmdate('Y-m-d H:i:s', $balance['checked']))) . '</p>';
    }
    foreach (['data', 'vas'] as $service) {
        $catalogue = get_transient('simcloud_wc_catalogue_' . $service);
        echo '<h2>' . esc_html(strtoupper($service) . ' catalogue') . '</h2>';
        if (!is_array($catalogue)) {
            echo '<p>' . esc_html__('Refresh to load the current catalogue. Cache lifetime: 15 minutes.', 'simcloud-woocommerce') . '</p>';
            continue;
        }
        /* translators: %s: UTC timestamp. */
        echo '<p>' . esc_html(sprintf(__('Refreshed %s UTC', 'simcloud-woocommerce'), gmdate('Y-m-d H:i:s', $catalogue['updated_at']))) . '</p><table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__('Product', 'simcloud-woocommerce') . '</th><th>' . esc_html__('Provider amounts (ZAR)', 'simcloud-woocommerce') . '</th></tr></thead><tbody>';
        foreach ($catalogue['products'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $amounts = $service === 'vas' ? implode(', ', array_map('strval', (array) ($entry['available_amounts'] ?? []))) : (string) ($entry['amount'] ?? '');
            echo '<tr><td>' . esc_html((string) ($entry['product_id'] ?? '')) . '</td><td>' . esc_html($service === 'vas' ? (string) ($entry['voucher_type'] ?? '') : (string) ($entry['description'] ?? '') . ' / ' . (string) ($entry['network_name'] ?? '')) . '</td><td>' . esc_html($amounts) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<p><a href="' . esc_url('https://github.com/ErikThiart/simcloud-woocommerce/blob/main/docs/merchant-guide.md') . '">' . esc_html__('Merchant documentation', 'simcloud-woocommerce') . '</a></p></div>';
}

function simcloud_wc_save_settings($input) {
    if (!current_user_can('manage_woocommerce')) {
        return new WP_Error('simcloud_settings_permission', __('You cannot manage SIMcloud.', 'simcloud-woocommerce'));
    }
    if (!defined('SIMCLOUD_WC_API_TOKEN')) {
        $token = isset($input['token']) && is_scalar($input['token']) ? trim(wp_unslash($input['token'])) : '';
        if ($token !== '' && preg_match('/[\x00-\x20\x7f]/', $token)) {
            return new WP_Error('simcloud_token_format', __('The API token contains invalid whitespace or control characters. Settings were not saved.', 'simcloud-woocommerce'));
        }
        if (!empty($input['remove_token'])) {
            delete_option('simcloud_wc_token');
        } elseif ($token !== '') {
            update_option('simcloud_wc_token', $token, false);
            delete_option('simcloud_wc_auth_error');
        }
    }
    $raw = isset($input['gateways']) && is_scalar($input['gateways']) ? wp_unslash($input['gateways']) : '';
    $gateways = array_diff(array_filter(array_map('sanitize_key', explode(',', $raw))), ['cod', 'bacs', 'cheque']);
    update_option('simcloud_wc_gateways', implode(',', $gateways), false);
    update_option('simcloud_wc_enabled', !empty($input['enabled']) ? 'yes' : 'no', false);
    if (!empty($input['enabled'])) {
        update_option('simcloud_wc_bound_site', hash('sha256', home_url('/')), false);
    }
    return true;
}

function simcloud_wc_check_connection() {
    $response = simcloud_wc_api('balance');
    if (is_wp_error($response)) {
        return $response;
    }
    if (($response['body']['status'] ?? '') !== 'success' || simcloud_wc_cents($response['body']['balance'] ?? '') === null) {
        return new WP_Error('simcloud_balance', __('Balance response was invalid.', 'simcloud-woocommerce'));
    }
    update_option('simcloud_wc_balance', ['amount' => simcloud_wc_decimal(simcloud_wc_cents($response['body']['balance'])), 'checked' => time()], false);
    delete_option('simcloud_wc_auth_error');
    return true;
}

function simcloud_wc_refresh_products() {
    if (!current_user_can('manage_woocommerce') || !current_user_can('edit_products') || !current_user_can('upload_files')) {
        return new WP_Error('simcloud_setup_permission', __('Product setup requires WooCommerce management, product editing and media upload permissions.', 'simcloud-woocommerce'));
    }
    if (get_woocommerce_currency() !== 'ZAR') {
        return new WP_Error('simcloud_setup_currency', __('Set the WooCommerce store currency to ZAR before loading products.', 'simcloud-woocommerce'));
    }
    $messages = [];
    foreach (['data', 'vas'] as $service) {
        $catalogue = simcloud_wc_catalogue($service, true);
        $import = is_wp_error($catalogue) ? $catalogue : simcloud_wc_import_products($service, $catalogue);
        /* translators: 1: service, 2: published, 3: existing, 4: skipped, 5: failed, 6: logos added, 7: logo warnings. */
        $messages[] = is_wp_error($import) ? strtoupper($service) . ': ' . $import->get_error_message()
            : sprintf(__('%1$s — published: %2$d; existing: %3$d; skipped: %4$d; save failures: %5$d; logos added: %6$d; logo warnings: %7$d.', 'simcloud-woocommerce'),
                strtoupper($service), $import['created'], $import['existing'], $import['skipped'], $import['failed'], $import['images_added'], $import['image_warnings']);
    }
    return implode(' ', $messages);
}

function simcloud_wc_connect_and_setup() {
    if (!current_user_can('manage_woocommerce') || !current_user_can('edit_products') || !current_user_can('upload_files')) {
        return new WP_Error('simcloud_setup_permission', __('You cannot set up SIMcloud products.', 'simcloud-woocommerce'));
    }
    $connection = simcloud_wc_check_connection();
    return is_wp_error($connection) ? $connection : simcloud_wc_refresh_products();
}

add_action('admin_post_simcloud_wc_settings', function () {
    simcloud_wc_admin_guard('simcloud_wc_settings');
    $operation = isset($_POST['operation']) && is_scalar($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
    $result = __('Unknown operation.', 'simcloud-woocommerce');
    if (in_array($operation, ['save', 'setup'], true)) {
        $saved = simcloud_wc_save_settings($_POST);
        $result = is_wp_error($saved) ? $saved : ($operation === 'setup' ? simcloud_wc_connect_and_setup() : __('Settings saved. Run the connection check before taking orders.', 'simcloud-woocommerce'));
    } elseif ($operation === 'balance') {
        $result = simcloud_wc_check_connection();
        if ($result === true) {
            $result = __('Connection verified. No purchase was submitted.', 'simcloud-woocommerce');
        }
    } elseif ($operation === 'catalogue') {
        $result = simcloud_wc_refresh_products();
    }
    set_transient('simcloud_wc_notice_' . get_current_user_id(), is_wp_error($result) ? $result->get_error_message() : $result, 60);
    wp_safe_redirect(admin_url('admin.php?page=simcloud-wc'));
    exit;
});
