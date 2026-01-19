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
 * Terminfinder Availability API
 * Handles saving and retrieving user availabilities
 */
class AvailabilityAPI {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->connect();
    }
    
    /**
     * Save user availability for a group
     */
    public function saveAvailability($groupCode, $userName, $availabilities) {
        try {
            $this->db->beginTransaction();
            
            // First, delete existing availabilities for this user in this group
            $stmt = $this->db->prepare("DELETE FROM `availabilities` WHERE group_code = ? AND user_name = ?");
            $stmt->execute([$groupCode, $userName]);
            
            // Insert new availabilities
            $stmt = $this->db->prepare("
                INSERT INTO `availabilities` (group_code, user_name, date, time_slot) 
                VALUES (?, ?, ?, ?)
            ");
            
            // Handle array format from frontend: [{date: "2026-01-20", timeSlot: "10:00", available: true}]
            foreach ($availabilities as $availability) {
                if (!isset($availability['available']) || !$availability['available']) {
                    continue; // Skip unavailable time slots
                }
                
                $date = $availability['date'];
                $timeSlot = $availability['timeSlot'];
                
                if (!validateDate($date)) {
                    throw new Exception("Invalid date format: {$date}");
                }
                
                if (!validateTimeSlot($timeSlot)) {
                    throw new Exception("Invalid time slot: {$timeSlot}");
                }
                
                $stmt->execute([$groupCode, $userName, $date, $timeSlot]);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Availability saved successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Failed to save availability: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get availability for a specific user and group
     */
    public function getUserAvailability($groupCode, $userName) {
        try {
            $stmt = $this->db->prepare("
                SELECT date, time_slot 
                FROM `availabilities` 
                WHERE group_code = ? AND user_name = ?
                ORDER BY date, time_slot
            ");
            $stmt->execute([$groupCode, $userName]);
            $availabilities = $stmt->fetchAll();
            
            $userAvailability = [];
            foreach ($availabilities as $availability) {
                $date = $availability['date'];
                $timeSlot = $availability['time_slot'];
                
                if (!isset($userAvailability[$date])) {
                    $userAvailability[$date] = [];
                }
                
                $userAvailability[$date][] = $timeSlot;
            }
            
            return ['success' => true, 'data' => $userAvailability];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get user availability: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all participants in a group
     */
    public function getParticipants($groupCode) {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT user_name 
                FROM `availabilities` 
                WHERE group_code = ? 
                ORDER BY user_name
            ");
            $stmt->execute([$groupCode]);
            $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return ['success' => true, 'data' => $participants];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to get participants: ' . $e->getMessage()];
        }
    }
}

// Handle API requests
$method = $_SERVER['REQUEST_METHOD'];
$api = new AvailabilityAPI();

if ($method === 'POST') {
    $input = getJsonInput();
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'save':
            $error = validateRequired($input, ['groupCode', 'userName', 'availabilities']);
            if ($error) {
                sendErrorResponse($error);
            }
            
            $result = $api->saveAvailability(
                $input['groupCode'], 
                $input['userName'], 
                $input['availabilities']
            );
            sendJsonResponse($result);
            break;
            
        case 'getUserAvailability':
            $error = validateRequired($input, ['groupCode', 'userName']);
            if ($error) {
                sendErrorResponse($error);
            }
            
            $result = $api->getUserAvailability($input['groupCode'], $input['userName']);
            sendJsonResponse($result);
            break;
            
        case 'getParticipants':
            $error = validateRequired($input, ['groupCode']);
            if ($error) {
                sendErrorResponse($error);
            }
            
            $result = $api->getParticipants($input['groupCode']);
            sendJsonResponse($result);
            break;
            
        default:
            sendErrorResponse('Invalid action');
    }
    
} else {
    sendErrorResponse('Method not allowed', 405);
}
?>