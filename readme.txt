=== Cleanup Report Generator ===
Contributors: aditya
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

Create branded cleanup/health reports from the WP Admin. Export as PDF (uses Dompdf if available, or falls back to browser print-to-PDF).

== Description ==
- Custom Post Type: Reports
- Fields: client name, site URL, date, severity counts, before/after metrics, highlights, action items
- Charts via QuickChart (no key required)
- Settings: logo URL, brand colors, footer
- Export: Dompdf integration if the Dompdf library is present

To enable server-side PDFs, install Dompdf (via Composer) in any plugin or mu-plugin so that \Dompdf\Dompdf class is autoloadable.

== Installation ==
1. Upload the zip to Plugins → Add New → Upload Plugin
2. Activate plugin
3. Set branding under Settings → Cleanup Reports
4. Add a new Report under Reports → Add New
5. Click View Report / Export PDF

== Dompdf (optional) ==
If your hosting allows Composer:
- `composer require dompdf/dompdf`
Ensure the autoloader is available (e.g., in an mu-plugin or a small loader). The plugin will detect `\Dompdf\Dompdf` automatically.
