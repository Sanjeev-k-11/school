<?php
session_start();

// Include database configuration file
require_once "../config.php"; // config.php is in the same directory

// Check if the user is logged in and is ADMIN
// Only users with role 'admin' can delete staff
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can delete staff records.</p>";
    header("location: ../login.php"); // Redirect to login (adjust path)
    exit;
}

// Check if id parameter exists and is valid
if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
    // Get URL parameter
    $staff_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($staff_id === false || $staff_id <= 0) {
         // Invalid id parameter. Redirect to manage staff with error message.
         $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid staff ID provided for deletion.</p>";
    } else {
        // Prevent admin from deleting their OWN account (optional but recommended)
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user' && isset($_SESSION['id']) && $_SESSION['id'] === $staff_id) {
             // Assuming the admin user ID is the same as the staff ID they might be trying to delete?
             // This logic needs refinement based on how admin users are stored.
             // If admin login uses the 'users' table with 'id' and staff uses 'staff' table with 'staff_id',
             // an admin trying to delete their 'users' account via this staff delete script won't work anyway.
             // If an admin *is* a staff member and logs in via staff login, check staff_id.
             if (isset($_SESSION['staff_id']) && $_SESSION['staff_id'] === $staff_id) {
                  $_SESSION['operation_message'] = "<p class='text-red-600'>You cannot delete your own staff account.</p>";
                  goto end_delete_process; // Skip deletion if it's the logged-in admin trying to delete themselves
             }
             // If admin logs in via 'users' table, their $_SESSION['id'] is from 'users',
             // while the staff ID is from 'staff'. They cannot delete themselves via this script.

        }


        // Prepare a delete statement
        $sql_delete = "DELETE FROM staff WHERE staff_id = ?";

        if ($link === false) {
             $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Could not delete staff record.</p>";
             error_log("Delete Staff DB connection failed: " . mysqli_connect_error());
        } elseif ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt_delete, "i", $staff_id);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_delete)) {
                // Check if any row was actually deleted
                if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
                     // Set success message in session
                     $_SESSION['operation_message'] = "<p class='text-green-600'>Staff record deleted successfully.</p>";
                } else {
                     // Record not found (or already deleted)
                     $_SESSION['operation_message'] = "<p class='text-warning-600'>Staff record not found or already deleted.</p>";
                }

            } else {
                 // Set error message in session
                 $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Could not delete staff record. " . mysqli_stmt_error($stmt_delete) . "</p>";
                 error_log("Delete Staff query failed: " . mysqli_stmt_error($stmt_delete));
            }

            // Close statement
            mysqli_stmt_close($stmt_delete);
        } else {
             // Set error message in session
             $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Could not prepare delete statement. " . mysqli_error($link) . "</p>";
              error_log("Delete Staff prepare statement failed: " . mysqli_error($link));
        }
    }
} else {
    // URL doesn't contain id parameter. Redirect to manage staff.
    $_SESSION['operation_message'] = "<p class='text-red-600'>No staff ID provided for deletion.</p>";
}

end_delete_process: // Label for goto

// Close connection
if (isset($link) && is_object($link)) {
     mysqli_close($link);
}

// Redirect to the manage staff page regardless of success or failure
header("location: manage_staff.php"); // Redirect back to manage staff page
exit();