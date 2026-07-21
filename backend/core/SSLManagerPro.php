<?php
namespace Burp\Core;

class SSLManagerPro {
    private $certPath;
    private $config;
    private $logger;
    
    public function __construct() {
        $this->certPath = $_ENV['SSL_CERT_PATH'] ?? __DIR__ . '/../storage/certs';
        $this->config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);
        $this->logger = Logger::getInstance();
        
        if (!is_dir($this->certPath)) {
            mkdir($this->certPath, 0755, true);
        }
    }
    
    public function generateCA() {
        $caKey = $this->certPath . '/ca.key';
        $caCrt = $this->certPath . '/ca.crt';
        
        if (file_exists($caKey) && file_exists($caCrt)) {
            return json_encode([
                'status' => 'exists',
                'message' => 'CA certificate already exists',
                'download_url' => '/backend/index.php?route=ssl/download'
            ]);
        }
        
        // Generate CA Key
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        
        if (!$key) {
            return json_encode(['error' => 'Failed to generate CA key']);
        }
        
        // Save private key
        openssl_pkey_export_to_file($key, $caKey);
        
        // Generate CA Certificate
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'San Francisco',
            'organizationName' => 'Burp Toolkit CA',
            'organizationalUnitName' => 'Security Testing',
            'commonName' => 'Burp Toolkit Root CA',
            'emailAddress' => 'admin@burp-toolkit.com'
        ];
        
        $cert = openssl_csr_new($dn, $key);
        $cert = openssl_csr_sign($cert, null, $key, 3650, [
            'digest_alg' => 'sha256'
        ]);
        
        // Save certificate
        openssl_x509_export_to_file($cert, $caCrt);
        
        $this->logger->info('CA Certificate generated successfully');
        
        return json_encode([
            'status' => 'generated',
            'message' => 'CA Certificate generated successfully',
            'download_url' => '/backend/index.php?route=ssl/download',
            'fingerprint' => openssl_x509_fingerprint($cert, 'sha256')
        ]);
    }
    
    public function downloadCA() {
        $caCrt = $this->certPath . '/ca.crt';
        
        if (!file_exists($caCrt)) {
            http_response_code(404);
            echo json_encode(['error' => 'CA Certificate not found. Generate first.']);
            return;
        }
        
        header('Content-Type: application/x-x509-ca-cert');
        header('Content-Disposition: attachment; filename="burp-ca.crt"');
        header('Content-Length: ' . filesize($caCrt));
        readfile($caCrt);
        exit;
    }
    
    public function generateDomainCert($params) {
        $domain = $params['domain'] ?? '';
        $san = $params['san'] ?? [];
        
        if (empty($domain)) {
            return json_encode(['error' => 'Domain is required']);
        }
        
        $caKey = $this->certPath . '/ca.key';
        $caCrt = $this->certPath . '/ca.crt';
        
        if (!file_exists($caKey) || !file_exists($caCrt)) {
            return json_encode(['error' => 'CA certificate not found. Generate CA first.']);
        }
        
        // Load CA
        $caKeyContent = file_get_contents($caKey);
        $caCrtContent = file_get_contents($caCrt);
        
        $caKey = openssl_pkey_get_private($caKeyContent);
        $caCrt = openssl_x509_read($caCrtContent);
        
        // Generate domain key
        $domainKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        
        if (!$domainKey) {
            return json_encode(['error' => 'Failed to generate domain key']);
        }
        
        // Generate CSR
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'San Francisco',
            'organizationName' => 'Burp Toolkit',
            'commonName' => $domain,
            'emailAddress' => 'admin@burp-toolkit.com'
        ];
        
        $csr = openssl_csr_new($dn, $domainKey, [
            'digest_alg' => 'sha256'
        ]);
        
        // Add SAN
        $sanArray = [$domain];
        foreach ($san as $altDomain) {
            $sanArray[] = $altDomain;
        }
        
        openssl_csr_set_subject($csr, $dn);
        
        // Sign certificate
        $domainCrt = openssl_csr_sign($csr, $caCrt, $caKey, 365, [
            'digest_alg' => 'sha256',
            'subjectAltName' => 'DNS:' . implode(',DNS:', $sanArray)
        ]);
        
        // Export
        $certFile = $this->certPath . '/' . md5($domain) . '.crt';
        $keyFile = $this->certPath . '/' . md5($domain) . '.key';
        
        openssl_x509_export_to_file($domainCrt, $certFile);
        openssl_pkey_export_to_file($domainKey, $keyFile);
        
        $this->logger->info('Domain certificate generated', ['domain' => $domain]);
        
        return json_encode([
            'status' => 'generated',
            'domain' => $domain,
            'cert_file' => basename($certFile),
            'key_file' => basename($keyFile),
            'expires' => date('Y-m-d', strtotime('+365 days')),
            'fingerprint' => openssl_x509_fingerprint($domainCrt, 'sha256')
        ]);
    }
    
    public function getPinningGuide() {
        $guide = [
            'title' => 'SSL Pinning Bypass Guide',
            'sections' => [
                [
                    'title' => '1. Frida Script',
                    'description' => 'Use Frida to bypass SSL pinning',
                    'script' => $this->getFridaScript()
                ],
                [
                    'title' => '2. Objection Tool',
                    'description' => 'Use Objection to disable SSL pinning',
                    'command' => 'objection -g com.example.app explore -s "android sslpinning disable"'
                ],
                [
                    'title' => '3. Magisk Module',
                    'description' => 'Install Magisk module to bypass system-wide pinning',
                    'module' => 'https://github.com/andip71/MagiskTrustUserCerts'
                ],
                [
                    'title' => '4. APK Repackaging',
                    'description' => 'Repack APK with networkSecurityConfig',
                    'steps' => [
                        'apktool d app.apk',
                        'Edit AndroidManifest.xml',
                        'Add networkSecurityConfig',
                        'apktool b app -o app-mod.apk',
                        'Sign APK'
                    ]
                ],
                [
                    'title' => '5. Xposed Module',
                    'description' => 'Use Xposed module to bypass pinning',
                    'module' => 'https://github.com/wish123/JustTrustMe'
                ]
            ]
        ];
        
        return json_encode($guide);
    }
    
    private function getFridaScript() {
        return <<<JS
Java.perform(function() {
    // SSL Pinning Bypass
    var TrustManager = Java.use('javax.net.ssl.X509TrustManager');
    TrustManager.checkServerTrusted.implementation = function(chain, authType) {
        console.log('[+] SSL Pinning Bypassed!');
    };
    
    var HostnameVerifier = Java.use('javax.net.ssl.HostnameVerifier');
    HostnameVerifier.verify.implementation = function(hostname, session) {
        return true;
    };
    
    // Certificate Pinner Bypass
    var CertificatePinner = Java.use('okhttp3.CertificatePinner');
    CertificatePinner.check.implementation = function(hostname, certificates) {
        console.log('[+] Certificate Pinner Bypassed!');
    };
});
JS;
    }
}
