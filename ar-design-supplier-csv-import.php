<?php
/**
 * Plugin Name: AR Design Supplier CSV Import
 * Plugin URI: https://github.com/Arpad70/woocommerce_ar-design-supplier-csv-import
 * Description: Import supplier CSV products into WooCommerce from an external URL and save the downloaded file to /import.
 * Version: 1.0.0
 * Author: Arpád Horák
 * Author URI: https://arpad-horak.cz
 * Developer: Arpád Horák
 * Developer URI: https://arpad-horak.cz
 * Text Domain: ar-design-supplier-csv-import
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, ar-design-shared-support
 * Update URI: https://github.com/Arpad70/woocommerce_ar-design-supplier-csv-import
 * WC requires at least: 4.2
 * WC tested up to: 10.6.1
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_FILE')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_FILE', __FILE__);
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_PATH')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_BASENAME')) {
    define('ARD_SUPPLIER_CSV_IMPORT_BASENAME', plugin_basename(__FILE__));
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_VERSION')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_VERSION', '1.0.0');
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_REPOSITORY')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_REPOSITORY', 'Arpad70/woocommerce_ar-design-supplier-csv-import');
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_SLUG')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_SLUG', 'ar-design-supplier-csv-import');
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_NAME')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_NAME', 'AR Design Supplier CSV Import');
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_DESCRIPTION')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_DESCRIPTION', 'Import supplier CSV products into WooCommerce from an external URL and save the downloaded file to /import.');
}

if (! defined('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_ROLLBACK_MESSAGE')) {
    define('ARD_SUPPLIER_CSV_IMPORT_PLUGIN_ROLLBACK_MESSAGE', 'Aktualizácia AR Design Supplier CSV Import zlyhala. Predchádzajúca verzia bola automaticky obnovená zo zálohy.');
}

$ardSupplierCsvImportBootstrapConfig = [
    'version' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_VERSION,
    'basename' => ARD_SUPPLIER_CSV_IMPORT_BASENAME,
    'path' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_PATH,
    'repository' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_REPOSITORY,
    'slug' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_SLUG,
    'plugin_name' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_NAME,
    'text_domain' => 'ar-design-supplier-csv-import',
    'description' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_DESCRIPTION,
    'rollback_message' => ARD_SUPPLIER_CSV_IMPORT_PLUGIN_ROLLBACK_MESSAGE,
    'register_updater' => true,
];

require_once ARD_SUPPLIER_CSV_IMPORT_PLUGIN_PATH . 'bootstrap/runtime-skeleton.php';
require_once ARD_SUPPLIER_CSV_IMPORT_PLUGIN_PATH . 'includes/Importer.php';

if (class_exists('\ArDesign\SupplierCsvImport\Importer')) {
    add_action('plugins_loaded', function (): void {
        if (! class_exists('WooCommerce')) {
            add_action('admin_notices', function (): void {
                if (! current_user_can('activate_plugins')) {
                    return;
                }

                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html__('AR Design Supplier CSV Import requires WooCommerce to be active.', 'ar-design-supplier-csv-import'); ?></p>
                </div>
                <?php
            });

            return;
        }

        \ArDesign\SupplierCsvImport\Importer::init();
    });

    register_activation_hook(__FILE__, ['\ArDesign\SupplierCsvImport\Importer', 'activate']);
    register_deactivation_hook(__FILE__, ['\ArDesign\SupplierCsvImport\Importer', 'deactivate']);
}
