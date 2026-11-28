#!/bin/bash
# Cleanup Report Generator: build the bundled, re-namespaced Dompdf (vendor-prefixed/).
#
# Run this on the machine you build the plugin zip on (needs Composer and PHP 8+). The zip then carries
# vendor-prefixed/, so the WordPress server needs no Composer. See readme.txt › Dompdf.
set -euo pipefail
cd "$(dirname "$0")"

command -v composer >/dev/null || { echo "Composer not found: install it from https://getcomposer.org/ first."; exit 1; }

echo "Installing Dompdf and copying it under the plugin's namespace (Strauss)..."
composer install --no-dev --no-interaction --optimize-autoloader

php -r '
require "vendor-prefixed/autoload.php";
if ( ! class_exists( "CleanupReportGenerator\\Vendor\\Dompdf\\Dompdf" ) ) { fwrite( STDERR, "Dompdf build failed: vendor-prefixed is incomplete.\n" ); exit( 1 ); }
echo "Dompdf ready in vendor-prefixed/.\n";
'

echo "Next: zip the plugin without the files in .distignore (for example: wp dist-archive .) and upload it in Plugins › Add New."
