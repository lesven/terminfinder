<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../backend/helpers.php';
require_once __DIR__ . '/../backend/groups.php';

class GroupApiTest extends TestCase {
    private $pdo;
    private $api;

    protected function setUp(): void {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Create tables matching the minimal schema needed for tests
        $this->pdo->exec("CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE group_passwords (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $this->pdo->exec("CREATE TABLE availabilities (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL, user_name TEXT NOT NULL, date DATE NOT NULL, time_slot TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE (group_code, user_name, date, time_slot))");
        $this->pdo->exec("CREATE TABLE share_links (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL, token_hash TEXT NOT NULL, expires_at DATETIME DEFAULT NULL, single_use INTEGER DEFAULT 0, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

        $this->api = new GroupAPI($this->pdo);
    }

    public function testCreateAndAuthenticateGroup() {
        $res = $this->api->authenticateGroup('g1', 'secret');
        $this->assertTrue($res['success']);
        $this->assertStringContainsString('created', $res['message']);

        $res2 = $this->api->authenticateGroup('g1', 'secret');
        $this->assertTrue($res2['success']);
        $this->assertEquals('Authentication successful', $res2['message']);

        $res3 = $this->api->authenticateGroup('g1', 'wrong');
        $this->assertFalse($res3['success']);
        $this->assertEquals('Invalid password', $res3['message']);
    }

    public function testGetGroupData() {
        // create group and add availabilities
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('demo')");
        $pw = hashPassword('pw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['demo', $pw]);

        $stmt = $this->pdo->prepare("INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES (?, ?, ?, ?)");
        $stmt->execute(['demo', 'Alice', '2026-01-20', 'morning']);
        $stmt->execute(['demo', 'Alice', '2026-01-20', 'afternoon']);
        $stmt->execute(['demo', 'Bob', '2026-01-21', 'evening']);

        $res = $this->api->getGroupData('demo');
        $this->assertTrue($res['success']);
        $data = $res['data'];
        $this->assertArrayHasKey('Alice', $data);
        $this->assertArrayHasKey('2026-01-20', $data['Alice']);
        $this->assertContains('morning', $data['Alice']['2026-01-20']);
        $this->assertContains('afternoon', $data['Alice']['2026-01-20']);
    }

    public function testShareLinkTokenFlow() {
        // create group and password
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('linkgrp')");
        $pw = hashPassword('linkpw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['linkgrp', $pw]);

        $create = $this->api->createShareLink('linkgrp', 'linkpw', 1, 0);
        $this->assertTrue($create['success']);
        $this->assertArrayHasKey('token', $create);

        $token = $create['token'];
        $auth = $this->api->authenticateWithToken($token);
        $this->assertTrue($auth['success']);
        $this->assertEquals('linkgrp', $auth['groupCode']);

        // single-use token test
        $create2 = $this->api->createShareLink('linkgrp', 'linkpw', 1, 1);
        $this->assertTrue($create2['success']);
        $token2 = $create2['token'];
        $a1 = $this->api->authenticateWithToken($token2);
        $this->assertTrue($a1['success']);
        $a2 = $this->api->authenticateWithToken($token2);
        $this->assertFalse($a2['success']);
        $this->assertEquals('Token already used', $a2['message']);
    }

    public function testAuthenticateGroupWithoutPassword() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('nopw')");
        $res = $this->api->authenticateGroup('nopw', 'whatever');
        $this->assertFalse($res['success']);
        $this->assertEquals('Group password not found', $res['message']);
    }

    public function testCreateShareLinkNoExpiryAndInvalidPassword() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('grp2')");
        $pw = hashPassword('pw2');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['grp2', $pw]);

        $bad = $this->api->createShareLink('grp2', 'wrong', 7, 0);
        $this->assertFalse($bad['success']);

        $noExpiry = $this->api->createShareLink('grp2', 'pw2', 0, 0);
        $this->assertTrue($noExpiry['success']);
        $this->assertArrayHasKey('token', $noExpiry);
        $this->assertArrayHasKey('expires_at', $noExpiry);
        $this->assertNull($noExpiry['expires_at']);
    }

    public function testAuthenticateWithExpiredTokenAndUsedAtSet() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('expiregrp')");
        $pw = hashPassword('pw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['expiregrp', $pw]);

        $token = 'deadbeefdeadbeefdeadbeefdeadbeef';
        $tokenHash = hash('sha256', $token);
        $this->pdo->prepare("INSERT INTO share_links (group_code, token_hash, expires_at, single_use) VALUES (?, ?, ?, ?)")
            ->execute(['expiregrp', $tokenHash, '2000-01-01 00:00:00', 0]);

        $res = $this->api->authenticateWithToken($token);
        $this->assertFalse($res['success']);
        $this->assertEquals('Invalid or expired token', $res['message']);

        // single-use used_at set check
        $create = $this->api->createShareLink('expiregrp', 'pw', 1, 1);
        $this->assertTrue($create['success']);
        $token2 = $create['token'];
        $a1 = $this->api->authenticateWithToken($token2);
        $this->assertTrue($a1['success']);

        $stmt = $this->pdo->prepare("SELECT used_at FROM share_links WHERE token_hash = ?");
        $stmt->execute([hash('sha256', $token2)]);
        $row = $stmt->fetch();
        $this->assertNotNull($row['used_at']);
    }

    public function testGetGroupDataEmpty() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('emptygrp')");
        $pw = hashPassword('pw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['emptygrp', $pw]);

        $res = $this->api->getGroupData('emptygrp');
        $this->assertTrue($res['success']);
        $this->assertEquals([], $res['data']);
    }

    public function testPrivateHelpersViaReflection() {
        // insert some data to use with fetchAll/fetchOne/execute
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('refgrp')");
        $this->pdo->exec("INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES ('refgrp','R','2026-01-22','morning')");

        $ref = new ReflectionClass($this->api);

        // success()
        $m = $ref->getMethod('success');
        $s = $m->invoke($this->api, []);
        $this->assertTrue($s['success']);

        // error()
        $m = $ref->getMethod('error');
        $e = $m->invoke($this->api, 'oops');
        $this->assertFalse($e['success']);
        $this->assertEquals('oops', $e['message']);

        // error() with code arg
        $e2 = $m->invoke($this->api, 'err', 42);
        $this->assertArrayHasKey('code', $e2);
        $this->assertEquals(42, $e2['code']);

        // groupExists()
        $m = $ref->getMethod('groupExists');
        $this->assertTrue($m->invoke($this->api, 'refgrp'));
        $this->assertFalse($m->invoke($this->api, 'nope'));

        // getPasswordHash() -> null when absent
        $m = $ref->getMethod('getPasswordHash');
        $this->assertNull($m->invoke($this->api, 'refgrp'));

        // fetchAll()
        $m = $ref->getMethod('fetchAll');
        $rows = $m->invoke($this->api, "SELECT user_name FROM availabilities WHERE group_code = ?", ['refgrp']);
        $this->assertNotEmpty($rows);

        // fetchOne()
        $m = $ref->getMethod('fetchOne');
        $row = $m->invoke($this->api, "SELECT user_name FROM availabilities WHERE group_code = ? LIMIT 1", ['refgrp']);
        $this->assertEquals('R', $row['user_name']);

        // execute()
        $m = $ref->getMethod('execute');
        $res = $m->invoke($this->api, "INSERT INTO availabilities (group_code,user_name,date,time_slot) VALUES (?, ?, ?, ?)", ['refgrp','S','2026-01-22','evening']);
        $this->assertInstanceOf(PDOStatement::class, $res);

        // groupDataFromRows()
        $m = $ref->getMethod('groupDataFromRows');
        $rows = [ ['user_name' => 'A', 'date' => '2026-01-20', 'time_slot' => 'morning'], ['user_name' => 'A', 'date' => '2026-01-20', 'time_slot' => 'afternoon'] ];
        $gd = $m->invoke($this->api, $rows);
        $this->assertArrayHasKey('A', $gd);
        $this->assertContains('morning', $gd['A']['2026-01-20']);
    }

    public function testCreateGroupFailureRollback() {
        // PDO stub that throws on group_passwords insert
        // Use a real in-memory SQLite PDO and add a trigger that aborts insert into group_passwords
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE)");
        $pdo->exec("CREATE TABLE group_passwords (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL)");
        // trigger to fail password insert
        $pdo->exec("CREATE TRIGGER fail_pw_insert BEFORE INSERT ON group_passwords BEGIN SELECT RAISE(ABORT,'insert failed'); END;");

        $api = new GroupAPI($pdo);
        $res = $api->authenticateGroup('willfail', 'pw');
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('Failed to create group', $res['message']);

        // Now call createGroup directly and ensure rollback path is taken
        $ref = new ReflectionClass($api);
        $m = $ref->getMethod('createGroup');
        $r = $m->invoke($api, 'willfail2', 'pw');
        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Failed to create group', $r['message']);
    }

    public function testDatabaseErrorsBubbleUp() {
        // stub that throws on fetchAll / fetchOne to trigger catch blocks
        // Use a PDO without tables to trigger "no such table" exceptions in the methods
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $api = new GroupAPI($pdo);
        $g = $api->getGroupData('x');
        $this->assertFalse($g['success']);
        $this->assertStringContainsString('Failed to get group data', $g['message']);

        $c = $api->createShareLink('x', 'pw');
        $this->assertFalse($c['success']);
        $this->assertStringContainsString('Failed to create share link', $c['message']);

        $a = $api->authenticateWithToken('token');
        $this->assertFalse($a['success']);
        $this->assertStringContainsString('Failed to authenticate token', $a['message']);
    }

    public function testConstructorFallbackToDatabase() {
        // Test constructor with null PDO (should fallback to Database class)
        // Skip if database driver not available in test environment
        try {
            $api = new GroupAPI(null);
            $this->assertInstanceOf(GroupAPI::class, $api);
            
            // Test constructor with non-PDO parameter
            $api2 = new GroupAPI('not-a-pdo');
            $this->assertInstanceOf(GroupAPI::class, $api2);
        } catch (Exception $e) {
            $this->markTestSkipped('Database driver not available in test environment: ' . $e->getMessage());
        }
    }

    public function testTokenLengthAndRandomness() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('toktest')");
        $pw = hashPassword('pw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['toktest', $pw]);

        $create1 = $this->api->createShareLink('toktest', 'pw', 1, 0);
        $create2 = $this->api->createShareLink('toktest', 'pw', 1, 0);
        
        $this->assertTrue($create1['success']);
        $this->assertTrue($create2['success']);
        
        // Tokens should be different (randomness)
        $this->assertNotEquals($create1['token'], $create2['token']);
        
        // Tokens should be 32 hex chars (16 bytes * 2)
        $this->assertEquals(32, strlen($create1['token']));
        $this->assertTrue(ctype_xdigit($create1['token']));
    }

    public function testShareLinkExpiryLogic() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('exptest')");
        $pw = hashPassword('pw');
        $stmt = $this->pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
        $stmt->execute(['exptest', $pw]);

        // Test with TTL = 0 (no expiry)
        $create = $this->api->createShareLink('exptest', 'pw', 0, 0);
        $this->assertTrue($create['success']);
        $this->assertNull($create['expires_at']);
        
        // Test with TTL = 1 (expires in 1 day)
        $create2 = $this->api->createShareLink('exptest', 'pw', 1, 0);
        $this->assertTrue($create2['success']);
        $this->assertNotNull($create2['expires_at']);
        
        // expires_at should be roughly 1 day from now
        $expiryTime = strtotime($create2['expires_at']);
        $expectedTime = time() + (24 * 3600);
        $this->assertLessThan(60, abs($expiryTime - $expectedTime)); // within 1 minute
    }

    public function testGroupDataStructureComplexity() {
        $this->pdo->exec("INSERT INTO groups (code) VALUES ('complex')");
        $stmt = $this->pdo->prepare("INSERT INTO availabilities (group_code, user_name, date, time_slot) VALUES (?, ?, ?, ?)");
        
        // Multiple users, dates, and time slots
        $stmt->execute(['complex', 'Alice', '2026-01-20', 'morning']);
        $stmt->execute(['complex', 'Alice', '2026-01-20', 'afternoon']);
        $stmt->execute(['complex', 'Alice', '2026-01-21', 'evening']);
        $stmt->execute(['complex', 'Bob', '2026-01-20', 'morning']);
        $stmt->execute(['complex', 'Bob', '2026-01-22', 'afternoon']);
        $stmt->execute(['complex', 'Charlie', '2026-01-23', '10:00']);
        
        $res = $this->api->getGroupData('complex');
        $this->assertTrue($res['success']);
        $data = $res['data'];
        
        // Verify structure
        $this->assertCount(3, $data); // 3 users
        $this->assertArrayHasKey('Alice', $data);
        $this->assertArrayHasKey('Bob', $data);
        $this->assertArrayHasKey('Charlie', $data);
        
        // Alice should have 2 dates
        $this->assertCount(2, $data['Alice']);
        $this->assertArrayHasKey('2026-01-20', $data['Alice']);
        $this->assertArrayHasKey('2026-01-21', $data['Alice']);
        
        // Alice's 2026-01-20 should have 2 time slots
        $this->assertCount(2, $data['Alice']['2026-01-20']);
        $this->assertContains('morning', $data['Alice']['2026-01-20']);
        $this->assertContains('afternoon', $data['Alice']['2026-01-20']);
        
        // Charlie should have specific time format
        $this->assertContains('10:00', $data['Charlie']['2026-01-23']);
    }

    public function testAuthenticateGroupVariousPasswordScenarios() {
        // Test various password edge cases
        $tests = [
            ['code' => 'empty_pw', 'password' => ''],
            ['code' => 'space_pw', 'password' => ' '],
            ['code' => 'special_pw', 'password' => '!@#$%^&*()'],
            ['code' => 'unicode_pw', 'password' => 'тест123'],
            ['code' => 'long_pw', 'password' => str_repeat('a', 100)]
        ];
        
        foreach ($tests as $test) {
            $res1 = $this->api->authenticateGroup($test['code'], $test['password']);
            $this->assertTrue($res1['success'], "Failed to create group with password: {$test['password']}");
            
            $res2 = $this->api->authenticateGroup($test['code'], $test['password']);
            $this->assertTrue($res2['success'], "Failed to authenticate with password: {$test['password']}");
        }
    }
}
