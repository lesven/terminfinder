<?php
// Disable error display and enable JSON-only responses
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON content type
header('Content-Type: application/json');

require_once 'database.php';
require_once 'helpers.php';

corsHeaders();

/**
 * Terminfinder Group API
 * Handles group creation, authentication, and data management
 */
class GroupAPI {
    private $db;
    private $tokenBytes = 16; // token entropy in bytes

    /**
     * Allow injecting a PDO instance for easier testing. If no PDO is provided,
     * fallback to the default Database connection.
     */
    public function __construct($pdo = null) {
        if ($pdo instanceof PDO) {
            $this->db = $pdo;
        } else {
            $database = new Database();
            $this->db = $database->connect();
        }
    }

    /* ---------- Public API methods (unchanged signatures) ---------- */

    public function authenticateGroup($code, $password) {
        try {
            if (!$this->groupExists($code)) {
                return $this->createGroup($code, $password);
            }

            $hash = $this->getPasswordHash($code);
            if (!$hash) {
                return $this->error('Group password not found');
            }

            if (!verifyPassword($password, $hash)) {
                return $this->error('Invalid password');
            }

            return $this->success(['message' => 'Authentication successful']);
        } catch (Exception $e) {
            return $this->error('Database error: ' . $e->getMessage());
        }
    }

    public function getGroupData($code) {
        try {
            $rows = $this->fetchAll(
                "SELECT user_name, date, time_slot FROM `availabilities` WHERE group_code = ? ORDER BY user_name, date, time_slot",
                [$code]
            );

            $groupData = $this->groupDataFromRows($rows);
            return $this->success(['data' => $groupData]);
        } catch (Exception $e) {
            return $this->error('Failed to get group data: ' . $e->getMessage());
        }
    }

    public function createShareLink($groupCode, $password, $ttlDays = 7, $singleUse = 0) {
        try {
            $hash = $this->getPasswordHash($groupCode);
            if (!$hash || !verifyPassword($password, $hash)) {
                return $this->error('Invalid password');
            }

            $token = bin2hex(random_bytes($this->tokenBytes));
            $tokenHash = hash('sha256', $token);
            $expiresAt = $ttlDays ? date('Y-m-d H:i:s', strtotime("+{$ttlDays} days")) : null;

            $this->execute(
                "INSERT INTO `share_links` (group_code, token_hash, expires_at, single_use) VALUES (?, ?, ?, ?)",
                [$groupCode, $tokenHash, $expiresAt, (int)$singleUse]
            );

            return $this->success(['token' => $token, 'expires_at' => $expiresAt]);
        } catch (Exception $e) {
            return $this->error('Failed to create share link: ' . $e->getMessage());
        }
    }

    public function authenticateWithToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $row = $this->fetchOne(
                "SELECT id, group_code, single_use, used_at FROM `share_links` WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)",
                [$tokenHash]
            );

            if (!$row) {
                return $this->error('Invalid or expired token');
            }

            if ($row['single_use'] && $row['used_at']) {
                return $this->error('Token already used');
            }

            if ($row['single_use']) {
                $this->execute("UPDATE `share_links` SET used_at = CURRENT_TIMESTAMP WHERE id = ?", [$row['id']]);
            }

            return $this->success(['groupCode' => $row['group_code']]);
        } catch (Exception $e) {
            return $this->error('Failed to authenticate token: ' . $e->getMessage());
        }
    }

    /* ---------- Private helpers ---------- */

    private function groupExists($code) {
        $row = $this->fetchOne("SELECT 1 FROM `groups` WHERE code = ?", [$code]);
        return (bool)$row;
    }

    private function getPasswordHash($groupCode) {
        $row = $this->fetchOne("SELECT password_hash FROM `group_passwords` WHERE group_code = ?", [$groupCode]);
        return $row['password_hash'] ?? null;
    }

    private function createGroup($code, $password) {
        try {
            $this->db->beginTransaction();

            $this->execute("INSERT INTO `groups` (code) VALUES (?)", [$code]);
            $passwordHash = hashPassword($password);
            $this->execute("INSERT INTO `group_passwords` (group_code, password_hash) VALUES (?, ?)", [$code, $passwordHash]);

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            return $this->success(['message' => 'Group created successfully']);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            return $this->error('Failed to create group: ' . $e->getMessage());
        }
    }

    private function fetchAll($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function fetchOne($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function execute($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function groupDataFromRows($rows) {
        $data = [];
        foreach ($rows as $r) {
            $user = $r['user_name'];
            $date = $r['date'];
            $slot = $r['time_slot'];

            if (!isset($data[$user])) {
                $data[$user] = [];
            }
            if (!isset($data[$user][$date])) {
                $data[$user][$date] = [];
            }
            $data[$user][$date][] = $slot;
        }
        return $data;
    }

    private function success($payload = []) {
        return array_merge(['success' => true], $payload);
    }

    private function error($message, $code = null) {
        $resp = ['success' => false, 'message' => $message];
        if ($code) {
            $resp['code'] = $code;
        }
        return $resp;
    }
}

// Handle API requests only when not used via CLI (so tests can require this file)
if (php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_METHOD'])) {
    $method = $_SERVER['REQUEST_METHOD'];
    $api = new GroupAPI();

    if ($method === 'POST') {
        $input = getJsonInput();
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'authenticate':
                $error = validateRequired($input, ['code', 'password']);
                if ($error) {
                    sendErrorResponse($error);
                }
                
                $result = $api->authenticateGroup($input['code'], $input['password']);
                sendJsonResponse($result);
                break;
                
            case 'getGroupData':
                $error = validateRequired($input, ['code']);
                if ($error) {
                    sendErrorResponse($error);
                }
                
                $result = $api->getGroupData($input['code']);
                sendJsonResponse($result);
                break;

            case 'createShareLink':
                $error = validateRequired($input, ['code', 'password']);
                if ($error) {
                    sendErrorResponse($error);
                }
                $ttl = isset($input['ttlDays']) ? (int)$input['ttlDays'] : 7;
                $singleUse = isset($input['singleUse']) ? (int)$input['singleUse'] : 0;
                $result = $api->createShareLink($input['code'], $input['password'], $ttl, $singleUse);
                sendJsonResponse($result);
                break;

            case 'authenticateWithToken':
                $error = validateRequired($input, ['token']);
                if ($error) {
                    sendErrorResponse($error);
                }
                $result = $api->authenticateWithToken($input['token']);
                sendJsonResponse($result);
                break;
                
            default:
                sendErrorResponse('Invalid action');
        }
        
    } else {
        sendErrorResponse('Method not allowed', 405);
    }
}
?>