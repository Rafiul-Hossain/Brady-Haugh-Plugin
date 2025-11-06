<?php
/**
 * Plugin Name: tapedb_plugin_backend
 * Description: Tape DB backend (module-style). Routes under tapedb/v2.
 * Author: rafiul
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) { exit; }

// --- constants (keep main clean) ---
define('TAPEDB_PLUGIN_VERSION', '1.0.0');
define('TAPEDB_MAIN_FILE', __FILE__);
define('TAPEDB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TAPEDB_PLUGIN_URL', plugin_dir_url(__FILE__));


require_once TAPEDB_PLUGIN_PATH . 'app/TableCreator.php';
require_once TAPEDB_PLUGIN_PATH . 'helper/ActivationHook.php';
require_once TAPEDB_PLUGIN_PATH . 'helper/AllLoadingFiles.php';

