<?php
session_start();

// Include config file
require_once "../config.php";

// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can manage student status.</p>";
    header("location: ../login.php");
    exit;
}

// Check if student ID is provided in the URL
if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Student ID not provided.</p>";
    header("location: ./allstudentList.php");
    exit;
}

$student_id = trim($_GET['id']);
$operation_success = false;
$message = "";
$is_currently_active = null;

// Validate ID - assuming user_id is an integer
if (!ctype_digit($student_id)) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Error: Invalid student ID format.</p>";
     header("location: ./allstudentList.php");
     exit;
}


// Database connection check
if ($link === false) {
    $message = "Error: Database connection failed. Could not toggle status.";
    error_log("Toggle Student Status DB connection failed: " . mysqli_connect_error());
} else {
    // First, fetch the current status
    $sql_fetch_status = "SELECT is_active, full_name FROM students WHERE user_id = ?";

    if ($stmt_fetch = mysqli_prepare($link, $sql_fetch_status)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $param_id);
        $param_id = $student_id;

        if (mysqli_stmt_execute($stmt_fetch)) {
            mysqli_stmt_store_result($stmt_fetch);

            if (mysqli_stmt_num_rows($stmt_fetch) == 1) {
                mysqli_stmt_bind_result($stmt_fetch, $is_currently_active, $full_name);
                mysqli_stmt_fetch($stmt_fetch);

                // Determine the new status
                $new_status = $is_currently_active ? 0 : 1; // 0 for inactive, 1 for active
                $action_done = $is_currently_active ? "deactivated" : "activated";
                $action_verb = $is_currently_active ? "Deactivating" : "Activating";


                // Now, perform the update
                $sql_update_status = "UPDATE students SET is_active = ? WHERE user_id = ?";

                if ($stmt_update = mysqli_prepare($link, $sql_update_status)) {
                    mysqli_stmt_bind_param($stmt_update, "ii", $param_new_status, $param_id);
                    $param_new_status = $new_status;

                    if (mysqli_stmt_execute($stmt_update)) {
                        // Check if any row was actually updated
                        if (mysqli_stmt_affected_rows($stmt_update) == 1) {
                             $message = "<p class='text-green-600'>Student account for <strong>" . htmlspecialchars($full_name) . "</strong> successfully " . $action_done . ".</p>";
                             $operation_success = true;
                        } else {
                            $message = "<p class='text-yellow-600'>Warning: Student account for <strong>" . htmlspecialchars($full_name) . "</strong> was already " . ($new_status ? "active" : "inactive") . " or no changes were needed.</p>";
                        }
                    } else {
                        $message = "<p class='text-red-600'>Error executing update query for student ID " . htmlspecialchars($student_id) . ": " . mysqli_stmt_error($stmt_update) . "</p>";
                        error_log($action_verb . " student status update failed: " . mysqli_stmt_error($stmt_update));
                    }
                    mysqli_stmt_close($stmt_update);
                } else {
                    $message = "<p class='text-red-600'>Error preparing update query for student ID " . htmlspecialchars($student_id) . ": " . mysqli_error($link) . "</p>";
                    error_log($action_verb . " student status prepare update failed: " . mysqli_error($link));
                }

            } else {
                 $message = "<p class='text-red-600'>Error: Student with ID " . htmlspecialchars($student_id) . " not found.</p>";
            }

        } else {
            $message = "<p class='text-red-600'>Error fetching student status for ID " . htmlspecialchars($student_id) . ": " . mysqli_stmt_error($stmt_fetch) . "</p>";
            error_log("Fetch student status query failed: " . mysqli_stmt_error($stmt_fetch));
        }
        mysqli_stmt_close($stmt_fetch);

    } else {
        $message = "<p class='text-red-600'>Error preparing fetch status query for student ID " . htmlspecialchars($student_id) . ": " . mysqli_error($link) . "</p>";
        error_log("Prepare fetch student status statement failed: " . mysqli_error($link));
    }

    // Close connection
    if (isset($link) && is_object($link) && mysqli_ping($link)) {
        mysqli_close($link);
    }
}

// Set session message and redirect back to the list
$_SESSION['operation_message'] = $message;
header("location: ./allstudentList.php");
exit;
?>