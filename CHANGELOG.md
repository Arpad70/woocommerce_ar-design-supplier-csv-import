# Changelog

## [1.0.4] - 2026-07-02
### Added
- Czech translations (cs_CZ) — added `ar-design-supplier-csv-import-cs_CZ.po` and compiled `*.mo`.
- Compiled and included translation files for English (`en_US`) and Slovak (`sk_SK`).

### Fixed
- Settings callbacks were made public so WordPress Settings API can invoke them (previously private methods caused fatal error when opening settings page).
- Ensured CLI-safe import configuration and parsing for `scripts/test-import-config.php` (bootstrap/path fixes and parsing robustness).
- `getImportSettings()` now works in non-WP CLI contexts by falling back to sensible defaults when `get_option()` is unavailable.
- `createImportDir()` has a fallback to `mkdir()` when `wp_mkdir_p()` is not available.
- Included `includes/Importer.php` in the release commit and rebuilt the release archive.

### Other
- Bumped plugin version to `1.0.4`.
- Built release ZIP: `build/ar-design-supplier-csv-import-v1.0.4.zip`.


For details, see commits on the `main` branch.
