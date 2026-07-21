<?php
namespace Burp\Middleware;

class AuthMiddleware {
    public static function validate() {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Token required']);
            exit;
        }
        
        // Remove Bearer prefix
        $token = str_replace('Bearer ', '', $token);
        
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET'], ['HS256']);
            return (array)$decoded;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized: Invalid token']);
            exit;
        }
    }
}
