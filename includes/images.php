<?php
defined('ABSPATH') || exit;

function simcloud_wc_logo_key($config, $entry = []) {
    if (in_array($config['service'] ?? '', ['airtime', 'data'], true)) {
        $networks = ['mtn' => 'mtn', 'vodacom' => 'vodacom', 'cellc' => 'cellc', 'heita' => 'telkom'];
        return isset($networks[$config['network'] ?? '']) ? 'networks/' . $networks[$config['network']] . '.jpg' : '';
    }
    if (($config['service'] ?? '') !== 'vas') {
        return '';
    }
    if (!$entry) {
        $catalogue = get_transient('simcloud_wc_catalogue_vas');
        foreach ((array) ($catalogue['products'] ?? []) as $candidate) {
            if (is_array($candidate) && (int) ($candidate['product_id'] ?? 0) === (int) ($config['product_id'] ?? 0)) {
                $entry = $candidate;
                break;
            }
        }
    }
    // API URLs only select a known bundled file; they are never downloaded at runtime.
    $url = simcloud_wc_text($entry['logo_url'] ?? '');
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    if (in_array($host, ['simcloud.co.za', 'www.simcloud.co.za'], true) && str_starts_with($path, '/img/vas_logos/')) {
        $key = 'vas/' . basename($path);
        if (str_ends_with($key, '.jpg') && is_file(SIMCLOUD_WC_DIR . 'assets/images/' . $key)) {
            return $key;
        }
    }
    $slug = preg_replace('/[^a-z0-9]/', '', strtolower(simcloud_wc_text($entry['slug'] ?? $entry['voucher_type'] ?? '')));
    $aliases = ['hussargrill' => 'hussarsgrill', 'johndorys' => 'johndory', 'pickandpay' => 'picknpay', 'takealot' => 'takealotcom'];
    $slug = $aliases[$slug] ?? $slug;
    foreach (glob(SIMCLOUD_WC_DIR . 'assets/images/vas/*.jpg') ?: [] as $file) {
        if (str_replace('-', '', pathinfo($file, PATHINFO_FILENAME)) === $slug) {
            return 'vas/' . basename($file);
        }
    }
    return '';
}

function simcloud_wc_set_default_image($product, $config, $entry = []) {
    if ($product->get_image_id('edit')) {
        return 0;
    }
    if (!current_user_can('upload_files')) {
        return new WP_Error('simcloud_image_permission', __('You cannot add product images.', 'simcloud-woocommerce'));
    }
    $key = simcloud_wc_logo_key($config, $entry);
    if ($key === '') {
        return new WP_Error('simcloud_logo_unknown', __('No bundled logo matches this product.', 'simcloud-woocommerce'));
    }
    $ids = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids',
        'meta_key' => '_simcloud_wc_logo', 'meta_value' => $key]);
    foreach ($ids as $id) {
        $file = get_attached_file($id);
        if (is_string($file) && is_file($file) && wp_attachment_is_image($id)) {
            $product->set_image_id($id);
            return $id;
        }
    }
    $source = SIMCLOUD_WC_DIR . 'assets/images/' . $key;
    $bytes = is_readable($source) ? file_get_contents($source) : false;
    if ($bytes === false) {
        return new WP_Error('simcloud_logo_file', __('The bundled product logo could not be read.', 'simcloud-woocommerce'));
    }
    $upload = wp_upload_bits('simcloud-' . basename($key), null, $bytes);
    if (!empty($upload['error'])) {
        return new WP_Error('simcloud_logo_upload', __('WordPress could not write the product logo. Check Media Library uploads.', 'simcloud-woocommerce'));
    }
    $labels = ['mtn' => 'MTN', 'vodacom' => 'Vodacom', 'cellc' => 'Cell C', 'telkom' => 'Telkom'];
    $basename = pathinfo($key, PATHINFO_FILENAME);
    $title = ($labels[$basename] ?? simcloud_wc_text($entry['voucher_type'] ?? ucwords(str_replace('-', ' ', $basename)))) . ' logo';
    $id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $title, 'post_status' => 'inherit',
        'comment_status' => 'closed', 'ping_status' => 'closed'], $upload['file'], 0, true);
    if (is_wp_error($id) || !$id) {
        wp_delete_file($upload['file']);
        return new WP_Error('simcloud_logo_attachment', __('WordPress could not register the product logo.', 'simcloud-woocommerce'));
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($id, $upload['file']);
    if (!is_array($metadata) || empty($metadata['width']) || empty($metadata['height'])) {
        wp_delete_attachment($id, true);
        return new WP_Error('simcloud_logo_metadata', __('WordPress could not process the product logo.', 'simcloud-woocommerce'));
    }
    wp_update_attachment_metadata($id, $metadata);
    update_post_meta($id, '_simcloud_wc_logo', $key);
    update_post_meta($id, '_wp_attachment_image_alt', $title);
    $product->set_image_id($id);
    return $id;
}
