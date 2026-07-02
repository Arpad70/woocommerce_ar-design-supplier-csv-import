<?php

namespace ArDesign\SupplierCsvImport;

use WP_Error;
use WC_Product;

if (! defined('ABSPATH')) {
    exit;
}

class Importer
{
    private const IMPORT_DIR = ABSPATH . 'import' . DIRECTORY_SEPARATOR;
    private const IMPORT_FILE_NAME = 'supplier-import.csv';
    private const OPTION_URL = 'ard_supplier_csv_import_url';
    private const OPTION_SCHEDULE = 'ard_supplier_csv_import_schedule';
    private const CRON_HOOK = 'ard_supplier_csv_import_cron_hook';
    private const OLD_OMEGA_PLUGIN_FILE = 'wc-omega-connector/wc-omega-connector.php';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'addAdminMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_init', [self::class, 'maybeHandleManualImport']);
        add_action(self::CRON_HOOK, [self::class, 'runScheduledImport']);
        add_action('admin_post_ard_supplier_csv_import_manual', [self::class, 'handleManualImportAction']);
        add_action('admin_notices', [self::class, 'maybeShowNotice']);
    }

    public static function activate(): void
    {
        self::createImportDir();

        $schedule = get_option(self::OPTION_SCHEDULE, 'hourly');
        if (! wp_get_schedule(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK);
        }

        self::deactivateOldOmegaConnector();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function deactivateOldOmegaConnector(): void
    {
        if (! function_exists('is_plugin_active') || ! function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active(self::OLD_OMEGA_PLUGIN_FILE)) {
            deactivate_plugins(self::OLD_OMEGA_PLUGIN_FILE, true);
            self::logNotice(__('The legacy wc-omega-connector plugin was deactivated automatically.', 'ar-design-supplier-csv-import'));
        }
    }

    public static function addAdminMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Supplier CSV Import', 'ar-design-supplier-csv-import'),
            __('Supplier CSV Import', 'ar-design-supplier-csv-import'),
            'manage_woocommerce',
            'ard-supplier-csv-import',
            [self::class, 'renderSettingsPage']
        );
    }

    public static function registerSettings(): void
    {
        register_setting('ard_supplier_csv_import', self::OPTION_URL, [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);

        register_setting('ard_supplier_csv_import', self::OPTION_SCHEDULE, [
            'type' => 'string',
            'sanitize_callback' => [self::class, 'sanitizeSchedule'],
            'default' => 'hourly',
        ]);

        add_settings_section(
            'ard_supplier_csv_import_section',
            __('Supplier CSV settings', 'ar-design-supplier-csv-import'),
            '__return_false',
            'ard_supplier_csv_import'
        );

        add_settings_field(
            self::OPTION_URL,
            __('CSV source URL', 'ar-design-supplier-csv-import'),
            [self::class, 'renderUrlField'],
            'ard_supplier_csv_import',
            'ard_supplier_csv_import_section'
        );

        add_settings_field(
            self::OPTION_SCHEDULE,
            __('Import interval', 'ar-design-supplier-csv-import'),
            [self::class, 'renderScheduleField'],
            'ard_supplier_csv_import',
            'ard_supplier_csv_import_section'
        );
    }

    public static function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Supplier CSV Import', 'ar-design-supplier-csv-import'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ard_supplier_csv_import');
                do_settings_sections('ard_supplier_csv_import');
                submit_button(__('Save settings', 'ar-design-supplier-csv-import'));
                ?>
            </form>
            <form method="post" action="admin-post.php">
                <input type="hidden" name="action" value="ard_supplier_csv_import_manual" />
                <?php wp_nonce_field('ard_supplier_csv_import_manual'); ?>
                <?php submit_button(__('Run import now', 'ar-design-supplier-csv-import'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public static function renderUrlField(): void
    {
        $url = get_option(self::OPTION_URL, '');
        printf(
            '<input type="url" name="%s" value="%s" class="regular-text" />',
            esc_attr(self::OPTION_URL),
            esc_attr($url)
        );
    }

    public static function renderScheduleField(): void
    {
        $value = get_option(self::OPTION_SCHEDULE, 'hourly');
        $options = [
            'hourly' => __('Hourly', 'ar-design-supplier-csv-import'),
            'twicedaily' => __('Twice daily', 'ar-design-supplier-csv-import'),
            'daily' => __('Daily', 'ar-design-supplier-csv-import'),
        ];

        foreach ($options as $key => $label) {
            printf(
                '<label><input type="radio" name="%s" value="%s" %s /> %s</label><br>',
                esc_attr(self::OPTION_SCHEDULE),
                esc_attr($key),
                checked($value, $key, false),
                esc_html($label)
            );
        }
    }

    public static function sanitizeSchedule(string $value): string
    {
        $allowed = ['hourly', 'twicedaily', 'daily'];
        return in_array($value, $allowed, true) ? $value : 'hourly';
    }

    public static function maybeHandleManualImport(): void
    {
        if (! isset($_GET['page']) || $_GET['page'] !== 'ard-supplier-csv-import') {
            return;
        }

        $schedule = get_option(self::OPTION_SCHEDULE, 'hourly');
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp && wp_get_schedule(self::CRON_HOOK) !== $schedule) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_schedule_event(time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK);
        }
    }

    public static function handleManualImportAction(): void
    {
        if (! current_user_can('manage_woocommerce') || ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'ard_supplier_csv_import_manual')) {
            wp_die(__('Unauthorized request.', 'ar-design-supplier-csv-import'));
        }

        self::runScheduledImport();
        wp_safe_redirect(add_query_arg('imported', '1', wp_get_referer() ?: admin_url('admin.php?page=ard-supplier-csv-import')));
        exit;
    }

    public static function runScheduledImport(): void
    {
        $url = get_option(self::OPTION_URL, '');
        if (empty($url)) {
            return;
        }

        $download_result = self::downloadCsv($url);
        if (is_wp_error($download_result)) {
            self::logNotice($download_result->get_error_message());
            return;
        }

        $file_path = $download_result;
        $rows = self::parseCsv($file_path);
        if (empty($rows)) {
            self::logNotice(__('Supplier CSV file is empty or invalid.', 'ar-design-supplier-csv-import'));
            return;
        }

        $result = self::processRows($rows);
        if ($result['updated'] > 0) {
            self::logNotice(sprintf(__('Supplier CSV import completed: %d products updated.', 'ar-design-supplier-csv-import'), $result['updated']));
        } elseif ($result['skipped'] > 0) {
            self::logNotice(__('Supplier CSV import completed: no matching products found for any SKU.', 'ar-design-supplier-csv-import'));
        } else {
            self::logNotice(__('Supplier CSV import completed with no updates.', 'ar-design-supplier-csv-import'));
        }
    }

    public static function downloadCsv(string $url)
    {
        self::createImportDir();

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'redirection' => 5,
            'headers' => [
                'Accept' => 'text/csv,application/csv,text/plain,*/*;q=0.1',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('download_failed', sprintf(__('Unable to download CSV, HTTP %s.', 'ar-design-supplier-csv-import'), $code));
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error('empty_body', __('Downloaded CSV is empty.', 'ar-design-supplier-csv-import'));
        }

        $file_path = self::IMPORT_DIR . self::IMPORT_FILE_NAME;
        $result = file_put_contents($file_path, $body);
        if ($result === false) {
            return new WP_Error('write_failed', __('Unable to write supplier CSV file.', 'ar-design-supplier-csv-import'));
        }

        return $file_path;
    }

    public static function parseCsv(string $file_path): array
    {
        if (! file_exists($file_path)) {
            return [];
        }

        $rows = [];
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = [];
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (empty($header)) {
                $header = array_map('trim', $line);
                continue;
            }

            $row = array_combine($header, array_map('trim', $line));
            if ($row === false) {
                continue;
            }

            $rows[] = $row; // phpstan ignore line
        }

        fclose($handle);
        return $rows;
    }

    public static function processRows(array $rows): array
    {
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $sku = trim($row['SKU'] ?? '');
            if ($sku === '') {
                continue;
            }

            $product_id = self::getProductBySku($sku);
            if ($product_id === 0) {
                $skipped++;
                continue;
            }

            $product = wc_get_product($product_id);
            if (! $product) {
                $skipped++;
                continue;
            }

            if (self::updateProductFromRow($product, $row)) {
                $updated++;
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private static function getProductBySku(string $sku): int
    {
        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sku'
             AND pm.meta_value = %s
             AND p.post_type IN ('product', 'product_variation')
             AND p.post_status NOT IN ('trash', 'auto-draft')
             LIMIT 1",
            $sku
        ));

        return $product_id ? (int) $product_id : 0;
    }

    /**
     * @param WC_Product $product
     * @param array $row
     * @return bool
     */
    private static function updateProductFromRow(WC_Product $product, array $row): bool
    {
        $needs_save = false;

        $mapping = [
            'Product Name' => 'name',
            'Item description' => 'description',
            'Prix de vente conseillé' => 'regular_price',
            'listprice' => 'regular_price',
            'Currency' => 'currency',
            'Weight' => 'weight',
            'Item length' => 'length',
            'Item width' => 'width',
            'Item height' => 'height',
            'Material' => 'attribute_material',
            'Colour' => 'attribute_color',
            'Brand' => 'attribute_brand',
            'Category 1' => 'category_1',
            'Category 2' => 'category_2',
            'Category 3' => 'category_3',
            'Image 1' => 'image_1',
            'Image 2' => 'image_2',
        ];

        foreach ($mapping as $csv_key => $field) {
            $value = trim($row[$csv_key] ?? '');
            if ($value === '') {
                continue;
            }

            switch ($field) {
                case 'name':
                    if ($product->get_name() !== $value) {
                        $product->set_name($value);
                        $needs_save = true;
                    }
                    break;
                case 'description':
                    if ($product->get_description() !== $value) {
                        $product->set_description($value);
                        $needs_save = true;
                    }
                    break;
                case 'regular_price':
                    $normalized = self::normalizePrice($value);
                    if ($normalized !== '' && $product->get_regular_price() !== $normalized) {
                        $product->set_regular_price($normalized);
                        $needs_save = true;
                    }
                    break;
                case 'weight':
                case 'length':
                case 'width':
                case 'height':
                    $normalized = self::normalizeNumeric($value);
                    if ($normalized !== '' && $product->{'get_' . $field}() !== $normalized) {
                        $product->{'set_' . $field}($normalized);
                        $needs_save = true;
                    }
                    break;
                case 'attribute_material':
                case 'attribute_color':
                case 'attribute_brand':
                    $attribute_name = str_replace('attribute_', '', $field);
                    self::setProductAttribute($product, $attribute_name, $value);
                    $needs_save = true;
                    break;
                case 'category_1':
                case 'category_2':
                case 'category_3':
                    $category = self::normalizeCategory($value);
                    if ($category !== '') {
                        self::assignCategory($product, $category);
                        $needs_save = true;
                    }
                    break;
                case 'image_1':
                case 'image_2':
                    self::processImage($product, $value);
                    $needs_save = true;
                    break;
            }
        }

        if ($needs_save) {
            $product->save();
        }

        return $needs_save;
    }

    private static function normalizePrice(string $value): string
    {
        $value = trim(str_replace([' ', ','], ['', '.'], $value));
        $value = preg_replace('/[^0-9\.]/', '', $value);
        return $value;
    }

    private static function normalizeNumeric(string $value): string
    {
        $value = trim(str_replace([' ', ','], ['', '.'], $value));
        $value = preg_replace('/[^0-9\.]/', '', $value);
        return $value;
    }

    private static function normalizeCategory(string $value): string
    {
        return trim($value);
    }

    /**
     * @param WC_Product $product
     * @param string $category
     * @return void
     */
    private static function assignCategory(WC_Product $product, string $category): void
    {
        if ($category === '') {
            return;
        }

        $term = term_exists($category, 'product_cat');
        if (! $term) {
            $term = wp_insert_term($category, 'product_cat');
        }

        if (is_wp_error($term)) {
            return;
        }

        $term_id = is_array($term) ? $term['term_id'] ?? 0 : (int) $term;
        if ($term_id > 0) {
            $current = $product->get_category_ids();
            if (! in_array($term_id, $current, true)) {
                $current[] = $term_id;
                $product->set_category_ids($current);
            }
        }
    }

    /**
     * @param WC_Product $product
     * @param string $name
     * @param string $value
     * @return void
     */
    private static function setProductAttribute(WC_Product $product, string $name, string $value): void
    {
        if ($value === '') {
            return;
        }

        $taxonomy = 'pa_' . sanitize_title($name);
        if (! \taxonomy_exists($taxonomy)) {
            if (! function_exists('wc_create_attribute')) {
                return;
            }

            $attribute_id = \wc_create_attribute([
                'name' => $name,
                'slug' => sanitize_title($name),
                'type' => 'select',
                'order_by' => 'name',
                'has_archives' => false,
            ]);

            if (\is_wp_error($attribute_id)) {
                return;
            }

            \register_taxonomy(
                $taxonomy,
                ['product'],
                [
                    'hierarchical' => false,
                    'show_ui' => false,
                    'query_var' => true,
                    'rewrite' => false,
                ]
            );
        }

        $term = \term_exists($value, $taxonomy);
        if (! $term) {
            $term = \wp_insert_term($value, $taxonomy);
        }

        if (\is_wp_error($term)) {
            return;
        }

        $term_id = is_array($term) ? $term['term_id'] ?? 0 : (int) $term;
        if ($term_id === 0) {
            return;
        }

        $attribute_data = $product->get_attributes();
        if (! class_exists('\WC_Product_Attribute')) {
            return;
        }

        $attr = new \WC_Product_Attribute();
        $attr->set_id($attribute_id ?? 0);
        $attr->set_name($taxonomy);
        $attr->set_options([$value]);
        $attr->set_position(count($attribute_data));
        $attr->set_visible(true);
        $attr->set_variation(false);
        $attribute_data[$taxonomy] = $attr;

        $product->set_attributes($attribute_data);
    }

    /**
     * @param WC_Product $product
     * @param string $url
     * @return void
     */
    private static function processImage(WC_Product $product, string $url): void
    {
        if ($url === '') {
            return;
        }

        $existing_ids = $product->get_gallery_image_ids() ?: [];
        if ($product->get_image_id()) {
            array_unshift($existing_ids, $product->get_image_id());
            $existing_ids = array_unique($existing_ids, SORT_NUMERIC);
        }

        $attachment_id = self::downloadImage($url);
        if ($attachment_id === 0) {
            return;
        }

        if (! in_array($attachment_id, $existing_ids, true)) {
            if (! $product->get_image_id()) {
                $product->set_image_id($attachment_id);
            } else {
                $existing_ids[] = $attachment_id;
                $product->set_gallery_image_ids($existing_ids);
            }
        }
    }

    private static function downloadImage(string $url): int
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp_file = download_url($url);
        if (is_wp_error($tmp_file) || $tmp_file === false) {
            return 0;
        }

        $file_name = sanitize_file_name(basename(parse_url($url, PHP_URL_PATH)) ?: 'imported-image.jpg');
        $file_array = [
            'name' => $file_name,
            'tmp_name' => $tmp_file,
        ];

        $attach_id = media_handle_sideload($file_array, 0);
        if (is_wp_error($attach_id)) {
            @unlink($tmp_file);
            return 0;
        }

        return $attach_id;
    }

    private static function mimeToExtension(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
        ];

        return $map[$mime] ?? '';
    }

    private static function createImportDir(): void
    {
        if (! file_exists(self::IMPORT_DIR)) {
            wp_mkdir_p(self::IMPORT_DIR);
        }
    }

    private static function logNotice(string $message): void
    {
        update_option('ard_supplier_csv_import_notice', sanitize_text_field($message));
    }

    public static function maybeShowNotice(): void
    {
        $message = '';

        if (isset($_GET['imported']) && $_GET['imported'] === '1' && current_user_can('manage_woocommerce')) {
            $message = __('Supplier CSV import has been started manually. Results will appear after the import finishes.', 'ar-design-supplier-csv-import');
        } else {
            $message = get_option('ard_supplier_csv_import_notice', '');
            if ($message !== '') {
                delete_option('ard_supplier_csv_import_notice');
            }
        }

        if ($message === '') {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
