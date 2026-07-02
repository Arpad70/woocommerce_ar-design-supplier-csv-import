AR Design Supplier CSV Import — User Guide

Overview

This plugin imports supplier CSV files into WooCommerce products using SKU matching. It supports configurable CSV delimiter, enclosure, header presence, field mapping, categories, attributes, images and stock.

Installation

- Install from GitHub release ZIP or via WP admin Plugins > Add New > Upload Plugin and activate.
- Requires WooCommerce active.

Basic usage

1. Go to WooCommerce > Supplier CSV Import.
2. Enter CSV source URL (or leave empty to upload manually later).
3. Configure delimiter (default `;`) and enclosure (default `"`).
4. Check "CSV contains header row" if your CSV has a header line.
5. Set "Match products by" to `SKU` (recommended) or `Post ID` if your feed contains WP post ids.
6. Configure "Field mapping" using format `field:CSV column` separated by `|`.
   - Example: `sku:SKU|name:Product Name|regular_price:Price|description:Desc|categories:Category`
7. Choose import behavior: Create/Update, Create only or Update only.
8. Toggle attribute/image/stock import as required.

Field mapping rules

- Left side (field) is the internal product field (e.g. `sku`, `name`, `regular_price`, `description`, `short_description`, `weight`, `length`, `width`, `height`, `categories`, `images`, `attributes`, `stock_quantity`).
- Right side is the exact header name from the CSV (when header present) or the column index label used by the import script.
- `categories` and `images` accept multiple values separated by `|` or `,`.
- `attributes` expects `Name:Value` pairs; only simple attributes (taxonomy) are created.

Images

- When enabled, images are downloaded and added to the product gallery. The plugin uses WordPress media sideload. Ensure PHP has network access and `allow_url_fopen` or curl available.

Translations

- Plugin includes translations (.po/.mo) for `en_US`, `sk_SK` and `cs_CZ` in the `languages/` folder. WordPress will select translation based on site language.

CLI testing

- A simple regression script is provided at `scripts/test-import-config.php` to validate mapping and parser in CLI. Run it from plugin directory: `php scripts/test-import-config.php`.

Troubleshooting

- If settings page crashes with callback errors, ensure plugin files are updated (methods need to be public for Settings API callbacks).
- Permission issues writing downloaded CSV: ensure webserver user can write to `wp-content/import` (plugin creates `import` folder in `ABSPATH`).

Support

Create an issue on the plugin GitHub repository with details and sample CSV (anonymized).
