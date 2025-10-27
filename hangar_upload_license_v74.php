<?php
// hangar_upload_license.php - FINAL DYNAMIC & SECURE VERSION

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- Security Checks ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        throw new Exception('The uploaded file likely exceeds the server\'s post_max_size limit.', 400);
    }
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- Input Validation ---
    $pilotId = filter_input(INPUT_POST, 'pilotId', FILTER_VALIDATE_INT);
    $validityField = trim($_POST['validityField'] ?? '');
    if (!$pilotId || empty($validityField)) {
        throw new Exception("Invalid pilot ID or validity field specified.", 400);
    }
    if (!isset($_FILES['licenseFile']) || $_FILES['licenseFile']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("No file uploaded or an upload error occurred.", 400);
    }
    
    // =========================================================================
    // === THE FIX: Create a DYNAMIC whitelist from the database             ===
    // =========================================================================
    $allowedFields = [];
    $stmt_get_fields = $mysqli->prepare("SELECT field_key FROM user_company_licence_fields WHERE company_id = ?");
    if (!$stmt_get_fields) throw new Exception("DB Error preparing to get fields.");
    $stmt_get_fields->bind_param("i", $company_id);
    $stmt_get_fields->execute();
    $result_fields = $stmt_get_fields->get_result();
    while ($row = $result_fields->fetch_assoc()) {
        $allowedFields[] = $row['field_key'];
    }
    $stmt_get_fields->close();

    if (empty($allowedFields) || !in_array($validityField, $allowedFields)) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    // Now we know the field is valid for this company.
    $documentColumn = $validityField . '_doc';
    // =========================================================================

    // --- File Validation ---
    $file = $_FILES['licenseFile'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file['size'] > 5 * 1024 * 1024) throw new Exception("File is too large (Max 5MB).", 400);
    if (!in_array($fileExt, ['pdf', 'jpg', 'jpeg', 'png'])) throw new Exception("Invalid file type.", 400);

    // --- File Path Preparation ---
    $uploadDir = __DIR__ . '/uploads/licenses/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    $newFileName = "pilot{$pilotId}_{$validityField}_" . uniqid() . "." . $fileExt;
    $targetPath = $uploadDir . $newFileName;
    $dbPath = 'uploads/licenses/' . $newFileName;

    // --- Database Operations ---
    $mysqli->begin_transaction();

    // 1. Get and delete the old document file if it exists
    $sqlSelect = "SELECT `$documentColumn` FROM user_licences_validity WHERE user_id = ?";
    $stmt_select = $mysqli->prepare($sqlSelect);
    if(!$stmt_select) throw new Exception("DB Prepare Error (Select): ".$mysqli->error);
    $stmt_select->bind_param("i", $pilotId);
    $stmt_select->execute();
    $oldPathResult = $stmt_select->get_result();
    if ($oldPathRow = $oldPathResult->fetch_assoc()) {
        $oldPath = $oldPathRow[$documentColumn];
        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
            unlink(__DIR__ . '/' . $oldPath);
        }
    }
    $stmt_select->close();

    // 2. Move the new file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Server error: Failed to save uploaded file.");
    }

    // 3. Update the database with the path to the new document
    $sqlUpdate = "
        INSERT INTO user_licences_validity (user_id, `$documentColumn`)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `$documentColumn` = VALUES(`$documentColumn`)
    ";
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if(!$stmt_update) throw new Exception("DB Prepare Error (Update): ".$mysqli->error);
    $stmt_update->bind_param("is", $pilotId, $dbPath);
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