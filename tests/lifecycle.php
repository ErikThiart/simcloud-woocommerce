<?php
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Isolated test installation required.');
}
require_once __DIR__ . '/mock-provider.php';
global $wpdb;
$id = (int) get_option('simcloud_wc_test_claim_id');
$row = simcloud_wc_row($id);
if (!$row || $row['state'] !== 'submitting') {
    throw new RuntimeException('Concurrent claim did not persist submission intent.');
}
$wpdb->update(simcloud_wc_table(), ['updated_at' => gmdate('Y-m-d H:i:s', time() - 600)], ['id' => $id]);
simcloud_wc_watchdog();
if (simcloud_wc_row($id)['state'] !== 'needs_review') {
    throw new RuntimeException('Interrupted submission was not flagged for reconciliation.');
}
$before = count($GLOBALS['simcloud_wc_test_calls']);
simcloud_wc_submit($id);
if (count($GLOBALS['simcloud_wc_test_calls']) !== $before) {
    throw new RuntimeException('Interrupted submission was replayed.');
}
echo "PASS: Concurrent submission intent survives interruption without replay.\n";
$count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . simcloud_wc_table());
simcloud_wc_deactivate();
if (simcloud_wc_live_ready() || (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . simcloud_wc_table()) !== $count) {
    throw new RuntimeException('Deactivation did not pause safely and preserve records.');
}
simcloud_wc_activate();
if (simcloud_wc_live_ready() || (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . simcloud_wc_table()) !== $count) {
    throw new RuntimeException('Reactivation must preserve records and remain paused.');
}
echo "PASS: Deactivation/reactivation preserve evidence and require explicit enablement.\n";
define('WP_UNINSTALL_PLUGIN', 'simcloud-woocommerce/simcloud-woocommerce.php');
require dirname(__DIR__) . '/uninstall.php';
if (get_option('simcloud_wc_token', '') !== '' || (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . simcloud_wc_table()) !== $count) {
    throw new RuntimeException('Uninstall did not remove credentials and preserve evidence.');
}
echo "PASS: Uninstall removes saved credentials and preserves fulfilment evidence.\n";
