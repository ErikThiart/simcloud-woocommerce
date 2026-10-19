<?php
defined('ABSPATH') || exit;

function simcloud_wc_table() {
    global $wpdb;
    return $wpdb->prefix . 'simcloud_wc_fulfilments';
}

function simcloud_wc_install_schema() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = simcloud_wc_table();
    $collate = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint unsigned NOT NULL,
        item_id bigint unsigned NOT NULL,
        state varchar(24) NOT NULL DEFAULT 'awaiting_payment',
        reference varchar(100) NOT NULL,
        provider_id bigint unsigned NOT NULL DEFAULT 0,
        provider_reference varchar(200) NOT NULL DEFAULT '',
        polls int unsigned NOT NULL DEFAULT 0,
        message varchar(255) NOT NULL DEFAULT '',
        result longtext NULL,
        created_at datetime NOT NULL,
        submitted_at datetime NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY item_id (item_id),
        KEY order_id (order_id),
        KEY state_updated (state,updated_at)
    ) $collate;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
        update_option('simcloud_wc_schema', '1', false);
    }
    if (!get_option('simcloud_wc_site_id')) {
        add_option('simcloud_wc_site_id', substr(str_replace('-', '', wp_generate_uuid4()), 0, 12), '', false);
    }
}

function simcloud_wc_activate($network_wide = false) {
    if ($network_wide) {
        wp_die(esc_html__('Activate SIMcloud separately on each site. Network activation is not supported.', 'simcloud-woocommerce'));
    }
    simcloud_wc_install_schema();
    update_option('simcloud_wc_enabled', 'no', false);
}

function simcloud_wc_deactivate() {
    update_option('simcloud_wc_enabled', 'no', false);
    if (function_exists('as_unschedule_all_actions')) {
        foreach (['simcloud_wc_submit', 'simcloud_wc_poll', 'simcloud_wc_watchdog'] as $hook) {
            as_unschedule_all_actions($hook, null, 'simcloud-wc');
        }
    }
}

function simcloud_wc_row($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', simcloud_wc_table(), $id), ARRAY_A);
}

function simcloud_wc_rows($order_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE order_id = %d ORDER BY id', simcloud_wc_table(), $order_id), ARRAY_A);
}

function simcloud_wc_record($order_id, $item_id) {
    global $wpdb;
    $table = simcloud_wc_table();
    $reference = 'wc-' . get_option('simcloud_wc_site_id') . '-' . $order_id . '-' . $item_id;
    $now = gmdate('Y-m-d H:i:s');
    // INSERT IGNORE and the unique item key make repeated checkout/payment hooks safe.
    $wpdb->query($wpdb->prepare('INSERT IGNORE INTO %i (order_id,item_id,reference,created_at,updated_at) VALUES (%d,%d,%s,%s,%s)', $table, $order_id, $item_id, $reference, $now, $now));
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE item_id = %d', $table, $item_id), ARRAY_A);
}

function simcloud_wc_transition($id, $from, $to, $values = []) {
    global $wpdb;
    $allowed = ['provider_id', 'provider_reference', 'polls', 'message', 'result', 'submitted_at'];
    $data = array_intersect_key($values, array_flip($allowed));
    $data['state'] = $to;
    $data['updated_at'] = gmdate('Y-m-d H:i:s');
    return $wpdb->update(simcloud_wc_table(), $data, ['id' => (int) $id, 'state' => $from]) === 1;
}

function simcloud_wc_schedule($hook, $id, $delay = 0) {
    if (function_exists('as_schedule_single_action')) {
        as_schedule_single_action(time() + $delay, $hook, [(int) $id], 'simcloud-wc', $hook === 'simcloud_wc_submit');
    }
}
