<?php
/**
 * Terminfinder API Helper Functions
 */

function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

function sendErrorResponse($message, $statusCode = 400) {
    sendJsonResponse(['error' => $message], $statusCode);
}

function validateRequired($data, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return "Field '{$field}' is required";
        }
        
        // Handle different types of values
        if (is_string($data[$field])) {
            if (empty(trim($data[$field]))) {
                return "Field '{$field}' is required";
            }
        } elseif (is_array($data[$field])) {
            if (empty($data[$field])) {
                return "Field '{$field}' is required";
            }
        } elseif ($data[$field] === null || $data[$field] === '') {
            return "Field '{$field}' is required";
        }
    }
    return null;
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendErrorResponse('Invalid JSON input');
    }
    
    return $data ?: [];
}

function corsHeaders() {
    // Skip CORS handling on CLI to avoid unnecessary headers and warnings
    if (php_sapi_name() === 'cli') {
        return;
    }

    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function validateTimeSlot($timeSlot) {
    // Allow both time formats: specific times (10:00) and general periods (morning, afternoon, evening)
    if (preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $timeSlot)) {
        return true; // Valid HH:MM format
    }
    
    return in_array($timeSlot, ['morning', 'afternoon', 'evening']);
}

function validateDate($date) {
    // Delegate validation to the LocalDate Value Object for consistency
    if (!is_string($date)) {
        return false;
    }

    return \Terminfinder\Domain\ValueObject\LocalDate::isValid($date);
}
?>