<?php
/**
 * Plugin Name: SIMcloud for WooCommerce
 * Plugin URI: https://github.com/ErikThiart/simcloud-woocommerce
 * Description: Sell South African airtime, data, VAS vouchers and prepaid electricity through SIMcloud.
 * Version: 0.3.0
 * Requires at least: 6.8.3
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 10.0.4
 * Author: Erik Thiart
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simcloud-woocommerce
 */

defined('ABSPATH') || exit;
define('SIMCLOUD_WC_VERSION', '0.3.0');
define('SIMCLOUD_WC_FILE', __FILE__);
define('SIMCLOUD_WC_DIR', __DIR__ . '/');

require_once SIMCLOUD_WC_DIR . 'includes/storage.php';
register_activation_hook(__FILE__, 'simcloud_wc_activate');
register_deactivation_hook(__FILE__, 'simcloud_wc_deactivate');

add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce') || version_compare(WC_VERSION, '10.0.4', '<')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('SIMcloud requires WooCommerce 10.0.4 or newer.', 'simcloud-woocommerce') . '</p></div>';
        });
        return;
    }
    foreach (['api', 'images', 'products', 'cart', 'fulfilment', 'orders', 'settings', 'privacy'] as $module) {
        require_once SIMCLOUD_WC_DIR . 'includes/' . $module . '.php';
    }
    if (get_option('simcloud_wc_schema') !== '1') {
        simcloud_wc_install_schema();
    }
    add_action('simcloud_wc_submit', 'simcloud_wc_submit', 10, 1);
    add_action('simcloud_wc_poll', 'simcloud_wc_poll', 10, 1);
    add_action('simcloud_wc_watchdog', 'simcloud_wc_watchdog');
    add_action('action_scheduler_init', function () {
        if (!as_has_scheduled_action('simcloud_wc_watchdog', [], 'simcloud-wc')) {
            as_schedule_recurring_action(time() + 300, 300, 'simcloud_wc_watchdog', [], 'simcloud-wc', true);
        }
    });
});
