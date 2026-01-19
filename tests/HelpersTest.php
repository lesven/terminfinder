<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/helpers.php';

class HelpersTest extends TestCase {
    public function testValidateRequired() {
        $data = ['a' => 'value', 'b' => ['x']];
        $this->assertNull(validateRequired($data, ['a']));
        $this->assertNull(validateRequired($data, ['b']));

        $this->assertEquals("Field 'c' is required", validateRequired($data, ['c']));

        $data2 = ['s' => '   '];
        $this->assertEquals("Field 's' is required", validateRequired($data2, ['s']));
    }

    public function testPasswordHashVerify() {
        $pw = 'secretpw';
        $hash = hashPassword($pw);
        $this->assertTrue(verifyPassword($pw, $hash));
        $this->assertFalse(verifyPassword('wrong', $hash));
    }

    public function testValidateTimeSlotAndDate() {
        $this->assertTrue(validateTimeSlot('10:00'));
        $this->assertTrue(validateTimeSlot('23:59'));
        $this->assertTrue(validateTimeSlot('morning'));
        $this->assertFalse(validateTimeSlot('midnight'));

        $this->assertTrue(validateDate('2026-01-20'));
        $this->assertFalse(validateDate('20-01-2026'));
    }

    public function testCorsHeadersCliNoop() {
        // Should just return without error on CLI
        $this->assertNull(corsHeaders());
    }
}
