<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Orders, purchase snapshots and fulfilment evidence survive uninstall intentionally.
foreach (['simcloud_wc_token', 'simcloud_wc_enabled', 'simcloud_wc_bound_site', 'simcloud_wc_gateways', 'simcloud_wc_balance', 'simcloud_wc_auth_error'] as $name) {
    delete_option($name);
}
foreach (['data', 'vas'] as $service) {
    delete_transient('simcloud_wc_catalogue_' . $service);
}
if (function_exists('as_unschedule_all_actions')) {
    foreach (['simcloud_wc_submit', 'simcloud_wc_poll', 'simcloud_wc_watchdog'] as $hook) {
        as_unschedule_all_actions($hook, null, 'simcloud-wc');
    }
}
