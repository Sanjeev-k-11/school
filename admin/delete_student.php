<?php
session_start();

// Include database configuration file
require_once "../config.php"; // Ensure this points to your database connection file

// Check if the user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
     $_SESSION['operation_message'] = "<p class='text-red-600'>You must be logged in as an admin to delete student records.</p>";
    header("location: ../login.php"); // Redirect to login if not logged in or not admin
    exit;
}

// Check if id parameter exists
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    // Get URL parameter
    $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($user_id === false || $user_id <= 0) {
         // Invalid id parameter. Redirect to dashboard with error message.
         $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid student ID provided for deletion.</p>";
         header("location: admin_dashboard.php");
         exit();
    } else {
        // Prepare a delete statement
        $sql_delete = "DELETE FROM students WHERE user_id = ?";

        if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt_delete, "i", $user_id);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_delete)) {
                // Set success message in session
                $_SESSION['operation_message'] = "<p class='text-green-600'>Student record deleted successfully.</p>";
            } else {
                 // Set error message in session
                 $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Could not delete student record. " . mysqli_stmt_error($stmt_delete) . "</p>";
                 // Log error: mysqli_stmt_error($stmt_delete);
            }

            // Close statement
            mysqli_stmt_close($stmt_delete);
        } else {
             // Set error message in session
             $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Could not prepare delete statement. " . mysqli_error($link) . "</p>";
              // Log error: mysqli_error($link);
        }
    }
} else {
    // URL doesn't contain id parameter. Redirect to dashboard.
    $_SESSION['operation_message'] = "<p class='text-red-600'>No student ID provided for deletion.</p>";
}

// Close connection
mysqli_close($link);

// Redirect to the dashboard page regardless of success or failure
header("location: admin_dashboard.php");
exit();
?>