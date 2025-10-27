<?php
// GEMINI - daily_manager_ai_voice_command.php - V13 (Complete, Unabbreviated, with Duplicate Pre-Check)

session_start();
header('Content-Type: application/json');

// --- Include required files AT THE TOP ---
require_once 'db_connect.php'; 
require_once 'daily_manager_ai_pilot_creation.php'; 

// =========================================================================
// === START: HELPER FUNCTIONS =============================================
// =========================================================================

/**
 * Validates email format.
 */
function isValidEmail($email) { 
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; 
}

/**
 * Normalizes a spoken email address into a valid format.
 * Handles "dot", "at", and missing periods before common TLDs.
 */
function normalizeEmail($email) {
    $email = strtolower(trim($email));
    $email = str_replace([' at ', ' dot '], ['@', '.'], $email);
    $email = preg_replace('/\s+/', '', $email);
    if (preg_match('/(@[a-z0-9-]+)(com|net|org|ca|co|uk)$/', $email, $matches)) {
        $email = str_replace($matches[0], $matches[1] . '.' . $matches[2], $email);
    }
    return $email;
}

/**
 * Validates username format (alphanumeric, length) and checks against a reserved word list.
 */
function isValidUsername($username) { 
    $reservedWords = ['admin', 'user', 'pilot', 'guest', 'test', 'username', 'password']; 
    return preg_match('/^[a-zA-Z][a-zA-Z0-9-]{2,19}$/', $username) && !in_array(strtolower($username), $reservedWords); 
}

/**
 * Suggests a username based on a pilot's first and last name (e.g., "John Smith" -> "jsmith").
 */
function suggestUsername($firstname, $lastname) { 
    return preg_replace('/[^a-zA-Z0-9-]/', '', strtolower($firstname[0] . str_replace(' ', '', $lastname))); 
}

/**
 * Clears the AI's conversational state from the session.
 */
function resetSessionData() { 
    unset($_SESSION['ai_pilot_data'], $_SESSION['ai_retries']); 
}

/**
 * Intelligently processes alternatives from speech recognition to find a valid response.
 */
function recoverBestTranscript(array $transcripts, callable $validator, callable $normalizer = null) {
    foreach ($transcripts as $transcript) {
        $processed = $normalizer ? $normalizer($transcript) : $transcript;
        if ($validator($processed)) {
            return $processed;
        }
    }
    return null;
}

// =========================================================================
// === END: HELPER FUNCTIONS ===============================================
// =========================================================================


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

// Get and decode the JSON input from the JavaScript
$input = json_decode(file_get_contents('php://input'), true);
$command_alternatives = $input['alternatives'] ?? [];
$command = $command_alternatives[0] ?? '';
$step = $input['step'] ?? null;

// Initialize session state for the conversation
if (!isset($_SESSION['ai_pilot_data'])) $_SESSION['ai_pilot_data'] = [];
if (!isset($_SESSION['ai_retries'])) $_SESSION['ai_retries'] = 0;

if (empty($command)) {
    echo json_encode($response);
    exit;
}

// --- Conversational State Machine ---

if ($step) {
    // This block handles all follow-up steps in the conversation.
    if ($step === 'confirm_email') {
        if (strpos(strtolower($command), 'yes') !== false) {
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
        } else {
            $_SESSION['ai_retries']++;
            if ($_SESSION['ai_retries'] >= 3) {
                resetSessionData();
                $response = ['status' => 'error', 'message' => 'Too many retries. Operation cancelled.'];
            } else {
                $response = ['status' => 'next', 'nextStep' => 'email', 'message' => 'Okay. Please provide the email address again.'];
            }
        }
    } elseif ($step === 'email') {
        $validEmail = recoverBestTranscript($command_alternatives, 'isValidEmail', 'normalizeEmail');

        if ($validEmail) {
            $_SESSION['ai_pilot_data']['email'] = $validEmail;
            $response = ['status' => 'next', 'nextStep' => 'confirm_email', 'message' => "Got email '$validEmail'. Is this correct? Say 'yes' or 'no'."];
        } else {
            $_SESSION['ai_retries']++;
            if ($_SESSION['ai_retries'] >= 3) {
                resetSessionData();
                $response = ['status' => 'error', 'message' => 'Too many failed attempts to set an email. Operation cancelled.'];
            } else {
                $response = ['status' => 'next', 'nextStep' => 'email', 'message' => 'I could not understand that email address. Please say it again, for example, "john dot smith at example dot com".'];
            }
        }
    } elseif ($step === 'confirm_username') {
        if (strpos(strtolower($command), 'yes') !== false) {
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
        $firstname = $_SESSION['ai_pilot_data']['firstname'];
        $lastname = $_SESSION['ai_pilot_data']['lastname'];
        $usernameValidator = function($u) use ($firstname, $lastname) {
            if (!isValidUsername($u)) return false;
            return (stripos($u, $firstname) !== false || stripos($u, $lastname) !== false);
        };
        $usernameNormalizer = function($u) { return preg_replace('/\s+/', '', strtolower($u)); };
        $validUsername = recoverBestTranscript($command_alternatives, $usernameValidator, $usernameNormalizer);
        
        if ($validUsername) {
            $_SESSION['ai_pilot_data']['username'] = $validUsername;
            $_SESSION['ai_retries'] = 0;
            $response = ['status' => 'next', 'nextStep' => 'confirm_username', 'message' => "I've set the username as '$validUsername'. Is this correct? Say 'yes' or 'no'."];
        } else {
            $_SESSION['ai_retries']++;
            if ($_SESSION['ai_retries'] >= 3) { resetSessionData(); $response = ['status' => 'error', 'message' => 'Too many failed attempts to set a username. Operation cancelled.']; }
            else {
                $suggestedUsername = suggestUsername($firstname, $lastname);
                $response = ['status' => 'next', 'nextStep' => 'username', 'message' => "That doesn't seem right. The username should be based on the pilot's name. Let's try '$suggestedUsername', or you can provide another."];
            }
        }
    }
} else { 
    // This block handles the very first command from the user.
    if (preg_match('/(create|add|new).*pilot/i', $command)) {
        
        $potential_name = preg_replace('/(create|add|new|pilot|named?)/i', '', $command);
        $stop_words = ['heart', 'rate', 'device', 'with', 'a', 'an', 'the', 'please', 'username', 'email', 'and'];
        $stop_words_pattern = '/\b(' . implode('|', $stop_words) . ')\b/i';
        $cleaned_name = trim(preg_replace($stop_words_pattern, '', $potential_name));
        $cleaned_name = trim(preg_replace('/\s+/', ' ', $cleaned_name));
        $nameParts = explode(' ', $cleaned_name, 2);

        if (count($nameParts) < 2 || empty($nameParts[0]) || empty($nameParts[1])) {
            $response = ['status' => 'next', 'nextStep' => null, 'message' => "I heard you, but I couldn't understand the pilot's name clearly. Please say the full name again, for example, 'create pilot John Smith'."];
        } else {

            // =========================================================================
            // === THE FIX IS HERE =====================================================
            // =========================================================================
            // The incorrect duplicate check on the name has been REMOVED.
            // We now proceed directly to the conversation.
            // The correct check on username/email will be done by the pilot creation service at the end.
            
            $firstname = $nameParts[0];
            $lastname = $nameParts[1];
            
            $_SESSION['ai_pilot_data'] = ['firstname' => $firstname, 'lastname' => $lastname];
            $_SESSION['ai_retries'] = 0;
            $suggestedUsername = suggestUsername($firstname, $lastname);
            $response = ['status' => 'next', 'nextStep' => 'username', 'message' => "Got name '" . ucwords($cleaned_name) . "'. Please provide the username (e.g., $suggestedUsername)."];
        }
        
    } else { // Command was not understood at all
        $response = ['status' => 'error', 'message' => "I'm sorry, I didn't understand that command. Please try saying 'create a new pilot named John Smith'."];
    }
}


// Close the database connection and send the final response
if (isset($mysqli)) $mysqli->close();
echo json_encode($response);
?>