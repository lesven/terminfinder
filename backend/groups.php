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
            
        default:
            sendErrorResponse('Invalid action');
    }
    
} else {
    sendErrorResponse('Method not allowed', 405);
}
?>