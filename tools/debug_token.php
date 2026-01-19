<?php
require_once __DIR__ . '/../backend/helpers.php';
require_once __DIR__ . '/../backend/groups.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec("CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT NOT NULL UNIQUE, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE group_passwords (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE share_links (id INTEGER PRIMARY KEY AUTOINCREMENT, group_code TEXT NOT NULL, token_hash TEXT NOT NULL, expires_at DATETIME DEFAULT NULL, single_use INTEGER DEFAULT 0, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

$api = new GroupAPI($pdo);
$pdo->exec("INSERT INTO groups (code) VALUES ('linkgrp')");
$stmt = $pdo->prepare("INSERT INTO group_passwords (group_code, password_hash) VALUES (?, ?)");
$stmt->execute(['linkgrp', hashPassword('linkpw')]);

$create = $api->createShareLink('linkgrp', 'linkpw', 1, 1);
var_dump($create);
$token = $create['token'];
$first = $api->authenticateWithToken($token);
var_dump($first);
$second = $api->authenticateWithToken($token);
var_dump($second);
