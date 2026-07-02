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
    private const OPTION_DELIMITER = 'ard_supplier_csv_import_delimiter';
    private const OPTION_ENCLOSURE = 'ard_supplier_csv_import_enclosure';
    private const OPTION_HAS_HEADER = 'ard_supplier_csv_import_has_header';
    private const OPTION_SKIP_ROWS = 'ard_supplier_csv_import_skip_rows';
    private const OPTION_MATCH_FIELD = 'ard_supplier_csv_import_match_field';
    private const OPTION_ACTION = 'ard_supplier_csv_import_action';
    private const OPTION_PRODUCT_TYPE = 'ard_supplier_csv_import_product_type';
    private const OPTION_FIELD_MAPPING = 'ard_supplier_csv_import_field_mapping';
    private const OPTION_CATEGORY_MODE = 'ard_supplier_csv_import_category_mode';
    private const OPTION_IMPORT_ATTRIBUTES = 'ard_supplier_csv_import_import_attributes';
    private const OPTION_IMPORT_IMAGES = 'ard_supplier_csv_import_import_images';
    private const OPTION_IMPORT_STOCK = 'ard_supplier_csv_import_import_stock';
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
        $settings = [
            self::OPTION_URL => ['type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => ''],
            self::OPTION_SCHEDULE => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeSchedule'], 'default' => 'hourly'],
            self::OPTION_DELIMITER => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeDelimiter'], 'default' => ';'],
            self::OPTION_ENCLOSURE => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeEnclosure'], 'default' => '"'],
            self::OPTION_HAS_HEADER => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitizeFlag'], 'default' => true],
            self::OPTION_SKIP_ROWS => ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0],
            self::OPTION_MATCH_FIELD => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeMatchField'], 'default' => 'sku'],
            self::OPTION_ACTION => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeAction'], 'default' => 'create_update'],
            self::OPTION_PRODUCT_TYPE => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeProductType'], 'default' => 'simple'],
            self::OPTION_FIELD_MAPPING => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeFieldMapping'], 'default' => 'sku:SKU|name:Product Name|regular_price:Prix de vente conseillé|description:Item description|short_description:Item description|weight:Weight|length:Item length|width:Item width|height:Item height|categories:Category 1'],
            self::OPTION_CATEGORY_MODE => ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitizeCategoryMode'], 'default' => 'append'],
            self::OPTION_IMPORT_ATTRIBUTES => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitizeFlag'], 'default' => true],
            self::OPTION_IMPORT_IMAGES => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitizeFlag'], 'default' => true],
            self::OPTION_IMPORT_STOCK => ['type' => 'boolean', 'sanitize_callback' => [self::class, 'sanitizeFlag'], 'default' => false],
        ];

        foreach ($settings as $option => $args) {
            register_setting('ard_supplier_csv_import', $option, $args);
        }

        add_settings_section(
            'ard_supplier_csv_import_section',
            __('Supplier CSV settings', 'ar-design-supplier-csv-import'),
            '__return_false',
            'ard_supplier_csv_import'
        );

        $fields = [
            self::OPTION_URL => [__('CSV source URL', 'ar-design-supplier-csv-import'), [self::class, 'renderUrlField']],
            self::OPTION_SCHEDULE => [__('Import interval', 'ar-design-supplier-csv-import'), [self::class, 'renderScheduleField']],
            self::OPTION_DELIMITER => [__('CSV delimiter', 'ar-design-supplier-csv-import'), [self::class, 'renderDelimiterField']],
            self::OPTION_ENCLOSURE => [__('CSV enclosure', 'ar-design-supplier-csv-import'), [self::class, 'renderEnclosureField']],
            self::OPTION_HAS_HEADER => [__('CSV contains header row', 'ar-design-supplier-csv-import'), [self::class, 'renderHasHeaderField']],
            self::OPTION_SKIP_ROWS => [__('Skip first rows', 'ar-design-supplier-csv-import'), [self::class, 'renderSkipRowsField']],
            self::OPTION_MATCH_FIELD => [__('Match products by', 'ar-design-supplier-csv-import'), [self::class, 'renderMatchField']],
            self::OPTION_ACTION => [__('Import behavior', 'ar-design-supplier-csv-import'), [self::class, 'renderActionField']],
            self::OPTION_PRODUCT_TYPE => [__('Default product type', 'ar-design-supplier-csv-import'), [self::class, 'renderProductTypeField']],
            self::OPTION_FIELD_MAPPING => [__('Field mapping', 'ar-design-supplier-csv-import'), [self::class, 'renderFieldMappingField']],
            self::OPTION_CATEGORY_MODE => [__('Category handling', 'ar-design-supplier-csv-import'), [self::class, 'renderCategoryModeField']],
            self::OPTION_IMPORT_ATTRIBUTES => [__('Import product attributes', 'ar-design-supplier-csv-import'), [self::class, 'renderImportAttributesField']],
            self::OPTION_IMPORT_IMAGES => [__('Import product images', 'ar-design-supplier-csv-import'), [self::class, 'renderImportImagesField']],
            self::OPTION_IMPORT_STOCK => [__('Import stock data', 'ar-design-supplier-csv-import'), [self::class, 'renderImportStockField']],
        ];

        foreach ($fields as $option => $field) {
            add_settings_field($option, $field[0], $field[1], 'ard_supplier_csv_import', 'ard_supplier_csv_import_section');
        }
    }

    public static function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Supplier CSV Import', 'ar-design-supplier-csv-import'); ?></h1>
            <p><?php esc_html_e('Configure CSV parsing, matching, field mapping, and WooCommerce-specific import behavior from this screen.', 'ar-design-supplier-csv-import'); ?></p>
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

    public static function sanitizeDelimiter(string $value): string
    {
        $value = trim($value);
        return $value !== '' ? substr($value, 0, 1) : ';';
    }

    public static function sanitizeEnclosure(string $value): string
    {
        $value = trim($value);
        return $value !== '' ? substr($value, 0, 1) : '"';
    }

    public static function sanitizeFlag($value): string
    {
        return $value ? '1' : '0';
    }

    public static function sanitizeMatchField(string $value): string
    {
        $allowed = ['sku', 'id'];
        return in_array($value, $allowed, true) ? $value : 'sku';
    }

    public static function sanitizeAction(string $value): string
    {
        $allowed = ['create_only', 'update_only', 'create_update'];
        return in_array($value, $allowed, true) ? $value : 'create_update';
    }

    public static function sanitizeProductType(string $value): string
    {
        $allowed = ['simple'];
        return in_array($value, $allowed, true) ? $value : 'simple';
    }

    public static function sanitizeFieldMapping(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'sku:SKU';
        }

        return preg_replace('/\s+/', ' ', $value);
    }

    public static function sanitizeCategoryMode(string $value): string
    {
        $allowed = ['append', 'replace', 'none'];
        return in_array($value, $allowed, true) ? $value : 'append';
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

    private static function getImportSettings(array $overrides = []): array
    {
        $defaults = [
            'url' => '',
            'schedule' => 'hourly',
            'delimiter' => ';',
            'enclosure' => '"',
            'has_header' => true,
            'skip_rows' => 0,
            'match_field' => 'sku',
            'action' => 'create_update',
            'product_type' => 'simple',
            'field_mapping' => 'sku:SKU|name:Product Name|regular_price:Prix de vente conseillé|description:Item description|short_description:Item description|weight:Weight|length:Item length|width:Item width|height:Item height|categories:Category 1',
            'category_mode' => 'append',
            'import_attributes' => true,
            'import_images' => true,
            'import_stock' => false,
        ];

        if (function_exists('get_option')) {
            $defaults['url'] = get_option(self::OPTION_URL, '');
            $defaults['schedule'] = get_option(self::OPTION_SCHEDULE, 'hourly');
            $defaults['delimiter'] = get_option(self::OPTION_DELIMITER, ';');
            $defaults['enclosure'] = get_option(self::OPTION_ENCLOSURE, '"');
            $defaults['has_header'] = (bool) get_option(self::OPTION_HAS_HEADER, '1');
            $defaults['skip_rows'] = (int) get_option(self::OPTION_SKIP_ROWS, '0');
            $defaults['match_field'] = get_option(self::OPTION_MATCH_FIELD, 'sku');
            $defaults['action'] = get_option(self::OPTION_ACTION, 'create_update');
            $defaults['product_type'] = get_option(self::OPTION_PRODUCT_TYPE, 'simple');
            $defaults['field_mapping'] = get_option(self::OPTION_FIELD_MAPPING, 'sku:SKU|name:Product Name|regular_price:Prix de vente conseillé|description:Item description|short_description:Item description|weight:Weight|length:Item length|width:Item width|height:Item height|categories:Category 1');
            $defaults['category_mode'] = get_option(self::OPTION_CATEGORY_MODE, 'append');
            $defaults['import_attributes'] = (bool) get_option(self::OPTION_IMPORT_ATTRIBUTES, '1');
            $defaults['import_images'] = (bool) get_option(self::OPTION_IMPORT_IMAGES, '1');
            $defaults['import_stock'] = (bool) get_option(self::OPTION_IMPORT_STOCK, '0');
        }

        return array_replace($defaults, $overrides);
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
        $settings = self::getImportSettings();
        $url = $settings['url'];
        if (empty($url)) {
            return;
        }

        $download_result = self::downloadCsv($url);
        if (is_wp_error($download_result)) {
            self::logNotice($download_result->get_error_message());
            return;
        }

        $file_path = $download_result;
        $rows = self::parseCsv($file_path, $settings);
        if (empty($rows)) {
            self::logNotice(__('Supplier CSV file is empty or invalid.', 'ar-design-supplier-csv-import'));
            return;
        }

        $result = self::processRows($rows, $settings);
        if ($result['created'] > 0 || $result['updated'] > 0) {
            self::logNotice(sprintf(__('Supplier CSV import completed: %d created, %d updated.', 'ar-design-supplier-csv-import'), $result['created'], $result['updated']));
        } elseif ($result['skipped'] > 0) {
            self::logNotice(__('Supplier CSV import completed: no matching products found for the configured criteria.', 'ar-design-supplier-csv-import'));
        } else {
            self::logNotice(__('Supplier CSV import completed with no changes.', 'ar-design-supplier-csv-import'));
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

    public static function parseCsv(string $file_path, array $settings = []): array
    {
        if (! file_exists($file_path)) {
            return [];
        }

        $settings = self::getImportSettings($settings);
        $rows = [];
        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = [];
        $line_number = 0;
        $skip_rows = (int) ($settings['skip_rows'] ?? 0);
        $delimiter = $settings['delimiter'] ?? ';';
        $enclosure = $settings['enclosure'] ?? '"';
        $has_header = (bool) ($settings['has_header'] ?? true);

        while (($line = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false) {
            if ($line_number < $skip_rows) {
                $line_number++;
                continue;
            }

            if ($has_header && empty($header)) {
                $header = array_map('trim', $line);
                $line_number++;
                continue;
            }

            $row = $has_header && ! empty($header)
                ? array_combine($header, array_map('trim', $line))
                : array_map('trim', $line);

            if ($row === false || $row === []) {
                $line_number++;
                continue;
            }

            $rows[] = $row;
            $line_number++;
        }

        fclose($handle);
        return $rows;
    }

    public static function parseCsvContent(string $content, array $settings = []): array
    {
        $settings = self::getImportSettings($settings);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if ($lines === false || $lines === []) {
            return [];
        }

        $rows = [];
        $header = [];
        $skip_rows = (int) ($settings['skip_rows'] ?? 0);
        $delimiter = $settings['delimiter'] ?? ';';
        $enclosure = $settings['enclosure'] ?? '"';
        $has_header = (bool) ($settings['has_header'] ?? true);

        foreach ($lines as $index => $line) {
            if ($index < $skip_rows) {
                continue;
            }

            $parsed = str_getcsv($line, $delimiter, $enclosure);
            if ($parsed === false) {
                continue;
            }

            if ($has_header && empty($header)) {
                $header = array_map('trim', $parsed);
                continue;
            }

            $row = $has_header && ! empty($header)
                ? array_combine($header, array_map('trim', $parsed))
                : array_map('trim', $parsed);

            if ($row === false || $row === []) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public static function parseFieldMap(string $mapping): array
    {
        $mapping = trim($mapping);
        if ($mapping === '') {
            return [];
        }

        $parsed = [];
        foreach (explode('|', $mapping) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            $parts = explode(':', $entry, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $parsed[trim($parts[0])] = trim($parts[1]);
        }

        return $parsed;
    }

    public static function processRows(array $rows, array $settings = []): array
    {
        $settings = self::getImportSettings($settings);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $mapping = self::parseFieldMap($settings['field_mapping'] ?? '');

        foreach ($rows as $row) {
            $product_data = self::mapRowToProductData($row, $mapping);
            $match_value = $product_data[$settings['match_field'] ?? 'sku'] ?? '';

            if ($match_value === '') {
                $skipped++;
                continue;
            }

            $product = self::findOrCreateProduct($match_value, $settings['match_field'] ?? 'sku', $settings);
            if (! $product) {
                $skipped++;
                continue;
            }

            $action = $settings['action'] ?? 'create_update';
            $exists = $product->get_id() > 0;
            if ($exists && $action === 'create_only') {
                $skipped++;
                continue;
            }

            if (! $exists && $action === 'update_only') {
                $skipped++;
                continue;
            }

            if (self::applyProductData($product, $product_data, $settings)) {
                if ($exists) {
                    $updated++;
                } else {
                    $created++;
                }
            } else {
                $skipped++;
            }
        }

        return [
            'created' => $created,
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
     * @param array $product_data
     * @param array $settings
     * @return bool
     */
    private static function applyProductData(WC_Product $product, array $product_data, array $settings): bool
    {
        $needs_save = false;

        if (isset($product_data['name']) && $product->get_name() !== $product_data['name']) {
            $product->set_name($product_data['name']);
            $needs_save = true;
        }

        if (isset($product_data['sku']) && $product->get_sku() !== $product_data['sku']) {
            $product->set_sku($product_data['sku']);
            $needs_save = true;
        }

        if (isset($product_data['description']) && $product->get_description() !== $product_data['description']) {
            $product->set_description($product_data['description']);
            $needs_save = true;
        }

        if (isset($product_data['short_description']) && $product->get_short_description() !== $product_data['short_description']) {
            $product->set_short_description($product_data['short_description']);
            $needs_save = true;
        }

        if (isset($product_data['regular_price'])) {
            $price = self::normalizePrice($product_data['regular_price']);
            if ($price !== '' && $product->get_regular_price() !== $price) {
                $product->set_regular_price($price);
                $needs_save = true;
            }
        }

        if (isset($product_data['sale_price'])) {
            $price = self::normalizePrice($product_data['sale_price']);
            if ($price !== '' && $product->get_sale_price() !== $price) {
                $product->set_sale_price($price);
                $needs_save = true;
            }
        }

        if (isset($product_data['weight']) && $product->get_weight() !== $product_data['weight']) {
            $product->set_weight($product_data['weight']);
            $needs_save = true;
        }

        if (isset($product_data['length']) && $product->get_length() !== $product_data['length']) {
            $product->set_length($product_data['length']);
            $needs_save = true;
        }

        if (isset($product_data['width']) && $product->get_width() !== $product_data['width']) {
            $product->set_width($product_data['width']);
            $needs_save = true;
        }

        if (isset($product_data['height']) && $product->get_height() !== $product_data['height']) {
            $product->set_height($product_data['height']);
            $needs_save = true;
        }

        if (! empty($product_data['categories'])) {
            $mode = $settings['category_mode'] ?? 'append';
            if ($mode === 'replace') {
                $product->set_category_ids([]);
            }

            foreach ($product_data['categories'] as $category) {
                self::assignCategory($product, $category);
            }
            $needs_save = true;
        }

        if (! empty($product_data['attributes']) && ! empty($settings['import_attributes'])) {
            foreach ($product_data['attributes'] as $attribute_name => $attribute_value) {
                self::setProductAttribute($product, $attribute_name, $attribute_value);
            }
            $needs_save = true;
        }

        if (! empty($product_data['images']) && ! empty($settings['import_images'])) {
            foreach ($product_data['images'] as $image_url) {
                self::processImage($product, $image_url);
            }
            $needs_save = true;
        }

        if (! empty($settings['import_stock']) && isset($product_data['stock_quantity'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $product_data['stock_quantity']);
            $product->set_stock_status(isset($product_data['stock_status']) ? $product_data['stock_status'] : 'instock');
            $needs_save = true;
        }

        if ($product->get_id() === 0) {
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
        }

        if ($needs_save) {
            $product->save();
        }

        return $needs_save;
    }

    private static function mapRowToProductData(array $row, array $mapping): array
    {
        $product_data = [];
        foreach ($mapping as $target => $source) {
            $value = trim((string) ($row[$source] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($target === 'categories') {
                $product_data['categories'] = array_values(array_unique(array_map('trim', preg_split('/[|,]/', $value) ?: [])));
                continue;
            }

            if ($target === 'images') {
                $product_data['images'] = array_values(array_unique(array_map('trim', preg_split('/[|,]/', $value) ?: [])));
                continue;
            }

            if ($target === 'attributes') {
                $parts = explode(':', $value, 2);
                if (count($parts) === 2) {
                    $product_data['attributes'][trim($parts[0])] = trim($parts[1]);
                }
                continue;
            }

            $product_data[$target] = $value;
        }

        return $product_data;
    }

    private static function findOrCreateProduct(string $match_value, string $match_field, array $settings): WC_Product
    {
        $product = null;
        $product_id = 0;

        if ($match_field === 'id') {
            $product_id = (int) $match_value;
            $product = wc_get_product($product_id);
        } else {
            $product_id = self::getProductBySku($match_value);
            $product = $product_id > 0 ? wc_get_product($product_id) : null;
        }

        if ($product instanceof WC_Product) {
            return $product;
        }

        $product = null;
        $product_id = self::getProductBySku($match_value);
        if ($product_id > 0) {
            $product = wc_get_product($product_id);
        }

        if (! $product instanceof WC_Product) {
            $product = new \WC_Product();
            $product->set_sku($match_value);
        }

        return $product;
    }

    private static function normalizePrice(string $value): string
    {
        $value = trim(str_replace([' ', ','], ['', '.'], $value));
        $value = preg_replace('/[^0-9\.]/', '', $value);
        return $value;
    }

    public static function renderDelimiterField(): void
    {
        $value = get_option(self::OPTION_DELIMITER, ';');
        echo '<input type="text" name="' . esc_attr(self::OPTION_DELIMITER) . '" value="' . esc_attr($value) . '" class="small-text" />';
    }

    public static function renderEnclosureField(): void
    {
        $value = get_option(self::OPTION_ENCLOSURE, '"');
        echo '<input type="text" name="' . esc_attr(self::OPTION_ENCLOSURE) . '" value="' . esc_attr($value) . '" class="small-text" />';
    }

    public static function renderHasHeaderField(): void
    {
        $value = get_option(self::OPTION_HAS_HEADER, '1');
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_HAS_HEADER) . '" value="1" ' . checked('1', $value, false) . ' />';
    }

    public static function renderSkipRowsField(): void
    {
        $value = get_option(self::OPTION_SKIP_ROWS, '0');
        echo '<input type="number" name="' . esc_attr(self::OPTION_SKIP_ROWS) . '" value="' . esc_attr((string) $value) . '" class="small-text" min="0" />';
    }

    public static function renderMatchField(): void
    {
        $value = get_option(self::OPTION_MATCH_FIELD, 'sku');
        echo '<select name="' . esc_attr(self::OPTION_MATCH_FIELD) . '">';
        echo '<option value="sku"' . selected($value, 'sku', false) . '>SKU</option>';
        echo '<option value="id"' . selected($value, 'id', false) . '>Post ID</option>';
        echo '</select>';
    }

    public static function renderActionField(): void
    {
        $value = get_option(self::OPTION_ACTION, 'create_update');
        echo '<select name="' . esc_attr(self::OPTION_ACTION) . '">';
        echo '<option value="create_update"' . selected($value, 'create_update', false) . '>Create or update</option>';
        echo '<option value="create_only"' . selected($value, 'create_only', false) . '>Create only</option>';
        echo '<option value="update_only"' . selected($value, 'update_only', false) . '>Update only</option>';
        echo '</select>';
    }

    public static function renderProductTypeField(): void
    {
        $value = get_option(self::OPTION_PRODUCT_TYPE, 'simple');
        echo '<select name="' . esc_attr(self::OPTION_PRODUCT_TYPE) . '">';
        echo '<option value="simple"' . selected($value, 'simple', false) . '>Simple product</option>';
        echo '</select>';
    }

    public static function renderFieldMappingField(): void
    {
        $value = get_option(self::OPTION_FIELD_MAPPING, 'sku:SKU|name:Product Name|regular_price:Prix de vente conseillé|description:Item description|short_description:Item description|weight:Weight|length:Item length|width:Item width|height:Item height|categories:Category 1');
        echo '<textarea name="' . esc_attr(self::OPTION_FIELD_MAPPING) . '" rows="5" cols="80">' . esc_textarea($value) . '</textarea><p class="description">Use format field:CSV column, separated by |. Example: sku:SKU|name:Product Name|regular_price:Prix de vente conseillé</p>';
    }

    public static function renderCategoryModeField(): void
    {
        $value = get_option(self::OPTION_CATEGORY_MODE, 'append');
        echo '<select name="' . esc_attr(self::OPTION_CATEGORY_MODE) . '">';
        echo '<option value="append"' . selected($value, 'append', false) . '>Append</option>';
        echo '<option value="replace"' . selected($value, 'replace', false) . '>Replace</option>';
        echo '<option value="none"' . selected($value, 'none', false) . '>Do not assign</option>';
        echo '</select>';
    }

    public static function renderImportAttributesField(): void
    {
        $value = get_option(self::OPTION_IMPORT_ATTRIBUTES, '1');
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_IMPORT_ATTRIBUTES) . '" value="1" ' . checked('1', $value, false) . ' />';
    }

    public static function renderImportImagesField(): void
    {
        $value = get_option(self::OPTION_IMPORT_IMAGES, '1');
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_IMPORT_IMAGES) . '" value="1" ' . checked('1', $value, false) . ' />';
    }

    public static function renderImportStockField(): void
    {
        $value = get_option(self::OPTION_IMPORT_STOCK, '0');
        echo '<input type="checkbox" name="' . esc_attr(self::OPTION_IMPORT_STOCK) . '" value="1" ' . checked('1', $value, false) . ' />';
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
        if (file_exists(self::IMPORT_DIR)) {
            return;
        }

        if (function_exists('wp_mkdir_p')) {
            wp_mkdir_p(self::IMPORT_DIR);
            return;
        }

        if (! mkdir(self::IMPORT_DIR, 0755, true) && ! is_dir(self::IMPORT_DIR)) {
            throw new \RuntimeException(sprintf('Unable to create import directory: %s', self::IMPORT_DIR));
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
