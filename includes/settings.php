<?php
defined('ABSPATH') || exit;

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
    echo '<button class="button button-primary" name="operation" value="save">' . esc_html__('Save settings', 'simcloud-woocommerce') . '</button>';
    echo ' <button class="button" name="operation" value="balance">' . esc_html__('Check connection / balance', 'simcloud-woocommerce') . '</button> <button class="button" name="operation" value="catalogue">' . esc_html__('Refresh catalogues', 'simcloud-woocommerce') . '</button></form>';
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

add_action('admin_post_simcloud_wc_settings', function () {
    simcloud_wc_admin_guard('simcloud_wc_settings');
    $operation = isset($_POST['operation']) && is_scalar($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
    $message = __('Unknown operation.', 'simcloud-woocommerce');
    if ($operation === 'save') {
        if (!defined('SIMCLOUD_WC_API_TOKEN')) {
            $token = isset($_POST['token']) && is_scalar($_POST['token']) ? trim(wp_unslash($_POST['token'])) : '';
            if (!empty($_POST['remove_token'])) {
                delete_option('simcloud_wc_token');
            } elseif ($token !== '' && !preg_match('/[\x00-\x20\x7f]/', $token)) {
                update_option('simcloud_wc_token', $token, false);
                delete_option('simcloud_wc_auth_error');
            }
        }
        $raw = isset($_POST['gateways']) && is_scalar($_POST['gateways']) ? wp_unslash($_POST['gateways']) : '';
        $gateways = array_filter(array_map('sanitize_key', explode(',', $raw)));
        $gateways = array_diff($gateways, ['cod', 'bacs', 'cheque']);
        update_option('simcloud_wc_gateways', implode(',', $gateways), false);
        update_option('simcloud_wc_enabled', !empty($_POST['enabled']) ? 'yes' : 'no', false);
        if (!empty($_POST['enabled'])) {
            update_option('simcloud_wc_bound_site', hash('sha256', home_url('/')), false);
        }
        $message = __('Settings saved. Run the connection check before taking orders.', 'simcloud-woocommerce');
    } elseif ($operation === 'balance') {
        $response = simcloud_wc_api('balance');
        if (!is_wp_error($response) && ($response['body']['status'] ?? '') === 'success' && simcloud_wc_cents($response['body']['balance'] ?? '') !== null) {
            update_option('simcloud_wc_balance', ['amount' => simcloud_wc_decimal(simcloud_wc_cents($response['body']['balance'])), 'checked' => time()], false);
            delete_option('simcloud_wc_auth_error');
            $message = __('Connection verified. No purchase was submitted.', 'simcloud-woocommerce');
        } else {
            $message = is_wp_error($response) ? $response->get_error_message() : __('Balance response was invalid.', 'simcloud-woocommerce');
        }
    } elseif ($operation === 'catalogue') {
        $data = simcloud_wc_catalogue('data', true);
        $vas = simcloud_wc_catalogue('vas', true);
        $message = is_wp_error($data) || is_wp_error($vas) ? __('A catalogue could not be refreshed. Check authentication and try again.', 'simcloud-woocommerce') : __('Data and VAS catalogues refreshed. Retail prices and historical purchases were preserved.', 'simcloud-woocommerce');
    }
    set_transient('simcloud_wc_notice_' . get_current_user_id(), $message, 60);
    wp_safe_redirect(admin_url('admin.php?page=simcloud-wc'));
    exit;
});
