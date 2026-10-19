<?php
if (!defined('SIMCLOUD_WC_TEST_RUN') || !SIMCLOUD_WC_TEST_RUN || !str_starts_with(DB_NAME, 'simcloud_wc_test')) {
    throw new RuntimeException('Isolated test installation required.');
}
$start = (float) ($args[0] ?? 0);
while (microtime(true) < $start) {
    usleep(10000);
}
$id = (int) get_option('simcloud_wc_test_claim_id');
echo simcloud_wc_transition($id, 'ready', 'submitting', ['submitted_at' => gmdate('Y-m-d H:i:s')]) ? '1' : '0';
