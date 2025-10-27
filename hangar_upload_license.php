<?php
// hangar_upload_license.php - REFACTORED FOR NEW DATABASE STRUCTURE

session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

header('Content-Type: application/json');
require_once 'db_connect.php';
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // --- Security Checks ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        throw new Exception('The uploaded file likely exceeds the server\'s post_max_size limit.', 400);
    }
    // REMOVED: CSRFHandler::validateToken
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- Input Validation ---
    $pilotId = filter_input(INPUT_POST, 'pilotId', FILTER_VALIDATE_INT);
    $field_key = trim($_POST['validityField'] ?? ''); // Changed variable name for clarity
    if (!$pilotId || empty($field_key)) {
        throw new Exception("Invalid pilot ID or validity field specified.", 400);
    }
    if (!isset($_FILES['licenseFile']) || $_FILES['licenseFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded or an upload error occurred.", 400);
    }
    
    // --- Whitelist Security Check (Still essential) ---
    $stmt_check = $mysqli->prepare("SELECT id FROM user_company_licence_fields WHERE company_id = ? AND field_key = ?");
    if (!$stmt_check) throw new Exception("DB Error preparing to validate field.");
    $stmt_check->bind_param("is", $company_id, $field_key);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    $stmt_check->close();

    // --- File Validation ---
    $file = $_FILES['licenseFile'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file['size'] > 5 * 1024 * 1024) throw new Exception("File is too large (Max 5MB).", 400);
    if (!in_array($fileExt, ['pdf', 'jpg', 'jpeg', 'png'])) throw new Exception("Invalid file type.", 400);

    // --- File Path Preparation ---
    $uploadDir = __DIR__ . '/uploads/licenses/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $newFileName = "pilot{$pilotId}_{$field_key}_" . uniqid() . "." . $fileExt;
    $targetPath = $uploadDir . $newFileName;
    $dbPath = 'uploads/licenses/' . $newFileName;

    // --- REFACTORED Database Operations ---
    $mysqli->begin_transaction();

    // 1. Get and delete the old document file if it exists, now from the new table.
    $sqlSelect = "SELECT document_path FROM user_licence_data WHERE user_id = ? AND field_key = ?";
    $stmt_select = $mysqli->prepare($sqlSelect);
    if(!$stmt_select) throw new Exception("DB Prepare Error (Select): ".$mysqli->error);
    $stmt_select->bind_param("is", $pilotId, $field_key);
    $stmt_select->execute();
    $oldPathResult = $stmt_select->get_result();
    if ($oldPathRow = $oldPathResult->fetch_assoc()) {
        $oldPath = $oldPathRow['document_path'];
        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
            unlink(__DIR__ . '/' . $oldPath);
        }
    }
    $stmt_select->close();

    // 2. Move the new file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Server error: Failed to save uploaded file.");
    }

    // 3. Update the database with the path to the new document using our robust method.
    $sqlUpdate = "
        INSERT INTO user_licence_data (user_id, company_id, field_key, document_path)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE document_path = VALUES(document_path)
    ";
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if(!$stmt_update) throw new Exception("DB Prepare Error (Update): ".$mysqli->error);
    $stmt_update->bind_param("iiss", $pilotId, $company_id, $field_key, $dbPath);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update database with new file path.");
    }
    $stmt_update->close();
    
    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = 'File uploaded successfully.';
    $response['newPath'] = $dbPath;

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->in_transaction) {
        $mysqli->rollback();
    }
    $response['error'] = $e->getMessage();
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
}

echo json_encode($response);
?>