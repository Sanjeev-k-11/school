<?php
// setup_admin.php
// RUN THIS SCRIPT ONCE TO CREATE THE DEFAULT ADMIN USER.
// AFTER RUNNING SUCCESSFULLY, DELETE OR SECURELY REMOVE THIS FILE.

// Database Credentials (replace with your actual credentials)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'login_db';

// Default Admin Details
$admin_username = 'sa';
$admin_password = '1110'; // The password you specified

// Hash the admin password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Check if hashing failed (unlikely, but good practice)
if ($hashed_password === false) {
    die("Error hashing password.");
}

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the admin user already exists
$check_sql = "SELECT id FROM users WHERE username = ?";
$stmt_check = $conn->prepare($check_sql);
$stmt_check->bind_param("s", $admin_username);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    echo "Admin user '{$admin_username}' already exists. Password will not be re-set.<br>";
    echo "If you need to reset the password, manually delete the user from the database and run this script again.<br>";
} else {
    // Insert the admin user with the hashed password
    $insert_sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')";
    $stmt_insert = $conn->prepare($insert_sql);
    $stmt_insert->bind_param("ss", $admin_username, $hashed_password);

    if ($stmt_insert->execute()) {
        echo "Admin user '{$admin_username}' added successfully.<br>";
        echo "Password is set to '{$admin_password}'.<br>";
        echo "<strong style='color: red;'>IMPORTANT: Delete or move this setup_admin.php file immediately!</strong><br>";
    } else {
        echo "Error adding admin user: " . $stmt_insert->error . "<br>";
    }
    $stmt_insert->close();
}

$stmt_check->close();
$conn->close();

?>