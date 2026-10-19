<?php
/** Real WooCommerce CRUD with synthetic catalogue responses. */
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Isolated test database required.');
}
require_once __DIR__ . '/mock-provider.php';
global $wpdb;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && str_starts_with($file, SIMCLOUD_WC_DIR)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
$GLOBALS['simcloud_wc_import_assertions'] = 0;
function sc_import_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $GLOBALS['simcloud_wc_import_assertions']++;
    echo 'PASS: ' . $message . "\n";
}
function sc_import_catalogue($products) {
    $GLOBALS['simcloud_wc_test_data_products'] = $products;
    return simcloud_wc_catalogue('data', true);
}
function sc_import_products() {
    $products = wc_get_products(['limit' => -1, 'status' => array_keys(get_post_stati())]);
    return array_values(array_filter($products, function ($product) {
        $id = (int) $product->get_meta('_simcloud_wc_data_product_id');
        return $id >= 990001 && $id <= 990020;
    }));
}
wp_set_current_user(1);
update_option('woocommerce_currency', 'ZAR');
update_option('simcloud_wc_token', 'synthetic-test-token-not-a-credential', false);
foreach (sc_import_products() as $product) {
    $product->delete(true);
}
$rows = [];
foreach (['pd-mtn', 'pd-vodacom', 'pd-cellc', 'pd-heita'] as $index => $network) {
    $rows[] = ['product_id' => 990001 + $index, 'network' => $network, 'description' => 'Synthetic import bundle ' . $index,
        'groupname' => 'Synthetic monthly bundles', 'amount' => 25 + $index, 'sellvalue' => 25 + $index];
}
$manual = new WC_Product_Simple();
$manual->set_name('Merchant manual data product');
$manual->set_status('draft');
$manual->set_regular_price('60.00');
$manual->update_meta_data('_simcloud_wc_config', ['service' => 'data', 'network' => 'vodacom', 'amount' => '26.00', 'product_id' => 990002]);
$manual->save();
try {
    $catalogue = sc_import_catalogue($rows);
    $result = simcloud_wc_import_data_products($catalogue);
    sc_import_assert($result === ['created' => 3, 'existing' => 1, 'skipped' => 0, 'failed' => 0, 'images_added' => 4, 'image_warnings' => 0], 'Data API catalogue creates missing products and recognizes a manually configured product');
    $imported = sc_import_products();
    sc_import_assert(count($imported) === 3, 'Exactly one WooCommerce product is created per missing provider product ID');
    foreach ($imported as $product) {
        $config = simcloud_wc_config($product);
        sc_import_assert($product->get_status() === 'publish' && simcloud_wc_cents($product->get_price()) === simcloud_wc_cents($config['amount']) && simcloud_wc_cents($product->get_regular_price()) === simcloud_wc_cents($config['amount']), 'Imported bundle is published with the API amount as its regular retail price');
        sc_import_assert($product->is_virtual() && !$product->is_downloadable() && simcloud_wc_validate_config($config) === true
            && $product->get_short_description() === 'Synthetic monthly bundles', 'Imported product retains valid service/network/amount/catalogue mapping and provider group');
    }
    $custom = $imported[0];
    $custom->set_name('Merchant custom title');
    $custom->set_description('Merchant custom description');
    $custom->set_sku('merchant-custom-import-sku');
    $custom->set_regular_price('70.00');
    $custom->set_sale_price('65.00');
    $custom->set_status('publish');
    $custom->save();
    $trash = $imported[1];
    $trash->set_status('trash');
    $trash->save();
    $changed = $imported[2];
    $changed->delete_meta_data('_simcloud_wc_config');
    $changed->save();
    $result = simcloud_wc_import_data_products($catalogue);
    sc_import_assert($result['created'] === 0 && $result['existing'] === 4, 'Repeated imports preserve published, manual, trashed and merchant-unmapped products without duplicates');
    $saved = wc_get_product($custom->get_id());
    sc_import_assert($saved->get_name() === 'Merchant custom title' && $saved->get_description() === 'Merchant custom description'
        && $saved->get_sku() === 'merchant-custom-import-sku' && $saved->get_regular_price() === '70.00'
        && $saved->get_sale_price() === '65.00' && $saved->get_status() === 'publish', 'Repeat import preserves merchant copy, SKU, regular/sale prices and publication state');
    sc_import_assert(wc_get_product($trash->get_id())->get_status() === 'trash' && !simcloud_wc_config(wc_get_product($changed->get_id())), 'Import does not restore trash or undo a merchant removing prepaid configuration');
    sc_import_assert(wc_get_product($manual->get_id())->get_status() === 'draft', 'A manually configured draft is not automatically published');
    $invalid = [null, ['product_id' => 0], ['product_id' => 990005, 'network' => 'unknown', 'amount' => 20, 'description' => 'Invalid'],
        ['product_id' => 990006, 'network' => 'pd-mtn', 'amount' => 30, 'description' => ['bad']],
        ['product_id' => 990007, 'network' => 'pd-mtn', 'amount' => 31, 'description' => 'Ambiguous A'],
        ['product_id' => 990008, 'network' => 'pd-mtn', 'amount' => 31, 'description' => 'Ambiguous B'],
        ['product_id' => 990009, 'network' => 'pd-mtn', 'amount' => 32, 'description' => 'Duplicate ID A'],
        ['product_id' => 990009, 'network' => 'pd-vodacom', 'amount' => 33, 'description' => 'Duplicate ID B']];
    $result = simcloud_wc_import_data_products(sc_import_catalogue($invalid));
    sc_import_assert($result['skipped'] === 8 && $result['created'] === 0, 'Malformed, unsupported and ambiguous data products are skipped without creating misleading listings');
    sc_import_assert(is_wp_error(simcloud_wc_import_data_products(['bad' => true])), 'Malformed catalogue cannot start an import');
    wp_set_current_user(0);
    sc_import_assert(is_wp_error(simcloud_wc_import_data_products($catalogue)), 'Unauthorized users cannot import products');
    wp_set_current_user(1);
    update_option('woocommerce_currency', 'USD');
    sc_import_assert(is_wp_error(simcloud_wc_import_data_products($catalogue)), 'A non-ZAR store cannot import rand-priced provider mappings');
    update_option('woocommerce_currency', 'ZAR');
    $other = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $lock = 'simcloud_products_' . md5(DB_NAME . $wpdb->prefix);
    $other->get_var($other->prepare('SELECT GET_LOCK(%s, 0)', $lock));
    $blocked = simcloud_wc_import_data_products($catalogue);
    $other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    $other->close();
    sc_import_assert(is_wp_error($blocked) && $blocked->get_error_code() === 'simcloud_import_busy', 'A second database connection prevents concurrent imports from creating duplicates');
    $failure_rows = [
        ['product_id' => 990010, 'network' => 'pd-mtn', 'amount' => 34, 'description' => 'Synthetic failed save'],
        ['product_id' => 990011, 'network' => 'pd-mtn', 'amount' => 35, 'description' => 'Synthetic successful save'],
    ];
    $fail_save = function ($product) {
        if ((int) $product->get_meta('_simcloud_wc_data_product_id') === 990010) {
            throw new RuntimeException('Synthetic save failure');
        }
    };
    add_action('woocommerce_before_product_object_save', $fail_save);
    $failure_catalogue = sc_import_catalogue($failure_rows);
    $result = simcloud_wc_import_data_products($failure_catalogue);
    remove_action('woocommerce_before_product_object_save', $fail_save);
    sc_import_assert($result['failed'] === 1 && $result['created'] === 1, 'A failed WooCommerce save is reported while remaining products can import');
    $result = simcloud_wc_import_data_products($failure_catalogue);
    sc_import_assert($result['created'] === 1 && $result['existing'] === 1, 'Import releases its lock after a save error and can safely fill the missing product');
    $manual->set_sku('simcloud-data-990012');
    $manual->save();
    $result = simcloud_wc_import_data_products(sc_import_catalogue([
        ['product_id' => 990012, 'network' => 'pd-mtn', 'amount' => 36, 'description' => 'Synthetic SKU conflict'],
    ]));
    sc_import_assert($result['failed'] === 1 && $result['created'] === 0 && wc_get_product($manual->get_id())->get_regular_price() === '60.00', 'SKU collision reports failure without overwriting an unrelated merchant product');
    $result = simcloud_wc_import_data_products(sc_import_catalogue([]));
    sc_import_assert($result['created'] === 0 && count(sc_import_products()) === 5, 'An empty catalogue never deletes existing merchant products');
    sc_import_assert(count(array_filter($GLOBALS['simcloud_wc_test_calls'], function ($call) { return $call['method'] === 'POST'; })) === 0, 'Data product import uses catalogue GET requests and never purchases prepaid value');
} finally {
    foreach (sc_import_products() as $product) {
        $product->delete(true);
    }
    $manual->delete(true);
    unset($GLOBALS['simcloud_wc_test_data_products']);
    delete_transient('simcloud_wc_catalogue_data');
}
echo 'Data import assertions: ' . $GLOBALS['simcloud_wc_import_assertions'] . "\n";
