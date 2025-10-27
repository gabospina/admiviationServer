<?php
// feedback_contact_email.php - FINAL, BULLETPROOF VERSION WITH OUTPUT BUFFERING

// Start output buffering immediately. This captures all potential warnings/notices.
ob_start();

// Use the PHPMailer library
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load the PHPMailer files
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false]; // Default response

// --- All the logic runs inside a try/catch block for safety ---
try {
    // --- 1. Security Check & Input Validation ---
    if (!isset($_SESSION['HeliUser'])) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid session. Please refresh the page.', 403);
        }
    }

    $message = trim($_POST['message'] ?? '');
    $from_name = !empty($_POST['name']) ? htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8') : '';
    $from_email = trim($_POST['email'] ?? '');

    if (empty($message)) {
        throw new Exception('A message is required.', 400);
    }
    // Only require email for the public form
    if (!isset($_SESSION['HeliUser']) && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
         throw new Exception('A valid email address is required.', 400);
    }

    // --- 2. Construct and Send the Email using PHPMailer ---
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'mail.admiviation.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'contact@admiviation.com';
    $mail->Password   = 'p,MTds6V{OQ1'; // Ensure this is the correct, real password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    // Recipients
    $mail->setFrom('contact@admiviation.com', 'Admiviation Platform');
    $mail->addAddress('gabospina@yahoo.com', 'Admin');
    if (!empty($from_email) && filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $mail->addReplyTo($from_email, $from_name);
    }

    // Content
    $mail->isHTML(false);
    if (isset($_SESSION['HeliUser'])) {
        $mail->Subject = 'New Feedback from Admiviation User';
        $body = "From User (ID: {$_SESSION['HeliUser']}): " . $from_name;
    } else {
        $mail->Subject = 'New Message from Admiviation Contact Form';
        $body = "From Visitor: " . $from_name . " <" . $from_email . ">";
    }
    $body .= "\n\n----------------------------------------\n\n" . $message;
    $mail->Body = $body;

    $mail->send();
    $response['success'] = true;
    $response['message'] = 'Your message has been sent successfully!';

} catch (Exception $e) {
    $response['error'] = "Message could not be sent. Please contact support.";
    // Set HTTP status code from the exception if available, otherwise default to 500
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    error_log("Feedback Form Error: " . $e->getMessage());
}

// --- FINAL, CLEAN OUTPUT ---
// Clear any accidental output (like PHP warnings) that was captured by ob_start()
ob_end_clean();

// Set the final header and echo the pure JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit(); // Stop script execution
?> 