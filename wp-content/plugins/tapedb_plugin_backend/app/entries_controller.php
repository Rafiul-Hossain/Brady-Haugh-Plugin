<?php
namespace Tapedb\App\Entries;

if (!defined('ABSPATH')) { exit; }

class EntriesController
{
    // ---------- upload helpers ----------

    private static function save_via_media_handle_upload(string $field) {
        if (empty($_FILES[$field]) || empty($_FILES[$field]['name'])) {
            return new \WP_Error('no_file', "No file in \$_FILES[$field]");
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_id = \media_handle_upload($field, 0);
        if (is_wp_error($attach_id)) return $attach_id;
        $url = \wp_get_attachment_url($attach_id);
        return $url ?: new \WP_Error('no_url', 'Could not get attachment URL');
    }

    private static function save_via_handle_upload(array $file) {
        if (empty($file) || empty($file['name'])) return new \WP_Error('no_file', 'No file array provided');
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_failed', 'Upload error: ' . (int)$file['error']);
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $moved = \wp_handle_upload($file, ['test_form' => false]);
        if (empty($moved['file'])) {
            $msg = isset($moved['error']) ? $moved['error'] : 'Upload failed';
            return new \WP_Error('upload_failed', $msg);
        }
        $filetype   = \wp_check_filetype(basename($moved['file']), null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name(pathinfo($moved['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id = \wp_insert_attachment($attachment, $moved['file']);
        if (is_wp_error($attach_id)) return $attach_id;
        $meta = \wp_generate_attachment_metadata($attach_id, $moved['file']);
        \wp_update_attachment_metadata($attach_id, $meta);
        $url = \wp_get_attachment_url($attach_id);
        return $url ?: new \WP_Error('no_url', 'Could not get attachment URL');
    }

    private static function attachment_id_from_url(?string $url): int {
        if (!$url) return 0;
        if (!function_exists('attachment_url_to_postid')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        return (int) attachment_url_to_postid($url);
    }

    private static function delete_attachment_by_url(?string $url): bool {
        $aid = self::attachment_id_from_url($url);
        return $aid ? (bool) wp_delete_attachment($aid, true) : true;
    }

    /**
     * Collect uploads for img1..img6 from $_FILES or request arrays.
     * Skips blank slots; can auto-map unnamed fields into img1..img6.
     */
    private static function collect_uploaded_images($request): array {
        $files_global = (!empty($_FILES) && is_array($_FILES)) ? $_FILES : [];
        $files_req    = $request->get_file_params();
        $files_req    = (is_array($files_req) && !empty($files_req)) ? $files_req : [];

        $hasReal = function($e): bool {
            if (!is_array($e) || !isset($e['name'])) return false;
            if (!is_array($e['name'])) {
                $name  = trim((string)($e['name'] ?? ''));
                $error = (int)($e['error'] ?? 0);
                return ($name !== '' && $error !== UPLOAD_ERR_NO_FILE);
            }
            $count = count($e['name']);
            for ($i = 0; $i < $count; $i++) {
                $name  = trim((string)($e['name'][$i] ?? ''));
                $error = (int)($e['error'][$i] ?? 0);
                if ($name !== '' && $error !== UPLOAD_ERR_NO_FILE) return true;
            }
            return false;
        };
        $expand = function($e): array {
            if (!is_array($e) || !isset($e['name'])) return [];
            if (!is_array($e['name'])) {
                $name  = (string)($e['name'] ?? '');
                $error = (int)($e['error'] ?? 0);
                if ($name === '' || $error === UPLOAD_ERR_NO_FILE) return [];
                return [ $e ];
            }
            $out = [];
            $count = count($e['name']);
            for ($i = 0; $i < $count; $i++) {
                $name  = (string)($e['name'][$i] ?? '');
                $error = (int)($e['error'][$i] ?? 0);
                if ($name === '' || $error === UPLOAD_ERR_NO_FILE) continue;
                $out[] = [
                    'name'     => $e['name'][$i]     ?? '',
                    'type'     => $e['type'][$i]     ?? '',
                    'tmp_name' => $e['tmp_name'][$i] ?? '',
                    'error'    => $error,
                    'size'     => $e['size'][$i]     ?? 0,
                ];
            }
            return $out;
        };

        $preferred = ['img1','img2','img3','img4','img5','img6'];
        $pairs = [];

        foreach ($preferred as $key) {
            if (!empty($files_global[$key]) && $hasReal($files_global[$key])) {
                foreach ($expand($files_global[$key]) as $one) { $pairs[] = [$key, $one]; }
            } elseif (!empty($files_req[$key]) && $hasReal($files_req[$key])) {
                foreach ($expand($files_req[$key]) as $one) { $pairs[] = [$key, $one]; }
            }
        }

        if (empty($pairs)) {
            $any = [];
            foreach ($files_global as $k => $v) {
                if ($hasReal($v)) foreach ($expand($v) as $one) { $any[] = $one; }
            }
            if (empty($any)) {
                foreach ($files_req as $k => $v) {
                    if ($hasReal($v)) foreach ($expand($v) as $one) { $any[] = $one; }
                }
            }
            for ($i = 0; $i < min(6, count($any)); $i++) {
                $pairs[] = [$preferred[$i], $any[$i]];
            }
        }

        $out = [];
        foreach ($pairs as [$field, $file]) {
            if (!empty($files_global[$field])) {
                $res = self::save_via_media_handle_upload($field);
            } else {
                $res = self::save_via_handle_upload($file);
            }
            if (is_wp_error($res)) {
                return ['__error' => $res];
            }
            $out[$field] = $res; // URL
        }
        return $out;
    }

    private static function read_body_params($request): array {
        $data = $request->get_json_params();
        if (!is_array($data) || empty($data)) $data = $request->get_body_params();
        if (!is_array($data) || empty($data)) $data = $request->get_params();
        if (!is_array($data) || empty($data)) $data = $_POST ?: [];
        return is_array($data) ? $data : [];
    }

    // ---------- reads (public) ----------

    public static function index($request) {
        global $wpdb;
        $table = TableCreator::table_name();

        $page     = max(1, intval($request->get_param('page') ?: 1));
        $per_page = max(1, intval($request->get_param('per_page') ?: 10));
        $offset   = ($page - 1) * $per_page;

        $search     = trim((string)($request->get_param('search') ?? ''));
        $start_year = trim((string)($request->get_param('start_year') ?? ''));
        $end_year   = trim((string)($request->get_param('end_year') ?? ''));

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $norm   = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
            $tokens = preg_split('/\s+/', $norm, -1, PREG_SPLIT_NO_EMPTY);
            $fields = ['name','title','distributor','case_desc','seal','sticker','watermarks','etching','notes','guard_color','upc'];
            $andClauses = [];
            foreach ($tokens as $tok) {
                $like    = '%' . $wpdb->esc_like($tok) . '%';
                $orParts = [];
                foreach ($fields as $f) {
                    $orParts[] = "LOWER($f) LIKE %s";
                    $params[]  = $like;
                }
                $andClauses[] = '(' . implode(' OR ', $orParts) . ')';
            }
            if (!empty($andClauses)) $where[] = implode(' AND ', $andClauses);
        }

        if ($start_year !== '') {
            if (preg_match('/^\d{1,4}$/', $start_year)) {
                $where[] = 'CAST(year AS UNSIGNED) >= %d'; $params[] = (int)$start_year;
            } else { $where[] = 'year >= %s'; $params[] = $start_year; }
        }
        if ($end_year !== '') {
            if (preg_match('/^\d{1,4}$/', $end_year)) {
                $where[] = 'CAST(year AS UNSIGNED) <= %d'; $params[] = (int)$end_year;
            } else { $where[] = 'year <= %s'; $params[] = $end_year; }
        }

        $whereSql  = implode(' AND ', $where);
        $count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE $whereSql", $params);
        $total     = (int)$wpdb->get_var($count_sql);

        $data_sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE $whereSql ORDER BY id DESC LIMIT %d OFFSET %d",
            array_merge($params, [(int)$per_page, (int)$offset])
        );
        $rows = $wpdb->get_results($data_sql, ARRAY_A);

        return new \WP_REST_Response([
            'status'       => true,
            'message'      => 'Entries fetched successfully.',
            'data'         => $rows,
            'search'       => $search,
            'current_page' => $page,
            'per_page'     => $per_page,
            'total_pages'  => (int) ceil($total / $per_page),
        ], 200);
    }

    public static function show($request) {
        global $wpdb;
        $table = TableCreator::table_name();
        $id = intval($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) return new \WP_REST_Response(['status' => false, 'message' => 'Not found'], 404);
        return new \WP_REST_Response([
            'status'  => true,
            'message' => 'Entry fetched successfully.',
            'data'    => $row,
        ], 200);
    }

    // ---------- writes (admin-only; routes also enforce) ----------

    public static function store($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response(['status' => false, 'message' => 'Forbidden'], 403);
        }
        global $wpdb;
        $table = TableCreator::table_name();

        $data     = self::read_body_params($request);
        $uploaded = self::collect_uploaded_images($request);
        if (isset($uploaded['__error']) && is_wp_error($uploaded['__error'])) {
            return new \WP_REST_Response(['status' => false, 'message' => $uploaded['__error']->get_error_message()], 400);
        }

        $allowed = [
            'user_id','name','title','year','distributor','case_desc','seal','sticker',
            'watermarks','etching','notes','qa_checked','guard_color','upc',
            'img1','img2','img3','img4','img5','img6','approved'
        ];
        $insert = [];
        foreach ($allowed as $k) { if (array_key_exists($k, $data) && $data[$k] !== '') $insert[$k] = $data[$k]; }
        foreach ($uploaded as $k => $v) { $insert[$k] = $v; }
        if (empty($insert['user_id'])) $insert['user_id'] = get_current_user_id();

        $ok = $wpdb->insert($table, $insert);
        if ($ok === false) return new \WP_REST_Response(['status' => false, 'message' => 'Insert failed'], 500);

        return new \WP_REST_Response([
            'status'  => true,
            'message' => 'Entry created successfully.',
            'id'      => (int)$wpdb->insert_id,
        ], 201);
    }

    public static function update($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response(['status' => false, 'message' => 'Forbidden'], 403);
        }
        global $wpdb;
        $table = TableCreator::table_name();

        $id       = intval($request['id']);
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$existing) return new \WP_REST_Response(['status' => false, 'message' => 'Not found'], 404);

        $uploaded = self::collect_uploaded_images($request);
        if (isset($uploaded['__error'])) {
            return new \WP_REST_Response(['status' => false, 'message' => $uploaded['__error']->get_error_message()], 400);
        }
        $replaced_fields = array_keys($uploaded);

        $data = self::read_body_params($request);

        $allowed = [
            'user_id','name','title','year','distributor','case_desc','seal','sticker',
            'watermarks','etching','notes','qa_checked','guard_color','upc',
            'img1','img2','img3','img4','img5','img6','approved'
        ];
        $update = [];
        foreach ($allowed as $k) { if (array_key_exists($k, $data) && $data[$k] !== '') $update[$k] = $data[$k]; }
        foreach ($uploaded as $k => $v) { $update[$k] = $v; }

        if (empty($update)) return new \WP_REST_Response(['status' => false, 'message' => 'No fields to update'], 400);

        $ok = $wpdb->update($table, $update, ['id' => $id]);
        if ($ok === false) return new \WP_REST_Response(['status' => false, 'message' => 'Update failed'], 500);

        // delete old attachments that were replaced
        if (!empty($replaced_fields)) {
            foreach ($replaced_fields as $field) {
                $old_url = $existing[$field] ?? null;
                $new_url = $update[$field]   ?? null;
                if ($old_url && $new_url && $old_url !== $new_url) {
                    self::delete_attachment_by_url($old_url);
                }
            }
        }

        return new \WP_REST_Response([
            'status'  => true,
            'message' => 'Entry updated successfully.',
            'id'      => $id,
        ], 200);
    }

    public static function destroy($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response(['status' => false, 'message' => 'Forbidden'], 403);
        }
        global $wpdb;
        $table = TableCreator::table_name();

        $id  = intval($request['id']);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
        if (!$row) return new \WP_REST_Response(['status' => false, 'message' => 'Not found'], 404);

        $ok = $wpdb->delete($table, ['id' => $id]);
        if ($ok === false) return new \WP_REST_Response(['status' => false, 'message' => 'Delete failed'], 500);

        foreach (['img1','img2','img3','img4','img5','img6'] as $field) {
            if (!empty($row[$field]) && is_string($row[$field])) {
                self::delete_attachment_by_url($row[$field]);
            }
        }

        return new \WP_REST_Response([
            'status'  => true,
            'message' => 'Entry and associated images deleted successfully.',
        ], 200);
    }
}
