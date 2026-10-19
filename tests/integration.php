<?php
/** Run with wp eval-file against the isolated test database. */
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Refusing to run destructive tests outside the isolated test installation.');
}
require_once __DIR__ . '/mock-provider.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if ((error_reporting() & $severity) && str_starts_with($file, SIMCLOUD_WC_DIR)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});
WC()->payment_gateways()->payment_gateways = [];
WC()->payment_gateways()->init();
$GLOBALS['simcloud_wc_assertions'] = 0;
function sc_assert($condition, $message) {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    $GLOBALS['simcloud_wc_assertions']++;
    echo 'PASS: ' . $message . "\n";
}
function sc_calls($method = 'POST') {
    return array_values(array_filter($GLOBALS['simcloud_wc_test_calls'], function ($call) use ($method) { return $call['method'] === $method; }));
}
function sc_product($service) {
    $configs = [
        'airtime' => ['service' => 'airtime', 'network' => 'mtn', 'amount' => '10.00'],
        'data' => ['service' => 'data', 'network' => 'mtn', 'amount' => '25.00', 'product_id' => 7],
        'vas' => ['service' => 'vas', 'amount' => '100.00', 'product_id' => 12],
        'electricity' => ['service' => 'electricity', 'amount' => '150.00'],
    ];
    $p = new WC_Product_Simple();
    $p->set_name('Synthetic ' . $service);
    $p->set_status('publish');
    $p->set_regular_price('200.00');
    $p->set_virtual(true);
    $p->update_meta_data('_simcloud_wc_config', $configs[$service]);
    $p->save();
    return $p;
}
function sc_order($product, $paid = true) {
    $order = wc_create_order();
    $order->set_currency('ZAR');
    $order->set_payment_method('simcloud_test');
    $order->set_billing_email('synthetic@example.invalid');
    $item_id = $order->add_product($product, 1);
    $item = $order->get_item($item_id);
    $s = simcloud_wc_purchase_input(simcloud_wc_config($product), ['recipient' => '0820000000', 'meter' => '00000000000']);
    if (is_wp_error($s)) {
        throw new RuntimeException($s->get_error_message());
    }
    $item->add_meta_data('_simcloud_wc_snapshot', $s, true);
    $item->save();
    $order->calculate_totals();
    $order->save();
    simcloud_wc_prepare_order($order->get_id());
    if ($paid) {
        $order->payment_complete('synthetic-payment');
    }
    return [wc_get_order($order->get_id()), simcloud_wc_rows($order->get_id())[0]];
}

update_option('woocommerce_currency', 'ZAR');
update_option('woocommerce_default_country', 'ZA');
update_option('woocommerce_calc_taxes', 'no');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_terms_page_id', 0);
update_option('simcloud_wc_token', 'synthetic-test-token-not-a-credential', false);
update_option('simcloud_wc_enabled', 'yes', false);
update_option('simcloud_wc_bound_site', hash('sha256', home_url('/')), false);
update_option('simcloud_wc_gateways', 'simcloud_test', false);
delete_option('simcloud_wc_auth_error');
simcloud_wc_install_schema();
wp_set_current_user(0);
wc_load_cart();
WC()->cart->empty_cart();
sc_assert(simcloud_wc_live_ready(), 'Isolated test configuration enables the mocked transport');
sc_assert(simcloud_wc_cents('10.01') === 1001 && simcloud_wc_cents('1e2') === null && simcloud_wc_cents('1.001') === null, 'Money uses exact cents and rejects scientific notation / excess precision');
sc_assert(simcloud_wc_phone('082 000 0000') === '+27820000000' && simcloud_wc_phone(['bad']) === '' && simcloud_wc_phone('082000000012') === '', 'Recipient normalization validates length and input type');
sc_assert(simcloud_wc_network('pd-mtn') === 'mtn' && simcloud_wc_network('Telkom') === 'heita', 'Service network mapping is explicit');
sc_assert(is_wp_error(simcloud_wc_validate_config(['service' => 'electricity', 'amount' => '55.50'])), 'Electricity rejects cents');
sc_assert(is_wp_error(simcloud_wc_validate_config(['service' => 'electricity', 'amount' => '54.00'])), 'Electricity enforces minimum R55');
sc_assert(is_wp_error(simcloud_wc_validate_config(['service' => 'airtime', 'network' => 'mtn', 'amount' => '1000.00'])), 'Airtime enforces maximum');

$products = [];
foreach (['airtime', 'data', 'vas', 'electricity'] as $service) {
    $products[$service] = sc_product($service);
    sc_assert(simcloud_wc_validate_config(simcloud_wc_config($products[$service])) === true, $service . ' product validates against its contract');
    [$order, $row] = sc_order($products[$service]);
    sc_assert($row['state'] === 'ready' && $order->get_status() === 'processing', $service . ' payment queues fulfilment without premature completion');
    $before = count(sc_calls());
    simcloud_wc_submit($row['id']);
    simcloud_wc_submit($row['id']);
    do_action('woocommerce_payment_complete', $order->get_id());
    simcloud_wc_submit($row['id']);
    $queued = simcloud_wc_row($row['id']);
    sc_assert(count(sc_calls()) === $before + 1 && $queued['state'] === 'queued' && (int) $queued['provider_id'] > 0, $service . ' repeated hooks/jobs produce one POST and persist provider ID');
    $call = end($GLOBALS['simcloud_wc_test_calls']);
    sc_assert(simcloud_wc_cents($call['payload']['amount']) === simcloud_wc_cents(simcloud_wc_config($products[$service])['amount']), $service . ' provider amount is independent of retail total');
    simcloud_wc_poll($row['id']);
    $delivered = simcloud_wc_row($row['id']);
    sc_assert($delivered['state'] === 'delivered' && wc_get_order($order->get_id())->get_status() === 'completed', $service . ' matching final delivery completes the order');
    if (in_array($service, ['vas', 'electricity'], true)) {
        sc_assert(!empty(json_decode($delivered['result'], true)[0]['code']), $service . ' redeemable code retained only in restricted fulfilment record');
        ob_start(); simcloud_wc_render_order($order); $hidden = ob_get_clean();
        sc_assert($hidden === '', $service . ' codes are hidden from an unrelated guest');
        $_GET['key'] = $order->get_order_key();
        ob_start(); simcloud_wc_render_order($order); $visible = ob_get_clean();
        unset($_GET['key']);
        sc_assert(str_contains($visible, 'TEST-NOT-REDEEMABLE') || str_contains($visible, '0000-0000-0000'), $service . ' guest order key authorizes code display');
    }
}

wp_set_current_user(1);
do_action('woocommerce_order_action_simcloud_wc_confirm_funds', wc_get_order($order->get_id()));
sc_assert(wc_get_order($order->get_id())->get_status() === 'completed', 'Repeating staff funds confirmation cannot reopen a completed purchase');
wp_set_current_user(0);

foreach (['timeout', 'bad_ack', 'conflict'] as $mode) {
    [$order, $row] = sc_order($products['airtime']);
    $GLOBALS['simcloud_wc_test_mode'] = $mode;
    $before = count(sc_calls());
    simcloud_wc_submit($row['id']);
    $GLOBALS['simcloud_wc_test_mode'] = 'success';
    simcloud_wc_submit($row['id']);
    sc_assert(simcloud_wc_row($row['id'])['state'] === 'needs_review' && count(sc_calls()) === $before + 1, $mode . ' creates a durable review state without automatic resubmission');
}
foreach (['missing_codes', 'mismatch', 'not_found', 'failed', 'server_error', 'pending'] as $mode) {
    [$order, $row] = sc_order($products[$mode === 'missing_codes' ? 'vas' : 'airtime']);
    simcloud_wc_submit($row['id']);
    $GLOBALS['simcloud_wc_test_mode'] = $mode;
    simcloud_wc_poll($row['id']);
    $state = simcloud_wc_row($row['id'])['state'];
    $expected = ['missing_codes' => 'needs_review', 'mismatch' => 'needs_review', 'not_found' => 'needs_review', 'failed' => 'failed', 'server_error' => 'pending', 'pending' => 'pending'][$mode];
    sc_assert($state === $expected && wc_get_order($order->get_id())->get_status() !== 'completed', $mode . ' status never reports false delivery');
    $GLOBALS['simcloud_wc_test_mode'] = 'success';
}

[$order, $row] = sc_order($products['airtime'], false);
$before = count(sc_calls()); simcloud_wc_submit($row['id']);
sc_assert(count(sc_calls()) === $before, 'Unpaid order cannot submit');
$order->set_payment_method('cod'); $order->save(); $order->payment_complete();
sc_assert(simcloud_wc_row($row['id'])['state'] === 'awaiting_payment', 'COD payment hook cannot automatically authorize funds');

[$order, $row] = sc_order($products['airtime']);
$order->update_status('cancelled'); $before = count(sc_calls()); simcloud_wc_submit($row['id']);
sc_assert(count(sc_calls()) === $before && simcloud_wc_row($row['id'])['state'] === 'needs_review', 'Cancellation before submission prevents wallet spending');

[$order, $row] = sc_order($products['airtime']);
$GLOBALS['simcloud_wc_test_mode'] = 'unauthorized'; simcloud_wc_submit($row['id']);
sc_assert(get_option('simcloud_wc_auth_error') === 'yes' && !simcloud_wc_live_ready(), 'Authentication failure pauses new fulfilment');
$GLOBALS['simcloud_wc_test_mode'] = 'success'; delete_option('simcloud_wc_auth_error');
update_option('simcloud_wc_bound_site', 'wrong-site');
sc_assert(!simcloud_wc_live_ready(), 'Copied site URL cannot submit with old binding');
update_option('simcloud_wc_bound_site', hash('sha256', home_url('/')));

$GLOBALS['simcloud_wc_test_mode'] = 'network_mismatch';
sc_assert(is_wp_error(simcloud_wc_purchase_input(simcloud_wc_config($products['airtime']), ['recipient' => '0820000000'])), 'Ported/network mismatch is rejected');
$GLOBALS['simcloud_wc_test_mode'] = 'meter_unknown';
sc_assert(is_wp_error(simcloud_wc_purchase_input(simcloud_wc_config($products['electricity']), ['recipient' => '0820000000', 'meter' => '00000000000'])), 'Unknown meter support is rejected before payment');
$GLOBALS['simcloud_wc_test_mode'] = 'success';

$_POST['simcloud_wc_recipient'] = '0820000000';
$key1 = WC()->cart->add_to_cart($products['airtime']->get_id(), 1);
$_POST['simcloud_wc_recipient'] = '0820000001';
$key2 = WC()->cart->add_to_cart($products['airtime']->get_id(), 1);
unset($_POST['simcloud_wc_recipient']);
sc_assert($key1 && $key2 && $key1 !== $key2 && count(WC()->cart->get_cart()) === 2, 'Classic cart preserves separate recipient lines');
WC()->cart->calculate_totals();
$classic_id = WC()->checkout()->create_order(['billing_email' => 'synthetic@example.invalid', 'billing_country' => 'ZA', 'payment_method' => 'simcloud_test']);
sc_assert(!is_wp_error($classic_id) && $classic_id > 0, 'Classic WooCommerce checkout creates a real order');
$classic = wc_get_order($classic_id);
sc_assert(count($classic->get_items()) === 2 && count(simcloud_wc_rows($classic_id)) === 2, 'Classic checkout persists per-line snapshots and fulfilment records');
foreach ($classic->get_items() as $item) { sc_assert(!empty($item->get_meta('_simcloud_wc_snapshot')['recipient']), 'Classic order snapshot retains recipient'); }
WC()->cart->empty_cart();

// Exercise actual Store API routing and schema serialization used by Checkout Blocks.
$request = new WP_REST_Request('POST', '/wc/store/v1/cart/add-item');
$request->set_header('Nonce', wp_create_nonce('wc_store_api'));
$request->set_body_params(['id' => $products['electricity']->get_id(), 'quantity' => 1, 'extensions' => ['simcloud-wc' => ['recipient' => '0820000000', 'meter' => '00000000000']]]);
$response = rest_do_request($request);
sc_assert($response->get_status() < 300, 'Store API adds a validated electricity line: ' . wp_json_encode($response->get_data()['message'] ?? 'OK'));
$cart = json_decode(wp_json_encode($response->get_data()), true);
sc_assert(($cart['items'][0]['extensions']['simcloud-wc']['meter'] ?? '') === '00000000000', 'Blocks cart exposes the correct session-owned meter data');
$request = new WP_REST_Request('POST', '/wc/store/v1/checkout');
$request->set_header('Nonce', wp_create_nonce('wc_store_api'));
$request->set_body_params(['billing_address' => ['first_name' => 'Synthetic', 'last_name' => 'Customer', 'email' => 'synthetic@example.invalid', 'country' => 'ZA', 'address_1' => '1 Test Road', 'city' => 'Cape Town', 'state' => 'WC', 'postcode' => '8000', 'phone' => '0820000000'],
    'payment_method' => 'simcloud_test', 'payment_data' => []]);
$response = rest_do_request($request);
$body = $response->get_data();
sc_assert($response->get_status() < 300, 'Checkout Blocks Store API completes checkout: ' . wp_json_encode($body['message'] ?? 'OK'));
$block_order = wc_get_order($body['order_id'] ?? 0);
sc_assert($block_order && count(simcloud_wc_rows($block_order->get_id())) === 1, 'Blocks checkout persists fulfilment record');
if ($block_order) {
    $row = simcloud_wc_rows($block_order->get_id())[0];
    sc_assert($row['state'] === 'ready', 'Blocks gateway payment confirms eligible funds');
    simcloud_wc_submit($row['id']); simcloud_wc_poll($row['id']);
    sc_assert(simcloud_wc_row($row['id'])['state'] === 'delivered', 'Blocks electricity purchase fulfils through token delivery');
}
WC()->cart->empty_cart();

// Purchase snapshots, financial changes, and authorization remain independent.
[$order, $row] = sc_order($products['airtime']);
$original = simcloud_wc_config($products['airtime']);
$changed = $original; $changed['amount'] = '99.00';
$products['airtime']->update_meta_data('_simcloud_wc_config', $changed); $products['airtime']->save();
simcloud_wc_submit($row['id']);
$calls = sc_calls(); $last = end($calls);
sc_assert(simcloud_wc_cents($last['payload']['amount']) === 1000, 'Editing a product after payment cannot alter the purchased snapshot');
$products['airtime']->update_meta_data('_simcloud_wc_config', $original); $products['airtime']->save();

[$order, $row] = sc_order($products['airtime']);
$refund = wc_create_refund(['order_id' => $order->get_id(), 'amount' => '1.00', 'reason' => 'Synthetic local refund', 'refund_payment' => false, 'restock_items' => false]);
sc_assert(!is_wp_error($refund), 'Synthetic partial refund persists without calling a real gateway');
$before = count(sc_calls()); simcloud_wc_submit($row['id']);
sc_assert(count(sc_calls()) === $before && simcloud_wc_row($row['id'])['state'] === 'needs_review', 'Partial refund before submission blocks wallet spending');

[$order, $row] = sc_order($products['airtime'], false);
$order->set_payment_method('bacs'); $order->update_status('on-hold');
do_action('woocommerce_order_action_simcloud_wc_confirm_funds', $order);
sc_assert(simcloud_wc_row($row['id'])['state'] === 'awaiting_payment', 'An unauthorized user cannot confirm merchant funds');
wp_set_current_user(1);
do_action('woocommerce_order_action_simcloud_wc_confirm_funds', $order);
sc_assert(simcloud_wc_row($row['id'])['state'] === 'ready', 'Authorized staff can explicitly confirm received BACS funds');
wp_set_current_user(0);

WC()->cart->empty_cart();
$_POST['simcloud_wc_recipient'] = '0820000000';
$bad_quantity = WC()->cart->add_to_cart($products['airtime']->get_id(), 1.5);
sc_assert($bad_quantity === false, 'Classic add-to-cart rejects fractional prepaid quantity');
wc_clear_notices();
$key = WC()->cart->add_to_cart($products['airtime']->get_id(), 1);
$ordinary = new WC_Product_Simple(); $ordinary->set_name('Synthetic ordinary product'); $ordinary->set_regular_price('1'); $ordinary->set_virtual(true); $ordinary->save();
WC()->cart->add_to_cart($ordinary->get_id(), 1);
sc_assert(count(simcloud_wc_cart_errors()) > 0, 'Mixed prepaid and ordinary carts are rejected before checkout');
unset($_POST['simcloud_wc_recipient']);
WC()->cart->empty_cart();
update_option('woocommerce_currency', 'USD');
sc_assert(is_wp_error(simcloud_wc_purchase_input(simcloud_wc_config($products['vas']), [])), 'Non-ZAR purchase is rejected');
update_option('woocommerce_currency', 'ZAR');

$vas_catalogue = simcloud_wc_catalogue('vas');
sc_assert(is_wp_error(simcloud_wc_validate_config(['service' => 'vas', 'amount' => '99.00', 'product_id' => 12])), 'VAS rejects a denomination absent from the current catalogue');
$data_catalogue = simcloud_wc_catalogue('data');
$ambiguous = $data_catalogue; $ambiguous['products'][] = $ambiguous['products'][0];
set_transient('simcloud_wc_catalogue_data', $ambiguous, 900);
sc_assert(is_wp_error(simcloud_wc_validate_config(simcloud_wc_config($products['data']))), 'Ambiguous data mappings cannot be offered as distinct bundles');
set_transient('simcloud_wc_catalogue_data', $data_catalogue, 900);

[$order, $row] = sc_order($products['vas']); simcloud_wc_submit($row['id']);
$row = simcloud_wc_row($row['id']);
$body = get_option('simcloud_wc_test_provider_' . $row['provider_id']);
$body['status'] = 'unrecognized';
sc_assert(is_wp_error(simcloud_wc_parse_status($body, simcloud_wc_order_snapshot($order, $row['item_id']), $row)), 'Unknown final provider status cannot imply delivery');
$body['status'] = 'success'; $body['order_id'] = ['malformed'];
sc_assert(!simcloud_wc_ack_valid($body, simcloud_wc_order_snapshot($order, $row['item_id']), $row['reference']), 'Malformed provider identifiers are rejected without PHP warnings');
ob_start(); do_action('woocommerce_email_after_order_table', $order, false, false); $email = ob_get_clean();
sc_assert(!str_contains($email, 'TEST-NOT-REDEEMABLE') && str_contains($email, 'View order'), 'Emails link to private order details without exposing redeemable codes');
$export = simcloud_wc_export('synthetic@example.invalid');
sc_assert(!empty($export['data']), 'WordPress privacy exporter includes prepaid purchase records');
$erasers = apply_filters('wp_privacy_personal_data_erasers', []);
$erased = $erasers['simcloud-wc']['callback']('synthetic@example.invalid', 1);
sc_assert($erased['items_retained'] && !$erased['items_removed'], 'Privacy eraser reports retained evidence rather than destroying unresolved value');

[$order, $row] = sc_order($products['airtime']);
update_option('simcloud_wc_test_claim_id', (int) $row['id'], false);
update_option('simcloud_wc_test_product_ids', array_map(function ($p) { return $p->get_id(); }, $products), false);
echo 'RESULT: ' . $GLOBALS['simcloud_wc_assertions'] . ' assertions passed. PHP ' . PHP_VERSION . '; WordPress ' . get_bloginfo('version') . '; WooCommerce ' . WC_VERSION . "; database " . $GLOBALS['wpdb']->db_version() . "\n";
