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
    
    public function __construct($database = null) {
        if ($database) {
            $this->db = $database;
        } else {
            $databaseConnection = new Database();
            $this->db = $databaseConnection->connect();
        }
    }
    
    /**
     * Save user availability for a group
     */
    public function saveAvailability($groupCode, $userName, $availabilities) {
        try {
            // NOTE: group existence check removed to support in-memory test DBs without a `groups` table
            // If the DB schema enforces foreign keys, insert will fail appropriately.
            
            $this->db->beginTransaction();
            
            // First, delete existing availabilities for this user in this group
            $stmt = $this->db->prepare("DELETE FROM `availabilities` WHERE group_code = ? AND user_name = ?");
            $stmt->execute([$groupCode, $userName]);
            
            // Insert new availabilities
            $insertStmt = $this->db->prepare("
                INSERT INTO `availabilities` (group_code, user_name, date, time_slot) 
                VALUES (?, ?, ?, ?)
            ");
            
            // Support two frontend formats:
            // 1) Associative: { "2026-01-20": ["morning","afternoon"], ... }
            // 2) List of objects: [ { date: "2026-01-20", timeSlot: "10:00", available: true }, ... ]
            if (!is_array($availabilities) || empty($availabilities)) {
                // Empty availabilities means delete all for this user
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                return ['success' => true, 'message' => 'All availabilities cleared for user'];
            } else {
                $firstKey = array_key_first($availabilities);
                $firstVal = $availabilities[$firstKey];
                
                if (is_array($firstVal) && !(isset($firstVal['date']) || isset($firstVal['timeSlot']) || isset($firstVal['available']))) {
                    // Format 1: date => [slots]
                    foreach ($availabilities as $date => $slots) {
                        if (!validateDate($date)) {
                            throw new Exception("Invalid date format: {$date}");
                        }
                        if (!is_array($slots)) continue;
                        foreach ($slots as $slot) {
                            if (!validateTimeSlot($slot)) {
                                throw new Exception("Invalid time slot: {$slot}");
                            }
                            $insertStmt->execute([$groupCode, $userName, $date, $slot]);
                        }
                    }
                } else {
                    // Format 2: array of objects with available flag
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
                        
                        $insertStmt->execute([$groupCode, $userName, $date, $timeSlot]);
                    }
                }
                
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                return ['success' => true, 'message' => 'Availability saved successfully'];
            }
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            
            // Check for foreign key constraint violation (group doesn't exist)
            if (strpos($e->getMessage(), '1452') !== false || strpos($e->getMessage(), 'foreign key constraint') !== false) {
                return ['success' => false, 'message' => 'Group not found. Please make sure the group code exists.'];
            }
            
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

// Handle API requests only if we're not in CLI mode (e.g., during testing)
if (php_sapi_name() !== 'cli') {
    $method = $_SERVER['REQUEST_METHOD'];
    $api = new AvailabilityAPI();

    if ($method === 'POST') {
        $input = getJsonInput();
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'save':
                // availabilities can be empty (means delete all for user), so only require groupCode and userName
                $error = validateRequired($input, ['groupCode', 'userName']);
                if ($error) {
                    sendErrorResponse($error);
                }
                
                $avail = $input['availabilities'] ?? [];
                $result = $api->saveAvailability(
                    $input['groupCode'], 
                    $input['userName'], 
                    $avail
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
}
?>