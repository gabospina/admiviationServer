<?php
class ApiResponse {
    public $success = true;  // Changed from private to public
    public $message = '';    // Changed from private to public
    public $data = null;     // Changed from private to public

    public function setSuccess($success) {
        $this->success = $success;
        return $this;
    }

    public function setMessage($message) {
        $this->message = $message;
        return $this;
    }

    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    public function setError($message) {
        $this->success = false;
        $this->message = $message;
        return $this;
    }

    public function send() {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data
        ]);
        exit;
    }

    public static function logError($message, $context = []) {
        $logDir = dirname(__DIR__) . '/logs';
        $logFile = $logDir . '/error.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                // If we can't create the directory, log to PHP's error log
                error_log("Failed to create log directory: " . $logDir);
                return;
            }
        }
        
        $logMessage = date('Y-m-d H:i:s') . " - " . $message;
        if (!empty($context)) {
            $logMessage .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
        }
        $logMessage .= "\n";
        
        if (!error_log($logMessage, 3, $logFile)) {
            // Fallback to PHP's error log if file logging fails
            error_log("Failed to write to log file: " . $logMessage);
        }
    }
}

function validateInput($required = [], $optional = []) {
    $errors = [];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $errors[] = "Missing required field: $field";
        }
    }
    return $errors;
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}