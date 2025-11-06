<?php
// zip-style: procedural, tiny
if (!defined('ABSPATH')) { exit; }

use Tapedb\App\Entries\TableCreator;

// register activation
register_activation_hook(TAPEDB_MAIN_FILE, function (): void {
    // dbDelta for create_table()
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    if (class_exists(TableCreator::class)) {
        TableCreator::create_table();
    }

    // optional: remember version
    update_option('tapedb_version', TAPEDB_PLUGIN_VERSION);

    // refresh routes/permalinks
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
});

// register deactivation
register_deactivation_hook(TAPEDB_MAIN_FILE, function (): void {
    // minimal — keep data. (you can add truncate/drop helpers later if desired)
    if (function_exists('flush_rewrite_rules')) {
        flush_rewrite_rules();
    }
});
