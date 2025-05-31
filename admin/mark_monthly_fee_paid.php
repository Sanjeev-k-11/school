<?php
// School/admin/mark_monthly_fee_paid.php

session_start();
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied.</p>";
    header("location: admin_dashboard.php"); // Or wherever your dashboard is
    exit;
}

// Get the monthly fee record ID from the URL
$monthly_fee_id = filter_input(INPUT_GET, 'monthly_fee_id', FILTER_VALIDATE_INT);
$student_id = null; // To store student_id for redirection

if ($monthly_fee_id === false || $monthly_fee_id <= 0) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid monthly fee record ID provided.</p>";
    header("location: admin_dashboard.php"); // Fallback redirect
    exit;
}

// Fetch the monthly fee record to get the student ID and check current status/amount
$sql_fetch_monthly = "SELECT student_id, amount_due, amount_paid, is_paid FROM student_monthly_fees WHERE id = ?";
if ($link === false) {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Database connection error. Could not mark fee as paid.</p>";
     header("location: admin_dashboard.php");
     exit;
}
// --- Opening brace for the mysqli_prepare block (Line 31 approx in previous code) ---
if ($stmt_fetch_monthly = mysqli_prepare($link, $sql_fetch_monthly)) {
    mysqli_stmt_bind_param($stmt_fetch_monthly, "i", $monthly_fee_id);
    // --- Opening brace for the mysqli_execute block ---
    if (mysqli_stmt_execute($stmt_fetch_monthly)) {
        $result_fetch_monthly = mysqli_stmt_get_result($stmt_fetch_monthly);
        $monthly_fee_data = mysqli_fetch_assoc($result_fetch_monthly);

        // --- Opening brace for the if ($monthly_fee_data) block ---
        if ($monthly_fee_data) {
            $student_id = $monthly_fee_data['student_id']; // Get student ID for successful redirection
            $amount_due = $monthly_fee_data['amount_due'];
            $amount_paid = $monthly_fee_data['amount_paid'];
            $is_paid = $monthly_fee_data['is_paid'];
            $due_amount = $amount_due - $amount_paid;


            if ($is_paid) {
                $_SESSION['operation_message'] = "<p class='text-yellow-600'>This fee record is already marked as paid.</p>";
            } elseif ($due_amount <= 0) {
                 $_SESSION['operation_message'] = "<p class='text-yellow-600'>This fee record has no amount due.</p>";
            } else {
                // --- Opening brace for the else (Proceed to mark as paid) block ---
                // Proceed to mark as paid - set amount_paid to amount_due, is_paid to TRUE, payment_date to NOW()
                $sql_update_monthly = "UPDATE student_monthly_fees SET amount_paid = amount_due, is_paid = TRUE, payment_date = CURRENT_DATE() WHERE id = ?";

                if ($stmt_update_monthly = mysqli_prepare($link, $sql_update_monthly)) {
                    mysqli_stmt_bind_param($stmt_update_monthly, "i", $monthly_fee_id);

                    if (mysqli_stmt_execute($stmt_update_monthly)) {
                        $_SESSION['operation_message'] = "<p class='text-green-600'>Monthly fee marked as paid successfully.</p>";
                    } else {
                        $_SESSION['operation_message'] = "<p class='text-red-600'>Error updating fee record: " . htmlspecialchars(mysqli_stmt_error($stmt_update_monthly)) . "</p>";
                        error_log("Error updating monthly fee record ID " . $monthly_fee_id . ": " . mysqli_stmt_error($stmt_update_monthly));
                    }
                    mysqli_stmt_close($stmt_update_monthly);
                } else {
                     $_SESSION['operation_message'] = "<p class='text-red-600'>Error preparing update statement.</p>";
                     error_log("Error preparing monthly fee update statement: " . mysqli_error($link));
                }
                // --- Closing brace for the else (Proceed to mark as paid) block ---
            }
        // --- Closing brace for the if ($monthly_fee_data) block ---
        } else {
            $_SESSION['operation_message'] = "<p class='text-red-600'>Monthly fee record not found.</p>";
        }
        mysqli_stmt_close($stmt_fetch_monthly);
    // --- Closing brace for the mysqli_execute block ---
    } else {
         $_SESSION['operation_message'] = "<p class='text-red-600'>Error fetching monthly fee record details.</p>";
         error_log("Error fetching monthly fee record ID " . $monthly_fee_id . ": " . mysqli_stmt_error($stmt_fetch_monthly));
    }
// --- Closing brace for the mysqli_prepare block (should match the opening on line 31) ---
} else {
    // This 'else' matches the first `if ($stmt_fetch_monthly = mysqli_prepare...)`
    $_SESSION['operation_message'] = "<p class='text-red-600'>Database error preparing fetch statement.</p>";
    error_log("Error preparing monthly fee fetch statement: " . mysqli_error($link));
}


// Close connection (will only run if no exit occurred earlier due to bad ID or initial DB connection failure)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
    mysqli_close($link);
}

// Redirect back to the student view page if student_id was found, otherwise to dashboard
// This should happen regardless of whether the update was successful or not,
// to display the operation_message stored in the session.
if ($student_id !== null) {
    header("location: view_student.php?id=" . htmlspecialchars($student_id));
} else {
    // If student_id was never set (e.g., invalid initial ID), redirect to dashboard
    header("location: admin_dashboard.php"); // Fallback
}
exit;
?>