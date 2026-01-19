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
}
