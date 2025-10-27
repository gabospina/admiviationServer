<?php
// hangar_send_thoughts.php
// Important: You must replace no-reply@yourdomain.com in the $headers variable with
// a real email address associated with your website's domain. This is critical to
// prevent your emails from being marked as spam.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set the response type to JSON
header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

// --- 1. Security & Validation ---
// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['error'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['HeliUser'])) {
    http_response_code(401); // Unauthorized
    $response['error'] = 'You must be logged in to send a message.';
    echo json_encode($response);
    exit;
}

// --- 2. Get and Sanitize Input Data ---
$message = trim($_POST['message'] ?? '');

// The message is the only required field
if (empty($message)) {
    http_response_code(400); // Bad Request
    $response['error'] = 'A message is required.';
    echo json_encode($response);
    exit;
}

// Sanitize optional fields
$from_name = !empty($_POST['name']) ? filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING) : 'Anonymous';
$from_email = !empty($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : 'Not Provided';

// If an email was provided but it's invalid, return an error
if (!empty($_POST['email']) && !$from_email) {
    http_response_code(400);
    $response['error'] = 'The provided email address is not valid.';
    echo json_encode($response);
    exit;
}

// --- 3. Construct and Send the Email ---
try {
    // --- Email Configuration ---
    $to = 'gabospina@yahoo.com'; // The manager's email address
    $subject = 'New Message from Hangar "Thoughts" Form';

    // Build the email body
    $body = "You have received a new message from a user.\n\n";
    $body .= "----------------------------------------\n";
    $body .= "From Name:  " . $from_name . "\n";
    $body .= "From Email: " . $from_email . "\n";
    $body .= "User ID:    " . $_SESSION['HeliUser'] . "\n"; // Include the logged-in user's ID
    $body .= "----------------------------------------\n\n";
    $body .= "Message:\n" . $message . "\n";

    // Build the email headers
    // Using a valid "From" address on your server is important for deliverability
    $headers = 'From: no-reply@yourdomain.com' . "\r\n" .
               'Reply-To: ' . $from_email . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    // Send the email
    if (mail($to, $subject, $body, $headers)) {
        $response['success'] = true;
        $response['message'] = 'Your message has been sent successfully!';
    } else {
        // This error happens if the mail server is misconfigured
        throw new Exception("The mail server failed to send the email.");
    }
    
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $response['error'] = $e->getMessage();
    // Log the error for your own records
    error_log("hangar_send_thoughts.php Error: " . $e->getMessage());
}

// --- 4. Send the JSON Response ---
echo json_encode($response);
?>