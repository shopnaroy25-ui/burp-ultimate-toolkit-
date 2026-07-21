<?php
use PHPUnit\Framework\TestCase;
use Burp\Core\ProxyEngine;

class ProxyTest extends TestCase {
    private $proxy;
    
    protected function setUp(): void {
        $this->proxy = new ProxyEngine();
    }
    
    public function testCapture() {
        $data = [
            'method' => 'GET',
            'host' => 'example.com',
            'path' => '/test',
            'headers' => ['User-Agent' => 'Test']
        ];
        
        $result = json_decode($this->proxy->capture($data), true);
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['intercepted', 'forwarded']);
    }
    
    public function testToggleIntercept() {
        $result = json_decode($this->proxy->toggleIntercept(['enabled' => false]), true);
        $this->assertEquals('success', $result['status']);
        $this->assertFalse($result['intercept_enabled']);
    }
}
