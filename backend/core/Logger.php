<?php
namespace Burp\Core;

class Logger {
    private static $instance = null;
    private $logFile;
    private $level;
    private $levels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    private function __construct() {
        $this->logFile = $_ENV['LOG_PATH'] ?? __DIR__ . '/../storage/logs/app.log';
        $this->level = $_ENV['LOG_LEVEL'] ?? 'info';
        
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    public function critical($message, $context = []) {
        $this->log('critical', $message, $context);
    }
    
    private function log($level, $message, $context) {
        $levelValue = $this->levels[$level] ?? 1;
        $currentLevel = $this->levels[$this->level] ?? 1;
        
        if ($levelValue < $currentLevel) {
            return;
        }
        
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $logLine = json_encode($entry) . "\n";
        file_put_contents($this->logFile, $logLine, FILE_APPEND);
        
        // Also log to error_log for production monitoring
        error_log($entry['level'] . ': ' . $message);
    }
}
