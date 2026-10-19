<?php
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Isolated test installation required.');
}
WC_Install::create_pages();
update_option('woocommerce_currency', 'ZAR');
update_option('woocommerce_default_country', 'ZA');
update_option('woocommerce_store_address', '1 Test Road');
update_option('woocommerce_store_city', 'Cape Town');
update_option('woocommerce_store_postcode', '8000');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_bacs_settings', ['enabled' => 'yes', 'title' => 'Test bank transfer', 'description' => 'Synthetic testing only. No bank payment is made.']);
update_option('simcloud_wc_token', 'synthetic-test-token-not-a-credential', false);
update_option('simcloud_wc_enabled', 'yes', false);
update_option('simcloud_wc_bound_site', hash('sha256', home_url('/')), false);
update_option('simcloud_wc_gateways', 'simcloud_test', false);
delete_option('simcloud_wc_auth_error');
$classic = get_page_by_path('classic-checkout');
if (!$classic) {
    wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Classic checkout', 'post_name' => 'classic-checkout', 'post_content' => '[woocommerce_checkout]']);
}
echo wp_json_encode(get_option('simcloud_wc_test_product_ids')) . "\n";
echo 'Block checkout page: ' . get_permalink(wc_get_page_id('checkout')) . "\n";
