<?php
/**
 * API RESPONSE HELPER - OPTIMIZED FOR MULTI-THREADING
 * Chuẩn hóa response cho tất cả API với tối ưu tốc độ
 */

class ApiResponse {
    
    public static function init() {
        // Performance headers
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        
        // Enable output buffering for faster response
        if (!ob_get_level()) {
            ob_start('ob_gzhandler');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    public static function success($data = [], $message = null) {
        $response = ['success' => true] + $data;
        if ($message) $response['message'] = $message;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function error($message, $code = 400, $data = []) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message] + $data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function param($key, $default = '') {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }
    
    public static function requireParams($params) {
        foreach ($params as $p) {
            if (empty(self::param($p))) {
                self::error("Missing required parameter: $p");
            }
        }
    }
}
