<?php
namespace Burp\Middleware;

class RateLimiter {
    private static $redis;
    
    public static function check($ip, $route) {
        $key = "rate_limit:{$ip}:{$route}";
        $limit = intval($_ENV['RATE_LIMIT_REQUESTS'] ?? 100);
        $time = intval($_ENV['RATE_LIMIT_TIME'] ?? 60);
        
        if (!self::$redis) {
            self::$redis = new \Redis();
            self::$redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        }
        
        $current = self::$redis->get($key);
        if ($current !== false && $current >= $limit) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            exit;
        }
        
        self::$redis->incr($key);
        self::$redis->expire($key, $time);
    }
}
