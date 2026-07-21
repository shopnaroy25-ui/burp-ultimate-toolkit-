<?php
namespace Burp\Core;

class RequestParser {
    public function parse($raw) {
        if (is_string($raw)) {
            return $this->parseRawString($raw);
        }
        
        if (is_array($raw)) {
            return $this->parseArray($raw);
        }
        
        return null;
    }
    
    private function parseRawString($raw) {
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
        $port = $headers['X-Forwarded-Port'] ?? ($scheme === 'https' ? 443 : 80);
        
        return [
            'method' => $method,
            'scheme' => $scheme,
            'host' => $host,
            'port' => intval($port),
            'path' => $path,
            'version' => $version,
            'headers' => $headers,
            'body' => trim($body)
        ];
    }
    
    private function parseArray($data) {
        return [
            'method' => $data['method'] ?? 'GET',
            'scheme' => $data['scheme'] ?? 'http',
            'host' => $data['host'] ?? 'localhost',
            'port' => intval($data['port'] ?? 80),
            'path' => $data['path'] ?? '/',
            'version' => $data['version'] ?? 'HTTP/1.1',
            'headers' => $data['headers'] ?? [],
            'body' => $data['body'] ?? ''
        ];
    }
    
    public function reconstruct($parsed) {
        $lines = [];
        $lines[] = $parsed['method'] . ' ' . $parsed['path'] . ' ' . $parsed['version'];
        
        foreach ($parsed['headers'] as $key => $value) {
            $lines[] = $key . ': ' . $value;
        }
        
        $lines[] = '';
        $lines[] = $parsed['body'];
        
        return implode("\n", $lines);
    }
}
