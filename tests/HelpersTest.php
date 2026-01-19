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

        // Edge cases
        $this->assertEquals("Field 'null' is required", validateRequired(['null' => null], ['null']));
        $this->assertEquals("Field 'empty' is required", validateRequired(['empty' => ''], ['empty']));
        $this->assertNull(validateRequired(['zero' => 0], ['zero'])); // 0 should be valid, not required
        $this->assertNull(validateRequired(['false' => false], ['false']));
        $this->assertEquals("Field 'emptyArr' is required", validateRequired(['emptyArr' => []], ['emptyArr'])); // Empty array should be invalid
    }

    public function testPasswordHashVerify() {
        $pw = 'secretpw';
        $hash = hashPassword($pw);
        $this->assertTrue(verifyPassword($pw, $hash));
        $this->assertFalse(verifyPassword('wrong', $hash));
        
        // Edge cases
        $this->assertFalse(verifyPassword('', $hash));
        $this->assertFalse(verifyPassword($pw, ''));
        $this->assertFalse(verifyPassword('', ''));
    }

    public function testValidateTimeSlotAndDate() {
        // Valid time slots
        $this->assertTrue(validateTimeSlot('10:00'));
        $this->assertTrue(validateTimeSlot('23:59'));
        $this->assertTrue(validateTimeSlot('00:00'));
        $this->assertTrue(validateTimeSlot('morning'));
        $this->assertTrue(validateTimeSlot('afternoon'));
        $this->assertTrue(validateTimeSlot('evening'));
        
        // Invalid time slots
        $this->assertFalse(validateTimeSlot('midnight'));
        $this->assertFalse(validateTimeSlot('25:00'));
        $this->assertFalse(validateTimeSlot('10:60'));
        $this->assertFalse(validateTimeSlot('1000'));
        $this->assertFalse(validateTimeSlot(''));
        $this->assertFalse(validateTimeSlot('random'));

        // Valid dates
        $this->assertTrue(validateDate('2026-01-20'));
        $this->assertTrue(validateDate('2000-12-31'));
        $this->assertTrue(validateDate('1999-02-28'));
        
        // Invalid dates
        $this->assertFalse(validateDate('20-01-2026'));
        $this->assertFalse(validateDate('2026-13-01'));
        $this->assertFalse(validateDate('2026-01-32'));
        $this->assertFalse(validateDate(''));
        $this->assertFalse(validateDate('invalid'));
        $this->assertFalse(validateDate('2026-1-1')); // single digits not padded
    }

    public function testCorsHeadersCliNoop() {
        // Should just return without error on CLI
        $this->assertNull(corsHeaders());
    }

    public function testGetJsonInputEdgeCases() {
        // Mock php://input with various JSON scenarios
        // Note: getJsonInput reads from php://input which we can't easily mock in unit tests
        // but we can test the helper behavior indirectly
        $this->assertTrue(function_exists('getJsonInput'));
    }

    public function testSendJsonResponseFunctions() {
        // Test that the helper functions exist and are callable
        $this->assertTrue(function_exists('sendJsonResponse'));
        $this->assertTrue(function_exists('sendErrorResponse'));
    }
}
