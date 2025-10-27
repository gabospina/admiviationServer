<?php
// daily_manager_ai_voice_command.php - FINAL VERSION (Complete Logic)

session_start();
header('Content-Type: application/json');

// --- Include required files AT THE TOP ---
require_once 'db_connect.php'; 
require_once 'daily_manager_ai_pilot_creation.php'; 

// --- Helper Functions ---
function isValidEmail($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function normalizeEmail($email) { return preg_replace('/\s+/', '', str_replace([' at ', ' dot '], ['@', '.'], strtolower(trim($email)))); }
function isValidUsername($username) { $reservedWords = ['admin', 'user', 'pilot', 'guest']; return preg_match('/^[a-zA-Z][a-zA-Z0-9-]{2,19}$/', $username) && !in_array(strtolower($username), $reservedWords); }
function suggestUsername($firstname, $lastname) { return preg_replace('/[^a-zA-Z0-9-]/', '', strtolower($firstname[0] . str_replace(' ', '', $lastname))); }
function resetSessionData() { unset($_SESSION['ai_pilot_data'], $_SESSION['ai_retries']); }

// --- Main Script Execution ---

// Initialize a default response. This will be sent if something goes wrong.
$response = ['status' => 'error', 'message' => 'Invalid command'];

// Authentication check
if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401);
    $response['message'] = 'Authentication required';
    echo json_encode($response);
    exit;
}

// Get and decode the JSON input
$input = json_decode(file_get_contents('php://input'), true);
$command = trim($input['command'] ?? '');
$step = $input['step'] ?? null;

// Initialize session state for the conversation
if (!isset($_SESSION['ai_pilot_data'])) $_SESSION['ai_pilot_data'] = [];
if (!isset($_SESSION['ai_retries'])) $_SESSION['ai_retries'] = 0;

if (empty($command)) {
    echo json_encode($response);
    exit;
}

// --- Conversational State Machine ---

if ($step === 'confirm_email') {
    if (strtolower($command) === 'yes' || strpos(strtolower($command), 'yes') !== false) {
        $pilotData = [
            'firstname' => $_SESSION['ai_pilot_data']['firstname'],
            'lastname' => $_SESSION['ai_pilot_data']['lastname'],
            'username' => $_SESSION['ai_pilot_data']['username'],
            'email' => $_SESSION['ai_pilot_data']['email'],
            'password' => 'Password123!', 'confpassword' => 'Password123!',
            'is_active' => 1, 'access_level' => 1, 'admin' => 0
        ];
        
        $result = create_new_pilot($pilotData, $mysqli, $_SESSION['company_id']);
        resetSessionData();

        if ($result['success']) {
            $response = ['status' => 'success', 'action' => 'CREATE_PILOT', 'message' => $result['message']];
        } else {
            $response = ['status' => 'error', 'message' => $result['error'] ?? 'Failed to create pilot.'];
        }
    } else { // --- MISSING LOGIC WAS HERE ---
        $_SESSION['ai_retries']++;
        if ($_SESSION['ai_retries'] >= 3) {
            resetSessionData();
            $response = ['status' => 'error', 'message' => 'Too many retries. Operation cancelled.'];
        } else {
            $response = ['status' => 'next', 'nextStep' => 'email', 'message' => 'Okay. Please provide the email address again.'];
        }
    }
} elseif ($step === 'email') {
    $normalizedEmail = normalizeEmail($command);
    if (!isValidEmail($normalizedEmail)) {
        $_SESSION['ai_retries']++;
        if ($_SESSION['ai_retries'] >= 3) { resetSessionData(); $response = ['status' => 'error', 'message' => 'Too many retries. Operation cancelled.']; }
        else { $response = ['status' => 'next', 'nextStep' => 'email', 'message' => 'Invalid email format. Please say the email again, for example, "john dot smith at example dot com".']; }
    } else {
        $_SESSION['ai_pilot_data']['email'] = $normalizedEmail;
        $response = ['status' => 'next', 'nextStep' => 'confirm_email', 'message' => "Got email '$normalizedEmail'. Is this correct? Say 'yes' or 'no'."];
    }
} elseif ($step === 'confirm_username') {
    if (strtolower($command) === 'yes' || strpos(strtolower($command), 'yes') !== false) {
        $response = ['status' => 'next', 'nextStep' => 'email', 'message' => "Great. Now, please provide the email address for {$_SESSION['ai_pilot_data']['firstname']}."];
    } else {
        $_SESSION['ai_retries']++;
        if ($_SESSION['ai_retries'] >= 3) { resetSessionData(); $response = ['status' => 'error', 'message' => 'Too many retries. Operation cancelled.']; }
        else {
            $suggestedUsername = suggestUsername($_SESSION['ai_pilot_data']['firstname'], $_SESSION['ai_pilot_data']['lastname']);
            $response = ['status' => 'next', 'nextStep' => 'username', 'message' => "Okay. Please provide the username again (e.g., $suggestedUsername)."];
        }
    }
} elseif ($step === 'username') {
    $potential_username = preg_replace('/\s+/', '', strtolower($command));
    if (isValidUsername($potential_username)) {
        $_SESSION['ai_pilot_data']['username'] = $potential_username;
        $_SESSION['ai_retries'] = 0;
        $response = ['status' => 'next', 'nextStep' => 'confirm_username', 'message' => "I've set the username as '$potential_username'. Is this correct? Say 'yes' or 'no'."];
    } else {
        $_SESSION['ai_retries']++;
        if ($_SESSION['ai_retries'] >= 3) { resetSessionData(); $response = ['status' => 'error', 'message' => 'Too many retries. Operation cancelled.']; }
        else {
            $suggestedUsername = suggestUsername($_SESSION['ai_pilot_data']['firstname'], $_SESSION['ai_pilot_data']['lastname']);
            $response = ['status' => 'next', 'nextStep' => 'username', 'message' => "That username is invalid. Let's try this one: '$suggestedUsername'. Or you can provide another."];
        }
    }
} else { // This is the initial command parsing
    if (preg_match('/(create|add|new).*pilot.*?(?:named?\s+)?([\w\s]+)/i', $command, $matches)) {
        $name = trim($matches[2]);
        $nameParts = explode(' ', $name, 2);
        if (count($nameParts) < 2) {
            $response = ['status' => 'next', 'nextStep' => null, 'message' => 'Please provide both a first and last name for the pilot.'];
        } else {
            $firstname = $nameParts[0];
            $lastname = $nameParts[1];
            $_SESSION['ai_pilot_data'] = ['firstname' => $firstname, 'lastname' => $lastname];
            $_SESSION['ai_retries'] = 0;
            $suggestedUsername = suggestUsername($firstname, $lastname);
            $response = ['status' => 'next', 'nextStep' => 'username', 'message' => "Got name '$name'. Please provide the username (e.g., $suggestedUsername)."];
        }
    } else { // Command was not understood at all
        $response = ['status' => 'error', 'message' => "I'm sorry, I didn't understand that command. Please try saying 'create a new pilot named John Smith'."];
    }
}

// Close the database connection and send the final response
if (isset($mysqli)) $mysqli->close();
echo json_encode($response);
?>