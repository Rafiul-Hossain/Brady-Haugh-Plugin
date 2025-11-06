<?php
namespace Tapedb\App\Entries;

if (!defined('ABSPATH')) { exit; }

class TableCreator {
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'esp_entries';
    }

    public static function create_table() {
        global $wpdb;

        // Ensure dbDelta is available
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset_collate = $wpdb->get_charset_collate();
        $table = self::table_name();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT NULL,
            name VARCHAR(191) NOT NULL,
            title VARCHAR(191) NULL,
            year VARCHAR(50) NULL,
            distributor VARCHAR(255) NULL,
            case_desc TEXT NULL,
            seal TEXT NULL,
            sticker TEXT NULL,
            watermarks TEXT NULL,
            etching TEXT NULL,
            notes TEXT NULL,
            qa_checked VARCHAR(10) NULL,
            guard_color VARCHAR(255) NULL,
            upc VARCHAR(255) NULL,
            img1 VARCHAR(255) NULL,
            img2 VARCHAR(255) NULL,
            img3 VARCHAR(255) NULL,
            img4 VARCHAR(255) NULL,
            img5 VARCHAR(255) NULL,
            img6 VARCHAR(255) NULL,
            approved TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id_idx (user_id)
        ) $charset_collate;";

        dbDelta($sql);
    }






    // --- table drop ---
    // public static function drop_table() {
    //     global $wpdb;
    //     $wpdb->query("DROP TABLE IF EXISTS " . self::table_name());
    // }

    // public static function truncate_table() {
    //     global $wpdb;
    //     $wpdb->query("TRUNCATE TABLE " . self::table_name());
    // }
}