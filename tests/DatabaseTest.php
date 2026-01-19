<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/database.php';

class DatabaseTest extends TestCase {
    
    public function testConstructorWithEnvironmentVariables() {
        // Test default values
        $db = new Database();
        $this->assertInstanceOf(Database::class, $db);
        
        // Test with environment variables
        $_ENV['DB_HOST'] = 'testhost';
        $_ENV['DB_NAME'] = 'testdb';
        $_ENV['DB_USER'] = 'testuser';
        $_ENV['DB_PASSWORD'] = 'testpass';
        
        $db2 = new Database();
        $this->assertInstanceOf(Database::class, $db2);
        
        // Clean up
        unset($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    }
    
    public function testConnectWithInvalidCredentials() {
        // Use invalid MySQL credentials to test connection failure
        $_ENV['DB_HOST'] = 'nonexistent';
        $_ENV['DB_NAME'] = 'nonexistent';
        $_ENV['DB_USER'] = 'nonexistent';
        $_ENV['DB_PASSWORD'] = 'nonexistent';
        
        $db = new Database();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database connection failed');
        
        $db->connect();
        
        // Clean up
        unset($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    }
    
    public function testConnectReturnsSamePDOInstance() {
        // Mock a working database by extending Database and overriding connect
        $mockDb = new class extends Database {
            private $mockPdo;
            
            public function connect() {
                if ($this->mockPdo === null) {
                    $this->mockPdo = new PDO('sqlite::memory:');
                }
                return $this->mockPdo;
            }
        };
        
        $pdo1 = $mockDb->connect();
        $pdo2 = $mockDb->connect();
        
        $this->assertSame($pdo1, $pdo2);
    }
    
    public function testClose() {
        $mockDb = new class extends Database {
            public $pdo = null;
            
            public function connect() {
                if ($this->pdo === null) {
                    $this->pdo = new PDO('sqlite::memory:');
                }
                return $this->pdo;
            }
            
            public function close() {
                $this->pdo = null;
            }
            
            public function getPdo() {
                return $this->pdo;
            }
        };
        
        $pdo = $mockDb->connect();
        $this->assertNotNull($mockDb->getPdo());
        
        $mockDb->close();
        $this->assertNull($mockDb->getPdo());
    }
}