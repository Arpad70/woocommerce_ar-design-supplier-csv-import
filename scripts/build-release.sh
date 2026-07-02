#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(tr -d '\r\n' < "$PLUGIN_DIR/VERSION")"
PLUGIN_SLUG="ar-design-supplier-csv-import"
BUILD_DIR="$PLUGIN_DIR/build"
OUTPUT_ZIP="$BUILD_DIR/${PLUGIN_SLUG}-v${VERSION}.zip"
TMP_DIR="$(mktemp -d)"

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

mkdir -p "$BUILD_DIR"
rm -f "$OUTPUT_ZIP"

mkdir -p "$TMP_DIR/$PLUGIN_SLUG"
rsync -a --delete "$PLUGIN_DIR/" "$TMP_DIR/$PLUGIN_SLUG/" --exclude-from="$PLUGIN_DIR/.distignore"

cd "$TMP_DIR"
zip -qr "$OUTPUT_ZIP" "$PLUGIN_SLUG"

echo "Built release archive: $OUTPUT_ZIP"
