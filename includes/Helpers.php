<?php
if (!defined('ABSPATH')) exit;

class YDZ_Helpers {

    public static function get_uri_path() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return trim(parse_url($uri, PHP_URL_PATH), '/');
    }

    public static function get_query($key, $default = null) {
        return $_GET[$key] ?? $default;
    }

    public static function redirect($url) {
        wp_redirect($url);
        exit;
    }

}