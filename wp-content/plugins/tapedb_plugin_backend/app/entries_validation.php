<?php
namespace Tapedb\App\Entries;

if (!defined('ABSPATH')) { exit; }

class Validator {
    public static function wrap(callable $handler, $action) {
        return function($request) use ($handler, $action) {
            $errors = self::validate($request, $action);
            if (!empty($errors)) {
                return new \WP_REST_Response([
                    'status'  => false,
                    'message' => 'Validation failed',
                    'errors'  => $errors,
                ], 422);
            }
            return call_user_func($handler, $request);
        };
    }

    private static function read_data($request): array {
        $data = $request->get_json_params();
        if (!is_array($data) || empty($data)) $data = $request->get_body_params();
        if (!is_array($data) || empty($data)) $data = $request->get_params();
        if (!is_array($data) || empty($data)) $data = $_POST ?: [];
        return is_array($data) ? $data : [];
    }

    private static function validate($request, $action) {
        $errors = [];
        $data   = self::read_data($request);

        $len = function($v) { return is_string($v) ? mb_strlen($v) : 0; };
        $isBoolish = function($v) {
            return ($v === 0 || $v === 1 || $v === '0' || $v === '1' || $v === true || $v === false);
        };

        $caps = [
            'name'        => 191,
            'title'       => 191,
            'year'        => 50,
            'distributor' => 255,
            'qa_checked'  => 10,
            'guard_color' => 255,
            'upc'         => 255,
            'img1'        => 500,
            'img2'        => 500,
            'img3'        => 500,
            'img4'        => 500,
            'img5'        => 500,
            'img6'        => 500,
        ];

        switch ($action) {
            case 'store':
                if (!isset($data['name']) || trim((string)$data['name']) === '') {
                    $errors['name'] = 'name is required';
                }
                foreach ($caps as $field => $max) {
                    if (strpos($field, 'img') === 0 && isset($_FILES[$field])) continue;
                    if (array_key_exists($field, $data) && is_string($data[$field]) && $len($data[$field]) > $max) {
                        $errors[$field] = "$field must be at most $max characters";
                    }
                }
                if (array_key_exists('approved', $data) && !$isBoolish($data['approved'])) {
                    $errors['approved'] = 'approved must be boolean/0/1';
                }
                break;

            case 'update':
                foreach ($caps as $field => $max) {
                    if (strpos($field, 'img') === 0 && isset($_FILES[$field])) continue;
                    if (array_key_exists($field, $data) && is_string($data[$field]) && $len($data[$field]) > $max) {
                        $errors[$field] = "$field must be at most $max characters";
                    }
                }
                if (array_key_exists('approved', $data) && !$isBoolish($data['approved'])) {
                    $errors['approved'] = 'approved must be boolean/0/1';
                }
                break;

            default:
                // index/show/destroy — no-op
                break;
        }
        return $errors;
    }
}
