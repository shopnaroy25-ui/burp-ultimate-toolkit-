<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Burp\Core\ProxyEngine;
use Burp\Core\RepeaterEngine;
use Burp\Core\IntruderEngine;
use Burp\Core\OTPBypassPro;
use Burp\Core\SSLManagerPro;
use Burp\Core\AIPredictor;
use Burp\Core\AutoExploit;
use Burp\Middleware\AuthMiddleware;
use Burp\Middleware\RateLimiter;
use Burp\Middleware\CorsHandler;

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['route'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Rate Limiting
RateLimiter::check($_SERVER['REMOTE_ADDR'], $path);

// Authentication (except public routes)
$publicRoutes = ['health', 'ssl/download'];
if (!in_array($path, $publicRoutes)) {
    AuthMiddleware::validate();
}

// CORS
CorsHandler::handle();

// Routing
switch($path) {
    // ===== PROXY ENGINE =====
    case 'proxy/capture':
        $proxy = new ProxyEngine();
        if ($method === 'POST') {
            echo $proxy->capture($input);
        } else {
            echo $proxy->getCaptured();
        }
        break;
        
    case 'proxy/intercept':
        $proxy = new ProxyEngine();
        echo $proxy->toggleIntercept($input);
        break;
        
    case 'proxy/forward':
        $proxy = new ProxyEngine();
        echo $proxy->forwardRequest($input);
        break;
        
    case 'proxy/drop':
        $proxy = new ProxyEngine();
        echo $proxy->dropRequest($input);
        break;
        
    // ===== REPEATER ENGINE =====
    case 'repeater/send':
        $repeater = new RepeaterEngine();
        echo $repeater->send($input);
        break;
        
    case 'repeater/history':
        $repeater = new RepeaterEngine();
        echo $repeater->getHistory();
        break;
        
    case 'repeater/delete':
        $repeater = new RepeaterEngine();
        echo $repeater->delete($input);
        break;
        
    // ===== INTRUDER ENGINE =====
    case 'intruder/start':
        $intruder = new IntruderEngine();
        echo $intruder->startAttack($input);
        break;
        
    case 'intruder/status':
        $intruder = new IntruderEngine();
        echo $intruder->getStatus();
        break;
        
    case 'intruder/stop':
        $intruder = new IntruderEngine();
        echo $intruder->stopAttack();
        break;
        
    case 'intruder/export':
        $intruder = new IntruderEngine();
        echo $intruder->exportResults($input);
        break;
        
    // ===== OTP BYPASS PRO =====
    case 'otp/bypass':
        $otp = new OTPBypassPro();
        echo $otp->runAll($input);
        break;
        
    case 'otp/bypass/single':
        $otp = new OTPBypassPro();
        echo $otp->runSingleTechnique($input);
        break;
        
    case 'otp/bypass/report':
        $otp = new OTPBypassPro();
        echo $otp->getReport($input);
        break;
        
    // ===== SSL MANAGER PRO =====
    case 'ssl/generate':
        $ssl = new SSLManagerPro();
        echo $ssl->generateCA();
        break;
        
    case 'ssl/download':
        $ssl = new SSLManagerPro();
        $ssl->downloadCA();
        break;
        
    case 'ssl/domain':
        $ssl = new SSLManagerPro();
        echo $ssl->generateDomainCert($input);
        break;
        
    case 'ssl/pinning-guide':
        $ssl = new SSLManagerPro();
        echo $ssl->getPinningGuide();
        break;
        
    // ===== AI PREDICTOR =====
    case 'ai/predict':
        $ai = new AIPredictor();
        echo $ai->predictFromData($input);
        break;
        
    case 'ai/train':
        $ai = new AIPredictor();
        echo $ai->trainModel($input);
        break;
        
    case 'ai/patterns':
        $ai = new AIPredictor();
        echo $ai->getPatterns();
        break;
        
    // ===== AUTO EXPLOIT =====
    case 'exploit/scan':
        $exploit = new AutoExploit();
        echo $exploit->scan($input);
        break;
        
    case 'exploit/run':
        $exploit = new AutoExploit();
        echo $exploit->exploit($input);
        break;
        
    // ===== SETTINGS =====
    case 'settings/get':
        $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
        echo json_encode($config);
        break;
        
    case 'settings/update':
        $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
        foreach ($input as $key => $value) {
            $config[$key] = $value;
        }
        file_put_contents(__DIR__ . '/config.json', json_encode($config, JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'success', 'message' => 'Settings updated']);
        break;
        
    // ===== HEALTH CHECK =====
    case 'health':
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '3.0.0',
            'services' => [
                'database' => 'connected',
                'redis' => 'connected',
                'ssl' => file_exists(__DIR__ . '/storage/certs/ca.crt')
            ]
        ]);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found', 'path' => $path]);
}
