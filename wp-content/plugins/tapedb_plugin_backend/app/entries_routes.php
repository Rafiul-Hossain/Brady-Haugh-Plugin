<?php
namespace Tapedb\App\Entries;

if (!defined('ABSPATH')) { exit; }

const TAPEDB_NS = 'tapedb/v2';

// admin-only helper
function tapedb_admin_only() {
    return current_user_can('manage_options');
}

function tapedb_register_entries_routes() {
    // /entries (public GET, admin POST)
    register_rest_route(TAPEDB_NS, '/entries', [
        [
            'methods'  => 'GET',
            'callback' => [EntriesController::class, 'index'],
            'permission_callback' => '__return_true',
            'args' => [
                'search'     => ['description' => 'AND across tokens, OR across fields'],
                'start_year' => ['description' => 'inclusive start year'],
                'end_year'   => ['description' => 'inclusive end year'],
                'page'       => ['description' => 'page number'],
                'per_page'   => ['description' => 'items per page'],
            ],
        ],
        [
            'methods'  => 'POST',
            'callback' => Validator::wrap([EntriesController::class, 'store'], 'store'),
            'permission_callback' => __NAMESPACE__ . '\\tapedb_admin_only',
        ],
    ]);

    // /entries/{id} (public GET, admin PUT/PATCH/POST/DELETE)
    register_rest_route(TAPEDB_NS, '/entries/(?P<id>\\d+)', [
        [
            'methods'  => 'GET',
            'callback' => [EntriesController::class, 'show'],
            'permission_callback' => '__return_true',
        ],
        [
            // allow POST for clients that can’t send PUT with multipart
            'methods'  => 'PUT,PATCH,POST',
            'callback' => Validator::wrap([EntriesController::class, 'update'], 'update'),
            'permission_callback' => __NAMESPACE__ . '\\tapedb_admin_only',
        ],
        [
            'methods'  => 'DELETE',
            'callback' => [EntriesController::class, 'destroy'],
            'permission_callback' => __NAMESPACE__ . '\\tapedb_admin_only',
        ],
    ]);
}

add_action('rest_api_init', __NAMESPACE__ . '\\tapedb_register_entries_routes');
