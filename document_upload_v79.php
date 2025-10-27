<?php
// Start session and include CSRF handler at the VERY TOP
if (session_status() == PHP_SESSION_NONE) {session_start();}

include_once 'login_csrf_handler.php';

// First thing: validate the submitted token.
// The form data will send a 'csrf_token' field.
// FIX: Use the CSRFHandler class method instead of undefined function
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token. Please refresh the page.'
    ]);
    exit;
}

// --- Basic Setup ---
ini_set('display_errors', 0); // Production
ini_set('log_errors', 1);
// Ensure errors are logged to a file accessible to you (check php.ini or use error_log directive)
// Example: ini_set('error_log', '/var/log/php_errors.log');
error_reporting(E_ALL);

session_start(); // Ensure session is started to access user ID

header('Content-Type: application/json');
// --- Default Failure Response ---
$response = [
    'success' => false,
    'message' => 'Initialization failed. Unknown error.', // Slightly more informative default
    'newCategoryCreated' => false,
    'file_id' => null,
    'category_id' => null,
    'categoryName' => null,
    'filepath' => null,
    'preview_filepath' => null,
    'debug' => ['post_data' => $_POST ?? [], 'files_data' => $_FILES ?? [], 'session' => $_SESSION ?? []] // Log FILES too
];

require_once 'db_connect.php';
require_once 'login_permissions.php'; // For the permission check

if (!$mysqli || $mysqli->connect_error) {
    error_log("document_upload: Database connection failed - " . ($mysqli->connect_error ?? 'mysqli object not created'));
    $response['message'] = 'Internal Server Error: Database connection failed.';
    echo json_encode($response);
    exit;
}

// === FIX #1: CHECK PERMISSION AND GET THE LOGGED-IN USER'S ID ===
$rolesThatCanUpload = ['training_manager pilot', 'manager pilot', 'schedule manager', 'training manager', 'manager', 'admin', 'admin pilot'];
if (!userHasRole($rolesThatCanUpload, $mysqli)) {
    $response['message'] = 'You do not have permission to upload documents.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}
// Get the User ID from the session
if (!isset($_SESSION['HeliUser'])) {
    $response['message'] = "Authentication error: User ID not found in session.";
    http_response_code(401);
    echo json_encode($response);
    exit;
}
$userId = (int)$_SESSION['HeliUser'];

// --- Configuration: Upload Directory ---
$uploadDir = 'uploads/'; // Relative path to main uploads folder from where the script RUNS
$docSubDir = 'documents/'; // Subdirectory within uploads
$fullUploadPath = __DIR__ . '/' . $uploadDir . $docSubDir; // *** Use Absolute Path for reliability ***
$relativeUploadPathForDB = $uploadDir . $docSubDir; // Path to store in DB (relative to web root usually)

// --- Directory Checks (using absolute path) ---
if (!is_dir($fullUploadPath)) {
    // Try to create it
    if (!mkdir($fullUploadPath, 0775, true)) { // Recursive, set appropriate permissions
        error_log("document_upload: Target directory does not exist and could not be created: " . $fullUploadPath);
        $response['message'] = 'Server configuration error: Target directory missing.';
        echo json_encode($response);
        exit;
    }
     error_log("document_upload: Created target directory: " . $fullUploadPath);
} elseif (!is_writable($fullUploadPath)) {
    error_log("document_upload: Target directory is not writable: " . $fullUploadPath . " Check permissions.");
    $response['message'] = 'Server configuration error: Target directory not writable.';
    echo json_encode($response);
    exit;
} else {
     error_log("document_upload: Target directory exists and is writable: " . $fullUploadPath);
}

// ============================================
// --- CORE LOGIC RESTRUCTURED ---
// ============================================

$categoryId = null;
$newCategoryCreated = false;
$categoryName = null;
$fileUploadedSuccessfully = false; // Flag specifically for the file part
$dbRecordCreatedSuccessfully = false; // Flag specifically for the DB insert part
$newFileId = null;
$originalFilename = null;
$storedUniqueFilename = null; // Renamed file
$uploadedFilepathRelative = null; // Path stored in DB for original
$previewFilepathRelative = null; // Path stored in DB for preview

try {
    // --- 1. Process Category Information ---
    $mysqli->begin_transaction(); // Start transaction

    $newCategoryNameProvided = isset($_POST['newCategoryName']) ? trim($_POST['newCategoryName']) : null;
    $selectedCategoryId = isset($_POST['categoryId']) ? trim($_POST['categoryId']) : null;

    if (!empty($newCategoryNameProvided)) {
        // --- User typed a new name ---
        $categoryName = $newCategoryNameProvided;
        error_log("DEBUG: Processing provided New Category Name: '$categoryName'");

        $stmtCheck = $mysqli->prepare("SELECT id FROM document_categories WHERE LOWER(category) = LOWER(?)");
        if (!$stmtCheck) { throw new Exception("Prepare failed (check category): " . $mysqli->error); }
        $lowerCategoryName = strtolower($categoryName); // Prepare for binding
        $stmtCheck->bind_param("s", $lowerCategoryName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $existingCategory = $resultCheck->fetch_assoc();
            $categoryId = (int)$existingCategory['id'];
            error_log("DEBUG: Category '$categoryName' already exists. Using ID $categoryId.");
        } else {
            error_log("DEBUG: Creating new category '$categoryName'");
            $stmtInsert = $mysqli->prepare("INSERT INTO document_categories (category, created_by, company_id) VALUES (?, ?, ?)");
            if (!$stmtInsert) { throw new Exception("Prepare failed (insert category): " . $mysqli->error); }
            $stmtInsert->bind_param("sii", $categoryName, $userId, $company_id);
            if ($stmtInsert->execute()) {
                $categoryId = $stmtInsert->insert_id;
                $newCategoryCreated = true;
                error_log("DEBUG: New category created with ID $categoryId");
            } else {
                throw new Exception("Execute failed (insert category): " . $stmtInsert->error);
            }
            $stmtInsert->close();
        }
        $stmtCheck->close();

    } elseif (!empty($selectedCategoryId) && ctype_digit($selectedCategoryId)) { // Basic validation
        // --- User selected an existing category ---
        $categoryId = (int)$selectedCategoryId;
        // Fetch the name for consistency
        $stmtGetName = $mysqli->prepare("SELECT category FROM document_categories WHERE id = ?");
         if ($stmtGetName) {
             $stmtGetName->bind_param("i", $categoryId);
             if ($stmtGetName->execute()) {
                 $resultName = $stmtGetName->get_result();
                 if($resultName->num_rows > 0) {
                     $catRow = $resultName->fetch_assoc();
                     $categoryName = $catRow['category'];
                     error_log("DEBUG: Using selected category ID $categoryId, Name: '$categoryName'.");
                 } else {
                    error_log("WARN: Selected category ID $categoryId not found in DB.");
                    $categoryId = null; // Treat invalid selection as Uncategorized
                    $categoryName = 'Uncategorized';
                 }
             } else { error_log("ERROR: Failed to execute query to get category name for ID $categoryId");}
             $stmtGetName->close();
         } else { error_log("ERROR: Failed to prepare query to get category name."); }

    } else {
        // --- Neither new name typed nor valid category selected ---
        error_log("DEBUG: No valid category specified. Document will be uncategorized.");
        $categoryId = null; // Explicitly NULL for DB
        $categoryName = 'Uncategorized';
    }
    // --- End Category Processing ---


    // --- 2. Process File Upload (Only if a file was actually sent and OK) ---
    $fileWasProcessed = false; // Track if we attempted to process a file
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileWasProcessed = true; // We have a file to deal with
        $file = $_FILES['file'];
        error_log("DEBUG: File '{$file['name']}' received with NO errors. Size: {$file['size']}. Type: {$file['type']}. Tmp: {$file['tmp_name']}");

        $originalFilename = basename($file["name"]);
        $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $fileSize = $file['size'];
        // Use finfo for more reliable MIME type determination
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif']; // Customize as needed
        $maxFileSize = 50 * 1024 * 1024; // Example: 50 MB limit

        if (empty($fileExtension) || !in_array($fileExtension, $allowedExtensions)) {
             $response['message'] = "Invalid file type: '." . htmlspecialchars($fileExtension) . "'. Allowed types: " . implode(', ', $allowedExtensions);
             error_log("Upload Error: " . $response['message'] . " (Original: '$originalFilename')");
             // Don't set $fileUploadedSuccessfully = true;
        } elseif ($fileSize > $maxFileSize) {
             $response['message'] = "File is too large (" . round($fileSize / 1024 / 1024, 2) . " MB). Maximum size: " . round($maxFileSize / 1024 / 1024, 2) . " MB.";
             error_log("Upload Error: " . $response['message'] . " (Original: '$originalFilename')");
             // Don't set $fileUploadedSuccessfully = true;
        } else {
             // --- File is valid, proceed with move and DB insert ---
            $baseUniqueName = uniqid('doc_', true);
            $storedUniqueFilename = $baseUniqueName . '.' . $fileExtension;
            $destinationAbsolute = $fullUploadPath . $storedUniqueFilename; // Absolute path for move_uploaded_file
            $uploadedFilepathRelative = $relativeUploadPathForDB . $storedUniqueFilename; // Relative path for DB

            error_log("DEBUG: Attempting move_uploaded_file: '{$file['tmp_name']}' TO '$destinationAbsolute'");

            if (move_uploaded_file($file['tmp_name'], $destinationAbsolute)) {
                error_log("DEBUG: move_uploaded_file SUCCEEDED for original file: '$destinationAbsolute'. Relative path for DB: '$uploadedFilepathRelative'");
                $fileUploadedSuccessfully = true; // File move was successful

                // *** START CONVERSION LOGIC *** (Keep your existing logic here)
                $previewFilepathRelative = null; // Initialize
                $convertableExtensions = ['docx', 'doc', 'odt', 'rtf', 'xlsx', 'xls', 'ods', 'pptx', 'ppt', 'odp'];

                if (in_array($fileExtension, $convertableExtensions)) {
                   // Define PDF filename and path (relative for DB, absolute needed for checks/commands)
                   $pdfPreviewFilename = $baseUniqueName . '.pdf';
                   $pdfPreviewDestinationAbsolute = $fullUploadPath . $pdfPreviewFilename; // Absolute path to check existence
                   $previewFilepathRelative = $relativeUploadPathForDB . $pdfPreviewFilename; // Relative path to store in DB

                   // Get Absolute Paths needed for the command
                   $absoluteInputPath = realpath($destinationAbsolute); // Absolute path to the original file
                   $absoluteOutputDir = realpath($fullUploadPath); // Absolute path to the DIRECTORY

                   if (!$absoluteOutputDir || !$absoluteInputPath) {
                       error_log("ERROR: Could not resolve absolute paths for conversion. OutputDir: '$absoluteOutputDir', InputFile: '$absoluteInputPath'");
                   } else {
                       // Build LibreOffice Command (ensure path is correct for your server)
                       $sofficePath = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"'; // WINDOWS EXAMPLE - ADJUST FOR YOUR SERVER OS (e.g., '/usr/bin/libreoffice' or '/usr/bin/soffice')
                       $command = $sofficePath . " --headless --convert-to pdf --outdir " . escapeshellarg($absoluteOutputDir) . " " . escapeshellarg($absoluteInputPath);
                       // Redirect output (especially stderr) for debugging if needed
                       // $command .= " > /path/to/writable/log/conversion.log 2>&1"; // Example Linux logging
                       $command .= " > NUL 2>&1"; // Windows: discard output

                       error_log("DEBUG: Executing conversion command: " . $command);
                       exec($command, $cmd_output_ignored, $return_var);
                       error_log("DEBUG: Conversion command return code: " . $return_var);

                       // Check if conversion likely succeeded AND if the output file exists
                       clearstatcache(); // Important before file_exists check after exec
                       if ($return_var === 0 && file_exists($pdfPreviewDestinationAbsolute)) {
                           error_log("DEBUG: Conversion successful. PDF created at: " . $pdfPreviewDestinationAbsolute . ". Relative path for DB: '$previewFilepathRelative'");
                           // $previewFilepathRelative is already set correctly
                       } else {
                           error_log("ERROR: LibreOffice conversion FAILED or PDF not found at expected absolute path '$pdfPreviewDestinationAbsolute'. Return code: $return_var.");
                           $previewFilepathRelative = null; // Ensure it's null if conversion failed
                       }
                   }
                } else {
                    error_log("DEBUG: File extension '.$fileExtension' is not in the list for PDF conversion.");
                    $previewFilepathRelative = null;
                }
                // *** END CONVERSION LOGIC ***

                // --- Insert Document Record ---
                $sql = "INSERT INTO documents (original_filename, stored_filename, filepath, preview_filepath, mime_type, filesize, category_id, creator, company_id, upload_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $mysqli->prepare($sql);
                if (!$stmt) {
                    // If prepare fails, the file was moved but DB failed - need to clean up
                    unlink($destinationAbsolute); // Delete the uploaded file
                    error_log("DB Error: Prepare failed (insert document): " . $mysqli->error . ". Deleted orphaned file: $destinationAbsolute");
                    throw new Exception("Database error preparing document record."); // Trigger outer catch
                }

                // Bind params carefully - Ensure types match DB columns (s=string, i=integer, d=double, b=blob)
                // Assuming filesize is INT, category_id is INT, creator is INT
                $stmt->bind_param("sssssiii",
                    $originalFilename,
                    $storedUniqueFilename, // The unique name used for storage
                    $uploadedFilepathRelative, // Relative path to original
                    $previewFilepathRelative, // Relative path to preview (can be NULL)
                    $mimeType,
                    $fileSize,
                    $categoryId, // Can be NULL
                    $userId, // This is the logged-in user's ID
                    $company_id  // Add this parameter
                );

                if ($stmt->execute()) {
                    $newFileId = $stmt->insert_id;
                    $dbRecordCreatedSuccessfully = true; // DB insert was successful
                    error_log("DEBUG: Document record inserted successfully. ID: $newFileId");
                } else {
                    // Execute failed - clean up file
                    unlink($destinationAbsolute);
                    error_log("DB Error: Execute failed (insert document): " . $stmt->error . ". Deleted orphaned file: $destinationAbsolute");
                    throw new Exception("Database error saving document record."); // Trigger outer catch
                }
                $stmt->close();
                // --- End Insert Document ---

            } else { // move_uploaded_file failed
                $move_error = error_get_last();
                $errorMsg = $move_error['message'] ?? 'Unknown error';
                error_log("FATAL: move_uploaded_file FAILED moving '{$file['tmp_name']}' to '$destinationAbsolute'. PHP Error: $errorMsg");
                $response['message'] = 'Failed to save uploaded file to server. Check server logs and permissions for ' . dirname($destinationAbsolute);
                // Don't set $fileUploadedSuccessfully = true; -> this is correct
                // We might still have created a category, so don't throw exception yet, let the final response logic handle it.
            }
        } // End valid file check
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        // File was sent, but had an upload error (size, partial, etc.)
        $fileWasProcessed = true; // We attempted to process it
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => "File exceeds upload_max_filesize directive in php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "File exceeds MAX_FILE_SIZE directive specified in the HTML form.",
            UPLOAD_ERR_PARTIAL    => "File was only partially uploaded.",
            UPLOAD_ERR_NO_FILE    => "No file was uploaded.", // Should be caught by initial check, but belt-and-suspenders
            UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
        ];
        $errorCode = $_FILES['file']['error'];
        $response['message'] = $uploadErrors[$errorCode] ?? 'Unknown file upload error code: ' . $errorCode;
        error_log('Upload Error: ' . $response['message'] . ' (Error Code: ' . $errorCode . ')');
    } else {
        // No file was submitted in the form
        error_log("DEBUG: No file submitted with the form.");
    }
    // --- End File Processing ---


    // --- 3. Determine Final Response & Commit/Rollback ---

    // Decide overall success based on what happened
    if ($dbRecordCreatedSuccessfully) { // File uploaded AND DB record created
        $response['success'] = true;
        $response['message'] = $newCategoryCreated ? "New category '$categoryName' created AND file uploaded successfully." : "File uploaded successfully to category '$categoryName'.";
        $mysqli->commit(); // COMMIT transaction
        error_log("SUCCESS: DB record created. Committing transaction.");
    } elseif ($fileUploadedSuccessfully && !$dbRecordCreatedSuccessfully) { // File moved BUT DB failed (should have been caught by exception, but safety check)
        $response['success'] = false;
        // Message already set by exception or previous error
        if (empty($response['message']) || $response['message'] === 'Initialization failed. Unknown error.') {
             $response['message'] = "File uploaded but failed to save database record."; // Fallback message
        }
        $mysqli->rollback(); // ROLLBACK transaction
        error_log("FAILURE: File moved but DB insert failed. Rolling back transaction.");
        // File should have been unlinked by the exception handler block
    } elseif ($newCategoryCreated && !$fileWasProcessed) { // New category created, no file submitted/processed
        $response['success'] = true;
        $response['message'] = "New category '$categoryName' created successfully. No file was uploaded.";
        $mysqli->commit(); // COMMIT transaction (for the category)
        error_log("SUCCESS: New category created, no file processed. Committing transaction.");
    } elseif (!$newCategoryCreated && !$fileWasProcessed && (!empty($newCategoryNameProvided) || !empty($selectedCategoryId)) ) { // Existing category used/checked, no file submitted/processed
        $response['success'] = true; // Considered success as no action failed
        $response['message'] = "No file was uploaded. Category '$categoryName' selected/checked.";
         // No DB changes were made other than potentially checking, so commit/rollback doesn't strictly matter, but commit is safe.
        $mysqli->commit();
        error_log("INFO: No file processed, category checked/selected. Committing transaction.");
    } elseif (!$newCategoryCreated && !$fileWasProcessed && empty($newCategoryNameProvided) && empty($selectedCategoryId)) { // Neither category nor file provided
        $response['success'] = false; // Changed: This isn't really a success.
        $response['message'] = "No category specified and no file uploaded.";
        $mysqli->rollback(); // No changes to commit/rollback
        error_log("INFO: No category or file specified. No action taken.");
    } elseif ($fileWasProcessed && !$fileUploadedSuccessfully) { // Attempted file upload, but it failed (validation, move etc)
        $response['success'] = false;
        // Message should already be set by the file processing logic
        if (empty($response['message']) || $response['message'] === 'Initialization failed. Unknown error.') {
             $response['message'] = "File processing failed. Category '" . ($categoryName ?? 'Unknown') . "' status: " . ($newCategoryCreated ? 'Created' : 'Used/Checked'); // Fallback message
        }
        $mysqli->rollback(); // Rollback category creation if file failed
        error_log("FAILURE: File processing failed. Rolling back transaction. Message: " . $response['message']);
    } else {
        // Catch-all for any unexpected state
        $response['success'] = false;
         if (empty($response['message']) || $response['message'] === 'Initialization failed. Unknown error.') {
            $response['message'] = "An unexpected error occurred during the upload process.";
         }
        $mysqli->rollback();
        error_log("FAILURE: Unexpected state reached in final response logic. Rolling back. FileProcessed: $fileWasProcessed, FileOK: $fileUploadedSuccessfully, DB OK: $dbRecordCreatedSuccessfully, CatCreated: $newCategoryCreated");
    }


    // --- Populate response details if overall success ---
    if ($response['success']) {
        $response['file_id'] = $newFileId; // ID of the 'documents' row (null if only category)
        $response['filepath'] = $uploadedFilepathRelative; // Relative path to original (null if no file)
        $response['preview_filepath'] = $previewFilepathRelative; // Relative path to preview (null if no preview)
        $response['category_id'] = $categoryId; // ID of category used/created (null if Uncategorized)
        $response['categoryName'] = $categoryName; // Name of category used/created
        $response['newCategoryCreated'] = $newCategoryCreated; // Boolean
    }

} catch (Exception $e) {
    $mysqli->rollback(); // Rollback on any exception
    $response['success'] = false;
    $response['message'] = "Server Error: " . $e->getMessage(); // Report exception message
    error_log("Upload Exception caught: " . $e->getMessage() . " - Rolling back transaction.");
    error_log("Exception Trace: " . $e->getTraceAsString()); // Log trace for detailed debugging

    // Clean up file if it exists from a partial success before exception
    if (isset($destinationAbsolute) && file_exists($destinationAbsolute)) {
        unlink($destinationAbsolute);
        error_log("Deleted orphaned file '$destinationAbsolute' due to exception.");
    }
    if (isset($pdfPreviewDestinationAbsolute) && file_exists($pdfPreviewDestinationAbsolute)) {
        unlink($pdfPreviewDestinationAbsolute);
         error_log("Deleted orphaned preview file '$pdfPreviewDestinationAbsolute' due to exception.");
    }
}

// --- Final Output ---
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

// Add final category/file details to debug regardless of success/failure for diagnostics
$response['debug']['final_category'] = ['id' => $categoryId, 'name' => $categoryName, 'new_created' => $newCategoryCreated];
$response['debug']['final_file'] = ['db_id' => $newFileId, 'original' => $originalFilename, 'stored' => $storedUniqueFilename, 'rel_path' => $uploadedFilepathRelative, 'rel_preview_path' => $previewFilepathRelative, 'upload_ok' => $fileUploadedSuccessfully, 'db_ok' => $dbRecordCreatedSuccessfully];

error_log("DEBUG: Final JSON Response: " . json_encode($response));
echo json_encode($response);
exit;
?>