<?php
use PHPUnit\Framework\TestCase;
use Burp\Core\IntruderEngine;

class IntruderTest extends TestCase {
    private $intruder;
    
    protected function setUp(): void {
        $this->intruder = new IntruderEngine();
    }
    
    public function testPayloadGeneration() {
        // Test numeric payload generation
        $params = [
            'payload_type' => 'numeric',
            'start' => 0,
            'end' => 5
        ];
        
        $method = new ReflectionMethod($this->intruder, 'generatePayloads');
        $method->setAccessible(true);
        $payloads = $method->invoke($this->intruder, 
            $params['payload_type'], 
            $params['start'], 
            $params['end'], 
            ''
        );
        
        $this->assertCount(6, $payloads);
        $this->assertEquals('000000', $payloads[0]);
        $this->assertEquals('000005', $payloads[5]);
    }
}
