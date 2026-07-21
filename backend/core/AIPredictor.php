<?php
namespace Burp\Core;

class AIPredictor {
    private $redis;
    private $logger;
    private $trainingData = [];
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect($_ENV['REDIS_HOST'] ?? 'redis', $_ENV['REDIS_PORT'] ?? 6379);
        $this->logger = Logger::getInstance();
        $this->loadTrainingData();
    }
    
    public function predictFromData($params) {
        $otps = $params['otps'] ?? [];
        $limit = $params['limit'] ?? 10;
        
        if (empty($otps)) {
            return json_encode(['error' => 'No OTPs provided']);
        }
        
        $patterns = $this->analyzePatterns($otps);
        $predictions = $this->generatePredictions($patterns, $limit);
        $confidence = $this->calculateConfidence($patterns);
        
        return json_encode([
            'predictions' => $predictions,
            'confidence' => $confidence,
            'patterns_found' => $patterns,
            'total_otps_analyzed' => count($otps)
        ]);
    }
    
    public function trainModel($params) {
        $data = $params['data'] ?? [];
        $otps = $params['otps'] ?? [];
        
        if (empty($data) && empty($otps)) {
            return json_encode(['error' => 'No training data provided']);
        }
        
        // Save training data
        $training = $this->redis->get('ai_training_data');
        $training = $training ? json_decode($training, true) : [];
        
        if (!empty($otps)) {
            $training[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'otps' => $otps,
                'source' => 'api'
            ];
        }
        
        if (!empty($data)) {
            foreach ($data as $item) {
                if (isset($item['otps'])) {
                    $training[] = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'otps' => $item['otps'],
                        'source' => 'direct'
                    ];
                }
            }
        }
        
        $this->redis->set('ai_training_data', json_encode($training));
        
        // Analyze patterns
        $allOtps = [];
        foreach ($training as $item) {
            $allOtps = array_merge($allOtps, $item['otps']);
        }
        
        $patterns = $this->analyzePatterns($allOtps);
        $this->redis->set('ai_patterns', json_encode($patterns));
        
        return json_encode([
            'status' => 'trained',
            'total_samples' => count($training),
            'total_otps' => count($allOtps),
            'patterns' => $patterns
        ]);
    }
    
    public function getPatterns() {
        $patterns = $this->redis->get('ai_patterns');
        return $patterns ? $patterns : json_encode(['error' => 'No patterns found']);
    }
    
    private function loadTrainingData() {
        $data = $this->redis->get('ai_training_data');
        if ($data) {
            $this->trainingData = json_decode($data, true);
        }
    }
    
    private function analyzePatterns($otps) {
        $patterns = [];
        $numbers = array_map('intval', $otps);
        sort($numbers);
        
        // Arithmetic Progression
        if (count($numbers) > 2) {
            $diff = $numbers[1] - $numbers[0];
            $isAP = true;
            for ($i = 2; $i < count($numbers); $i++) {
                if ($numbers[$i] - $numbers[$i-1] !== $diff) {
                    $isAP = false;
                    break;
                }
            }
            if ($isAP) {
                $patterns['arithmetic_progression'] = [
                    'difference' => $diff,
                    'next' => max($numbers) + $diff
                ];
            }
        }
        
        // Repeating digits
        $repeating = [];
        foreach ($otps as $otp) {
            if (preg_match('/(\d)\1{2,}/', $otp)) {
                $repeating[] = $otp;
            }
        }
        if (!empty($repeating)) {
            $patterns['repeating_digits'] = $repeating;
        }
        
        // Sequential digits
        $sequential = [];
        foreach ($otps as $otp) {
            if (preg_match('/012|123|234|345|456|567|678|789|890/', $otp)) {
                $sequential[] = $otp;
            }
        }
        if (!empty($sequential)) {
            $patterns['sequential_digits'] = $sequential;
        }
        
        // Common patterns
        $common = [];
        foreach ($otps as $otp) {
            if (preg_match('/^(012345|123456|234567|345678|456789|567890)$/', $otp)) {
                $common[] = $otp;
            }
        }
        if (!empty($common)) {
            $patterns['common_patterns'] = $common;
        }
        
        // Birthday patterns
        $birthday = [];
        foreach ($otps as $otp) {
            if (preg_match('/^(0[1-9]|1[0-2])(0[1-9]|[12][0-9]|3[01])$/', $otp)) {
                $birthday[] = $otp;
            }
        }
        if (!empty($birthday)) {
            $patterns['birthday_patterns'] = $birthday;
        }
        
        return $patterns;
    }
    
    private function generatePredictions($patterns, $limit) {
        $predictions = [];
        
        if (isset($patterns['arithmetic_progression'])) {
            $next = $patterns['arithmetic_progression']['next'];
            for ($i = 0; $i < $limit; $i++) {
                $predictions[] = str_pad($next + $i, 6, '0', STR_PAD_LEFT);
            }
        }
        
        if (isset($patterns['repeating_digits'])) {
            foreach ($patterns['repeating_digits'] as $repeat) {
                $predictions[] = $repeat;
                $predictions[] = str_repeat($repeat[0], 6);
                $predictions[] = str_repeat($repeat[0], 4) . '00';
                $predictions[] = '00' . str_repeat($repeat[0], 4);
            }
        }
        
        if (isset($patterns['common_patterns'])) {
            foreach ($patterns['common_patterns'] as $common) {
                $predictions[] = $common;
                $predictions[] = strrev($common);
            }
        }
        
        if (isset($patterns['birthday_patterns'])) {
            foreach ($patterns['birthday_patterns'] as $bd) {
                $predictions[] = $bd;
                $predictions[] = substr($bd, 2, 4) . substr($bd, 0, 2);
            }
        }
        
        // Add some common OTPs
        $commonOtps = ['123456', '000000', '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999'];
        foreach ($commonOtps as $common) {
            $predictions[] = $common;
        }
        
        $predictions = array_unique($predictions);
        return array_slice($predictions, 0, $limit);
    }
    
    private function calculateConfidence($patterns) {
        $weight = 0;
        if (isset($patterns['arithmetic_progression'])) $weight += 0.4;
        if (isset($patterns['repeating_digits'])) $weight += 0.2;
        if (isset($patterns['sequential_digits'])) $weight += 0.15;
        if (isset($patterns['common_patterns'])) $weight += 0.15;
        if (isset($patterns['birthday_patterns'])) $weight += 0.1;
        return min($weight, 1.0);
    }
}
