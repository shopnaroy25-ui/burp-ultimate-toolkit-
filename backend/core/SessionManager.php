<?php
namespace Burp\Core;

class SessionManager {
    private static $instance = null;
    private $redis;
    private $config;
    private $token = null;
    
    private function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
        $this->token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function createSession($data) {
        $id = uniqid('session_');
        $session = [
            'id' => $id,
            'created' => date('Y-m-d H:i:s'),
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->redis->setex("session:$id", 86400, json_encode($session));
        return $id;
    }
    
    public function getSession($id) {
        $data = $this->redis->get("session:$id");
        if ($data) {
            return json_decode($data, true);
        }
        return null;
    }
    
    public function validateToken($token) {
        try {
            $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET'], ['HS256']);
            return (array)$decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function generateToken($data) {
        $payload = array_merge($data, [
            'iat' => time(),
            'exp' => time() + 86400 // 24 hours
        ]);
        
        return \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }
}
