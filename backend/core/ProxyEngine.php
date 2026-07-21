<?php
namespace Burp\Core;

use Redis;
use Exception;

class ProxyEngine {
    private $config;
    private $redis;
    private $sslManager;
    private $logger;
    private $sessionManager;
    
    public function __construct() {
        $this->config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
        $this->redis = new Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->sslManager = new SSLManagerPro();
        $this->logger = Logger::getInstance();
        $this->sessionManager = SessionManager::getInstance();
    }
    
    public function capture($data) {
        try {
            // Parse incoming request
            $request = $this->parseRequest($data);
            
            // Check if should intercept
            if ($this->shouldIntercept($request)) {
                $id = uniqid('req_');
                $request['id'] = $id;
                $request['timestamp'] = date('Y-m-d H:i:s');
                
                // Store in Redis
                $this->redis->setex("request:$id", 3600, json_encode($request));
                $this->redis->lpush('requests', $id);
                
                // Log
                $this->logger->info("Request intercepted", ['id' => $id, 'host' => $request['host']]);
                
                // Broadcast via WebSocket
                $this->broadcast([
                    'type' => 'new_request',
                    'data' => $request,
                    'id' => $id
                ]);
                
                return json_encode([
                    'status' => 'intercepted',
                    'id' => $id,
                    'request' => $request
                ]);
            }
            
            // Forward if not intercepted
            return $this->forwardRequest($request);
            
        } catch (Exception $e) {
            $this->logger->error('Proxy capture error', ['error' => $e->getMessage()]);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function getCaptured() {
        $ids = $this->redis->lrange('requests', 0, 99);
        $requests = [];
        foreach ($ids as $id) {
            $data = $this->redis->get("request:$id");
            if ($data) {
                $requests[] = json_decode($data, true);
            }
        }
        return json_encode(['requests' => $requests]);
    }
    
    public function forwardRequest($data) {
        $request = is_string($data) ? json_decode($data, true) : $data;
        
        if (!isset($request['id'])) {
            $request['id'] = uniqid('fwd_');
        }
        
        // Build URL
        $url = ($request['scheme'] ?? 'http') . '://' . $request['host'] . ($request['port'] ? ':' . $request['port'] : '') . ($request['path'] ?? '/');
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['request_timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Method
        $method = strtoupper($request['method'] ?? 'GET');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Headers
        $headers = [];
        if (isset($request['headers']) && is_array($request['headers'])) {
            foreach ($request['headers'] as $key => $value) {
                if (!in_array(strtolower($key), ['host', 'content-length'])) {
                    $headers[] = "$key: $value";
                }
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Body
        if (isset($request['body']) && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
        }
        
        // Execute
        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return json_encode(['error' => $error]);
        }
        
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Parse response
        $headerSize = $info['header_size'];
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        $result = [
            'status' => 'forwarded',
            'id' => $request['id'],
            'forwarded_to' => $url,
            'status_code' => $info['http_code'],
            'headers' => $this->parseHeaders($responseHeaders),
            'body' => $responseBody,
            'size' => $info['size_download'],
            'time' => round(($endTime - $startTime) * 1000, 2) . 'ms'
        ];
        
        // Store history
        $this->redis->setex("response:{$request['id']}", 3600, json_encode($result));
        
        return json_encode($result);
    }
    
    public function toggleIntercept($data) {
        $enabled = $data['enabled'] ?? false;
        $this->config['intercept_enabled'] = $enabled;
        file_put_contents(__DIR__ . '/../config.json', json_encode($this->config, JSON_PRETTY_PRINT));
        
        return json_encode(['status' => 'success', 'intercept_enabled' => $enabled]);
    }
    
    public function dropRequest($data) {
        $id = $data['id'] ?? null;
        if ($id) {
            $this->redis->del("request:$id");
            $this->redis->lrem('requests', 0, $id);
            return json_encode(['status' => 'dropped', 'id' => $id]);
        }
        return json_encode(['error' => 'Request ID required']);
    }
    
    private function parseRequest($data) {
        $request = [];
        
        // Method
        $request['method'] = $data['method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Host
        $request['host'] = $data['host'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $request['port'] = $data['port'] ?? ($_SERVER['SERVER_PORT'] ?? 80);
        $request['scheme'] = $data['scheme'] ?? ($_SERVER['HTTPS'] ? 'https' : 'http');
        $request['path'] = $data['path'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        
        // Headers
        $request['headers'] = $data['headers'] ?? $this->getAllHeaders();
        
        // Body
        $request['body'] = $data['body'] ?? file_get_contents('php://input');
        
        // Client info
        $request['client_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $request['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        return $request;
    }
    
    private function shouldIntercept($request) {
        // Check if intercept is enabled
        if (!($this->config['intercept_enabled'] ?? true)) {
            return false;
        }
        
        // Check exclude hosts
        foreach ($this->config['exclude_hosts'] ?? [] as $exclude) {
            if (strpos($request['host'], $exclude) !== false) {
                return false;
            }
        }
        
        // Check include methods
        if (!empty($this->config['include_methods'])) {
            if (!in_array($request['method'], $this->config['include_methods'])) {
                return false;
            }
        }
        
        return true;
    }
    
    private function parseHeaders($headerString) {
        $headers = [];
        $lines = explode("\n", $headerString);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        return $headers;
    }
    
    private function getAllHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
    
    private function broadcast($data) {
        // Broadcast via WebSocket (Swoole)
        global $wss;
        if ($wss) {
            foreach ($wss->connections as $fd) {
                $wss->push($fd, json_encode($data));
            }
        }
    }
}
