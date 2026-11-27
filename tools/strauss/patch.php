<?php
/**
 * Fixes class names Strauss can't see because they're built at runtime (composer prefix-namespaces, after Strauss).
 * Fails the build if a patch no longer applies, so a dependency update that moves the code is noticed.
 */
$cleanup_report_root    = dirname( __DIR__, 2 );
$cleanup_report_patches = [
    // php-font-lib builds the TrueType/OpenType directory-entry class from the font type; used for every TTF font (Roboto).
    'vendor-prefixed/dompdf/php-font-lib/src/FontLib/TrueType/File.php' => [
        '"FontLib\\\\$type\\\\TableDirectoryEntry"' => '"CleanupReportGenerator\\\\Vendor\\\\FontLib\\\\$type\\\\TableDirectoryEntry"',
        // getFontType() reads the font type (TrueType, OpenType…) as the 2nd namespace part; it's the 2nd-last now.
        'return $class_parts[1];'                   => 'return $class_parts[ count( $class_parts ) - 2 ];',
    ],
];
foreach ( $cleanup_report_patches as $cleanup_report_file => $cleanup_report_pairs ) {
    $cleanup_report_path = $cleanup_report_root . '/' . $cleanup_report_file;
    $cleanup_report_code = (string) file_get_contents( $cleanup_report_path );
    foreach ( $cleanup_report_pairs as $cleanup_report_from => $cleanup_report_to ) {
        if ( false !== strpos( $cleanup_report_code, $cleanup_report_to ) ) {
            continue; // already patched
        }
        if ( false === strpos( $cleanup_report_code, $cleanup_report_from ) ) {
            fwrite( STDERR, "prefix patch no longer applies in $cleanup_report_file: $cleanup_report_from\n" );
            exit( 1 );
        }
        $cleanup_report_code = str_replace( $cleanup_report_from, $cleanup_report_to, $cleanup_report_code );
    }
    file_put_contents( $cleanup_report_path, $cleanup_report_code );
}
echo "Prefix patches applied.\n";
