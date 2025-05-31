<?php
// School/admin/add_bulk_monthly_fee.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
// Allowing Principal as they often manage finances/fees
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can add bulk fee records.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Add Bulk Monthly Fee Records";


// --- Variables ---
// Filter variables
$selected_year = $_GET['academic_year'] ?? '';
$selected_class = $_GET['current_class'] ?? '';
// Use 'takes_van' in filter variable name for clarity, but keep form input name 'van_filter'
$selected_van_filter = $_GET['van_filter'] ?? ''; // 'all', 'yes', 'no'

// Fee input variables (for POST submission)
$fee_month_input = $_POST['fee_month'] ?? '';
$fee_year_input = $_POST['fee_year'] ?? '';
$base_monthly_fee_input = $_POST['base_monthly_fee'] ?? '';
$monthly_van_fee_input = $_POST['monthly_van_fee'] ?? '';
$monthly_exam_fee_input = $_POST['monthly_exam_fee'] ?? '';
$monthly_electricity_fee_input = $_POST['monthly_electricity_fee'] ?? '';

// Display variables (retain POST values on error, use defaults on GET)
$fee_month_display = $fee_month_input;
$fee_year_display = $fee_year_input ?: date('Y'); // Default year to current
$base_monthly_fee_display = $base_monthly_fee_input;
$monthly_van_fee_display = $monthly_van_fee_input;
$monthly_exam_fee_display = $monthly_exam_fee_input;
$monthly_electricity_fee_display = $monthly_electricity_fee_input;


// Error variables
$filter_errors = []; // Currently unused, but kept
$fee_input_errors = [];
$bulk_operation_results = null; // To store summary of bulk insert operation

// Available options for filters (fetched from DB)
$available_years = [];
$available_classes = [];

// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'

// Check for operation messages set in other pages or previous requests
if (isset($_SESSION['operation_message'])) {
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg)); // Use strip_tags for safety

     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
          $toast_type = 'success';
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'problem') !== false) {
          $toast_type = 'error';
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) {
          $toast_type = 'warning';
     } else {
          $toast_type = 'info';
     }
    $toast_message = strip_tags($msg); // Pass the stripped message to JS
    unset($_SESSION['operation_message']); // Clear the session message
}


// --- Fetch Filter Options ---
if ($link === false) {
    // DB connection error handled below, no filters fetched
     if (empty($toast_message)) { // Only set if no other toast message exists
         $toast_message = "Database connection error. Cannot load filter options.";
         $toast_type = 'error';
     }
     error_log("Add Bulk Fee DB connection failed: " . mysqli_connect_error());
} else {
    // Fetch distinct academic years from results (or fees table if more comprehensive)
    // Using results table as it's generally populated and provides context
    $sql_years = "SELECT DISTINCT academic_year FROM student_exam_results WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC";
    if ($result_years = mysqli_query($link, $sql_years)) {
        while ($row = mysqli_fetch_assoc($result_years)) {
            $available_years[] = htmlspecialchars($row['academic_year']);
        }
        mysqli_free_result($result_years);
    } else {
         // Log the error, but don't set a toast here, it might be noisy if DB is down
         error_log("Error fetching years for filter: " . mysqli_error($link));
    }

    // Fetch distinct classes from students table
    $sql_classes = "SELECT DISTINCT current_class FROM students WHERE current_class IS NOT NULL AND current_class != '' ORDER BY current_class ASC";
     if ($result_classes = mysqli_query($link, $sql_classes)) {
         while ($row = mysqli_fetch_assoc($result_classes)) {
             $available_classes[] = htmlspecialchars($row['current_class']);
         }
         mysqli_free_result($result_classes);
     } else {
          // Log the error
          error_log("Error fetching classes for filter: " . mysqli_error($link));
     }
}


// --- Handle POST Request (Bulk Fee Addition) ---
$students_for_fee_processing = []; // List of students fetched based on filters for POST processing
// $processing_message used below for results summary

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Re-collect filter values from POST as they were hidden inputs
     $selected_year = $_POST['academic_year'] ?? '';
     $selected_class = $_POST['current_class'] ?? '';
     $selected_van_filter = $_POST['van_filter'] ?? '';

    // Re-validate fee inputs
    $fee_month_for_db = null;
    $fee_year_for_db = null;
    $base_monthly_fee_for_db = null; // Will be null if validation fails
    $monthly_van_fee_for_db = 0.0; // Default 0 if empty or invalid
    $monthly_exam_fee_for_db = 0.0; // Default 0 if empty or invalid
    $monthly_electricity_fee_for_db = 0.0; // Default 0 if empty or invalid

    // Validate Month
    if (empty($fee_month_input)) {
        $fee_input_errors['fee_month'] = "Please select a month.";
    } else {
        $month_int = filter_var($fee_month_input, FILTER_VALIDATE_INT);
        if ($month_int === false || $month_int < 1 || $month_int > 12) {
            $fee_input_errors['fee_month'] = "Invalid month selected.";
        } else {
            $fee_month_for_db = $month_int;
        }
    }

    // Validate Year
    if (empty($fee_year_input)) {
        $fee_input_errors['fee_year'] = "Please enter a year.";
    } else {
         $year_int = filter_var($fee_year_input, FILTER_VALIDATE_INT);
         if ($year_int === false || $year_int < 2000 || $year_int > 2100) { // Simple year range validation
              $fee_input_errors['fee_year'] = "Invalid year (e.g., 2000-2100).";
         } else {
              $fee_year_for_db = $year_int;
         }
    }

    // Validate Base Fee
    if ($base_monthly_fee_input === '') { // Allow 0 but not empty string
        $fee_input_errors['base_monthly_fee'] = "Base fee is required.";
    } else {
         $base_fee_float = filter_var($base_monthly_fee_input, FILTER_VALIDATE_FLOAT);
         if ($base_fee_float === false || $base_fee_float < 0 ) {
              $fee_input_errors['base_monthly_fee'] = "Please enter a valid non-negative number for base fee.";
         } else {
              $base_monthly_fee_for_db = $base_fee_float;
         }
    }

    // Validate Optional Fee fields (can be empty, treated as 0)
    if (!empty($monthly_van_fee_input)) {
        $van_fee_float = filter_var($monthly_van_fee_input, FILTER_VALIDATE_FLOAT);
         if ($van_fee_float === false || $van_fee_float < 0) {
             $fee_input_errors['monthly_van_fee'] = "Invalid Van fee.";
         } else {
             $monthly_van_fee_for_db = $van_fee_float;
         }
    } else {
        $monthly_van_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }

     if (!empty($monthly_exam_fee_input)) {
        $exam_fee_float = filter_var($monthly_exam_fee_input, FILTER_VALIDATE_FLOAT);
         if ($exam_fee_float === false || $exam_fee_float < 0) {
             $fee_input_errors['monthly_exam_fee'] = "Invalid Exam fee.";
         } else {
             $monthly_exam_fee_for_db = $exam_fee_float;
         }
    } else {
        $monthly_exam_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }

     if (!empty($monthly_electricity_fee_input)) {
        $elec_fee_float = filter_var($monthly_electricity_fee_input, FILTER_VALIDATE_FLOAT);
         if ($elec_fee_float === false || $elec_fee_float < 0) {
             $fee_input_errors['monthly_electricity_fee'] = "Invalid Electricity fee.";
         } else {
             $monthly_electricity_fee_for_db = $elec_fee_float;
         }
    } else {
         $monthly_electricity_fee_for_db = 0.0; // Explicitly set to 0 if empty and no validation error
    }


    // Proceed if fee inputs are valid AND DB connection is good
    if (empty($fee_input_errors) && $link !== false) {

        // --- Fetch Students based on Filters (Using POSTed filter values) ---
        // CORRECTED: Use `takes_van` in SELECT list
         $sql_select_students = "SELECT user_id, full_name, current_class, whatsapp_number, takes_van FROM students"; // <-- Uses 'takes_van'
         $student_where_clauses = [];
         $student_param_types = "";
         $student_param_values = [];

        if (!empty($selected_class)) {
            $student_where_clauses[] = "current_class = ?";
            $student_param_types .= "s";
            $student_param_values[] = $selected_class;
        }

        // CORRECTED: Use `takes_van` in WHERE clause for filtering
         if ($selected_van_filter === 'yes') {
             $student_where_clauses[] = "takes_van = 1"; // <-- Uses 'takes_van'
         } elseif ($selected_van_filter === 'no') {
             $student_where_clauses[] = "takes_van = 0"; // <-- Uses 'takes_van'
         }
         // If van_filter is 'all' or empty, no van clause is added.

        if (!empty($student_where_clauses)) {
            $sql_select_students .= " WHERE " . implode(" AND ", $student_where_clauses);
        }
        $sql_select_students .= " ORDER BY current_class ASC, full_name ASC"; // Order for processing and re-display

        if ($stmt_select = mysqli_prepare($link, $sql_select_students)) {
             if (!empty($student_param_types)) {
                 // Use call_user_func_array for dynamic binding
                 $bind_params = [$student_param_types];
                 foreach ($student_param_values as &$value) { $bind_params[] = &$value; }
                 call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_select], $bind_params));
                 unset($value); // Unset the reference
             }

            if (mysqli_stmt_execute($stmt_select)) {
                $result_select = mysqli_stmt_get_result($stmt_select);
                 $students_for_fee_processing = mysqli_fetch_all($result_select, MYSQLI_ASSOC);
                mysqli_free_result($result_select);

                 if (empty($students_for_fee_processing)) {
                      // No students matched filters for processing
                      $processing_message = "No students found matching the selected filters. Cannot add fees.";
                      $toast_type = 'warning';
                 }

            } else {
                 // ERROR FETCHING STUDENTS FOR PROCESSING
                 // The error message here needs to reflect the missing column if that's the issue.
                 // mysqli_stmt_error will give the exact SQL error.
                 $db_error = mysqli_stmt_error($stmt_select);
                 $processing_message = "Error fetching students for processing. Database error: " . htmlspecialchars($db_error);
                 $toast_type = 'error';
                 error_log("Add Bulk Fee student select query failed: " . $db_error);
            }
            mysqli_stmt_close($stmt_select);
        } else {
             // ERROR PREPARING STUDENT SELECT STATEMENT (DURING POST)
             // This is where the 'Error preparing student list statement' comes from.
             // Include the actual SQL error for better debugging.
             $db_error = mysqli_error($link);
             $processing_message = "Error preparing student select statement. Database error: " . htmlspecialchars($db_error); // Plain text message + DB error
             $toast_type = 'error';
             error_log("Add Bulk Fee prepare student select failed: " . $db_error);
        }

        // --- Process Bulk Insert if Students Found AND No Processing Message Set ---
        // Check if $students_for_fee_processing is not empty AND $processing_message is still empty
        // This condition ensures we only attempt inserts if students were fetched successfully and no errors occurred before this point.
        if (!empty($students_for_fee_processing) && empty($processing_message)) {

            $added_count = 0;
            $skipped_duplicate_count = 0;
            $failed_insert_count = 0; // Count failures due to DB errors during insert or duplicate check preparation

            // Start transaction - important for bulk operations
            mysqli_begin_transaction($link);
            $transaction_success = true; // Flag to track transaction status (optional for full rollback)


             // Prepare the insert statement ONCE outside the loop
            $sql_insert_fee = "INSERT INTO student_monthly_fees (student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)";

            if ($stmt_insert = mysqli_prepare($link, $sql_insert_fee)) {

                foreach ($students_for_fee_processing as $student) {
                    $student_id = $student['user_id'];
                    $is_duplicate = false; // Assume not duplicate initially

                    // Check for duplicate entry for this specific student, year, and month
                    $sql_check_duplicate = "SELECT id FROM student_monthly_fees WHERE student_id = ? AND fee_year = ? AND fee_month = ?";
                     if ($stmt_check = mysqli_prepare($link, $sql_check_duplicate)) {
                         mysqli_stmt_bind_param($stmt_check, "iii", $student_id, $fee_year_for_db, $fee_month_for_db);
                         mysqli_stmt_execute($stmt_check);
                         mysqli_stmt_store_result($stmt_check);
                         $is_duplicate = mysqli_stmt_num_rows($stmt_check) > 0;
                         mysqli_stmt_close($stmt_check);
                     } else {
                         // Handle error preparing duplicate check statement - log and treat as failure
                         $db_error = mysqli_error($link);
                         error_log("Add Bulk Fee prepare duplicate check failed for student ID " . $student_id . ": " . $db_error);
                         $failed_insert_count++; // Count as a failure because we couldn't verify uniqueness
                         $transaction_success = false; // Mark transaction for potential rollback
                         continue; // Skip insert attempt for this student
                     }


                    if ($is_duplicate) {
                        $skipped_duplicate_count++;
                    } else {
                        // Calculate amount_due for THIS student
                        $current_student_amount_due = (float)$base_monthly_fee_for_db;
                         // CORRECTED: Check against $student['takes_van']
                        // Add van fee only if the student uses van service (check against 'takes_van' from DB) AND a non-zero van fee amount was provided in the form
                         // Check if 'takes_van' key exists in the fetched student data AND its value is 1
                         $is_van_user = isset($student['takes_van']) && (($student['takes_van'] ?? 0) == 1); // Use ?? 0 for safety


                        if ($is_van_user && (float)$monthly_van_fee_for_db > 0) {
                           $current_student_amount_due += (float)$monthly_van_fee_for_db;
                        }

                         // Add exam fee if a non-zero amount was provided
                         if ((float)$monthly_exam_fee_for_db > 0) {
                             $current_student_amount_due += (float)$monthly_exam_fee_for_db;
                         }
                         // Add electricity fee if a non-zero amount was provided
                         if ((float)$monthly_electricity_fee_for_db > 0) {
                              $current_student_amount_due += (float)$monthly_electricity_fee_for_db;
                         }


                        // Bind parameters for the insert statement and execute for this student
                        $bind_types_fee = "iiiddddd";
                        // Using temporary variables to bind by reference
                         $tmp_student_id = $student_id;
                         $tmp_fee_year = $fee_year_for_db;
                         $tmp_fee_month = $fee_month_for_db;
                         $tmp_base_fee = $base_monthly_fee_for_db;

                         // The actual van fee amount stored: it's the provided amount IF the student takes van AND column exists, otherwise 0.0
                         $applied_van_fee = ($is_van_user && (float)$monthly_van_fee_for_db > 0) ? (float)$monthly_van_fee_for_db : 0.0;

                         $tmp_van_fee = $applied_van_fee; // Bind the *applied* van fee
                         $tmp_exam_fee = $monthly_exam_fee_for_db;
                         $tmp_elec_fee = $monthly_electricity_fee_for_db;
                         $tmp_amount_due = $current_student_amount_due; // Already calculated

                         mysqli_stmt_bind_param($stmt_insert, $bind_types_fee,
                              $tmp_student_id,
                              $tmp_fee_year,
                              $tmp_fee_month,
                              $tmp_base_fee,
                              $tmp_van_fee,
                              $tmp_exam_fee,
                              $tmp_elec_fee,
                              $tmp_amount_due
                         );

                        if (mysqli_stmt_execute($stmt_insert)) {
                            $added_count++;
                        } else {
                            // Handle insert execution error - log and fail this student's insert
                             $db_error = mysqli_stmt_error($stmt_insert);
                             error_log("Add Bulk Fee insert failed for student ID " . $student_id . " Year " . $fee_year_for_db . " Month " . $fee_month_for_db . ": " . $db_error);
                            $failed_insert_count++;
                            $transaction_success = false; // Mark transaction for potential rollback
                        }
                    } // end if !is_duplicate
                } // end foreach student

                 mysqli_stmt_close($stmt_insert); // Close the insert statement after the loop

                 // Decide whether to commit or rollback the entire batch
                 if (!$transaction_success && $failed_insert_count > 0) { // Only rollback if there were actual failures
                     mysqli_rollback($link);
                     $processing_message = "Bulk fee addition partially failed or encountered errors. Rolling back transaction. " . $failed_insert_count . " record(s) failed to insert.";
                     $toast_type = 'error'; // Use error type for rollback
                     error_log("Bulk fee addition transaction rolled back due to " . $failed_insert_count . " failures.");
                 } else {
                      // Transaction was successful, or all failures were caught before insert attempt (e.g., duplicate check prepare)
                      mysqli_commit($link);
                      // Set success/summary message for toast/display
                      if ($added_count > 0) {
                          // If any records were added successfully, use success toast
                          $_SESSION['operation_message'] = "<p class='text-green-600'>" . $added_count . " monthly fee records added successfully.</p>";
                          $toast_type = 'success';
                      }

                      $processing_message = "Bulk fee addition complete. " . $added_count . " records added. " . $skipped_duplicate_count . " skipped (duplicate). " . $failed_insert_count . " failed.";

                       if ($added_count == 0 && $skipped_duplicate_count == 0 && $failed_insert_count == 0) {
                          // Edge case: no students matched filters, but this should have been caught earlier.
                           // Or no students met criteria for fee application (e.g. base fee 0 and optional fees 0 for all).
                           $processing_message = "No fee records were added.";
                           $toast_type = 'info';
                       } elseif ($failed_insert_count > 0) {
                           // If some failed but others were added and committed
                            $toast_type = 'warning'; // Use warning if some failed but committed
                            // The processing_message already includes the failed count
                       }
                 }

                // Redirect or stay on page? Staying on page shows the results message. Let's stay.
            } else {
                 // ERROR PREPARING INSERT STATEMENT (DURING POST)
                 $db_error = mysqli_error($link);
                 $processing_message = "Error preparing fee insert statement for bulk operation. Database error: " . htmlspecialchars($db_error); // Plain text message
                 $toast_type = 'error';
                 error_log("Add Bulk Fee prepare insert failed: " . $db_error);
                 mysqli_rollback($link); // Rollback the transaction if prepare failed
            }

        } // else if (!empty($students_for_fee_processing) && empty($processing_message))
         // If students_for_fee_processing was empty or processing_message was already set,
         // the messages were handled above the bulk insert block.


    } elseif (!empty($fee_input_errors)) {
        // Fee input validation failed on POST, errors are in $fee_input_errors
         $processing_message = "Please correct the errors in the fee amount fields."; // Plain text message
         $toast_type = 'error';
         // The student list for display will be fetched in the block below using the POSTed filter values.
    } elseif ($link === false) {
         // DB connection failed, error already logged and message set via toast.
         // No need to fetch students or process fees.
         $processing_message = "Database connection failed. Cannot process fee addition.";
         $toast_type = 'error';
    }


} // --- End POST Request Handling ---


// --- Handle GET Request OR POST with errors (Display Students List) ---
// This block runs for initial GET or if POST had validation/processing/DB errors *before* insert loop
$students_list_for_display = [];
$students_list_message = "Apply filters to see students."; // Default message when no filters are applied yet
$students_list_message_type = 'info'; // Default style for the list message

// Check if filters are applied OR if it was a POST request (even with errors, show the targeted students)
// We always try to fetch the student list if filters are set or if it's a POST request,
// unless the DB connection is down.
$is_filtered = !empty($selected_year) || !empty($selected_class) || !empty($selected_van_filter);
$should_fetch_list = $is_filtered || $_SERVER["REQUEST_METHOD"] == "POST";


if ($should_fetch_list && $link !== false) {

    // Build the SQL query to select students based on filters (using $selected_* variables)
    // CORRECTED: Use `takes_van` in SELECT list
    $sql_select_students_display = "SELECT user_id, full_name, current_class, whatsapp_number, takes_van FROM students"; // <-- Uses 'takes_van'
    $student_where_clauses_display = [];
    $student_param_types_display = "";
    $student_param_values_display = [];

    // Academic Year filter is only for context in the filter form, not student selection SQL
    if (!empty($selected_class)) {
        $student_where_clauses_display[] = "current_class = ?";
        $student_param_types_display .= "s";
        $student_param_values_display[] = $selected_class;
    }

     // CORRECTED: Use `takes_van` in WHERE clause for filtering
     if ($selected_van_filter === 'yes') {
         $student_where_clauses_display[] = "takes_van = 1"; // <-- Uses 'takes_van'
     } elseif ($selected_van_filter === 'no') {
         $student_where_clauses_display[] = "takes_van = 0"; // <-- Uses 'takes_van'
     }

    if (!empty($student_where_clauses_display)) {
        $sql_select_students_display .= " WHERE " . implode(" AND ", $student_where_clauses_display);
    }
    $sql_select_students_display .= " ORDER BY current_class ASC, full_name ASC"; // Order for display

    // Prepare and execute the student select statement for display
    if ($stmt_select_display = mysqli_prepare($link, $sql_select_students_display)) {
         if (!empty($student_param_types_display)) {
             // Use call_user_func_array for dynamic binding
             $bind_params_display = [$student_param_types_display];
             foreach ($student_param_values_display as &$value) { $bind_params_display[] = &$value; }
             call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_select_display], $bind_params_display));
             unset($value); // Unset the reference
         }

        if (mysqli_stmt_execute($stmt_select_display)) {
            $result_select_display = mysqli_stmt_get_result($stmt_select_display);
             $students_list_for_display = mysqli_fetch_all($result_select_display, MYSQLI_ASSOC);
            mysqli_free_result($result_select_display);

            if (empty($students_list_for_display)) {
                $students_list_message = "No students found matching the selected filters.";
                $students_list_message_type = 'warning'; // Use warning style for no students found

                 // Set toast message ONLY if it's a GET request with filters applied and no other toast is set
                 if (empty($toast_message) && $_SERVER["REQUEST_METHOD"] == "GET" && $is_filtered) {
                     $toast_message = $students_list_message;
                     $toast_type = $students_list_message_type;
                 }

            } else {
                 $students_list_message = "Found " . count($students_list_for_display) . " students matching filters:";
                 $students_list_message_type = 'info'; // Use info style for showing students found
            }

        } else {
             // ERROR FETCHING STUDENTS LIST FOR DISPLAY
             // This is where the 'Error preparing student list statement' comes from on GET/filter submit.
             // Include the actual SQL error for better debugging.
             $db_error = mysqli_stmt_error($stmt_select_display);
             $students_list_message = "Error fetching students list. Database error: " . htmlspecialchars($db_error); // Plain text message + DB error
             $students_list_message_type = 'error'; // Use error style

             // Set toast message if not already set
             if (empty($toast_message)) {
                  $toast_message = $students_list_message;
                  $toast_type = $students_list_message_type;
             }
             error_log("Add Bulk Fee student select query failed for display: " . $db_error);
        }
        mysqli_stmt_close($stmt_select_display);
    } else {
         // ERROR PREPARING STUDENT SELECT STATEMENT (FOR DISPLAY)
         // This is where the 'Error preparing student list statement' comes from on initial load (if DB link is good but query bad).
         // Include the actual SQL error for better debugging.
         $db_error = mysqli_error($link);
         $students_list_message = "Error preparing student list statement. Database error: " . htmlspecialchars($db_error); // Plain text message + DB error
         $students_list_message_type = 'error'; // Use error style

          // Set toast message if not already set
          if (empty($toast_message)) {
               $toast_message = $students_list_message;
               $toast_type = $students_list_message_type;
          }
         error_log("Add Bulk Fee prepare student list failed: " . $db_error);
    }
} elseif ($link === false) {
     // DB connection failed - this case is handled early and sets a toast message.
     // Set the display message as well.
     $students_list_message = "Database connection failed. Cannot fetch student list.";
     $students_list_message_type = 'error';
     // Toast message already set above
}
// If !$should_fetch_list (i.e., initial GET with no filters), $students_list_message remains the default "Apply filters..."


// Close connection
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>

<?php
// Include the header file.
require_once "./admin_header.php";
?>

     <!-- Custom Styles -->
     <style>
         body {
             padding-top: 4.5rem; /* Space for fixed header */
             background-color: #f3f4f6;
             min-height: 100vh;
              transition: padding-left 0.3s ease; /* Smooth transition for padding when sidebar opens/closes */
         }
         body.sidebar-open {
             padding-left: 16rem; /* Adjust based on your sidebar width */
         }
         .fixed-header {
              position: fixed;
              top: 0;
              left: 0;
              right: 0;
              height: 4.5rem;
              background-color: #ffffff;
              box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
              padding: 1rem;
              display: flex;
              align-items: center;
              z-index: 10;
               transition: left 0.3s ease;
         }
          body.sidebar-open .fixed-header {
              left: 16rem;
          }
         .main-content-wrapper {
             width: 100%;
             max-width: 1280px;
             margin-left: auto;
             margin-right: auto;
             padding: 2rem 1rem; /* py-8 px-4 */
         }
          @media (min-width: 768px) { /* md breakpoint */
               .main-content-wrapper {
                   padding-left: 2rem; /* md:px-8 */
                   padding-right: 2rem; /* md:px-8 */
               }
          }

         .form-error {
            color: #dc2626; /* red-600 */
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25em;
            display: block;
         }
         .form-control.is-invalid {
             border-color: #dc2626; /* red-600 */
         }
         .form-control.is-invalid:focus {
              border-color: #ef4444; /* red-500 */
              box-shadow: 0 0 0 1px #ef4444; /* ring red-500 */
         }
         input[type="number"]::placeholder {
               color: #9ca3af; /* gray-400 */
           }

         /* --- Toast Notification Styles --- */
         .toast-container {
             position: fixed; top: 1rem; right: 1rem; z-index: 100; display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; max-width: 90%;
         }
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto; min-width: 200px; max-width: 350px; display: flex; align-items: center; word-break: break-word;
         }
         .toast.show { opacity: 1; transform: translateX(0); }
         .toast-success { border-left: 5px solid #10b981; color: #065f46; }
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }
         .toast .close-button {
             margin-left: auto; background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0 0.25rem; line-height: 1; font-weight: bold;
         }

          /* Specific styles for student list */
          .student-list-container {
               background-color: #ffffff;
               padding: 1.5rem;
               border-radius: 0.5rem;
               box-shadow: 0 1px 3px rgba(0,0,0,0.1);
               margin-bottom: 2rem;
          }
           .student-list-container h3 {
                font-size: 1.25rem;
                font-weight: 600;
                color: #1f2937; /* Default H3 color */
                margin-bottom: 1rem;
           }
            /* Style for the message box inside the list container */
           .student-list-container .message-box {
                margin: 0; /* Remove margin to fit better */
                padding: 0.75rem 1rem; /* Adjust padding */
           }
          .student-list {
               list-style: none;
               padding: 0;
               margin: 0;
               display: flex;
               flex-wrap: wrap; /* Allow items to wrap */
               gap: 0.5rem; /* Space between list items */
          }
           .student-list li {
               background-color: #f9fafb; /* gray-50 */
               border: 1px solid #e5e7eb; /* gray-200 */
               padding: 0.4rem 0.8rem; /* py-1.5 px-3 */
               border-radius: 0.25rem; /* rounded-sm */
               font-size: 0.875rem; /* text-sm */
               color: #374151; /* gray-700 */
           }
           /* Message styles (reused for general messages and specific ones) */
            .message-box {
                padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem; text-align: center;
            }
             .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; } /* green */
              .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; } /* red */
              .message-box.warning { color: #b45309; background-color: #fffce0; border-color: #fde68a; } /* yellow/amber */
              .message-box.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; } /* cyan/blue */

     </style>
     <script>
          // --- Toast Notification JS ---
         document.addEventListener('DOMContentLoaded', function() {
             const toastContainer = document.getElementById('toastContainer');
             if (!toastContainer) {
                 console.error('Toast container #toastContainer not found.');
             }

             function showToast(message, type = 'info', duration = 5000) {
                 if (!message || !toastContainer) return;

                 const toast = document.createElement('div');
                 // Use innerHTML to allow basic HTML tags like <p> if they come from session messages,
                 // but ensure the content is safe (strip_tags is used in PHP).
                 toast.innerHTML = message;
                 toast.classList.add('toast', `toast-${type}`);

                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.textContent = '×';
                 closeButton.onclick = () => toast.remove();
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });

                 if (duration > 0) {
                     setTimeout(() => {
                         toast.classList.remove('show');
                         toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                     }, duration);
                 }
             }

             // Trigger toast display on DOM load if a message exists
             const phpMessage = <?php echo json_encode($toast_message); ?>;
             const messageType = <?php echo json_encode($toast_type); ?>;

             if (phpMessage) {
                 showToast(phpMessage, messageType);
             }
         });
     </script>
</head>
<body class="bg-gray-100">

    <?php
    // Include the admin sidebar and fixed header.
    // Assumes admin_sidebar.php renders the fixed header or includes logic for it.
    $sidebar_path = "./admin_sidebar.php";
    if (file_exists($sidebar_path)) {
        require_once $sidebar_path;
    } else {
        // Fallback header if sidebar file is missing
        echo '<div class="fixed-header">';
        echo '<h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">Add Bulk Monthly Fee Records (Sidebar file missing!)</h1>';
        echo '<span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold">' . htmlspecialchars($_SESSION['name'] ?? 'Admin') . '</span></span>';
        echo '<a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>';
        echo '</div>';
         echo '<div class="w-full max-w-screen-xl mx-auto px-4 py-8" style="margin-top: 4.5rem;">';
         echo '<div class="message-box error" role="alert">Admin sidebar file not found! Check path: `' . htmlspecialchars($sidebar_path) . '`</div>';
         echo '</div>';
    }
    ?>

    <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here by JS -->
    </div>

    <!-- Main content wrapper -->
    <div class="main-content-wrapper">

        <h2 class="text-2xl font-bold mb-6 text-gray-800">Add Bulk Monthly Fee Records</h2>

         <!-- Filter Form -->
         <!-- Uses GET method to update the student list without processing fees -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="bg-white p-6 rounded-lg shadow-md mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
             <!-- Academic Year Filter - Not used for student selection SQL, but can be for context -->
             <div>
                 <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year (For Context)</label>
                 <select name="academic_year" id="academic_year" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                     <option value="">All Years</option>
                     <?php foreach ($available_years as $year): ?>
                         <option value="<?php echo $year; ?>" <?php echo ($selected_year === $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
             <!-- Class Filter -->
             <div>
                 <label for="current_class" class="block text-sm font-medium text-gray-700">Filter by Class</label>
                 <select name="current_class" id="current_class" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                     <option value="">All Classes</option>
                     <?php foreach ($available_classes as $class): ?>
                         <option value="<?php echo $class; ?>" <?php echo ($selected_class === $class) ? 'selected' : ''; ?>><?php echo $class; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
             <!-- Van Service Filter -->
             <!-- CORRECTED: Filter now uses the 'takes_van' column -->
             <div>
                 <label for="van_filter" class="block text-sm font-medium text-gray-700">Filter by Van Service</label>
                 <select name="van_filter" id="van_filter" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                     <option value="all" <?php echo ($selected_van_filter === 'all' || $selected_van_filter === '') ? 'selected' : ''; ?>>All Students</option>
                     <option value="yes" <?php echo ($selected_van_filter === 'yes') ? 'selected' : ''; ?>>Uses Van Service</option>
                     <option value="no" <?php echo ($selected_van_filter === 'no') ? 'selected' : ''; ?>>Does NOT Use Van Service</option>
                 </select>
             </div>
             <div class="md:col-span-3 text-right"> <!-- Span across columns and align right -->
                  <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">Apply Filters</button>
             </div>
        </form>

         <!-- Students List Preview -->
         <div class="student-list-container">
              <?php
               // Determine message style based on list message type
               $list_message_class = 'info'; // Default
               if ($students_list_message_type === 'error') $list_message_class = 'error';
               elseif ($students_list_message_type === 'warning') $list_message_class = 'warning';

               // If it's an error or warning, display as a message box.
               // Otherwise, display the info message as a standard heading.
              if ($students_list_message_type === 'error' || $students_list_message_type === 'warning') {
                   echo "<div class='message-box " . $list_message_class . " mb-4'>" . htmlspecialchars($students_list_message) . "</div>";
               } else {
                    echo "<h3>" . htmlspecialchars($students_list_message) . "</h3>";
               }
              ?>

               <?php if (!empty($students_list_for_display)): ?>
                    <ul class="student-list">
                         <?php foreach($students_list_for_display as $student): ?>
                             <li>
                                 <?php echo htmlspecialchars($student['full_name']) . " (Class: " . htmlspecialchars($student['current_class']) . ")"; ?>
                                 <?php
                                 // CORRECTED: Show Van User indicator based on 'takes_van' column
                                 if (($student['takes_van'] ?? 0) == 1) {
                                     echo ' - Van User';
                                 }
                                 ?>
                             </li>
                         <?php endforeach; ?>
                    </ul>
               <?php elseif ($should_fetch_list && $students_list_message_type !== 'error' && $students_list_message_type !== 'warning'): ?>
                    <!-- This shows the 'No students found...' message only if filters were applied and no error occurred -->
                    <p class="text-gray-600 italic text-sm">No students matched the selected criteria.</p>
               <?php elseif (!$should_fetch_list && $link !== false): ?>
                     <p class="text-gray-600 italic text-sm">Select criteria above and click "Apply Filters" to preview students.</p>
               <?php endif; ?>
         </div>

        <!-- Fee Input Form (only shown if students are found matching filters) -->
        <?php if (!empty($students_list_for_display)): ?>
             <div class="bg-white p-6 rounded-lg shadow-md">
                 <h3 class="text-xl font-semibold mb-4 text-gray-800">Enter Monthly Fee Details</h3>

                 <?php
                 // Display processing results/errors from POST
                  if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($processing_message)) {
                       $message_type = 'info';
                       // Use the type explicitly set in PHP ($toast_type) for POST processing messages if available,
                       // otherwise infer from message content.
                       $message_type_from_toast = $toast_type; // Get the type set for the toast

                       if (!empty($message_type_from_toast)) {
                            $message_type = $message_type_from_toast;
                       } else {
                            // Fallback to inferring from message content if toast type isn't set for this message
                           $msg_lower = strtolower(strip_tags($processing_message));
                           if (strpos($msg_lower, 'success') !== false || strpos($msg_lower, 'added') !== false) {
                                $message_type = 'success';
                           } elseif (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                                $message_type = 'error';
                           } elseif (strpos($msg_lower, 'skipped') !== false || strpos($msg_lower, 'no students found') !== false || strpos($msg_lower, 'warning') !== false) {
                                $message_type = 'warning';
                           } else {
                                $message_type = 'info'; // Fallback
                           }
                       }

                       echo "<div class='message-box " . htmlspecialchars($message_type) . " mb-4'>" . htmlspecialchars($processing_message) . "</div>";
                   }
                 ?>

                 <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">

                     <!-- Hidden inputs to carry filter values through POST -->
                     <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($selected_year); ?>">
                     <input type="hidden" name="current_class" value="<?php echo htmlspecialchars($selected_class); ?>">
                     <input type="hidden" name="van_filter" value="<?php echo htmlspecialchars($selected_van_filter); ?>">


                     <!-- Month and Year for the Fee Record -->
                     <div class="grid grid-cols-2 gap-4">
                         <div>
                             <label for="fee_month_post" class="block text-sm font-medium text-gray-700 mb-1">Month <span class="text-red-500">*</span></label>
                             <select name="fee_month" id="fee_month_post" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['fee_month'])) ? 'is-invalid' : ''; ?>">
                                 <option value="">Select Month</option>
                                 <?php
                                 $month_names_select = [
                                     1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                     5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                     9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                 ];
                                 for ($m = 1; $m <= 12; $m++) {
                                     $selected = ((int)($fee_month_display ?? 0) === $m) ? 'selected' : '';
                                     echo "<option value='" . $m . "'" . $selected . ">" . htmlspecialchars($month_names_select[$m]) . "</option>";
                                 }
                                 ?>
                             </select>
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['fee_month'] ?? ''); ?></span>
                         </div>
                         <div>
                             <label for="fee_year_post" class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                             <input type="number" name="fee_year" id="fee_year_post" step="1" min="2000" max="2100" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['fee_year'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($fee_year_display ?? date('Y')); ?>" placeholder="e.g., 2024">
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['fee_year'] ?? ''); ?></span>
                         </div>
                     </div>

                     <!-- Fee Breakdown Inputs -->
                     <div><h4 class="text-md font-semibold text-gray-700 border-b pb-1 mb-3 mt-3">Fee Breakdown for ALL Selected Students</h4></div>

                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                              <label for="base_monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Base Fee <span class="text-red-500">*</span></label>
                              <input type="number" name="base_monthly_fee" id="base_monthly_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['base_monthly_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($base_monthly_fee_display ?? ''); ?>" placeholder="e.g., 1200.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['base_monthly_fee'] ?? ''); ?></span>
                          </div>
                          <div>
                              <label for="monthly_van_fee" class="block text-sm font-medium text-gray-700 mb-1">Van Fee <span class="text-gray-500">(Only applied to students who use van service)</span></label>
                              <input type="number" name="monthly_van_fee" id="monthly_van_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['monthly_van_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_van_fee_display ?? ''); ?>" placeholder="e.g., 300.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_van_fee'] ?? ''); ?></span>
                          </div>
                      </div>

                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                         <div>
                             <label for="monthly_exam_fee" class="block text-sm font-medium text-gray-700 mb-1">Exam Fee (Optional)</label>
                             <input type="number" name="monthly_exam_fee" id="monthly_exam_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['monthly_exam_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_exam_fee_display ?? ''); ?>" placeholder="e.g., 100.00">
                             <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_exam_fee'] ?? ''); ?></span>
                         </div>
                          <div>
                              <label for="monthly_electricity_fee" class="block text-sm font-medium text-gray-700 mb-1">Electricity Fee (Optional)</label>
                              <input type="number" name="monthly_electricity_fee" id="monthly_electricity_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_input_errors['monthly_electricity_fee'])) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_electricity_fee_display ?? ''); ?>" placeholder="e.g., 50.00">
                              <span class="form-error"><?php echo htmlspecialchars($fee_input_errors['monthly_electricity_fee'] ?? ''); ?></span>
                          </div>
                     </div>


                     <div class="flex items-center justify-end gap-4 mt-6">
                          <!-- Disable button if no students are listed -->
                          <button type="submit"
                                  class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                                  <?php echo empty($students_list_for_display) ? 'disabled' : ''; ?>
                          >Add Fee Records for Selected Students</button>
                          <a href="admin_dashboard.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">Cancel</a>
                     </div>
                 </form>
             </div>
        <?php elseif($should_fetch_list && empty($students_list_for_display) && $link !== false && empty($processing_message) && $students_list_message_type !== 'error'): ?>
             <!-- This case is now handled by the students_list_message display block above, which will show the warning box -->
             <?php /* Removed redundant warning message box */ ?>
        <?php elseif($link === false): ?>
             <!-- This case is now handled by the students_list_message display block above, which will show the error box -->
              <?php /* Removed redundant database connection error message box */ ?>
        <?php endif; ?>


    </div> <!-- End main-content-wrapper -->

<?php
// Include the footer file.
require_once "./admin_footer.php";
?>