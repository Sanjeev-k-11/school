<?php
// School/admin/edit_monthly_fee.php

session_start();

// Adjust path as needed - assuming config.php is one directory up from admin
require_once "../config.php";

// Check if user is logged in and is ADMIN
// Only Admins should be able to edit fee records.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    // Use SESSION to store message and redirect
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can edit fee records.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Edit Monthly Fee Record";

// --- Variables ---
// Define variables and initialize with empty values for the monthly fee record
$fee_id = null; // The ID of the specific monthly fee record being edited
$student_id = null; // The ID of the student associated with this fee record

// Variables to hold data for display in form fields
$fee_year_display = ''; // Read-only
$fee_month_display = ''; // Read-only
$base_monthly_fee_display = ''; // Editable input value
$monthly_van_fee_display = ''; // Editable input value
$monthly_exam_fee_display = ''; // Editable input value
$monthly_electricity_fee_display = ''; // Editable input value
$amount_due_display_calculated = 0.0; // Calculated value, display only
$amount_paid_display = ''; // Editable input value
$is_paid_display = 0; // Status derived from calculation, display only (0 or 1)
$payment_date_display = ''; // Editable input type="date" value (YYYY-MM-DD)
$notes_display = ''; // Editable textarea value

$student_full_name = 'Loading...'; // To display student name for context

// Error variables for input fields
$base_monthly_fee_err = '';
$monthly_van_fee_err = '';
$monthly_exam_fee_err = '';
$monthly_electricity_fee_err = '';
$amount_paid_err = '';
$payment_date_err = ''; // For date validation format

// General error messages (for non-field specific issues like DB errors, invalid ID)
$general_error = '';

// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'

// --- Check for operation messages from previous pages (e.g., redirect) ---
// This ensures messages set in $_SESSION['operation_message'] on a redirect are displayed as a toast
if (isset($_SESSION['operation_message'])) {
    // Use htmlspecialchars and strip_tags for safety before displaying in toast
    $toast_message = htmlspecialchars(strip_tags($_SESSION['operation_message']));
    // Determine toast type based on message content (a simple heuristic)
    $lower_toast_message = strtolower($toast_message); // Use lower case for checking
    if (strpos($_SESSION['operation_message'], 'text-green-600') !== false || strpos($lower_toast_message, 'success') !== false || strpos($lower_toast_message, 'updated') !== false) {
        $toast_type = 'success';
    } elseif (strpos($_SESSION['operation_message'], 'text-red-600') !== false || strpos($lower_toast_message, 'denied') !== false || strpos($lower_toast_message, 'error') !== false || strpos($lower_toast_message, 'failed') !== false || strpos($lower_toast_message, 'invalid') !== false) {
        $toast_type = 'error';
    } elseif (strpos($lower_toast_message, 'warning') !== false || strpos($lower_toast_message, 'exists') !== false || strpos($lower_toast_message, 'not found') !== false) {
         $toast_type = 'warning';
    } else {
        $toast_type = 'info';
    }
    unset($_SESSION['operation_message']); // Clear the message after retrieving it
}


// --- Handle POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get the fee record ID and student ID from the hidden input fields
    $fee_id = filter_input(INPUT_POST, 'fee_id', FILTER_VALIDATE_INT);
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT); // Keep student_id from POST

    // Also retrieve read-only fields from POST for potential display on error
    $fee_year_display = trim($_POST['fee_year'] ?? '');
    $fee_month_display = trim($_POST['fee_month'] ?? '');


    // Validate the fee record ID and student ID from POST
    if ($fee_id === false || $fee_id <= 0) {
        $general_error = "Invalid fee record ID provided for submission."; // Use general error
        $toast_type = 'error'; // This will be displayed by toast script
        $fee_id = null; // Ensure it's null if invalid
        // No further processing possible without a valid fee_id
    }

    // Validate student_id from POST (required for cancel link/context)
     if ($student_id === false || $student_id <= 0) {
         // This is an error but less critical than fee_id missing. Log it.
         error_log("Edit Monthly Fee: Invalid student_id received in POST for fee_id " . htmlspecialchars($fee_id ?? 'N/A') . ". Value: " . ($_POST['student_id'] ?? ''));
         // Keep student_id as null, the cancel link will go to dashboard.
          $student_id = null;
     }


    if ($fee_id !== null) { // Only proceed with update if we have a valid fee record ID

        // --- Get and Trim editable input values from the form ---
        // These variables will hold the raw POST values (trimmed) for display on error
        $base_monthly_fee_display = trim($_POST['base_monthly_fee'] ?? '');
        $monthly_van_fee_display = trim($_POST['monthly_van_fee'] ?? '');
        $monthly_exam_fee_display = trim($_POST['monthly_exam_fee'] ?? '');
        $monthly_electricity_fee_display = trim($_POST['monthly_electricity_fee'] ?? '');
        $amount_paid_display = trim($_POST['amount_paid'] ?? '');
        $payment_date_display = trim($_POST['payment_date'] ?? '');
        $notes_display = trim($_POST['notes'] ?? '');

        // Variables to hold validated/converted values for DB
        $base_monthly_fee_for_db = null; // Will hold filtered value
        $monthly_van_fee_for_db = 0.0;
        $monthly_exam_fee_for_db = 0.0;
        $monthly_electricity_fee_for_db = 0.0;
        $amount_paid_for_db = null;
        $payment_date_for_db = null; // Will be set to 'YYYY-MM-DD' or null
        $notes_for_db = $notes_display; // Use trimmed value for DB


        // --- Validation ---

        // Validate Breakdown Fees (Require non-negative float)
        if ($base_monthly_fee_display === '') { // Base fee is required
             $base_monthly_fee_err = "Base fee is required.";
        } else {
             $filtered_base = filter_var($base_monthly_fee_display, FILTER_VALIDATE_FLOAT);
             if ($filtered_base === false || $filtered_base < 0) {
                 $base_monthly_fee_err = "Invalid Base fee.";
             } else {
                 $base_monthly_fee_for_db = $filtered_base;
             }
        }

        // Van Fee (Optional, validate if entered)
        if ($monthly_van_fee_display !== '') {
            $filtered_van = filter_var($monthly_van_fee_display, FILTER_VALIDATE_FLOAT);
            if ($filtered_van === false || $filtered_van < 0) {
                $monthly_van_fee_err = "Invalid Van fee.";
            } else {
                $monthly_van_fee_for_db = $filtered_van; // Store validated value
            }
        } else {
            $monthly_van_fee_for_db = 0.0; // Treat empty optional as 0 for DB
        }

        // Exam Fee (Optional, validate if entered)
        if ($monthly_exam_fee_display !== '') {
            $filtered_exam = filter_var($monthly_exam_fee_display, FILTER_VALIDATE_FLOAT);
            if ($filtered_exam === false || $filtered_exam < 0) {
                $monthly_exam_fee_err = "Invalid Exam fee.";
            } else {
                $monthly_exam_fee_for_db = $filtered_exam; // Store validated value
            }
        } else {
            $monthly_exam_fee_for_db = 0.0; // Treat empty optional as 0 for DB
        }

        // Electricity Fee (Optional, validate if entered)
        if ($monthly_electricity_fee_display !== '') {
            $filtered_elec = filter_var($monthly_electricity_fee_display, FILTER_VALIDATE_FLOAT);
            if ($filtered_elec === false || $filtered_elec < 0) {
                $monthly_electricity_fee_err = "Invalid Electricity fee.";
            } else {
                $monthly_electricity_fee_for_db = $filtered_elec; // Store validated value
            }
        } else {
            $monthly_electricity_fee_for_db = 0.0; // Treat empty optional as 0 for DB
        }


        // Validate Amount Paid (Required, non-negative float)
        if ($amount_paid_display === '') {
             $amount_paid_err = "Amount Paid is required."; // Amount Paid is crucial for payment status
        } else {
            $filtered_paid = filter_var($amount_paid_display, FILTER_VALIDATE_FLOAT);
            if ($filtered_paid === false || $filtered_paid < 0) {
                $amount_paid_err = "Invalid Amount Paid.";
            } else {
                $amount_paid_for_db = $filtered_paid; // Store validated value
            }
        }

        // Validate Payment Date (Optional, validate format if entered)
        if (!empty($payment_date_display)) {
            $date_obj = DateTime::createFromFormat('YYYY-MM-DD', $payment_date_display); // Correct format check
            // Also check if the date is a valid date after formatting (prevents '2023-02-30' becoming '2023-03-02')
            if ($date_obj && $date_obj->format('Y-m-d') === $payment_date_display) {
                $payment_date_for_db = $date_obj->format('Y-m-d'); // Correct format for DB
            } else {
                $payment_date_err = "Invalid date format. Please use YYYY-MM-DD.";
                 // $payment_date_display already holds the invalid input for display
            }
        } else {
            $payment_date_for_db = null; // Store as NULL if empty
        }


        // Check if there are any validation errors
        $has_errors = !empty($base_monthly_fee_err) || !empty($monthly_van_fee_err) ||
                      !empty($monthly_exam_fee_err) || !empty($monthly_electricity_fee_err) ||
                      !empty($amount_paid_err) || !empty($payment_date_err);


        if (!$has_errors && $fee_id > 0 && $base_monthly_fee_for_db !== null && $amount_paid_for_db !== null) {
            // Proceed with update only if no validation errors AND essential validated values are available

            // --- Recalculate Amount Due and is_paid status ---
            // Use the validated values for calculation
            $new_amount_due_calculated = ($base_monthly_fee_for_db ?? 0) + ($monthly_van_fee_for_db ?? 0) +
                                         ($monthly_exam_fee_for_db ?? 0) + ($monthly_electricity_fee_for_db ?? 0);

            // Determine is_paid status: 1 if amount paid is >= calculated total due, 0 otherwise
            // Consider floating point comparison carefully, but simple >= is usually okay for currency
            $new_is_paid_status = ($amount_paid_for_db >= $new_amount_due_calculated) ? 1 : 0; // Use validated amount_paid


             // If amount paid is 0 or less, clear the payment date if one was set.
             // If amount paid is > 0, keep the provided payment date or null if none was provided/valid.
             if ($amount_paid_for_db <= 0) {
                  $payment_date_for_db = null; // Clear payment date if amount paid is zero
             }
             // Note: $payment_date_for_db is already null if the input was empty or invalid


            // --- Prepare and Execute the UPDATE statement ---
            // Columns to update: breakdown fees, amount_due (calculated), amount_paid, is_paid (calculated), payment_date, notes, updated_at
            $sql_update = "UPDATE student_monthly_fees SET
                            base_monthly_fee = ?,
                            monthly_van_fee = ?,
                            monthly_exam_fee = ?,
                            monthly_electricity_fee = ?,
                            amount_due = ?,
                            amount_paid = ?,
                            is_paid = ?,
                            payment_date = ?,
                            notes = ?,
                            updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?";

            if ($link === false) {
                 $toast_message = "Database connection error. Could not save changes.";
                 $toast_type = 'error';
                 error_log("Edit Monthly Fee DB connection failed (POST): " . mysqli_connect_error());
            } elseif ($stmt_update = mysqli_prepare($link, $sql_update)) {
                // Bind parameters - Match the order in the UPDATE SET clause + WHERE (10 parameters)
                // Bind string: d d d d d d i s s i
                mysqli_stmt_bind_param($stmt_update, "ddddddissi",
                    $base_monthly_fee_for_db,       // d (validated)
                    $monthly_van_fee_for_db,        // d (validated, 0 if empty)
                    $monthly_exam_fee_for_db,       // d (validated, 0 if empty)
                    $monthly_electricity_fee_for_db,// d (validated, 0 if empty)
                    $new_amount_due_calculated,     // d (Calculated total due)
                    $amount_paid_for_db,            // d (validated)
                    $new_is_paid_status,            // i (Calculated paid status)
                    $payment_date_for_db,           // s (Bind DATE as string, NULL if null)
                    $notes_for_db,                  // s (trimmed notes)
                    $fee_id                         // i (WHERE clause)
                );

                if (mysqli_stmt_execute($stmt_update)) {
                    // Close statement *before* setting session message and redirecting/re-fetching
                    mysqli_stmt_close($stmt_update);

                    // Set success message for toast display on THIS page after re-fetch
                    $toast_message = "Monthly fee record updated successfully.";
                    $toast_type = 'success';

                    // Re-fetch data below to display the updated record details
                    // (No redirect needed, stay on the page)

                } else {
                     // Error executing the update statement
                     $db_error = mysqli_stmt_error($stmt_update);
                     $toast_message = "Error: Could not update monthly fee record. Database error: " . htmlspecialchars($db_error);
                     $toast_type = 'error'; // Use error type
                     error_log("Edit Monthly Fee update query failed for ID " . $fee_id . ": " . $db_error);
                     // Display variables already populated from $_POST for display on error.
                     mysqli_stmt_close($stmt_update); // Close statement on error too
                }
            } else {
                 // Error preparing update statement
                 $db_error = mysqli_error($link);
                 $toast_message = "Error: Could not prepare update statement. Database error: " . htmlspecialchars($db_error);
                 $toast_type = 'error'; // Use error type
                 error_log("Edit Monthly Fee prepare update failed: " . $db_error);
                 // Display variables already populated from $_POST for display on error.
            }
        } else {
            // If there were validation errors
            // $general_error is not used here; specific errors are next to fields.
            // Display variables are already populated from $_POST by the validation block above.
            $toast_message = "Please fix the errors in the form below.";
            $toast_type = 'error'; // Use error type
        }
    }
    // If $fee_id was invalid from the start of the POST block, the toast message is already set.
    // Now, regardless of success or failure, or whether the initial ID was valid,
    // we attempt to re-fetch the data if $fee_id is now valid (e.g., if it was valid initially)
    // This re-populates the form with either updated data (on success) or the original data (on DB error) or POST data (on validation error)
    // AND fetches the student's name.

    // --- Fetch fee record data AND student data if $fee_id is valid (Needed for initial GET or POST re-display) ---
    // Note: On POST with validation errors, the display variables already hold the POSTed values.
    // We will only overwrite them from the database fetch if there was *no* validation error for that field.
    if ($fee_id > 0) { // Ensure fee_id is valid before fetching

         // Fetch the specific monthly fee record
         $sql_fetch_fee = "SELECT id, student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid, payment_date, notes FROM student_monthly_fees WHERE id = ?";
         if ($link === false) {
              if(empty($general_error)) $general_error = "Database connection error. Could not load fee data."; // Only set if not already set
              if(empty($toast_message)) { $toast_message = $general_error; $toast_type = 'error'; }
              error_log("Edit Monthly Fee fetch DB connection failed (POST refetch): " . mysqli_connect_error());
              $fee_id = null; // Invalidate if DB connection fails
         } elseif ($stmt_fetch_fee = mysqli_prepare($link, $sql_fetch_fee)) {
             mysqli_stmt_bind_param($stmt_fetch_fee, "i", $fee_id);
             if (mysqli_stmt_execute($stmt_fetch_fee)) {
                 $result_fetch_fee = mysqli_stmt_get_result($stmt_fetch_fee);
                 if (mysqli_num_rows($result_fetch_fee) == 1) {
                      $fee_record = mysqli_fetch_assoc($result_fetch_fee);

                      // Populate variables with fetched data for display
                      // On POST with errors, display variables already hold submitted values.
                      // Only update from DB fetch if there wasn't a validation error for that field.
                       $student_id = $fee_record['student_id']; // Always get student ID from valid record
                       $fee_year_display = $fee_record['fee_year']; // Read-only, always update from fetch
                       $fee_month_display = $fee_record['fee_month']; // Read-only, always update from fetch

                       // Only overwrite display variables from DB fetch if no validation error occurred for them on POST
                       if (empty($base_monthly_fee_err)) $base_monthly_fee_display = $fee_record['base_monthly_fee'];
                       if (empty($monthly_van_fee_err)) $monthly_van_fee_display = $fee_record['monthly_van_fee'];
                       if (empty($monthly_exam_fee_err)) $monthly_exam_fee_display = $fee_record['monthly_exam_fee'];
                       if (empty($monthly_electricity_fee_err)) $monthly_electricity_fee_display = $fee_record['monthly_electricity_fee'];
                       if (empty($amount_paid_err)) $amount_paid_display = $fee_record['amount_paid'];
                       if (empty($payment_date_err)) {
                           // Only update payment_date_display from fetch if no validation error AND fetched date is valid/not empty
                           $payment_date_display = (!empty($fee_record["payment_date"]) && $fee_record["payment_date"] !== '0000-00-00') ? $fee_record["payment_date"] : '';
                       }
                       // Notes and calculated values are always updated from fetch as they aren't validated per se
                       $notes_display = $fee_record['notes'];
                       $amount_due_display_calculated = $fee_record['amount_due']; // Always display DB calculated amount due
                       $is_paid_display = $fee_record['is_paid']; // Always update from fetch (latest status)


                       mysqli_free_result($result_fetch_fee);

                       // Now fetch the student's name using the student_id
                       if ($student_id > 0) {
                            $sql_fetch_student = "SELECT full_name FROM students WHERE user_id = ?";
                             if ($link === false) { /* DB connection error already reported */ }
                             elseif ($stmt_fetch_student = mysqli_prepare($link, $sql_fetch_student)) {
                                 mysqli_stmt_bind_param($stmt_fetch_student, "i", $student_id);
                                 if (mysqli_stmt_execute($stmt_fetch_student)) {
                                     $result_fetch_student = mysqli_stmt_get_result($stmt_fetch_student);
                                     if (mysqli_num_rows($result_fetch_student) == 1) {
                                         $student = mysqli_fetch_assoc($result_fetch_student);
                                         $student_full_name = htmlspecialchars($student['full_name']);
                                     } else {
                                         $student_full_name = "Student Not Found"; // Should not happen
                                         error_log("Edit Monthly Fee: Student not found for ID " . $student_id . " linked to fee ID " . $fee_id);
                                     }
                                     mysqli_free_result($result_fetch_student);
                                 } else {
                                     error_log("Edit Monthly Fee student fetch query failed for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_fetch_student));
                                     $student_full_name = "Error fetching student name";
                                 }
                                 mysqli_stmt_close($stmt_fetch_student);
                             } elseif ($link !== false) {
                                  error_log("Edit Monthly Fee prepare student fetch failed: " . mysqli_error($link));
                                  $student_full_name = "Error preparing student fetch";
                             }
                       } else {
                            $student_full_name = "Invalid Student ID"; // Should not happen if fee record has a student_id
                       }

                 } else { // Fee record not found by ID
                     $general_error = "Monthly fee record not found.";
                     $toast_type = 'error'; // Use toast for this error
                     // Clear variables as record is not available
                     $fee_id = null; $student_id = null; $fee_year_display = ''; $fee_month_display = '';
                     $base_monthly_fee_display = ''; $monthly_van_fee_display = '';
                     $monthly_exam_fee_display = ''; $monthly_electricity_fee_display = '';
                     $amount_paid_display = ''; $is_paid_display = 0; $payment_date_display = ''; $notes_display = '';
                     $student_full_name = 'Record Not Found';
                 }
             } else {
                 $db_error = mysqli_stmt_error($stmt_fetch_fee);
                 $general_error = "Error fetching fee record data: " . htmlspecialchars($db_error);
                  $toast_type = 'error';
                 error_log("Edit Monthly Fee fetch query failed for ID " . $fee_id . ": " . $db_error);
                  $fee_id = null; // Invalidate if fetch fails
             }
             mysqli_stmt_close($stmt_fetch_fee);
         } elseif ($link !== false) {
              $db_error = mysqli_error($link);
              $general_error = "Error preparing to fetch fee record: " . htmlspecialchars($db_error);
               $toast_type = 'error';
              error_log("Edit Monthly Fee prepare fetch failed: " . $db_error);
              $fee_id = null; // Invalidate if prepare fails
         }

    } else { // If $fee_id was invalid initially (GET or POST)
        // Variables are already initialized to empty/null at the top
        $fee_id = null; $student_id = null; $fee_year_display = ''; $fee_month_display = '';
        $base_monthly_fee_display = ''; $monthly_van_fee_display = '';
        $monthly_exam_fee_display = ''; $monthly_electricity_fee_display = '';
        $amount_paid_display = ''; $is_paid_display = 0; $payment_date_display = ''; $notes_display = '';
        $student_full_name = 'Invalid Record ID'; // Indicate state
        if(empty($general_error)) $general_error = "Could not load the requested fee record."; // Generic error if none specific
        if(empty($toast_message)) { $toast_message = $general_error; $toast_type = 'error'; }

    }


// --- Closing brace for if ($_SERVER["REQUEST_METHOD"] == "POST") ---
} else { // GET request - Display the form with existing data

    // Expect a student_id in the query string
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        $fee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($fee_id === false || $fee_id <= 0) {
            $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid fee record ID provided.</p>";
            header("location: admin_dashboard.php"); // Adjust redirect
            exit();
        } else {
            // Fetch the specific monthly fee record
            $sql_fetch_fee = "SELECT id, student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid, payment_date, notes FROM student_monthly_fees WHERE id = ?";

            if ($link === false) {
                 // Use toast for error messages
                $general_error = "Database connection error. Could not load fee data.";
                $toast_type = 'error';
                $toast_message = $general_error; // Set toast message
                 error_log("Edit Monthly Fee fetch DB connection failed (GET): " . mysqli_connect_error());
                 $fee_id = null; // Invalidate if DB connection fails
            } elseif ($stmt_fetch_fee = mysqli_prepare($link, $sql_fetch_fee)) {
                mysqli_stmt_bind_param($stmt_fetch_fee, "i", $fee_id);

                if (mysqli_stmt_execute($stmt_fetch_fee)) {
                    $result_fetch_fee = mysqli_stmt_get_result($stmt_fetch_fee);

                    if (mysqli_num_rows($result_fetch_fee) == 1) {
                        $fee_record = mysqli_fetch_assoc($result_fetch_fee);

                        // Populate variables with fetched data for display on GET
                        $student_id = $fee_record['student_id']; // Get student ID
                        $fee_year_display = $fee_record['fee_year'];
                        $fee_month_display = $fee_record['fee_month'];
                        $base_monthly_fee_display = $fee_record['base_monthly_fee'];
                        $monthly_van_fee_display = $fee_record['monthly_van_fee'];
                        $monthly_exam_fee_display = $fee_record['monthly_exam_fee'];
                        $monthly_electricity_fee_display = $fee_record['monthly_electricity_fee'];
                        $amount_due_display_calculated = $fee_record['amount_due']; // calculated value from DB
                        $amount_paid_display = $fee_record['amount_paid'];
                        $is_paid_display = $fee_record['is_paid'];
                         // Handle Payment Date for display input type="date"
                         $payment_date_display = (!empty($fee_record["payment_date"]) && $fee_record["payment_date"] !== '0000-00-00') ? $fee_record["payment_date"] : '';
                        $notes_display = $fee_record['notes'];

                        mysqli_free_result($result_fetch_fee); // Free fee result set

                        // Now fetch the student's name using the student_id
                       if ($student_id > 0) {
                            $sql_fetch_student = "SELECT full_name FROM students WHERE user_id = ?";
                             if ($link === false) { /* DB connection error already reported */ }
                             elseif ($stmt_fetch_student = mysqli_prepare($link, $sql_fetch_student)) {
                                 mysqli_stmt_bind_param($stmt_fetch_student, "i", $student_id);
                                 if (mysqli_stmt_execute($stmt_fetch_student)) {
                                     $result_fetch_student = mysqli_stmt_get_result($stmt_fetch_student);
                                     if (mysqli_num_rows($result_fetch_student) == 1) {
                                         $student = mysqli_fetch_assoc($result_fetch_student);
                                         $student_full_name = htmlspecialchars($student['full_name']);
                                     } else {
                                         $student_full_name = "Student Not Found"; // Should not happen
                                         error_log("Edit Monthly Fee: Student not found for ID " . $student_id . " linked to fee ID " . $fee_id);
                                     }
                                     mysqli_free_result($result_fetch_student);
                                 } else {
                                     error_log("Edit Monthly Fee student fetch query failed during GET for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_fetch_student));
                                     $student_full_name = "Error fetching student name";
                                 }
                                 mysqli_stmt_close($stmt_fetch_student);
                             } elseif ($link !== false) {
                                  error_log("Edit Monthly Fee prepare student fetch failed: " . mysqli_error($link));
                                  $student_full_name = "Error preparing student fetch";
                             }
                       } else {
                            $student_full_name = "Invalid Student ID"; // Should not happen if fee record has a student_id
                       }

                    } else { // Fee record not found by ID
                        $_SESSION['operation_message'] = "<p class='text-red-600'>Monthly fee record not found.</p>";
                        header("location: admin_dashboard.php"); // Adjust redirect
                        exit();
                    }
                } else { // Execute error
                    $general_error = "Oops! Something went wrong. Could not fetch fee record. Please try again later.";
                    $toast_type = 'error';
                    $toast_message = $general_error; // Set toast message
                    error_log("Edit Monthly Fee fetch query failed: " . mysqli_stmt_error($stmt_fetch_fee));
                     $fee_id = null; // Invalidate if fetch fails
                }
                mysqli_stmt_close($stmt_fetch_fee); // Close fee statement
            } else { // Prepare error
                 $general_error = "Oops! Something went wrong. Could not prepare fetch statement. Please try again later.";
                 $toast_type = 'error';
                 $toast_message = $general_error; // Set toast message
                 error_log("Edit Monthly Fee prepare fetch statement failed: " . mysqli_error($link));
                 $fee_id = null; // Invalidate if prepare fails
            }
        }
    } else { // No ID in GET
        $_SESSION['operation_message'] = "<p class='text-red-600'>No fee record ID provided for editing.</p>";
        header("location: admin_dashboard.php"); // Adjust redirect
        exit();
    }

// --- Closing brace for else (GET request) ---
}


// Calculate amount_remaining for display regardless of GET or POST, using current display values
// **FIXED: Explicitly cast to float to avoid TypeError**
$amount_due_display_calculated = (float)($base_monthly_fee_display ?? 0) + (float)($monthly_van_fee_display ?? 0) +
                                 (float)($monthly_exam_fee_display ?? 0) + (float)($monthly_electricity_fee_display ?? 0);
$amount_remaining_calculated = $amount_due_display_calculated - (float)($amount_paid_display ?? 0);


// Close connection (Only if $link is valid and not already closed by a redirect)
// This is placed at the very end of the PHP script.
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Edit Fee Record'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         body {
             padding-top: 4.5rem; /* Space for fixed header */
             background-color: #f3f4f6; /* Default light gray background */
             min-height: 100vh; /* Ensure body takes at least full viewport height */
             transition: padding-left 0.3s ease; /* Smooth transition for padding when sidebar opens/closes */
         }
         /* Adjust padding when sidebar is open (assuming sidebar adds a class like 'sidebar-open' to body) */
         body.sidebar-open {
             padding-left: 16rem; /* Example width, adjust based on your sidebar width */
         }

         /* Fixed Header/Navbar styles */
         /* Assumes admin_header.php does NOT render the fixed header itself */
         .fixed-header {
              position: fixed;
              top: 0;
              left: 0; /* Aligned left */
              right: 0; /* Aligned right */
              height: 4.5rem; /* Fixed height */
              background-color: #ffffff; /* White background */
              box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* Subtle shadow */
              padding: 0 1rem; /* py-0 px-4 or similar */
              display: flex; /* Use flexbox */
              align-items: center; /* Vertically center items */
              z-index: 10; /* Ensure it stays on top */
              transition: left 0.3s ease; /* Smooth transition when sidebar changes body padding */
         }
          /* Adjust header position when sidebar is open */
          body.sidebar-open .fixed-header {
              left: 16rem; /* Shift header right by sidebar width */
          }
          /* Add padding specifically within the fixed header for content */
          .fixed-header > * {
              padding-top: 0.5rem; /* Adjust vertical alignment */
              padding-bottom: 0.5rem; /* Adjust vertical alignment */
          }
          .fixed-header h1 {
              margin: 0; /* Remove default margin */
          }

         /* Main Content Wrapper */
         /* This centers the primary content block and adds horizontal padding */
         .main-content-wrapper {
             width: 100%;
             max-width: 600px; /* Max width for the form */
             margin-left: auto;
             margin-right: auto;
             padding: 2rem 1rem; /* py-8 px-4, use consistent padding */
         }
          @media (min-width: 768px) { /* md breakpoint */
               .main-content-wrapper {
                   padding-left: 2rem; /* md:px-8 */
                   padding-right: 2rem; /* md:px-8 */
               }
          }

         /* Specific Form Element Styles */
         .form-control {
            /* Apply standard input styles */
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem; /* py-2 px-3 */
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem; /* leading-5 */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
         }
         .form-control:focus {
             outline: none;
             border-color: #4f46e5; /* indigo-500 */
             box-shadow: 0 0 0 1px #4f46e5, 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* ring-indigo-500 + shadow-sm */
         }

         /* Style for read-only fields */
         .form-control[readonly] {
              background-color: #e5e7eb; /* gray-200 */
              cursor: not-allowed;
         }

         /* Error state for form controls */
         .form-control.is-invalid {
             border-color: #ef4444; /* red-500 */
             padding-right: 2.5rem; /* Add space for potential icon */
              background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6'%3e%3cpath stroke-linecap='round' stroke-linejoin='round' d='M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z' /%3e%3c/svg%3e"); /* Optional error icon */
              background-repeat: no-repeat;
              background-position: right 0.75rem center;
              background-size: 1.25rem 1.25rem;
         }
         .form-control.is-invalid:focus {
              border-color: #dc2626; /* red-600 */
              box-shadow: 0 0 0 1px #dc2626; /* ring red-600 */
         }

         /* Style for error messages below inputs */
         .form-error {
            color: #dc2626; /* red-600 */
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25rem; /* Small space above */
            display: block; /* Ensure it takes its own line */
         }

         /* Style for number input placeholders */
         input[type="number"]::placeholder {
               color: #9ca3af; /* gray-400 */
           }
         input[type="date"]::placeholder {
               color: #9ca3af; /* gray-400 */
           }

         /* Style for calculated display paragraphs */
         .calculated-display {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem; /* py-2 px-3 */
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            background-color: #e5e7eb; /* gray-200 - clearly not an input */
            font-size: 0.875rem; /* text-sm */
            line-height: 1.25rem; /* leading-5 */
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* gray-800 */
         }
         .calculated-display.text-red { color: #b91c1c; } /* red-700 or 800 */
         .calculated-display.text-green { color: #047857; } /* green-700 or 800 */


         /* --- Toast Notification Styles --- */
         /* Container to hold all toasts */
         .toast-container {
             position: fixed; top: 1rem; right: 1rem; z-index: 1000; /* High z-index */ display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; /* Allow clicks to pass through container */ max-width: 90%; width: 350px; /* Max width for individual toasts */
         }
          /* Individual toast style */
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1rem; border-radius: 0.375rem; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); /* Stronger shadow */
             opacity: 0; transform: translateX(100%); transition: opacity 0.4s ease-out, transform 0.4s ease-out; /* Slower transition */
             pointer-events: auto; /* Re-enable pointer events for the toast itself */
             display: flex; align-items: center; word-break: break-word; /* Ensure long words wrap */
             line-height: 1.4; /* Better readability */
         }
         .toast.show { opacity: 1; transform: translateX(0); } /* Show state */

         /* Colors and borders for different toast types */
         .toast-success { border-left: 5px solid #10b981; /* green-500 */ color: #065f46; /* green-900 */ }
         .toast-error { border-left: 5px solid #ef4444; /* red-500 */ color: #991b1b; /* red-900 */ }
         .toast-warning { border-left: 5px solid #f59e0b; /* amber-500 */ color: #9a3412; /* amber-900 */ }
         .toast-info { border-left: 5px solid #3b82f6; /* blue-500 */ color: #1e40af; /* blue-900 */ }

          /* Close button for toasts */
         .toast .close-button {
             margin-left: 1rem; /* Space from message */ background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0; line-height: 1; font-weight: bold; opacity: 0.6; transition: opacity 0.2s ease-in-out;
         }
          .toast .close-button:hover { opacity: 1; }


        /* General message box styles (for errors not handled by toast or before page load) */
         .message-box {
             padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem; text-align: left; /* Align left */
             font-size: 0.875rem; /* text-sm */
         }
          .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; } /* green */
           .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; } /* red */
           .message-box.warning { color: #9a3412; background-color: #fef3c7; border-color: #fde68a; } /* yellow/amber */
           .message-box.info { color: #1e40af; background-color: #dbeafe; border-color: #bfdbfe; } /* blue */

    </style>

     <!-- JavaScript for Toast Notifications and potential sidebar toggle -->
     <script>
         document.addEventListener('DOMContentLoaded', function() {
             const toastContainer = document.getElementById('toastContainer');
             if (!toastContainer) {
                 console.error('Toast container #toastContainer not found.');
                 return; // Exit if container isn't available
             }

             /**
              * Shows a toast notification.
              * @param {string} message - The message to display (HTML stripped in PHP).
              * @param {'success'|'error'|'warning'|'info'} type - The type of toast.
              * @param {number} [duration=5000] - How long the toast stays visible in ms. 0 means persistent.
              */
             function showToast(message, type = 'info', duration = 5000) {
                 if (!message) return;

                 const toast = document.createElement('div');
                 // innerHTML is used as the message is expected to be safe (stripped in PHP)
                 toast.innerHTML = `<p class="flex-grow">${message}</p>`; // Wrap message in paragraph for better flex alignment
                 toast.classList.add('toast', `toast-${type}`);

                 // Close button
                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.setAttribute('aria-label', 'Close');
                 closeButton.innerHTML = '×'; // HTML entity for 'x'
                 closeButton.onclick = () => {
                     toast.classList.remove('show');
                     toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                 };
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 // Use requestAnimationFrame to ensure the element is added before applying 'show' class for transition
                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });

                 // Auto-dismiss if duration is positive
                 if (duration > 0) {
                     setTimeout(() => {
                         toast.classList.remove('show');
                         // Remove the toast element after the transition ends
                         toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                     }, duration);
                 }
             }

             // Trigger toast display on DOM load if a message exists from PHP
             // PHP variables are json_encoded for safe JavaScript inclusion
             const phpMessage = <?php echo json_encode($toast_message); ?>;
             const messageType = <?php echo json_encode($toast_type); ?>;

             if (phpMessage) {
                 showToast(phpMessage, messageType);
             }

              // --- Sidebar Toggle JS (Assuming this is standard across admin pages) ---
             const sidebarToggleOpen = document.getElementById('admin-sidebar-toggle-open');
             const body = document.body;
             const sidebar = document.getElementById('admin-sidebar'); // Assuming your sidebar has this ID

             if (sidebarToggleOpen && sidebar && body) {
                 sidebarToggleOpen.addEventListener('click', () => {
                     body.classList.toggle('sidebar-open');
                     // Optional: Save state in localStorage
                     // if (body.classList.contains('sidebar-open')) {
                     //     localStorage.setItem('sidebar-state', 'open');
                     // } else {
                     //     localStorage.setItem('sidebar-state', 'closed');
                     // }
                 });

                 // Optional: Restore sidebar state from localStorage on load
                 // const savedState = localStorage.getItem('sidebar-state');
                 // if (savedState === 'open') {
                 //     body.classList.add('sidebar-open');
                 // } else {
                 //     body.classList.remove('sidebar-open'); // Ensure it's closed by default or based on 'closed' state
                 // }
             } else {
                 // console.warn("Sidebar toggle elements not found. Sidebar functionality might be disabled.");
             }

              // --- Calculate Amount Remaining on Input Change ---
              // This adds client-side calculation for immediate feedback
              const baseFeeInput = document.getElementById('base_monthly_fee');
              const vanFeeInput = document.getElementById('monthly_van_fee');
              const examFeeInput = document.getElementById('monthly_exam_fee');
              const elecFeeInput = document.getElementById('monthly_electricity_fee');
              const amountPaidInput = document.getElementById('amount_paid');
              const totalDueDisplay = document.getElementById('total_due_display');
              const amountRemainingDisplay = document.getElementById('amount_remaining_display');
              const statusDisplay = document.getElementById('status_display');


              function calculateFees() {
                  const baseFee = parseFloat(baseFeeInput.value) || 0;
                  const vanFee = parseFloat(vanFeeInput.value) || 0;
                  const examFee = parseFloat(examFeeInput.value) || 0;
                  const elecFee = parseFloat(elecFeeInput.value) || 0;
                  const amountPaid = parseFloat(amountPaidInput.value) || 0;

                  const totalDue = baseFee + vanFee + examFee + elecFee;
                  const amountRemaining = totalDue - amountPaid;

                  if(totalDueDisplay) {
                      totalDueDisplay.textContent = totalDue.toFixed(2); // Format to 2 decimal places
                  }
                  if(amountRemainingDisplay) {
                      amountRemainingDisplay.textContent = amountRemaining.toFixed(2);
                       // Update text color based on remaining amount
                      if (amountRemaining > 0.001) { // Use a small epsilon for float comparison
                          amountRemainingDisplay.classList.remove('text-green');
                          amountRemainingDisplay.classList.add('text-red');
                          if(statusDisplay) statusDisplay.textContent = 'Due';
                      } else {
                          amountRemainingDisplay.classList.remove('text-red');
                          amountRemainingDisplay.classList.add('text-green');
                           if(statusDisplay) statusDisplay.textContent = 'Paid';
                      }
                  }
                   if(statusDisplay) {
                        statusDisplay.classList.remove('text-red', 'text-green');
                        if (amountRemaining > 0.001) { // Use a small epsilon for float comparison
                            statusDisplay.classList.add('text-red');
                        } else {
                            statusDisplay.classList.add('text-green');
                        }
                   }

              }

              // Attach event listeners to relevant inputs
              if(baseFeeInput) baseFeeInput.addEventListener('input', calculateFees);
              if(vanFeeInput) vanFeeInput.addEventListener('input', calculateFees);
              if(examFeeInput) examFeeInput.addEventListener('input', calculateFees);
              if(elecFeeInput) elecFeeInput.addEventListener('input', calculateFees);
              if(amountPaidInput) amountPaidInput.addEventListener('input', calculateFees);

              // Initial calculation on page load
              calculateFees();
         });
     </script>

</head>
<body class="bg-gray-100">

    <?php
    // Include the admin sidebar.
    // Assumes admin_sidebar.php handles its own rendering (e.g., fixed position, width).
    // It should also contain the button with id="admin-sidebar-toggle-open" if the fixed header button is inside the sidebar.
    // If your sidebar is positioned absolutely or fixed, this include is correct.
    // If admin_sidebar.php renders the fixed header itself, remove the fixed-header block below.
    $sidebar_path = "./admin_sidebar.php"; // Adjust path as needed
     if (file_exists($sidebar_path)) {
         require_once $sidebar_path;
     } else {
         // Fallback/error message if sidebar file is not found
         // This block manually renders a simplified header if the sidebar is missing
         echo '<div class="fixed-header">';
         echo '<h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow pl-4 md:pl-6">Edit Monthly Fee Record <span class="text-red-500">(Sidebar file missing!)</span></h1>'; // Added padding
         echo '<span class="ml-auto text-sm text-gray-700 hidden md:inline px-4 md:px-6">Logged in as: <span class="font-semibold">' . htmlspecialchars($_SESSION['name'] ?? 'Admin') . '</span></span>';
         echo '<a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium pr-4 md:pr-6 hidden md:inline">Logout</a>';
         echo '</div>';
         // Add a message box alerting the user about the missing file below the header
         echo '<div class="w-full max-w-screen-xl mx-auto px-4 py-4" style="margin-top: 4.5rem;">'; // Add margin to push content down
         echo '<div class="message-box error" role="alert">Admin sidebar file not found! Check path: `' . htmlspecialchars($sidebar_path) . '`</div>';
         echo '</div>';
         // Continue script execution without exiting
     }
    ?>

     <!-- Toast Container (Positioned fixed) -->
     <!-- This container will hold dynamically created toast notifications -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here by the JavaScript -->
    </div>

     <!-- Fixed Header/Navbar content -->
     <!-- This block should ONLY be rendered if admin_sidebar.php was found AND it does NOT render its own fixed header. -->
     <!-- If your admin_sidebar.php *does* render a fixed header, remove this block. -->
     <?php if (file_exists("./admin_sidebar.php")): // Check if sidebar file exists before rendering header ?>
         <!-- Assuming the sidebar file *doesn't* render the fixed header itself -->
         <div class="fixed-header">
             <!-- Sidebar toggle button -->
              <!-- This button ID must match the one in the JS for the sidebar toggle to work -->
              <!-- Add ml/mr/px for spacing inside the header -->
              <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 ml-4 md:ml-6 mr-4 md:mr-6" aria-label="Toggle sidebar">
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
              </button>

              <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">Edit Monthly Fee Record</h1>
              <!-- Add ml/mr/px for spacing -->
              <span class="ml-auto text-sm text-gray-700 hidden md:inline mr-4">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
              <!-- Add px for spacing -->
              <a href="../logout.php" class="text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium pr-4 md:pr-6 hidden md:inline">Logout</a>
         </div>
     <?php endif; ?>


    <!-- Main content wrapper -->
     <!-- This div contains all the primary content of the page -->
     <!-- The CSS class 'main-content-wrapper' handles its centering and responsiveness -->
     <!-- Body padding handles the space for the fixed header -->
     <div class="main-content-wrapper">

         <?php
         // Display general error messages (e.g., invalid ID on POST, DB errors before form display)
         // Toast messages handle operation results from redirects and client-side messages.
         if (!empty($general_error)) {
              // Determine message box type - assume general_error is always 'error' in this context
              $message_type = 'error'; // Or warning/info based on error content if you add logic
              echo "<div class='message-box " . htmlspecialchars($message_type) . " mb-6' role='alert'>" . htmlspecialchars($general_error) . "</div>";
         }
         ?>


        <div class="bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Edit Monthly Fee Record</h2>

             <!-- Student Context -->
             <div class="mb-6 text-center text-gray-700">
                 <?php if ($student_id > 0): ?>
                      <p><strong class="font-semibold">Student:</strong> <?php echo $student_full_name; ?> (ID: <?php echo htmlspecialchars($student_id); ?>)</p>
                 <?php else: ?>
                      <p><strong class="font-semibold">Student:</strong> N/A</p>
                 <?php endif; ?>
                 <p><strong class="font-semibold">Fee Record ID:</strong> <?php echo htmlspecialchars($fee_id ?? 'N/A'); ?></p>
             </div>

             <!-- Back Link -->
            <div class="mb-6 text-left">
                 <?php if ($student_id > 0): ?>
                      <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">
                           <svg class="-ml-1 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                           Back to Student Fees
                      </a>
                 <?php else: ?>
                      <a href="admin_dashboard.php" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">
                           <svg class="-ml-1 mr-1 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                           Back to Dashboard
                      </a>
                 <?php endif; ?>
            </div>


            <?php if ($fee_id > 0): // Only show the form if we have a valid fee record ID ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6"> <!-- Increased vertical spacing -->

                    <input type="hidden" name="fee_id" value="<?php echo htmlspecialchars($fee_id); ?>">
                     <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id ?? ''); ?>"> <!-- Pass student_id for cancel link -->

                    <!-- Month and Year (Read-only) -->
                    <div class="grid grid-cols-2 gap-4">
                         <div>
                             <label for="fee_month_display" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                              <?php
                                  $month_names_display = [
                                      1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                      5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                      9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                  ];
                              ?>
                             <input type="text" id="fee_month_display" class="form-control" value="<?php echo htmlspecialchars($month_names_display[$fee_month_display] ?? 'N/A'); ?>" readonly>
                             <input type="hidden" name="fee_month" value="<?php echo htmlspecialchars($fee_month_display); ?>"> <!-- Keep hidden input if needed on POST -->
                         </div>
                         <div>
                             <label for="fee_year_display" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                             <input type="text" id="fee_year_display" class="form-control" value="<?php echo htmlspecialchars($fee_year_display); ?>" readonly>
                              <input type="hidden" name="fee_year" value="<?php echo htmlspecialchars($fee_year_display); ?>"> <!-- Keep hidden input if needed on POST -->
                         </div>
                    </div>

                    <!-- Fee Breakdown (Editable) -->
                    <div><h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4 mt-4">Fee Breakdown</h3></div> <!-- Slightly larger font, better spacing -->

                     <div class="grid grid-cols-1 md:grid-cols-2 gap-6"> <!-- Increased gap -->
                         <div>
                             <label for="base_monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Base Fee <span class="text-red-500">*</span></label>
                             <input type="number" name="base_monthly_fee" id="base_monthly_fee" step="0.01" min="0" class="form-control <?php echo (!empty($base_monthly_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($base_monthly_fee_display ?? ''); ?>">
                             <?php if (!empty($base_monthly_fee_err)): ?><span class="form-error"><?php echo htmlspecialchars($base_monthly_fee_err); ?></span><?php endif; ?>
                         </div>
                         <div>
                             <label for="monthly_van_fee" class="block text-sm font-medium text-gray-700 mb-1">Van Fee (Optional)</label>
                             <input type="number" name="monthly_van_fee" id="monthly_van_fee" step="0.01" min="0" class="form-control <?php echo (!empty($monthly_van_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_van_fee_display ?? ''); ?>">
                             <?php if (!empty($monthly_van_fee_err)): ?><span class="form-error"><?php echo htmlspecialchars($monthly_van_fee_err); ?></span><?php endif; ?>
                         </div>
                         <div>
                             <label for="monthly_exam_fee" class="block text-sm font-medium text-gray-700 mb-1">Exam Fee (Optional)</label>
                             <input type="number" name="monthly_exam_fee" id="monthly_exam_fee" step="0.01" min="0" class="form-control <?php echo (!empty($monthly_exam_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_exam_fee_display ?? ''); ?>">
                             <?php if (!empty($monthly_exam_fee_err)): ?><span class="form-error"><?php echo htmlspecialchars($monthly_exam_fee_err); ?></span><?php endif; ?>
                         </div>
                         <div>
                             <label for="monthly_electricity_fee" class="block text-sm font-medium text-gray-700 mb-1">Electricity Fee (Optional)</label>
                             <input type="number" name="monthly_electricity_fee" id="monthly_electricity_fee" step="0.01" min="0" class="form-control <?php echo (!empty($monthly_electricity_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_electricity_fee_display ?? ''); ?>">
                             <?php if (!empty($monthly_electricity_fee_err)): ?><span class="form-error"><?php echo htmlspecialchars($monthly_electricity_fee_err); ?></span><?php endif; ?>
                         </div>
                     </div>

                     <!-- Calculated Total Due (Display Only) -->
                     <div>
                         <label class="block text-sm font-medium text-gray-700 mb-1">Total Due (Calculated)</label>
                         <!-- Display current calculated value from PHP, JS will update on input -->
                         <p id="total_due_display" class="calculated-display">
                              <?php echo number_format($amount_due_display_calculated, 2); ?>
                         </p>
                     </div>


                    <!-- Payment Information -->
                    <div><h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4 mt-4">Payment Details</h3></div> <!-- Slightly larger font, better spacing -->

                     <div>
                         <label for="amount_paid" class="block text-sm font-medium text-gray-700 mb-1">Amount Paid <span class="text-red-500">*</span></label>
                         <input type="number" name="amount_paid" id="amount_paid" step="0.01" min="0" class="form-control <?php echo (!empty($amount_paid_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($amount_paid_display ?? ''); ?>">
                         <?php if (!empty($amount_paid_err)): ?><span class="form-error"><?php echo htmlspecialchars($amount_paid_err); ?></span><?php endif; ?>
                     </div>

                     <div>
                         <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date (Optional)</label>
                          <?php
                             // Ensure date is in the correct format for the input value attribute (YYYY-MM-DD)
                             // and handle the case where the date is empty or '0000-00-00'
                             $payment_date_input_value = null;
                             if (!empty($payment_date_display) && $payment_date_display !== '0000-00-00') {
                                  $date_obj = DateTime::createFromFormat('Y-m-d', $payment_date_display);
                                  if ($date_obj && $date_obj->format('Y-m-d') === $payment_date_display) {
                                      $payment_date_input_value = $date_obj->format('Y-m-d');
                                  } else {
                                       // If date is invalid format, don't populate input value
                                       $payment_date_input_value = '';
                                       error_log("Edit Monthly Fee: Invalid date format fetched for fee ID " . $fee_id . ": " . $payment_date_display);
                                  }
                             }
                          ?>
                         <input type="date" name="payment_date" id="payment_date" class="form-control placeholder-gray-400 <?php echo (!empty($payment_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($payment_date_input_value ?? ''); ?>">
                         <?php if (!empty($payment_date_err)): ?><span class="form-error"><?php echo htmlspecialchars($payment_date_err); ?></span><?php endif; ?>
                     </div>

                     <!-- Calculated Remaining Due (Display Only) -->
                     <div>
                          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Remaining</label>
                         <p id="amount_remaining_display" class="calculated-display
                             <?php echo ($amount_remaining_calculated > 0) ? 'text-red' : 'text-green'; ?>">
                              <?php echo number_format($amount_remaining_calculated, 2); ?>
                         </p>
                     </div>

                     <!-- Paid Status (Display Only - Derived from calculation) -->
                      <div>
                           <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                           <p id="status_display" class="calculated-display
                               <?php echo ($amount_remaining_calculated > 0) ? 'text-red' : 'text-green'; ?>">
                                <?php echo ($amount_remaining_calculated <= 0) ? 'Paid' : 'Due'; ?>
                           </p>
                       </div>

                     <div>
                         <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                         <textarea name="notes" id="notes" rows="3" class="form-control"><?php echo htmlspecialchars($notes_display ?? ''); ?></textarea>
                     </div>


                    <div class="flex flex-col sm:flex-row items-center justify-end gap-4 mt-8"> <!-- Increased top margin, added responsiveness -->
                        <button type="submit" class="w-full sm:w-auto px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">Update Fee Record</button>
                         <?php if ($student_id > 0): ?>
                             <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="w-full sm:w-auto text-center px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-800 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">Cancel</a>
                         <?php else: ?>
                              <a href="admin_dashboard.php" class="w-full sm:w-auto text-center px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-800 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">Back to Dashboard</a>
                         <?php endif; ?>
                    </div>
                </form>
            <?php else: ?>
                <!-- Message displayed if fee_id was invalid on GET or became invalid on POST -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6 text-center">
                    <p class="text-red-600 font-semibold">Could not load the monthly fee record.</p>
                    <div class="mt-4">
                       <a href="admin_dashboard.php" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">
                            <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                           Back to Dashboard
                       </a>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- End of bg-white card -->

    </div> <!-- End of main content wrapper -->

</body>
</html>