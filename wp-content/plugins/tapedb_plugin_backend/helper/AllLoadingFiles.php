<?php
namespace Tapedb\Helper;

if (!defined('ABSPATH')) { exit; }

$base = rtrim(TAPEDB_PLUGIN_PATH, '/\\') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

$files = [
    $base . 'TableCreator.php',
    $base . 'entries_validation.php',
    $base . 'entries_controller.php',
    $base . 'entries_routes.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('tapedb_plugin_backend: missing file ' . $file);
        }
    }
}
