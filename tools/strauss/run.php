<?php
/**
 * Runs Strauss from the plugin root (composer prefix-namespaces).
 *
 * Strauss's own launcher loads ./vendor/autoload.php when run from the plugin root, which is the plugin's autoloader,
 * not Strauss's. Load Strauss's first so its classes resolve; everything else is Strauss as shipped.
 */
set_error_handler( static function () { return true; }, E_DEPRECATED | E_USER_DEPRECATED );
require __DIR__ . '/vendor/autoload.php';
restore_error_handler();

$cleanup_report_strauss = \Composer\InstalledVersions::getPrettyVersion( 'brianhenryie/strauss' );
( new BrianHenryIE\Strauss\Console\Application( (string) $cleanup_report_strauss ) )->run();
