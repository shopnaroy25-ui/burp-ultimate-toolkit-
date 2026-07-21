<?php
namespace Burp\Core;

class ResponseAnalyzer {
    private $patterns = [];
    
    public function __construct() {
        $this->patterns = $this->loadPatterns();
    }
    
    public function analyze($response) {
        $results = [
            'status_code' => $this->extractStatusCode($response),
            'headers' => $this->extractHeaders($response),
            'body' => $this->extractBody($response),
            'size' => strlen($response),
            'time' => $this->extractTime($response),
            'content_type' => $this->detectContentType($response),
            'security_headers' => $this->checkSecurityHeaders($response),
            'vulnerabilities' => $this->scanForVulnerabilities($response)
        ];
        
        return $results;
    }
    
    private function extractStatusCode($response) {
        if (preg_match('/HTTP\/\d\.\d (\d+)/', $response, $matches)) {
            return intval($matches[1]);
        }
        return 0;
    }
    
    private function extractHeaders($response) {
        $headers = [];
        $lines = explode("\n", $response);
        $isHeader = true;
        
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $isHeader = false;
                continue;
            }
            
            if ($isHeader && strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    private function extractBody($response) {
        $pos = strpos($response, "\r\n\r\n");
        if ($pos === false) {
            $pos = strpos($response, "\n\n");
        }
        
        if ($pos !== false) {
            return substr($response, $pos + 4);
        }
        
        return '';
    }
    
    private function extractTime($response) {
        if (preg_match('/X-Response-Time: (\d+)/i', $response, $matches)) {
            return intval($matches[1]) . 'ms';
        }
        return 'unknown';
    }
    
    private function detectContentType($response) {
        if (stripos($response, 'Content-Type: application/json') !== false) {
            return 'json';
        }
        if (stripos($response, 'Content-Type: text/html') !== false) {
            return 'html';
        }
        if (stripos($response, 'Content-Type: application/xml') !== false) {
            return 'xml';
        }
        if (stripos($response, 'Content-Type: text/plain') !== false) {
            return 'text';
        }
        return 'unknown';
    }
    
    private function checkSecurityHeaders($response) {
        $headers = $this->extractHeaders($response);
        $security = [];
        
        $securityHeaders = [
            'Strict-Transport-Security' => 'HSTS',
            'X-Frame-Options' => 'Clickjacking Protection',
            'X-XSS-Protection' => 'XSS Protection',
            'X-Content-Type-Options' => 'MIME Sniffing Protection',
            'Content-Security-Policy' => 'CSP',
            'Referrer-Policy' => 'Referrer Policy'
        ];
        
        foreach ($securityHeaders as $header => $name) {
            $security[$name] = isset($headers[$header]);
        }
        
        return $security;
    }
    
    private function scanForVulnerabilities($response) {
        $vulns = [];
        $body = $this->extractBody($response);
        
        // Check for error messages
        $errorPatterns = [
            'Warning:' => 'PHP Warnings',
            'Notice:' => 'PHP Notices',
            'Fatal error' => 'PHP Fatal Errors',
            'SQL' => 'SQL Errors',
            'mysql_' => 'MySQL Errors',
            'ORA-' => 'Oracle Errors',
            'PostgreSQL' => 'PostgreSQL Errors'
        ];
        
        foreach ($errorPatterns as $pattern => $type) {
            if (stripos($body, $pattern) !== false) {
                $vulns[] = [
                    'type' => 'Information Disclosure',
                    'details' => $type . ' detected',
                    'severity' => 'MEDIUM'
                ];
                break;
            }
        }
        
        // Check for sensitive data
        $sensitive = [
            'password' => 'Password field',
            'token' => 'Token',
            'secret' => 'Secret',
            'apikey' => 'API Key',
            'authorization' => 'Authorization header'
        ];
        
        foreach ($sensitive as $pattern => $desc) {
            if (stripos($body, $pattern) !== false) {
                $vulns[] = [
                    'type' => 'Sensitive Data Exposure',
                    'details' => $desc . ' found in response',
                    'severity' => 'HIGH'
                ];
                break;
            }
        }
        
        return $vulns;
    }
    
    private function loadPatterns() {
        return json_decode(file_get_contents(__DIR__ . '/../patterns.json'), true) ?? [];
    }
}
