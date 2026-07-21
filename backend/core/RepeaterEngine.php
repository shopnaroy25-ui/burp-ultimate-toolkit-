<?php
namespace Burp\Core;

class RepeaterEngine {
    private $redis;
    private $logger;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->logger = Logger::getInstance();
    }
    
    public function send($data) {
        try {
            $request = $data['request'] ?? '';
            $headers = $data['headers'] ?? [];
            $method = $data['method'] ?? 'GET';
            $url = $data['url'] ?? '';
            
            if (empty($url)) {
                return json_encode(['error' => 'URL is required']);
            }
            
            // Parse request if raw
            if (!empty($request) && empty($headers)) {
                $parsed = $this->parseRawRequest($request);
                $url = $parsed['url'] ?? $url;
                $headers = $parsed['headers'] ?? [];
                $method = $parsed['method'] ?? $method;
                $body = $parsed['body'] ?? '';
            } else {
                $body = $data['body'] ?? '';
            }
            
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            
            // Headers
            $headerArray = [];
            foreach ($headers as $key => $value) {
                if (!in_array(strtolower($key), ['host', 'content-length'])) {
                    $headerArray[] = "$key: $value";
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            
            // Body
            if (!empty($body) && $method !== 'GET') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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
                'id' => uniqid('rep_'),
                'timestamp' => date('Y-m-d H:i:s'),
                'request' => [
                    'url' => $url,
                    'method' => $method,
                    'headers' => $headers,
                    'body' => $body
                ],
                'response' => [
                    'status_code' => $info['http_code'],
                    'headers' => $this->parseHeaders($responseHeaders),
                    'body' => $responseBody,
                    'size' => $info['size_download'],
                    'time' => round(($endTime - $startTime) * 1000, 2) . 'ms'
                ]
            ];
            
            // Store history
            $this->redis->lpush('repeater_history', json_encode($result));
            $this->redis->ltrim('repeater_history', 0, 99);
            
            $this->logger->info('Repeater request sent', ['url' => $url, 'status' => $info['http_code']]);
            
            return json_encode($result);
            
        } catch (\Exception $e) {
            $this->logger->error('Repeater error', ['error' => $e->getMessage()]);
            return json_encode(['error' => $e->getMessage()]);
        }
    }
    
    public function getHistory() {
        $history = $this->redis->lrange('repeater_history', 0, 99);
        $items = [];
        foreach ($history as $item) {
            $items[] = json_decode($item, true);
        }
        return json_encode(['history' => $items]);
    }
    
    public function delete($data) {
        $id = $data['id'] ?? null;
        if ($id) {
            $history = $this->redis->lrange('repeater_history', 0, -1);
            $newHistory = [];
            foreach ($history as $item) {
                $parsed = json_decode($item, true);
                if ($parsed['id'] !== $id) {
                    $newHistory[] = $item;
                }
            }
            $this->redis->del('repeater_history');
            foreach ($newHistory as $item) {
                $this->redis->rpush('repeater_history', $item);
            }
            return json_encode(['status' => 'deleted', 'id' => $id]);
        }
        return json_encode(['error' => 'ID required']);
    }
    
    private function parseRawRequest($raw) {
        $lines = explode("\n", str_replace("\r\n", "\n", $raw));
        $firstLine = array_shift($lines);
        $parts = explode(' ', $firstLine);
        
        $method = $parts[0] ?? 'GET';
        $path = $parts[1] ?? '/';
        $version = $parts[2] ?? 'HTTP/1.1';
        
        $headers = [];
        $body = '';
        $isBody = false;
        
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($isBody) {
                $body .= $line . "\n";
                continue;
            }
            
            if (trim($line) === '') {
                $isBody = true;
                continue;
            }
            
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        $host = $headers['Host'] ?? 'localhost';
        $scheme = isset($headers['X-Forwarded-Proto']) ? $headers['X-Forwarded-Proto'] : 'http';
        $url = $scheme . '://' . $host . $path;
        
        return [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => trim($body),
            'version' => $version
        ];
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
}
