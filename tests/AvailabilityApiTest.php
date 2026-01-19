<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/availability.php';

class AvailabilityApiTest extends TestCase {
    private $availabilityApi;
    private $mockDatabase;
    
    protected function setUp(): void {
        // Create in-memory SQLite database for testing
        $this->mockDatabase = new PDO('sqlite::memory:');
        $this->mockDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create AvailabilityAPI instance with dependency injection
        $this->availabilityApi = new AvailabilityAPI($this->mockDatabase);
        
        $this->setupTestDatabase();
    }
    
    private function setupTestDatabase(): void {
        // Create availabilities table matching the schema from the API
        $this->mockDatabase->exec('
            CREATE TABLE availabilities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_code TEXT NOT NULL,
                user_name TEXT NOT NULL,
                date TEXT NOT NULL,
                time_slot TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
    
    public function testSaveAvailabilityFormat1() {
        // Test associative format: { "2026-01-20": ["morning","afternoon"] }
        $availabilities = [
            '2024-03-01' => ['morning', 'afternoon'],
            '2024-03-02' => ['morning']
        ];
        
        $result = $this->availabilityApi->saveAvailability('TEST123', 'TestUser', $availabilities);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Availability saved successfully', $result['message']);
        
        // Verify database entries
        $stmt = $this->mockDatabase->prepare('SELECT * FROM availabilities WHERE group_code = ? AND user_name = ? ORDER BY date, time_slot');
        $stmt->execute(['TEST123', 'TestUser']);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertCount(3, $entries);
        $this->assertEquals('2024-03-01', $entries[0]['date']);
        $this->assertEquals('afternoon', $entries[0]['time_slot']);
        $this->assertEquals('2024-03-01', $entries[1]['date']);
        $this->assertEquals('morning', $entries[1]['time_slot']);
    }
    
    public function testSaveAvailabilityFormat2() {
        // Test object format: [{ date: "2026-01-20", timeSlot: "10:00", available: true }]
        $availabilities = [
            ['date' => '2024-03-01', 'timeSlot' => '09:00', 'available' => true],
            ['date' => '2024-03-01', 'timeSlot' => '10:00', 'available' => true],
            ['date' => '2024-03-01', 'timeSlot' => '11:00', 'available' => false], // Should be skipped
            ['date' => '2024-03-02', 'timeSlot' => '09:00', 'available' => true]
        ];
        
        $result = $this->availabilityApi->saveAvailability('TEST456', 'TestUser2', $availabilities);
        
        $this->assertTrue($result['success']);
        
        // Verify only available slots are saved
        $stmt = $this->mockDatabase->prepare('SELECT COUNT(*) FROM availabilities WHERE group_code = ? AND user_name = ?');
        $stmt->execute(['TEST456', 'TestUser2']);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(3, $count); // Only 3 available slots should be saved
    }
    
    public function testSaveAvailabilityEmptyArray() {
        // First add some data
        $availabilities = ['2024-03-01' => ['morning']];
        $this->availabilityApi->saveAvailability('TEST789', 'TestUser3', $availabilities);
        
        // Then save empty array (should delete existing)
        $result = $this->availabilityApi->saveAvailability('TEST789', 'TestUser3', []);
        
        $this->assertTrue($result['success']);
        
        // Verify all entries are deleted
        $stmt = $this->mockDatabase->prepare('SELECT COUNT(*) FROM availabilities WHERE group_code = ? AND user_name = ?');
        $stmt->execute(['TEST789', 'TestUser3']);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(0, $count);
    }
    
    public function testSaveAvailabilityReplaceExisting() {
        // Save initial availability
        $initial = ['2024-03-01' => ['morning']];
        $this->availabilityApi->saveAvailability('REPLACE', 'User', $initial);
        
        // Save new availability (should replace)
        $new = ['2024-03-02' => ['afternoon', 'evening']];
        $result = $this->availabilityApi->saveAvailability('REPLACE', 'User', $new);
        
        $this->assertTrue($result['success']);
        
        // Verify old entries are gone and new ones exist
        $stmt = $this->mockDatabase->prepare('SELECT date, time_slot FROM availabilities WHERE group_code = ? AND user_name = ? ORDER BY date, time_slot');
        $stmt->execute(['REPLACE', 'User']);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertCount(2, $entries);
        $this->assertEquals('2024-03-02', $entries[0]['date']);
        $this->assertEquals('afternoon', $entries[0]['time_slot']);
        $this->assertEquals('2024-03-02', $entries[1]['date']);
        $this->assertEquals('evening', $entries[1]['time_slot']);
    }
    
    public function testSaveAvailabilityInvalidDate() {
        $availabilities = ['invalid-date' => ['morning']];
        
        $result = $this->availabilityApi->saveAvailability('INVALID', 'User', $availabilities);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid date format', $result['message']);
    }
    
    public function testSaveAvailabilityInvalidTimeSlot() {
        $availabilities = ['2024-03-01' => ['invalid-slot']];
        
        $result = $this->availabilityApi->saveAvailability('INVALID', 'User', $availabilities);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid time slot', $result['message']);
    }
    
    public function testGetUserAvailability() {
        // Insert test data
        $stmt = $this->mockDatabase->prepare('INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES (?, ?, ?, ?)');
        $stmt->execute(['GETTEST', 'TestUser', '2024-03-01', 'morning']);
        $stmt->execute(['GETTEST', 'TestUser', '2024-03-01', 'afternoon']);
        $stmt->execute(['GETTEST', 'TestUser', '2024-03-02', 'morning']);
        
        $result = $this->availabilityApi->getUserAvailability('GETTEST', 'TestUser');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        
        $data = $result['data'];
        $this->assertArrayHasKey('2024-03-01', $data);
        $this->assertArrayHasKey('2024-03-02', $data);
        $this->assertCount(2, $data['2024-03-01']);
        $this->assertCount(1, $data['2024-03-02']);
        $this->assertContains('morning', $data['2024-03-01']);
        $this->assertContains('afternoon', $data['2024-03-01']);
        $this->assertContains('morning', $data['2024-03-02']);
    }
    
    public function testGetUserAvailabilityNoData() {
        $result = $this->availabilityApi->getUserAvailability('NODATA', 'NoUser');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }
    
    public function testGetParticipants() {
        // Insert test data for multiple users
        $stmt = $this->mockDatabase->prepare('INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES (?, ?, ?, ?)');
        $stmt->execute(['PARTICIPANTS', 'Alice', '2024-03-01', 'morning']);
        $stmt->execute(['PARTICIPANTS', 'Bob', '2024-03-01', 'afternoon']);
        $stmt->execute(['PARTICIPANTS', 'Alice', '2024-03-02', 'morning']); // Duplicate user
        $stmt->execute(['PARTICIPANTS', 'Charlie', '2024-03-01', 'evening']);
        
        $result = $this->availabilityApi->getParticipants('PARTICIPANTS');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        
        $participants = $result['data'];
        $this->assertCount(3, $participants); // Should be unique
        $this->assertContains('Alice', $participants);
        $this->assertContains('Bob', $participants);
        $this->assertContains('Charlie', $participants);
    }
    
    public function testGetParticipantsNoData() {
        $result = $this->availabilityApi->getParticipants('EMPTY_GROUP');
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
    }
    
    public function testGetParticipantsOrdered() {
        // Insert test data in non-alphabetical order
        $stmt = $this->mockDatabase->prepare('INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES (?, ?, ?, ?)');
        $stmt->execute(['ORDER_TEST', 'Zoe', '2024-03-01', 'morning']);
        $stmt->execute(['ORDER_TEST', 'Alice', '2024-03-01', 'afternoon']);
        $stmt->execute(['ORDER_TEST', 'Bob', '2024-03-01', 'evening']);
        
        $result = $this->availabilityApi->getParticipants('ORDER_TEST');
        
        $this->assertTrue($result['success']);
        $participants = $result['data'];
        
        // Should be alphabetically ordered
        $this->assertEquals(['Alice', 'Bob', 'Zoe'], $participants);
    }
}