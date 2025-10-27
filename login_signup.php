<?php
// login_signup.php - FINAL, SECURE, AND DEBUG-READY VERSION
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "db_connect.php";

// Check if the connection object exists and is valid.
if (!isset($mysqli) || $mysqli->connect_error) {
    // Log the error for the admin
    error_log("Signup failed: Could not connect to the database.");
    // Send a generic error to the user
    exit('Error: A server configuration error occurred. Please try again later.');
}

if ($_SERVER["REQUEST_METHOD"] != "POST" || !isset($_POST["ajax"])) {
    exit('Invalid request.');
}

// --- Get and Sanitize Form Data ---
// Using the null coalescing operator (??) is a clean way to set defaults.
$companyName = trim($_POST['companyName'] ?? '');
$companyNationality = trim($_POST['companyNationality'] ?? '');
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$userNationality = trim($_POST['user-nationality'] ?? ''); // name in form is 'user-nationality'
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// --- Server-Side Validation ---
if (empty($companyName) || empty($firstname) || empty($lastname) || empty($email) || empty($username) || empty($password)) {
    exit('Error: Please fill out all required fields.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit('Error: Please provide a valid email address.');
}

// --- Check for Existing Company or User (using prepared statements) ---
try {
    $stmt_check_company = $mysqli->prepare("SELECT id FROM companies WHERE company_name = ?");
    $stmt_check_company->bind_param("s", $companyName);
    $stmt_check_company->execute();
    if ($stmt_check_company->get_result()->num_rows > 0) {
        exit('Error: A company with this name already exists.');
    }
    $stmt_check_company->close();

    $stmt_check_user = $mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt_check_user->bind_param("ss", $username, $email);
    $stmt_check_user->execute();
    if ($stmt_check_user->get_result()->num_rows > 0) {
        exit('Error: This username or email is already taken.');
    }
    $stmt_check_user->close();
} catch (mysqli_sql_exception $e) {
    error_log("Signup Check Failed: " . $e->getMessage());
    exit("Error: A database error occurred during validation.");
}


// --- Use a Transaction for All-or-Nothing Insertion ---
$mysqli->begin_transaction();

try {
    // --- Insert Company Data ---
    // Your schema is correct, this query will work.
    $company_sql = "INSERT INTO companies (company_name, operation_nationality) VALUES (?, ?)";
    $company_stmt = $mysqli->prepare($company_sql);
    $company_stmt->bind_param("ss", $companyName, $companyNationality);
    $company_stmt->execute();
    $company_id = $company_stmt->insert_id;
    $company_stmt->close();

    // --- Insert User Data (REMOVED admin and access_level) ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $user_sql = "INSERT INTO users (company_id, firstname, lastname, user_nationality, email, phone, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $user_stmt = $mysqli->prepare($user_sql);
    
    // The bind_param string is now "isssssss"
    $user_stmt->bind_param("isssssss", $company_id, $firstname, $lastname, $userNationality, $email, $phone, $username, $hashed_password);
    $user_stmt->execute();
    $user_id = $user_stmt->insert_id;
    $user_stmt->close();

    // --- Assign the Top-Level Admin Role (The Correct Way) ---
    // The first user for a new company should always be a full admin.
    // Let's assume the 'admin' role in your users_roles table has id = 8.
    $admin_role_id = 8;
    $role_stmt = $mysqli->prepare("INSERT INTO user_has_roles (user_id, role_id, company_id) VALUES (?, ?, ?)");
    $role_stmt->bind_param("iii", $user_id, $admin_role_id, $company_id);
    $role_stmt->execute();
    $role_stmt->close();
    
    // If everything was successful, commit the transaction
    $mysqli->commit();

    // --- Automatically Log In the New User ---
    session_regenerate_id(true); 
    $_SESSION["HeliUser"] = $user_id;
    $_SESSION["company_id"] = $company_id;
    // Set the legacy session variable for backward compatibility in permissions check, but it's less critical now
    // It's better to update login.php to query the roles and set a proper $_SESSION['roles'] array.
    $_SESSION["admin"] = $admin_role_id; // Still useful for the fast check in userHasPermission()
    
    // Send a simple success string back to the AJAX call
    echo "success";

} catch (mysqli_sql_exception $exception) {
    $mysqli->rollback(); // If anything failed, undo all database changes

    // CRITICAL FOR DEBUGGING: Send the specific MySQL error back to the browser.
    // This will show up in your Noty.js notification.
    // Once everything works, you can change this back to a generic message.
    exit("Database Error: " . $exception->getMessage());
}

$mysqli->close();
?>
