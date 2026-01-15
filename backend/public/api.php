<?php
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message = '', $data = null, $status = 200): void {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
if ($action === '') {
    respond(false, 'Aktion fehlt.', null, 400);
}

$dataDir = '/var/www/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$dbPath = $dataDir . '/terminfinder.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS groups (code TEXT PRIMARY KEY, password TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS availabilities (code TEXT NOT NULL, name TEXT NOT NULL, date TEXT NOT NULL, slots TEXT NOT NULL, PRIMARY KEY (code, name, date))');
} catch (PDOException $e) {
    respond(false, 'Datenbankfehler.', null, 500);
}

function loadGroup(PDO $db, string $code): array {
    $stmt = $db->prepare('SELECT name, date, slots FROM availabilities WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($data[$row['name']])) {
            $data[$row['name']] = [];
        }
        $data[$row['name']][$row['date']] = json_decode($row['slots'], true) ?? [];
    }
    return $data;
}

if ($action === 'load') {
    $code = trim($_GET['code'] ?? '');
    $password = trim($_GET['password'] ?? '');

    if ($code === '' || $password === '') {
        respond(false, 'Code und Passwort sind erforderlich.', null, 400);
    }

    $stmt = $db->prepare('SELECT password FROM groups WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['password'] !== $password) {
        respond(false, 'Falsches Passwort.', null, 403);
    }

    $data = loadGroup($db, $code);
    respond(true, '', $data);
}

if ($action === 'save') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true) ?? [];

    $name = trim($payload['name'] ?? '');
    $code = trim($payload['code'] ?? '');
    $password = trim($payload['password'] ?? '');
    $slots = $payload['slots'] ?? [];

    if ($name === '' || $code === '' || $password === '') {
        respond(false, 'Name, Code und Passwort sind erforderlich.', null, 400);
    }

    if (!is_array($slots) || count($slots) === 0) {
        respond(false, 'Mindestens ein Zeitfenster muss ausgewählt sein.', null, 400);
    }

    $stmt = $db->prepare('SELECT password FROM groups WHERE code = :code');
    $stmt->execute([':code' => $code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['password'] !== $password) {
        respond(false, 'Falsches Passwort.', null, 403);
    }

    $db->beginTransaction();

    try {
        if (!$row) {
            $insertGroup = $db->prepare('INSERT INTO groups (code, password) VALUES (:code, :password)');
            $insertGroup->execute([':code' => $code, ':password' => $password]);
        }

        $deleteStmt = $db->prepare('DELETE FROM availabilities WHERE code = :code AND name = :name');
        $deleteStmt->execute([':code' => $code, ':name' => $name]);

        $insertStmt = $db->prepare('INSERT INTO availabilities (code, name, date, slots) VALUES (:code, :name, :date, :slots)');
        foreach ($slots as $date => $slotValues) {
            $insertStmt->execute([
                ':code' => $code,
                ':name' => $name,
                ':date' => $date,
                ':slots' => json_encode(array_values($slotValues)),
            ]);
        }

        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        respond(false, 'Fehler beim Speichern.', null, 500);
    }

    $data = loadGroup($db, $code);
    respond(true, '', $data);
}

respond(false, 'Unbekannte Aktion.', null, 400);
