<?php
/** Real product/media storage with catalogue and balance GET fixtures. */
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Isolated test database required.');
}
require_once __DIR__ . '/mock-provider.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && str_starts_with($file, SIMCLOUD_WC_DIR)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
$GLOBALS['simcloud_wc_setup_assertions'] = 0;
function sc_setup_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $GLOBALS['simcloud_wc_setup_assertions']++;
    echo 'PASS: ' . $message . "\n";
}
function sc_setup_products() {
    return array_values(array_filter(wc_get_products(['limit' => -1, 'status' => array_keys(get_post_stati())]), function ($product) {
        $config = simcloud_wc_config($product);
        $id = (int) ($config['product_id'] ?? 0);
        return $id >= 995001 && $id <= 995101;
    }));
}
wp_set_current_user(1);
update_option('woocommerce_currency', 'ZAR');
update_option('simcloud_wc_token', 'synthetic-test-token-not-a-credential', false);
foreach (sc_setup_products() as $product) {
    $product->delete(true);
}
$vas_rows = [
    ['product_id' => 995001, 'voucher_type' => 'Pick n Pay', 'slug' => 'pick-n-pay', 'logo_url' => 'https://simcloud.co.za/img/vas_logos/picknpay.jpg', 'available_amounts' => [50, 100, 100, '25.005']],
    ['product_id' => 995002, 'voucher_type' => 'Spur', 'slug' => 'spur', 'available_amounts' => [50, 100]],
];
$GLOBALS['simcloud_wc_test_vas_products'] = $vas_rows;
$manual = new WC_Product_Simple();
$manual->set_name('Merchant Spur product');
$manual->set_regular_price('65.00');
$manual->set_status('draft');
$manual->update_meta_data('_simcloud_wc_config', ['service' => 'vas', 'product_id' => 995002, 'amount' => '50.00']);
$manual->save();
try {
    $result = simcloud_wc_import_vas_products(simcloud_wc_catalogue('vas', true));
    sc_setup_assert($result['created'] === 3 && $result['existing'] === 1 && $result['skipped'] === 1 && $result['images_added'] === 4 && $result['image_warnings'] === 0, 'VAS import publishes each unique available denomination and fills default logos');
    $products = sc_setup_products();
    sc_setup_assert(count($products) === 4, 'Duplicate denominations do not create duplicate VAS products');
    $pnp = [];
    foreach ($products as $product) {
        $config = simcloud_wc_config($product);
        sc_setup_assert(wp_attachment_is_image($product->get_image_id()) && is_file(get_attached_file($product->get_image_id())), 'Imported logo is a real local WordPress image attachment');
        if ($config['product_id'] === 995001) {
            $pnp[] = $product;
            sc_setup_assert($product->get_status() === 'publish' && $product->is_virtual() && !$product->is_downloadable()
                && simcloud_wc_cents($product->get_regular_price()) === simcloud_wc_cents($config['amount'])
                && get_post_meta($product->get_image_id(), '_simcloud_wc_logo', true) === 'vas/picknpay.jpg', 'Published VAS product has the API denomination as price and the correct brand logo');
        }
    }
    sc_setup_assert(count($pnp) === 2 && $pnp[0]->get_image_id() === $pnp[1]->get_image_id(), 'VAS denominations reuse one shared brand attachment');
    $custom = new WC_Product_Simple();
    $custom_image = simcloud_wc_set_default_image($custom, ['service' => 'data', 'network' => 'mtn']);
    $pnp[0]->set_image_id($custom_image);
    $pnp[0]->set_name('Merchant custom voucher title');
    $pnp[0]->set_regular_price('140.00');
    $pnp[0]->set_status('draft');
    $pnp[0]->save();
    $result = simcloud_wc_import_vas_products(simcloud_wc_catalogue('vas'));
    $saved = wc_get_product($pnp[0]->get_id());
    sc_setup_assert($result['created'] === 0 && $result['existing'] === 4 && $result['images_added'] === 0
        && (int) $saved->get_image_id() === (int) $custom_image && $saved->get_regular_price() === '140.00'
        && $saved->get_name() === 'Merchant custom voucher title' && $saved->get_status() === 'draft', 'Repeat import preserves custom images, prices, copy and draft state');
    sc_setup_assert(wc_get_product($manual->get_id())->get_regular_price() === '65.00' && wc_get_product($manual->get_id())->get_status() === 'draft', 'A manually configured VAS denomination is preserved while receiving its missing default logo');
    $aliases = ['Hussar Grill' => 'hussarsgrill', 'John Dorys' => 'johndory', 'Pick n Pay' => 'picknpay', 'Takealot.com' => 'takealot-com', 'PEP Home' => 'pephome', 'Outdoor Warehouse' => 'outdoor-warehouse'];
    foreach ($aliases as $name => $filename) {
        sc_setup_assert(simcloud_wc_logo_key(['service' => 'vas'], ['voucher_type' => $name]) === 'vas/' . $filename . '.jpg', 'Brand alias maps exactly to bundled logo: ' . $name);
    }
    sc_setup_assert(simcloud_wc_logo_key(['service' => 'data', 'network' => 'heita']) === 'networks/telkom.jpg', 'Telkom data code maps to the Telkom network logo');
    $unknown = simcloud_wc_set_default_image(new WC_Product_Simple(), ['service' => 'vas'], ['voucher_type' => 'Unknown future brand', 'logo_url' => 'https://example.invalid/img/vas_logos/pep.jpg']);
    sc_setup_assert(is_wp_error($unknown) && $unknown->get_error_code() === 'simcloud_logo_unknown', 'Unknown brands and foreign image URLs cannot select or download an unrelated logo');
    $upload_failure = function () { return 'Synthetic upload failure'; };
    add_filter('wp_upload_bits', $upload_failure);
    $bad_upload = simcloud_wc_set_default_image(new WC_Product_Simple(), ['service' => 'vas'], ['slug' => 'netflorist']);
    remove_filter('wp_upload_bits', $upload_failure);
    sc_setup_assert(is_wp_error($bad_upload) && $bad_upload->get_error_code() === 'simcloud_logo_upload', 'An unwritable Media Library is reported without a broken product image');
    $metadata_failure = function () { return []; };
    add_filter('wp_generate_attachment_metadata', $metadata_failure);
    $bad_metadata = simcloud_wc_set_default_image(new WC_Product_Simple(), ['service' => 'vas'], ['slug' => 'medirite']);
    remove_filter('wp_generate_attachment_metadata', $metadata_failure);
    sc_setup_assert(is_wp_error($bad_metadata) && $bad_metadata->get_error_code() === 'simcloud_logo_metadata', 'Failed image processing cleans up its failed attachment and returns an explicit error');
    $no_upload = function ($caps) { $caps['upload_files'] = false; return $caps; };
    add_filter('user_has_cap', $no_upload);
    $forbidden = simcloud_wc_connect_and_setup();
    remove_filter('user_has_cap', $no_upload);
    sc_setup_assert(is_wp_error($forbidden), 'Quick setup requires media-upload permission as well as WooCommerce/product management');
    $GLOBALS['simcloud_wc_test_calls'] = [];
    $GLOBALS['simcloud_wc_test_mode'] = 'unauthorized';
    $denied = simcloud_wc_connect_and_setup();
    sc_setup_assert(is_wp_error($denied) && count($GLOBALS['simcloud_wc_test_calls']) === 1
        && $GLOBALS['simcloud_wc_test_calls'][0]['service'] === 'balance', 'Failed connection stops quick setup before catalogue reads or product changes');
    $GLOBALS['simcloud_wc_test_mode'] = 'success';
    $saved = simcloud_wc_save_settings(['token' => 'synthetic-test-token-not-a-credential', 'gateways' => 'simcloud_test,bacs,cheque,cod']);
    sc_setup_assert($saved === true && get_option('simcloud_wc_enabled') === 'no' && get_option('simcloud_wc_gateways') === 'simcloud_test', 'Saving setup keeps live spend disabled and excludes offline gateways from automatic authorization');
    $invalid_token = simcloud_wc_save_settings(['token' => "bad token", 'enabled' => 1]);
    sc_setup_assert(is_wp_error($invalid_token) && simcloud_wc_token() === 'synthetic-test-token-not-a-credential' && get_option('simcloud_wc_enabled') === 'no', 'Invalid pasted token cannot silently reuse old credentials or enable wallet spending');
    $GLOBALS['simcloud_wc_test_data_products'] = [['product_id' => 995101, 'network' => 'pd-vodacom', 'description' => 'Synthetic quick-setup data bundle', 'amount' => 25]];
    $GLOBALS['simcloud_wc_test_vas_products'][] = ['product_id' => 995003, 'voucher_type' => 'Woolworths', 'slug' => 'woolworths', 'available_amounts' => [25]];
    $GLOBALS['simcloud_wc_test_calls'] = [];
    $message = simcloud_wc_connect_and_setup();
    sc_setup_assert(is_string($message) && str_contains($message, 'DATA') && str_contains($message, 'VAS')
        && get_option('simcloud_wc_balance')['amount'] === '2000.00' && !get_option('simcloud_wc_auth_error'), 'One setup operation verifies balance and imports both catalogues with service-specific results');
    sc_setup_assert(count(sc_setup_products()) === 6, 'One-click setup creates the missing data and VAS products together');
    $message = simcloud_wc_connect_and_setup();
    sc_setup_assert(is_string($message) && count(sc_setup_products()) === 6 && get_option('simcloud_wc_enabled') === 'no', 'Repeated one-click setup remains idempotent and leaves wallet spend opt-in');
    sc_setup_assert(count(array_filter($GLOBALS['simcloud_wc_test_calls'], function ($call) { return $call['method'] === 'POST'; })) === 0, 'Quick setup uses only balance/catalogue GET requests and never purchases prepaid value');
} finally {
    foreach (sc_setup_products() as $product) {
        $product->delete(true);
    }
    unset($GLOBALS['simcloud_wc_test_data_products'], $GLOBALS['simcloud_wc_test_vas_products']);
    delete_transient('simcloud_wc_catalogue_data');
    delete_transient('simcloud_wc_catalogue_vas');
}
echo 'Quick setup assertions: ' . $GLOBALS['simcloud_wc_setup_assertions'] . "\n";
