<?php
use PHPUnit\Framework\TestCase;
use Burp\Core\OTPBypassPro;

class OTPBypassTest extends TestCase {
    private $otpBypass;
    
    protected function setUp(): void {
        $this->otpBypass = new OTPBypassPro();
    }
    
    public function testParameterRemoval() {
        $params = [
            'target' => 'https://example.com/verify?otp=123456',
            'otp' => '123456',
            'method' => 'GET'
        ];
        
        $method = new ReflectionMethod($this->otpBypass, 'bypass_parameter_removal');
        $method->setAccessible(true);
        $result = $method->invoke($this->otpBypass, 
            $params['target'],
            $params['otp'],
            $params['method'],
            [],
            ''
        );
        
        $this->assertArrayHasKey('url', $result);
        $this->assertStringNotContainsString('otp=', $result['url']);
    }
}
