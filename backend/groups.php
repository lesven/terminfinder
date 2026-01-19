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
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    /**
     * Authenticate group with code and password
     */
    public function authenticateGroup($code, $password) {
        try {
            // Check if group exists
            $stmt = $this->db->prepare("SELECT code FROM `groups` WHERE code = ?");
            $stmt->execute([$code]);
            $group = $stmt->fetch();
            
            if (!$group) {
                // Group doesn't exist, create it with the provided password
                return $this->createGroup($code, $password);
            }
            
            // Group exists, check password
            $stmt = $this->db->prepare("SELECT password_hash FROM `group_passwords` WHERE group_code = ?");
            $stmt->execute([$code]);
            $passwordData = $stmt->fetch();
            
            if (!$passwordData) {
                return ['success' => false, 'message' => 'Group password not found'];
            }
            
            if (!verifyPassword($password, $passwordData['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid password'];
            }
            
            return ['success' => true, 'message' => 'Authentication successful'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create new group with password
     */
    private function createGroup($code, $password) {
        try {
            $this->db->beginTransaction();
            
            // Insert group
            $stmt = $this->db->prepare("INSERT INTO `groups` (code) VALUES (?)");
            $stmt->execute([$code]);
            
            // Insert password
            $passwordHash = hashPassword($password);
            $stmt = $this->db->prepare("INSERT INTO `group_passwords` (group_code, password_hash) VALUES (?, ?)");
            $stmt->execute([$code, $passwordHash]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Group created successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Failed to create group: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all group data (participants and their availabilities)
     */
    public function getGroupData($code) {
        try {
            $stmt = $this->db->prepare("
                SELECT user_name, date, time_slot 
                FROM `availabilities` 
                WHERE group_code = ? 
                ORDER BY user_name, date, time_slot
            ");
            $stmt->execute([$code]);
            $availabilities = $stmt->fetchAll();
            
            $groupData = [];
            foreach ($availabilities as $availability) {
                $user = $availability['user_name'];
                $date = $availability['date'];
                $timeSlot = $availability['time_slot'];
                
                if (!isset($groupData[$user])) {
                    $groupData[$user] = [];
                }
                if (!isset($groupData[$user][$date])) {
                    $groupData[$user][$date] = [];
                }
                
                $groupData[$user][$date][] = $timeSlot;
            }
            
            return ['success' => true, 'data' => $groupData];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get group data: ' . $e->getMessage()];
        }
    }

    /**
     * Create a share link token (requires group password) — returns token (plaintext) and expires_at
     */
    public function createShareLink($groupCode, $password, $ttlDays = 7, $singleUse = 0) {
        try {
            // Verify password
            $stmt = $this->db->prepare("SELECT password_hash FROM `group_passwords` WHERE group_code = ?");
            $stmt->execute([$groupCode]);
            $passwordData = $stmt->fetch();
            if (!$passwordData || !verifyPassword($password, $passwordData['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid password'];
            }

            // Generate token and store its hash
            $token = bin2hex(random_bytes(16)); // 32 hex chars = 128 bits
            $tokenHash = hash('sha256', $token);
            $expiresAt = $ttlDays ? date('Y-m-d H:i:s', strtotime("+{$ttlDays} days")) : null;

            $stmt = $this->db->prepare("INSERT INTO `share_links` (group_code, token_hash, expires_at, single_use) VALUES (?, ?, ?, ?)");
            $stmt->execute([$groupCode, $tokenHash, $expiresAt, (int)$singleUse]);

            return ['success' => true, 'token' => $token, 'expires_at' => $expiresAt];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create share link: ' . $e->getMessage()];
        }
    }

    /**
     * Authenticate using a share token (plaintext token provided by client)
     */
    public function authenticateWithToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->db->prepare("SELECT id, group_code, single_use, used_at FROM `share_links` WHERE token_hash = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['success' => false, 'message' => 'Invalid or expired token'];
            }

            if ($row['single_use'] && $row['used_at']) {
                return ['success' => false, 'message' => 'Token already used'];
            }

            // Mark single-use tokens as used
            if ($row['single_use']) {
                $update = $this->db->prepare("UPDATE `share_links` SET used_at = NOW() WHERE id = ?");
                $update->execute([$row['id']]);
            }

            return ['success' => true, 'groupCode' => $row['group_code']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to authenticate token: ' . $e->getMessage()];
        }
    }
}

// Handle API requests
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
?>