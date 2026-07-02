<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! isset($ardSupplierCsvImportBootstrapConfig) || ! is_array($ardSupplierCsvImportBootstrapConfig)) {
    return;
}

$config = $ardSupplierCsvImportBootstrapConfig;
unset($ardSupplierCsvImportBootstrapConfig);

require_once WP_PLUGIN_DIR . '/ar-design-shared-support/includes/updates/ReportingModuleRuntime.php';
ard_shared_register_reporting_module_update_runtime($config);

return;
