<?php
// School/admin/edit_student.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal (Added Principal check for viewing)
// The dashboard and management features are primarily for admins.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can access this page.</p>";
    header("location: ../login.php");
    exit;
}
// Check if role is NOT admin, redirect if they shouldn't edit (Principals might only view)
// Adjust this condition if Principals should also be able to edit student details.
if ($_SESSION['role'] !== 'admin') {
     $_SESSION['operation_message'] = "<p class='text-red-600'>Permission denied. Only Admins can edit student records.</p>";
     // Redirect to view page if they can view, otherwise dashboard
     if (isset($_GET['id']) && filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) > 0) {
         header("location: view_student.php?id=" . htmlspecialchars($_GET['id']));
     } else {
         header("location: admin_dashboard.php");
     }
     exit;
}


// Define variables and initialize with empty values for the main student data
$user_id = null; // This holds the student_id being edited/viewed
// Corrected variable names to match form inputs and mapping intentions
$virtual_id = '';
$full_name = $father_name = $mother_name = $phone_number = $whatsapp_number = "";
$current_class = $previous_class = $previous_school = "";
$previous_marks_percentage_display = ''; // Using _display for input value
$current_marks_display = '';             // Using _display for input value
$roll_number = '';
$village = '';
$date_of_birth_display = ''; // For input type="date"

// Fee related fields (adjusting names based on our schema discussion) - These are the DEFAULTS from `students` table
// ONLY KEEPING TAKES_VAN
$takes_van_display = '';           // Maps to 'takes_van' in DB ('on' or '')
// Removed: $default_monthly_fee_display


$address = $pincode = $state = "";

// Monthly fee records data (will be fetched for display only)
$monthly_fee_records = [];

// Variables for the NEW monthly fee form (POST data and display)
$new_fee_month_display = '';
$new_fee_year_display = '';
// New breakdown fields for the form input
$new_base_monthly_fee_display = '';
$new_monthly_van_fee_display = '';
$new_monthly_exam_fee_display = '';
$new_monthly_electricity_fee_display = '';


// Error variables for main student data update form
$full_name_err = $father_name_err = $mother_name_err = $phone_number_err = "";
$current_class_err = "";
$virtual_id_err = ""; // Not editable, but might show previous error if linked from creation error
$roll_number_err = "";
$village_err = "";
$date_of_birth_err = "";
// Removed: $default_monthly_fee_err


// Error variables for the NEW monthly fee form submission (These remain as they are for the monthly fee records section)
$new_fee_month_err = '';
$new_fee_year_err = '';
$new_base_monthly_fee_err = ''; // Error for new base fee input
$new_monthly_van_fee_err = ''; // Error for new van fee input
$new_monthly_exam_fee_err = ''; // Error for new exam fee input
$new_monthly_electricity_fee_err = ''; // Error for new electricity fee input
$new_monthly_fee_general_err = ''; // For general errors like duplicate entry or student ID


// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'


// Variable to store the student ID during GET or POST
$student_id_to_edit = null;


// --- Handle POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") { // <-- Opening brace for POST block

    // Determine which form was submitted using the hidden 'form_type' input
    $form_type = $_POST['form_type'] ?? '';

    // Get the student ID from the hidden input field (present in both forms)
    // This is the ID of the student whose page we are on.
    // Use student_id_for_fee if submitted from fee form, user_id if from details form.
     $student_id_to_edit = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ??
                           filter_input(INPUT_POST, 'student_id_for_fee', FILTER_VALIDATE_INT);


    // Validate the student ID *once* here after trying both sources
     if ($student_id_to_edit === false || $student_id_to_edit <= 0) {
          // If the ID is invalid from either source, set a general error and skip processing any form
          // Use toast message for this global error
          $toast_message = "Invalid student ID provided for processing.";
          $toast_type = 'error';
          $student_id_to_edit = null; // Ensure it's null if invalid
     }


    if ($student_id_to_edit !== null) { // Only proceed if we have a valid student ID

        if ($form_type === 'student_details') { // <-- Opening brace for student_details form POST

            // --- Trim and get input values for all editable fields from student_details form ---
            // These PHP variables match the form input names
            $full_name = trim($_POST["full_name"] ?? '');
            $father_name = trim($_POST["father_name"] ?? '');
            $mother_name = trim($_POST["mother_name"] ?? '');
            $phone_number = trim($_POST["phone_number"] ?? '');
            $whatsapp_number = trim($_POST["whatsapp_number"] ?? '');
            $current_class = trim($_POST["current_class"] ?? '');
            $previous_class = trim($_POST["previous_class"] ?? '');
            $previous_school = trim($_POST["previous_school"] ?? '');
            $roll_number = trim($_POST["roll_number"] ?? '');
            $village = trim($_POST["village"] ?? '');

            // Handle Date of Birth input
            $date_of_birth_display = trim($_POST["date_of_birth"] ?? ''); // Keep for display
            $date_of_birth_for_db = null; // Will be set to 'YYYY-MM-DD' or null
            if (!empty($date_of_birth_display)) {
                 // Validate and format the date string from YYYY-MM-DD (standard for input type="date")
                 $dob_datetime = DateTime::createFromFormat('Y-m-d', $date_of_birth_display);
                 // Check if DateTime object was created successfully and the original string matches the formatted string
                 if ($dob_datetime && $dob_datetime->format('Y-m-d') === $date_of_birth_display) {
                     $date_of_birth_for_db = $dob_datetime->format('Y-m-d'); // Store as YYYY-MM-DD string for DATE column
                 } else {
                     $date_of_birth_err = "Invalid date format. Please use YYYY-MM-DD or date picker.";
                     // Invalid input remains in $date_of_birth_display for display
                 }
             }
             // If $date_of_birth_display is empty, $date_of_birth_for_db remains null, which is correct for an optional field.


            // Handle numeric fields with validation (Keep previous_marks and current_marks)
            $previous_marks_percentage_input = trim($_POST['previous_marks_percentage'] ?? '');
            $previous_marks_percentage_for_db = null; // Default to null
            $previous_marks_percentage_display = $previous_marks_percentage_input; // Keep user input for display
            if ($previous_marks_percentage_input !== '') {
                $filtered_marks = filter_var($previous_marks_percentage_input, FILTER_VALIDATE_FLOAT);
                if ($filtered_marks === false || $filtered_marks < 0 || $filtered_marks > 100) {
                 // If invalid, keep the original input for display, DB gets null
                 $previous_marks_percentage_for_db = null; // Remains null
                } else {
                 $previous_marks_percentage_for_db = $filtered_marks; // Store valid float
                }
            } else {
                 $previous_marks_percentage_display = ''; // Ensure empty string if input was empty
            }


            $current_marks_input = trim($_POST['current_marks'] ?? '');
            $current_marks_for_db = null; // Default to null
            $current_marks_display = $current_marks_input; // Keep user input for display
            if ($current_marks_input !== '') {
                 $filtered_marks = filter_var($current_marks_input, FILTER_VALIDATE_FLOAT);
                 if ($filtered_marks === false || $filtered_marks < 0 || $filtered_marks > 100) {
                  // If invalid, keep original input for display, DB gets null
                  $current_marks_for_db = null; // Remains null
                 } else {
                  $current_marks_for_db = $filtered_marks; // Store valid float
                 }
            } else {
                 $current_marks_display = ''; // Ensure empty string if input was empty
            }


            // Fee fields processing - ONLY KEEPING TAKES_VAN
            // Removed: $default_monthly_fee_input, $default_monthly_fee_display, $default_monthly_fee_for_db, $default_monthly_fee_err
            // Removed: $default_van_fee_input, $default_van_fee_display, $default_van_fee_for_db, $default_van_fee_err
            // Removed: $other_one_time_fees_input, $other_one_time_fees_display, $other_one_time_fees_for_db, $other_one_time_fees_err

            // Process Takes Van checkbox (KEEPING THIS)
            // Checkboxes are only present in $_POST if they are checked.
            $takes_van_display = isset($_POST['takes_van']) ? 'on' : ''; // Retain 'on' or '' for display
            $takes_van_for_db = ($takes_van_display === 'on') ? 1 : 0; // Store 1 or 0 for boolean/tinyint


            $address = trim($_POST['address'] ?? '');
            $pincode = trim($_POST['pincode'] ?? '');
            $state = trim($_POST['state'] ?? '');

            // Basic Required field validation
            if(empty($full_name)) $full_name_err = "Full name is required.";
            if(empty($father_name)) $father_name_err = "Father's name is required.";
            if(empty($mother_name)) $mother_name_err = "Mother's name is required.";
             if (empty($phone_number)) {
                 $phone_number_err = "Phone number is required.";
             } elseif (!preg_match("/^\d{7,15}$/", $phone_number)) { // Basic phone validation
                  $phone_number_err = "Please enter a valid phone number (7-15 digits).";
             }
            if(empty($current_class)) $current_class_err = "Current class is required.";


            // --- Check ALL input errors before DB update ---
            // Removed error checks for default_monthly_fee_err, default_van_fee_err, other_one_time_fees_err
            $has_errors = !empty($full_name_err) || !empty($father_name_err) || !empty($mother_name_err) || !empty($phone_number_err) ||
                          !empty($current_class_err) || !empty($date_of_birth_err) ||
                          !empty($roll_number_err) || !empty($village_err); // Note: roll/village errors might not be set if no validation added


            if (!$has_errors) { // <-- Opening brace for if (!$has_errors)
                // Prepare variables for database update (handle empty strings for nullables)
                $whatsapp_number_db = ($whatsapp_number === '') ? null : $whatsapp_number;
                $previous_class_db = ($previous_class === '') ? null : $previous_class;
                $previous_school_db = ($previous_school === '') ? null : $previous_school;
                $roll_number_db = ($roll_number === '') ? null : $roll_number;
                $village_db = ($village === '') ? null : $village;
                $address_db = ($address === '') ? null : $address;
                $pincode_db = ($pincode === '') ? null : $pincode;
                $state_db = ($state === '') ? null : $state;

                // Use validated numeric/boolean values for DB for relevant fields
                // $previous_marks_percentage_for_db = ... (already set above)
                // $current_marks_for_db = ... (already set above)
                // $takes_van_for_db = ... (already set above)


                // Prepare an update statement including all default fields from the main form
                // *** CORRECTED SQL QUERY TO MATCH DATABASE COLUMN NAMES (using underscores) ***
                // REMOVED: student_fees, van_fee, optional_fees
                $sql_update = "UPDATE students SET full_name=?, father_name=?, mother_name=?, phone_number=?, whatsapp_number=?, current_class=?, previous_class=?, previous_school=?, previous_marks_percentage=?, current_marks=?, takes_van=?, address=?, pincode=?, state=?, roll_number=?, village=?, date_of_birth=? WHERE user_id=?";

                if ($link === false) {
                     $toast_message = "Database connection error. Could not save changes.";
                     $toast_type = 'error';
                     error_log("Edit Student DB connection failed (POST - Student Details): " . mysqli_connect_error());
                } elseif ($stmt_update = mysqli_prepare($link, $sql_update)) { // <-- Opening brace for elseif ($stmt_update = mysqli_prepare)

                    // Bind variables - Match the order in the UPDATE SET clause + WHERE (18 parameters)
                    // Count types:
                    // s: full, father, mother, phone, whatsapp, current_class, prev_class, prev_school (8)
                    // d: prev_marks, curr_marks (2)
                    // i: takes_van (1)
                    // s: address, pincode, state, roll_number, village, date_of_birth (6)
                    // i: user_id (WHERE) (1)
                    // Total: 8 + 2 + 1 + 6 + 1 = 18
                    // Corrected Bind string: s s s s s s s s d d i s s s s s s i (18 characters)
                    // Corresponding to the *corrected* column names: full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, takes_van, address, pincode, state, roll_number, village, date_of_birth, user_id

                    // FIX: Corrected the bind types string to match the 18 parameters
                    $bind_types = "ssssssssddissssssi"; // This bind string matches the 18 placeholders/variables

                    // Build the arguments array directly for call_user_func_array
                    $bind_args = [];
                    $bind_args[] = $stmt_update; // The mysqli_stmt object is the first argument
                    $bind_args[] = $bind_types; // The types string is the second argument
                    // Add all variables by reference starting from the third argument
                    $bind_args[] = &$full_name;
                    $bind_args[] = &$father_name;
                    $bind_args[] = &$mother_name;
                    $bind_args[] = &$phone_number;
                    $bind_args[] = &$whatsapp_number_db;
                    $bind_args[] = &$current_class;
                    $bind_args[] = &$previous_class_db;
                    $bind_args[] = &$previous_school_db;
                    $bind_args[] = &$previous_marks_percentage_for_db;
                    $bind_args[] = &$current_marks_for_db;
                    $bind_args[] = &$takes_van_for_db;
                    $bind_args[] = &$address_db;
                    $bind_args[] = &$pincode_db;
                    $bind_args[] = &$state_db;
                    $bind_args[] = &$roll_number_db;
                    $bind_args[] = &$village_db;
                    $bind_args[] = &$date_of_birth_for_db;
                    $bind_args[] = &$student_id_to_edit; // The WHERE clause parameter

                    // Call bind_param using the prepared array and references
                    // This is line 283
                    if (call_user_func_array('mysqli_stmt_bind_param', $bind_args)) { // <-- Use the new $bind_args array

                         if (mysqli_stmt_execute($stmt_update)) { // <-- Opening brace for if (mysqli_stmt_execute)
                             // Close statement after successful execution
                             mysqli_stmt_close($stmt_update);

                             // Set success message for toast
                             $toast_message = "Student details updated successfully.";
                             $toast_type = 'success';
                             // Do not redirect here yet, as we need to fetch monthly fees below

                         } else { // <-- Opening brace for else (execute error)
                              // Error executing the update statement
                              $toast_message = "Error: Could not update student record."; // Don't show raw SQL error
                              $toast_type = 'error';
                              error_log("Edit Student update query failed for ID " . $student_id_to_edit . ": " . mysqli_stmt_error($stmt_update));
                              // Variables already populated from $_POST for display on error.
                              mysqli_stmt_close($stmt_update); // Close statement on error too
                         } // <-- Closing brace for else (execute error)
                    } else { // <-- Opening brace for else (bind_param error)
                         // Error binding parameters (less common than execute error, but possible)
                         $toast_message = "Error: Could not bind parameters for update statement."; // Don't show raw SQL error
                         $toast_type = 'error';
                          error_log("Edit Student bind_param failed for ID " . $student_id_to_edit . ": " . mysqli_stmt_error($stmt_update)); // Use stmt_error for bind errors too
                         mysqli_stmt_close($stmt_update); // Close statement on error
                    }
                 // --- Closing brace for if (call_user_func_array) ---
                } else { // <-- Opening brace for else (prepare error)
                     // Error preparing update statement
                     $toast_message = "Error: Could not prepare update statement."; // Don't show raw SQL error
                     $toast_type = 'error';
                     error_log("Edit Student prepare update failed: " . mysqli_error($link));
                     // Variables already populated from $_POST for display on error.
                } // <-- Closing brace for else (prepare error)
             // --- Closing brace for elseif ($stmt_update = mysqli_prepare) ---
            } else {
                // If there were validation errors on the student details form
                $toast_message = "Validation errors found. Please check the student details form.";
                $toast_type = 'error';
                 // Display variables are already populated from $_POST by the validation block above.
            }
         // --- Closing brace for if (!$has_errors) ---
        } // <-- This brace closes the if ($form_type === 'student_details') block


        // --- Handle 'add_monthly_fee' form POST ---
        // This section remains UNCHANGED as it deals with the separate student_monthly_fees table
        elseif ($form_type === 'add_monthly_fee') { // <-- Opening brace for add_monthly_fee form POST

            // Collect and trim inputs
            $new_fee_month_display = trim($_POST['new_fee_month'] ?? '');
            $new_fee_year_display = trim($_POST['new_fee_year'] ?? '');
            $new_base_monthly_fee_display = trim($_POST['new_base_monthly_fee'] ?? '');
            $new_monthly_van_fee_display = trim($_POST['new_monthly_van_fee'] ?? '');
            $new_monthly_exam_fee_display = trim($_POST['new_monthly_exam_fee'] ?? '');
            $new_monthly_electricity_fee_display = trim($_POST['new_monthly_electricity_fee'] ?? '');

            // Variables to hold validated/converted values for DB
            $new_fee_month_for_db = null;
            $new_fee_year_for_db = null;
            $new_base_monthly_fee_for_db = null;
            $new_monthly_van_fee_for_db = null;
            $new_monthly_exam_fee_for_db = null;
            $new_monthly_electricity_fee_for_db = null;
            $new_amount_due_calculated = 0.0;

            // Validation
            if (empty($new_fee_month_display)) {
                $new_fee_month_err = "Please select a month.";
            } else {
                $month_int = filter_var($new_fee_month_display, FILTER_VALIDATE_INT);
                if ($month_int === false || $month_int < 1 || $month_int > 12) {
                    $new_fee_month_err = "Invalid month selected.";
                } else {
                    $new_fee_month_for_db = $month_int;
                }
            }

            if (empty($new_fee_year_display)) {
                $new_fee_year_err = "Please enter a year.";
            } else {
                 $year_int = filter_var($new_fee_year_display, FILTER_VALIDATE_INT);
                 if ($year_int === false || $year_int < 2000 || $year_int > 2100) { // Simple year range validation
                      $new_fee_year_err = "Invalid year (e.g., 2000-2100).";
                 } else {
                      $new_fee_year_for_db = $year_int;
                 }
            }

            if (empty($new_base_monthly_fee_display)) {
                $new_base_monthly_fee_err = "Base fee is required.";
            } else {
                 $base_fee_float = filter_var($new_base_monthly_fee_display, FILTER_VALIDATE_FLOAT);
                 if ($base_fee_float === false || $base_fee_float < 0) {
                      $new_base_monthly_fee_err = "Please enter a valid non-negative number for base fee.";
                 } else {
                      $new_base_monthly_fee_for_db = $base_fee_float;
                      // Add to total (calculation happens *after* validation)
                 }
            }

            // Optional fee fields validation and calculation
            if (!empty($new_monthly_van_fee_display)) {
                $van_fee_float = filter_var($new_monthly_van_fee_display, FILTER_VALIDATE_FLOAT);
                 if ($van_fee_float === false || $van_fee_float < 0) {
                     $new_monthly_van_fee_err = "Invalid Van fee.";
                 } else {
                     $new_monthly_van_fee_for_db = $van_fee_float;
                     // Add to total (calculation happens *after* validation)
                 }
            } else {
                 $new_monthly_van_fee_for_db = 0.0; // Treat empty optional as 0 for calculation
            }

             if (!empty($new_monthly_exam_fee_display)) {
                $exam_fee_float = filter_var($new_monthly_exam_fee_display, FILTER_VALIDATE_FLOAT);
                 if ($exam_fee_float === false || $exam_fee_float < 0) {
                     $new_monthly_exam_fee_err = "Invalid Exam fee.";
                 } else {
                     $new_monthly_exam_fee_for_db = $exam_fee_float;
                     // Add to total (calculation happens *after* validation)
                 }
            } else {
                 $new_monthly_exam_fee_for_db = 0.0; // Treat empty optional as 0 for calculation
            }

             if (!empty($new_monthly_electricity_fee_display)) {
                $elec_fee_float = filter_var($new_monthly_electricity_fee_display, FILTER_VALIDATE_FLOAT);
                 if ($elec_fee_float === false || $elec_fee_float < 0) {
                     $new_monthly_electricity_fee_err = "Invalid Electricity fee.";
                 } else {
                     $new_monthly_electricity_fee_for_db = $elec_fee_float;
                     // Add to total (calculation happens *after* validation)
                 }
            } else {
                 $new_monthly_electricity_fee_for_db = 0.0; // Treat empty optional as 0 for calculation
            }

             // Calculate the total amount due ONLY if base fee is valid
             if (empty($new_base_monthly_fee_err)) {
                 $new_amount_due_calculated = ($new_base_monthly_fee_for_db ?? 0.0) +
                                              ($new_monthly_van_fee_for_db ?? 0.0) +
                                              ($new_monthly_exam_fee_for_db ?? 0.0) +
                                              ($new_monthly_electricity_fee_for_db ?? 0.0);
             }


            // Check if there are any validation errors for the fee form
            $has_fee_errors = !empty($new_fee_month_err) || !empty($new_fee_year_err) ||
                              !empty($new_base_monthly_fee_err) || !empty($new_monthly_van_fee_err) ||
                              !empty($new_monthly_exam_fee_err) || !empty($new_monthly_electricity_fee_err);


            if (!$has_fee_errors && $student_id_to_edit > 0) { // Proceed if valid student ID and no validation errors

                // Check for duplicate entry (same student, year, and month)
                $sql_check_duplicate = "SELECT id FROM student_monthly_fees WHERE student_id = ? AND fee_year = ? AND fee_month = ?";
                if ($link === false) {
                     $new_monthly_fee_general_err = "Database connection error during duplicate check.";
                     $toast_type = 'error'; // Set toast type for this error
                } elseif ($stmt_check = mysqli_prepare($link, $sql_check_duplicate)) {
                    mysqli_stmt_bind_param($stmt_check, "iii", $student_id_to_edit, $new_fee_year_for_db, $new_fee_month_for_db);
                    if (mysqli_stmt_execute($stmt_check)) {
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $new_monthly_fee_general_err = "A fee record for this month and year already exists for this student.";
                            $toast_type = 'warning'; // Use warning for duplicate
                        }
                    } else {
                         $new_monthly_fee_general_err = "Database error during duplicate check.";
                         $toast_type = 'error'; // Set toast type for this error
                         error_log("Edit Student monthly fee duplicate check failed: " . mysqli_stmt_error($stmt_check));
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                     $new_monthly_fee_general_err = "Database error preparing duplicate check.";
                     $toast_type = 'error'; // Set toast type for this error
                     error_log("Edit Student prepare duplicate check failed: " . mysqli_error($link));
                }


                // If no duplicate error and no validation errors, insert the new fee record
                if (empty($new_monthly_fee_general_err)) {
                    // Prepare the insert statement for the student_monthly_fees table
                    // This query already uses the correct column names from the student_monthly_fees table schema (assuming it matches the code's use)
                    $sql_insert_fee = "INSERT INTO student_monthly_fees (student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"; // amount_paid and is_paid are initialized

                     if ($link === false) {
                         $new_monthly_fee_general_err = "Database connection error. Could not add fee record.";
                         $toast_type = 'error';
                     } elseif ($stmt_insert = mysqli_prepare($link, $sql_insert_fee)) {
                        // Bind parameters: i i i d d d d d (8 parameters total)
                        $bind_types_fee = "iiiddddd";
                        // Using the new explicit call_user_func_array structure for consistency and safety
                        $bind_args_fee = [];
                        $bind_args_fee[] = $stmt_insert;
                        $bind_args_fee[] = $bind_types_fee;
                        $bind_args_fee[] = &$student_id_to_edit;
                        $bind_args_fee[] = &$new_fee_year_for_db;
                        $bind_args_fee[] = &$new_fee_month_for_db;
                        $bind_args_fee[] = &$new_base_monthly_fee_for_db;
                        $bind_args_fee[] = &$new_monthly_van_fee_for_db;
                        $bind_args_fee[] = &$new_monthly_exam_fee_for_db;
                        $bind_args_fee[] = &$new_monthly_electricity_fee_for_db;
                        $bind_args_fee[] = &$new_amount_due_calculated; // Use the calculated amount

                        if (call_user_func_array('mysqli_stmt_bind_param', $bind_args_fee)) {
                             if (mysqli_stmt_execute($stmt_insert)) {
                                 mysqli_stmt_close($stmt_insert);
                                 // Set success message for toast
                                 $toast_message = "Monthly fee record added successfully.";
                                 $toast_type = 'success';

                                 // Clear fee form display variables on success
                                 $new_fee_month_display = '';
                                 $new_fee_year_display = date('Y'); // Reset to current year
                                 $new_base_monthly_fee_display = '';
                                 $new_monthly_van_fee_display = '';
                                 $new_monthly_exam_fee_display = '';
                                 $new_monthly_electricity_fee_display = '';

                                  // Clear fee form error variables on success
                                 $new_fee_month_err = '';
                                 $new_fee_year_err = '';
                                 $new_base_monthly_fee_err = '';
                                 $new_monthly_van_fee_err = '';
                                 $new_monthly_exam_fee_err = '';
                                 $new_monthly_electricity_fee_err = '';
                                 $new_monthly_fee_general_err = '';


                             } else {
                                 // Error executing insert
                                  $new_monthly_fee_general_err = "Error: Could not add monthly fee record."; // Don't show raw SQL error
                                  $toast_type = 'error';
                                  error_log("Edit Student monthly fee insert failed for student ID " . $student_id_to_edit . ": " . mysqli_stmt_error($stmt_insert));
                                 mysqli_stmt_close($stmt_insert);
                             }
                        } else {
                            // Error binding parameters for fee insert
                             $new_monthly_fee_general_err = "Error: Could not bind parameters for fee insert statement.";
                             $toast_type = 'error';
                              error_log("Edit Student monthly fee bind_param failed for student ID " . $student_id_to_edit . ": " . mysqli_stmt_error($stmt_insert));
                            mysqli_stmt_close($stmt_insert);
                        }
                    } else {
                         // Error preparing insert statement
                         $new_monthly_fee_general_err = "Error: Could not prepare fee insert statement."; // Don't show raw SQL error
                         $toast_type = 'error';
                         error_log("Edit Student prepare fee insert failed: " . mysqli_error($link));
                    }
                }

            } else {
                // If there were validation errors for the fee form, set a general error toast
                 if ($student_id_to_edit <= 0) {
                     $new_monthly_fee_general_err = "Invalid student ID provided for fee submission.";
                     $toast_type = 'error';
                 } else {
                     $new_monthly_fee_general_err = "Validation errors found. Please check the monthly fee form.";
                     $toast_type = 'error';
                 }
            }
             // If a general error occurred in the fee form processing, ensure it's sent to the toast
             // Only add to toast_message if it hasn't been set by a successful update already
             if (!empty($new_monthly_fee_general_err) && empty($toast_message)) {
                 $toast_message = $new_monthly_fee_general_err;
                 if (empty($toast_type)) $toast_type = 'error'; // Default to error if not set by specific error handling
             }


        } // <-- This brace closes the elseif ($form_type === 'add_monthly_fee') block


         // After processing ANY POST (whether success or error for details or fees),
         // we need to fetch the main student data and ALL monthly fee data
         // to display the page correctly.
         // $student_id_to_edit is already set at the top of the POST block.

         // Fetch student data again to repopulate form fields *if* there were validation errors on the main form
         // or just to ensure all display variables are set correctly after any POST (except for fee form inputs on error).
         if ($student_id_to_edit > 0) { // <-- Opening brace for if ($student_id_to_edit > 0)
             // *** CORRECTED SQL FETCH QUERY TO MATCH DATABASE COLUMN NAMES (using underscores) ***
             // Removed fee default fields from SELECT
             $sql_fetch = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, takes_van, address, pincode, state, roll_number, village, date_of_birth FROM students WHERE user_id = ?";

             if ($link === false) {
                 // DB connection error already reported
             } elseif ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
                 mysqli_stmt_bind_param($stmt_fetch, "i", $student_id_to_edit);

                 if (mysqli_stmt_execute($stmt_fetch)) {
                     $result_fetch = mysqli_stmt_get_result($stmt_fetch);
                      if (mysqli_num_rows($result_fetch) == 1) {
                          $student = mysqli_fetch_assoc($result_fetch);

                          // Populate main form variables from fetched data
                          // *** UPDATED MAPPING FROM DB COLUMN NAMES (underscore) TO PHP VARIABLES ***
                           $user_id = $student["user_id"]; // Set hidden ID (should already be set)
                           $virtual_id = $student["virtual_id"]; // Display virtual ID
                           $full_name = $student["full_name"];
                           $father_name = $student["father_name"];
                           $mother_name = $student["mother_name"];
                           $phone_number = $student["phone_number"];
                           $whatsapp_number = $student["whatsapp_number"];
                           $current_class = $student["current_class"];
                           $previous_class = $student["previous_class"];
                           $previous_school = $student["previous_school"];
                           $roll_number = $student["roll_number"];
                           $village = $student["village"];

                            // Date of birth fetch - use display variable (expecting YYYY-MM-DD from DB DATE type)
                           $date_of_birth_display = (!empty($student["date_of_birth"]) && $student["date_of_birth"] !== '0000-00-00') ? $student["date_of_birth"] : '';


                            // Populate display variables for numeric/boolean/nullable fields from fetched data
                            // ONLY POPULATE THESE FROM DB FETCH IF THERE WAS NO VALIDATION ERROR ON THE MAIN FORM
                            // Otherwise, the _display variables already hold the user's input from $_POST
                           $previous_marks_percentage_display = empty($previous_marks_percentage_err) ? ($student["previous_marks_percentage"] ?? '') : $previous_marks_percentage_display;
                           $current_marks_display = empty($current_marks_err) ? ($student["current_marks"] ?? '') : $current_marks_display;
                           // Removed mapping for default_monthly_fee_display, default_van_fee_display, other_one_time_fees_display

                           // Map takes_van from DB (0 or 1) to 'on' or '' for checkbox display (KEEPING THIS)
                           $takes_van_display = (($student["takes_van"] ?? 0) == 1) ? 'on' : '';


                           $address = empty($address_err) ? ($student["address"] ?? '') : $address;
                           $pincode = empty($pincode_err) ? ($student["pincode"] ?? '') : $pincode;
                           $state = empty($state_err) ? ($student["state"] ?? '') : $state;

                           // Monthly fee *form* display variables retain their POSTed value if there was an error on the fee form.
                           // If the fee form was successful, these were cleared ($new_fee_month_display = ''; etc.).
                           // If the student details form was submitted (successfully or with errors), these should NOT
                           // be overwritten by fee form variables, but they should also not be cleared.
                           // The current logic correctly retains fee form inputs only if the fee form was submitted with errors.
                           // Submitting the details form leaves the fee form variables as they were before the details submit. This is acceptable.


                      } else {
                           $toast_message .= "Could not refetch student data after form submission."; // Append message
                           $toast_type = 'error';
                      }
                     mysqli_free_result($result_fetch);
                 } else {
                      error_log("Edit Student refetch query failed after form submit: " . mysqli_stmt_error($stmt_fetch));
                      if(empty($toast_message)) {
                           $toast_message = "Error refetching student data.";
                           $toast_type = 'error';
                      }
                 }
                 mysqli_stmt_close($stmt_fetch);
             } elseif ($link !== false) {
                  error_log("Edit Student prepare refetch failed after form submit: " . mysqli_error($link));
                  if(empty($toast_message)) {
                       $toast_message = "Error preparing to refetch student data.";
                       $toast_type = 'error';
                  }
             }


             // Fetch ALL Monthly Fee Data for display (including the newly added one if successful)
             // This is needed after *any* POST submission for this student.
             // This query is for the student_monthly_fees table, which seems to have the correct column names already based on the code's structure.
             $sql_monthly_fees = "SELECT id, student_id, fee_year, fee_month, amount_due, amount_paid, is_paid, payment_date, notes, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee FROM student_monthly_fees WHERE student_id = ? ORDER BY fee_year ASC, fee_month ASC";

             if ($link === false) {
                  // DB connection error already reported, just skip fetching monthly fees
             } elseif ($stmt_monthly = mysqli_prepare($link, $sql_monthly_fees)) {
                 mysqli_stmt_bind_param($stmt_monthly, "i", $student_id_to_edit); // Use the student ID ($user_id)

                 if (mysqli_stmt_execute($stmt_monthly)) {
                     $result_monthly = mysqli_stmt_get_result($stmt_monthly);
                     while ($row = mysqli_fetch_assoc($result_monthly)) {
                         $monthly_fee_records[] = $row;
                     }
                     mysqli_free_result($result_monthly);
                 } else {
                     error_log("Edit Student monthly fees query failed during POST (refetch) for student ID " . $student_id_to_edit . ": " . mysqli_stmt_error($stmt_monthly));
                      if(empty($toast_message)) {
                           $toast_message = "Error fetching monthly fee records.";
                           $toast_type = 'error';
                      }
                 }
                 mysqli_stmt_close($stmt_monthly);
             } elseif ($link !== false) {
                 error_log("Edit Student prepare monthly fees failed during POST (refetch): " . mysqli_error($link));
                  if(empty($toast_message)) {
                       $toast_message = "Error preparing to fetch monthly fee records.";
                       $toast_type = 'error';
                  }
             }

         } else { // $student_id_to_edit was invalid or <= 0, general error message already set by toast at the start of POST.
              // No monthly fees to fetch.
         }


    } // <-- Closing brace for if ($student_id_to_edit !== null)
     // If $student_id_to_edit was null, the initial toast message was already set.

// --- Closing brace for if ($_SERVER["REQUEST_METHOD"] == "POST") ---
} else { // GET request - Display the form with existing data // <-- Opening brace for GET block

    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        $student_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($student_id_to_edit === false || $student_id_to_edit <= 0) {
            $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid student ID.</p>";
            header("location: admin_dashboard.php"); // Adjust redirect
            exit();
        } else { // Valid ID on GET
            // *** CORRECTED SQL FETCH QUERY TO MATCH DATABASE COLUMN NAMES (using underscores) ***
            // Removed fee default fields from SELECT
            $sql_fetch = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, takes_van, address, pincode, state, roll_number, village, date_of_birth FROM students WHERE user_id = ?";

            if ($link === false) {
                $toast_message = "Database connection error. Could not load student data.";
                $toast_type = 'error';
                 error_log("Edit Student fetch DB connection failed (GET): " . mysqli_connect_error());
            } elseif ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
                mysqli_stmt_bind_param($stmt_fetch, "i", $student_id_to_edit);

                if (mysqli_stmt_execute($stmt_fetch)) {
                    $result_fetch = mysqli_stmt_get_result($stmt_fetch);

                    if (mysqli_num_rows($result_fetch) == 1) {
                        $student = mysqli_fetch_assoc($result_fetch);

                        // Populate variables with fetched data for display
                        // *** UPDATED MAPPING FROM DB COLUMN NAMES (underscore) TO PHP VARIABLES ***
                        $user_id = $student["user_id"]; // Set the user_id for the hidden field
                        $virtual_id = $student["virtual_id"]; // Display virtual ID
                        $full_name = $student["full_name"];
                        $father_name = $student["father_name"];
                        $mother_name = $student["mother_name"];
                        $phone_number = $student["phone_number"];
                        $whatsapp_number = $student["whatsapp_number"];
                        $current_class = $student["current_class"];
                        $previous_class = $student["previous_class"];
                        $previous_school = $student["previous_school"];
                        $roll_number = $student["roll_number"];
                        $village = $student["village"];

                         // Date of birth fetch - use display variable (expecting YYYY-MM-DD from DB DATE type)
                        $date_of_birth_display = (!empty($student["date_of_birth"]) && $student["date_of_birth"] !== '0000-00-00') ? $student["date_of_birth"] : '';


                         // Populate display variables for numeric/boolean/nullable fields from fetched data
                         $previous_marks_percentage_display = $student["previous_marks_percentage"];
                         $current_marks_display = $student["current_marks"];
                         // Removed mapping for default_monthly_fee_display, default_van_fee_display, other_one_time_fees_display

                         // Map takes_van from DB (0 or 1) to 'on' or '' for checkbox display (KEEPING THIS)
                         $takes_van_display = (($student["takes_van"] ?? 0) == 1) ? 'on' : '';


                        $address = $student["address"];
                        $pincode = $student["pincode"];
                        $state = $student["state"];


                        // --- Fetch Monthly Fee Data for display ---
                        // Use the student ID obtained from fetching student data
                        // This query is for the student_monthly_fees table, which seems to have the correct column names already based on the code's structure.
                        $sql_monthly_fees = "SELECT id, student_id, fee_year, fee_month, amount_due, amount_paid, is_paid, payment_date, notes, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee FROM student_monthly_fees WHERE student_id = ? ORDER BY fee_year ASC, fee_month ASC";

                        if ($link === false) {
                             // DB connection error already reported, just skip fetching monthly fees
                        } elseif ($stmt_monthly = mysqli_prepare($link, $sql_monthly_fees)) {
                            mysqli_stmt_bind_param($stmt_monthly, "i", $user_id); // Use the student ID ($user_id)

                            if (mysqli_stmt_execute($stmt_monthly)) {
                                $result_monthly = mysqli_stmt_get_result($stmt_monthly);
                                while ($row = mysqli_fetch_assoc($result_monthly)) {
                                    $monthly_fee_records[] = $row;
                                }
                                mysqli_free_result($result_monthly);
                            } else {
                                 error_log("Edit Student monthly fees query failed during GET for student ID " . $user_id . ": " . mysqli_stmt_error($stmt_monthly));
                                  if(empty($toast_message)) {
                                       $toast_message = "Error fetching monthly fee records.";
                                       $toast_type = 'error';
                                  }
                            }
                            mysqli_stmt_close($stmt_monthly);
                        } elseif ($link !== false) {
                            error_log("Edit Student prepare monthly fees failed during GET: " . mysqli_error($link));
                             if(empty($toast_message)) {
                                  $toast_message = "Error preparing to fetch monthly fee records.";
                                  $toast_type = 'error';
                             }
                        }


                    } else {
                        $_SESSION['operation_message'] = "<p class='text-red-600'>Student record not found.</p>";
                        header("location: admin_dashboard.php"); // Adjust redirect
                        exit();
                    }
                    mysqli_free_result($result_fetch); // Free student result set
                } else {
                    $toast_message = "Oops! Something went wrong. Could not fetch record. Please try again later.";
                    $toast_type = 'error';
                     error_log("Edit Student fetch query failed: " . mysqli_stmt_error($stmt_fetch));
                }
                mysqli_stmt_close($stmt_fetch);
            } else {
                 $toast_message = "Oops! Something went wrong. Could not prepare fetch statement. Please try again later.";
                 $toast_type = 'error';
                 error_log("Edit Student prepare fetch statement failed: " . mysqli_error($link));
            }
        }
    } else {
        $_SESSION['operation_message'] = "<p class='text-red-600'>No student ID provided for editing.</p>";
        header("location: admin_dashboard.php"); // Adjust redirect
        exit();
    }
}


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
    <title>Edit Student Record</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         body {
             background-color: #f3f4f6;
             min-height: 100vh;
         }
        .form-error {
            color: #dc3545;
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25em;
            display: block;
        }
         .form-control.is-invalid {
             border-color: #dc3545;
              /* Removed background image and padding for simplicity with Tailwind */
              /* padding-right: 2.25rem; */
              /* background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.5 8.5v-3h1v3h-1zM6 9.5v.5h-1v-.5h1z'/%3e%3c/svg%3e"); */
              /* background-repeat: no-repeat; */
              /* background-position: right 0.5625rem center; */
              /* background-size: 1.125rem 1.125rem; */
         }
         /* Added simple focus ring for invalid state */
         .form-control.is-invalid:focus {
              border-color: #dc2626; /* red-600 */
              ring-color: #f87171; /* red-400 */
         }


         /* Removed message-box styles - will use toast instead */
         /* .message-box { ... } */
         /* .message-box.success { ... } */
         /* .message-box.error { ... } */
         /* .message-box.warning { ... } */

         /* Style for checkbox and its label */
         .form-check {
             display: flex;
             align-items: center;
         }
          /* Style for numeric input placeholder color */
           input[type="number"]::placeholder {
               color: #9ca3af; /* Tailwind gray-400 */
           }
           /* Style for date input placeholder color */
            input[type="date"]::placeholder {
                color: #9ca3af; /* Tailwind gray-400 */
            }


           /* --- Styles for the Monthly Fee Table --- */
         .monthly-fee-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 1rem;
             font-size: 0.9rem; /* text-sm */
         }
         .monthly-fee-table th,
         .monthly-fee-table td {
             padding: 0.75rem 0.5rem; /* py-3 px-2 */
             border: 1px solid #e5e7eb; /* border-gray-200 */
             text-align: left;
         }
         .monthly-fee-table th {
             background-color: #f3f4f6; /* bg-gray-100 */
             font-weight: 600; /* semibold */
             color: #4b5563; /* text-gray-600 */
         }
         .monthly-fee-table tbody tr:nth-child(even) {
             background-color: #f9fafb; /* bg-gray-50 */
         }
          .monthly-fee-table td {
              color: #1f2937; /* text-gray-900 */
          }
          .status-paid {
              color: #065f46; /* green-800 */
              font-weight: 600; /* semibold */
          }
          .status-due {
               color: #b91c1c; /* red-800 */
               font-weight: 600; /* semibold */
          }
         .monthly-fee-table .action-link {
             color: #4f46e5; /* indigo-600 */
             text-decoration: none;
             font-weight: 500;
         }
         .monthly-fee-table .action-link:hover {
             text-decoration: underline;
             color: #4338ca; /* indigo-700 */
         }

         /* --- Toast Notification Styles --- */
         .toast-container {
             position: fixed;
             top: 1rem; /* Adjust position */
             right: 1rem; /* Adjust position */
             z-index: 100; /* High z-index to be on top */
             display: flex;
             flex-direction: column;
             gap: 0.5rem; /* Space between toasts */
             pointer-events: none; /* Allows clicks to pass through container */
         }

         .toast {
             background-color: #fff;
             color: #333;
             padding: 0.75rem 1.25rem;
             border-radius: 0.375rem;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; /* Start hidden */
             transform: translateX(100%); /* Start off-screen */
             transition: opacity 0.3s ease-out, transform 0.3s ease-out; /* Animation */
             pointer-events: auto; /* Toasts themselves should be clickable (e.g., close button) */
             min-width: 200px;
             max-width: 300px;
             display: flex;
             align-items: center;
         }

         .toast.show {
             opacity: 1;
             transform: translateX(0);
         }

         /* Specific toast types */
         .toast-success { border-left: 5px solid #10b981; /* green-500 */ color: #065f46; /* green-800 */ }
         .toast-error { border-left: 5px solid #ef4444; /* red-500 */ color: #991b1b; /* red-800 */ }
         .toast-warning { border-left: 5px solid #f59e0b; /* yellow-500 */ color: #9a3412; /* yellow-800 */ }
         .toast-info { border-left: 5px solid #3b82f6; /* blue-500 */ color: #1e40af; /* blue-800 */ }

         .toast .close-button {
             margin-left: auto; /* Push button to the right */
             background: none;
             border: none;
             color: inherit; /* Inherit color from toast type */
             font-size: 1.2rem; /* Slightly larger for easier clicking */
             cursor: pointer;
             padding: 0 0.25rem;
             line-height: 1; /* Prevent extra space */
         }

    </style>
    <!-- Removed photo-specific JavaScript -->
     <script>
         // Photo preview JS removed

         // Keep this simple script for handling checkbox value when unchecked
         document.addEventListener('DOMContentLoaded', function() {
            const takesVanCheckbox = document.getElementById('takes_van');
             if (takesVanCheckbox) {
                 // Create a hidden input to ensure a value is sent when the checkbox is unchecked
                 // This is a common pattern for form submissions to handle unchecked checkboxes
                 const hiddenInput = document.createElement('input');
                 hiddenInput.type = 'hidden';
                 hiddenInput.name = 'takes_van'; // Must have the same name as the checkbox
                 hiddenInput.value = ''; // Value sent when checkbox is unchecked

                 // Insert the hidden input right before the checkbox in the DOM
                 takesVanCheckbox.parentNode.insertBefore(hiddenInput, takesVanCheckbox);

                 // Add an event listener to the checkbox
                 takesVanCheckbox.addEventListener('change', function() {
                     // If the checkbox IS checked, disable the hidden input so its value isn't sent
                     // If the checkbox is NOT checked, the hidden input is enabled
                     // Its '' value will be sent if the checkbox wasn't checked
                     hiddenInput.disabled = this.checked;
                 });

                 // Initialize the hidden input's disabled state based on the checkbox's initial state (from PHP)
                 // This is important if the checkbox is pre-checked when the page loads
                 hiddenInput.disabled = takesVanCheckbox.checked;
             }

             // --- JavaScript for Toggle Section ---
             const toggleButton = document.getElementById('toggleAddFeeForm');
             const addFeeFormContainer = document.getElementById('addMonthlyFeeFormContainer');

             if (toggleButton && addFeeFormContainer) {
                  // Determine initial state based on presence of fee form errors
                  const hasFeeErrors = addFeeFormContainer.querySelectorAll('.form-error:not(:empty)').length > 0;
                  if (hasFeeErrors) {
                      // If there were errors, show the form initially
                      addFeeFormContainer.classList.remove('hidden');
                      toggleButton.textContent = 'Hide Add Monthly Fee Form';
                       // Optional: Scroll to the form if it was shown due to errors
                       addFeeFormContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                  } else {
                       // Otherwise, hide it initially (matching the HTML 'hidden' class)
                       addFeeFormContainer.classList.add('hidden');
                       toggleButton.textContent = 'Add New Monthly Fee Record';
                  }


                 toggleButton.addEventListener('click', function() {
                     // Toggle the 'hidden' class on the form container
                     addFeeFormContainer.classList.toggle('hidden');

                     // Change button text and optionally clear form/scroll
                     if (addFeeFormContainer.classList.contains('hidden')) {
                         toggleButton.textContent = 'Add New Monthly Fee Record';
                         // Optional: Clear the form fields and errors when hiding
                         addFeeFormContainer.querySelector('form').reset();
                         addFeeFormContainer.querySelectorAll('.form-error').forEach(span => span.textContent = '');
                         addFeeFormContainer.querySelectorAll('.form-control.is-invalid').forEach(input => input.classList.remove('is-invalid'));

                     } else {
                         toggleButton.textContent = 'Hide Add Monthly Fee Form';
                         // Optional: Scroll to the newly visible form
                         addFeeFormContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                     }
                 });

                 // Optional: Add event listener to the Cancel button inside the form
                 const cancelAddFeeButton = addFeeFormContainer.querySelector('.cancel-add-fee');
                 if (cancelAddFeeButton) {
                     cancelAddFeeButton.addEventListener('click', function(e) {
                         e.preventDefault(); // Prevent default form submission/button action
                         addFeeFormContainer.classList.add('hidden');
                         toggleButton.textContent = 'Add New Monthly Fee Record'; // Reset button text
                          // Optional: Clear the form fields and errors when cancelling
                          addFeeFormContainer.querySelector('form').reset();
                          addFeeFormContainer.querySelectorAll('.form-error').forEach(span => span.textContent = '');
                          addFeeFormContainer.querySelectorAll('.form-control.is-invalid').forEach(input => input.classList.remove('is-invalid'));
                     });
                 }
             }
             // --- End Toggle Section JS ---


             // --- Toast Notification JS ---
             const toastContainer = document.getElementById('toastContainer');
             if (!toastContainer) {
                 console.error('Toast container #toastContainer not found.');
             }

             function showToast(message, type = 'info', duration = 5000) {
                 if (!message || !toastContainer) return;

                 const toast = document.createElement('div');
                 toast.classList.add('toast', `toast-${type}`);
                 toast.textContent = message; // Use textContent for safety

                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.textContent = '×'; // Multiplication sign
                 closeButton.onclick = () => toast.remove();
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 // Use requestAnimationFrame for safer DOM manipulation and smooth transition start
                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });


                 // Auto-hide after duration
                 if (duration > 0) {
                     setTimeout(() => {
                         toast.classList.remove('show');
                         // Remove the toast element after the transition ends
                         toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                     }, duration);
                 }
             }

             // Trigger toast display on DOM load if a message exists
             // Pass the PHP message and type here
             const phpMessage = <?php echo json_encode($toast_message); ?>;
             const messageType = <?php echo json_encode($toast_type); ?>;

             if (phpMessage) {
                 showToast(phpMessage, messageType);
             }
             // --- End Toast Notification JS ---
         });

     </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center py-8 px-4">

    <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here -->
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-2xl"> <!-- Increased max-width slightly -->
        <h2 class="text-xl font-semibold mb-6 text-center">Edit Student Record</h2>

        <?php
        // Messages are now handled by the toast system, so we don't echo $edit_message directly here.
        // if (!empty($edit_message)) { ... }
        ?>

         <!-- Back to Dashboard Link -->
        <div class="mb-6 text-left">
             <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Dashboard</a>
        </div>


        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
            <!-- enctype="multipart/form-data" is technically not needed anymore without file uploads,
                 but keeping it doesn't hurt. Can be removed if desired. -->

            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($student_id_to_edit ?? ''); ?>">
             <input type="hidden" name="form_type" value="student_details"> <!-- Identify this form -->

            <!-- Section: Personal Information -->
            <div><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Personal Information</h3></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                     <label for="display_user_id" class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                     <input type="text" id="display_user_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm" value="<?php echo htmlspecialchars($student_id_to_edit ?? ''); ?>" readonly>
                 </div>
                 <div>
                     <label for="virtual_id" class="block text-sm font-medium text-gray-700 mb-1">Virtual ID</label>
                     <!-- Kept input name as virtual_id because PHP variable is virtual_id, and DB column is virtual_id -->
                     <input type="text" name="virtual_id" id="virtual_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm" value="<?php echo htmlspecialchars($virtual_id ?? ''); ?>" readonly>
                      <span class="text-gray-500 text-xs italic">Virtual ID cannot be changed here.</span>
                 </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <!-- Kept input name as full_name because PHP variable is full_name, and DB column is full_name -->
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name ?? ''); ?>">
                    <span class="form-error"><?php echo htmlspecialchars($full_name_err ?? ''); ?></span>
                </div>

                <div>
                    <!-- Kept input name as father_name because PHP variable is father_name, and DB column is father_name -->
                    <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">Father's Name <span class="text-red-500">*</span></label>
                    <input type="text" name="father_name" id="father_name" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($father_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($father_name ?? ''); ?>">
                    <span class="form-error"><?php echo htmlspecialchars($father_name_err ?? ''); ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="mother_name" class="block text-sm font-medium text-gray-700 mb-1">Mother's Name <span class="text-red-500">*</span></label>
                    <input type="text" name="mother_name" id="mother_name" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($mother_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($mother_name ?? ''); ?>">
                    <span class="form-error"><?php echo htmlspecialchars($mother_name_err ?? ''); ?></span>
                </div>
                 <!-- Added Village field from Create Student -->
                 <div>
                     <label for="village" class="block text-sm font-medium text-gray-700 mb-1">Village (Optional)</label>
                      <input type="text" name="village" id="village" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($village ?? ''); ?>">
                 </div>
            </div>

             <!-- Added Date of Birth field from Create Student -->
             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                     <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth (Optional)</label>
                      <?php
                         // Format date for input type="date" (YYYY-MM-DD)
                         $date_of_birth_input_value = null;
                         if (!empty($date_of_birth_display) && $date_of_birth_display !== '0000-00-00') {
                              // Ensure date is in the correct format for the input value attribute
                              $date_obj = DateTime::createFromFormat('Y-m-d', $date_of_birth_display);
                              if ($date_obj) {
                                  $date_of_birth_input_value = $date_obj->format('Y-m-d');
                              } else {
                                   // If fetched date was invalid format, ensure it doesn't populate input
                                   $date_of_birth_input_value = '';
                              }
                         }
                      ?>
                     <input type="date" name="date_of_birth" id="date_of_birth" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($date_of_birth_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($date_of_birth_input_value ?? ''); ?>">
                     <span class="form-error"><?php echo htmlspecialchars($date_of_birth_err ?? ''); ?></span>
                 </div>
             </div>


             <!-- Section: Contact Information -->
             <div><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-4">Contact Information</h3></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                     <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                     <input type="text" name="phone_number" id="phone_number" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone_number ?? ''); ?>">
                     <span class="form-error"><?php echo htmlspecialchars($phone_number_err ?? ''); ?></span>
                 </div>

                  <div>
                     <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number (Optional)</label>
                     <input type="text" name="whatsapp_number" id="whatsapp_number" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($whatsapp_number ?? ''); ?>">
                 </div>
            </div>

             <div>
                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address (Optional)</label>
                <textarea name="address" id="address" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="pincode" class="block text-sm font-medium text-gray-700 mb-1">Pincode (Optional)</label>
                    <input type="text" name="pincode" id="pincode" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($pincode ?? ''); ?>">
                </div>

                 <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 mb-1">State (Optional)</label>
                    <input type="text" name="state" id="state" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($state ?? ''); ?>">
                </div>
            </div>


             <!-- Section: Academic Information -->
             <div><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-4">Academic Information</h3></div>

             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="current_class" class="block text-sm font-medium text-gray-700 mb-1">Current Class <span class="text-red-500">*</span></label>
                    <input type="text" name="current_class" id="current_class" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($current_class_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($current_class ?? ''); ?>">
                    <span class="form-error"><?php echo htmlspecialchars($current_class_err ?? ''); ?></span>
                </div>
                  <!-- Added Roll Number field from Create Student -->
                 <div>
                     <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">Roll Number (Optional)</label>
                      <input type="text" name="roll_number" id="roll_number" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($roll_number ?? ''); ?>">
                 </div>
             </div>

             <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="previous_class" class="block text-sm font-medium text-gray-700 mb-1">Previous Class (Optional)</label>
                    <input type="text" name="previous_class" id="previous_class" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_class ?? ''); ?>">
                </div>

                 <div>
                    <label for="previous_school" class="block text-sm font-medium text-gray-700 mb-1">Previous School (Optional)</label>
                    <input type="text" name="previous_school" id="previous_school" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_school ?? ''); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="previous_marks_percentage" class="block text-sm font-medium text-gray-700 mb-1">Previous Marks (%) (Optional)</label>
                    <input type="number" name="previous_marks_percentage" id="previous_marks_percentage" step="0.01" min="0" max="100" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_marks_percentage_display ?? ''); ?>">
                </div>

                <div>
                    <label for="current_marks" class="block text-sm font-medium text-gray-700 mb-1">Current Marks (%) (Optional)</label>
                    <input type="number" name="current_marks" id="current_marks" step="0.01" min="0" max="100" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($current_marks_display ?? ''); ?>">
                </div>
            </div>


            <!-- Section: Fee Information (Simplified) -->
            <div><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-4">Fee Structure (Defaults - Editing these does NOT change past monthly dues)</h3></div>

             <!-- Row: Takes Van checkbox - KEEPING THIS -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4"> <!-- Using grid for layout consistency -->
                 <div>
                     <div class="form-check flex items-center pt-2">
                         <input type="checkbox" name="takes_van" id="takes_van" value="on" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out border-gray-300 rounded focus:ring-indigo-500"
                                <?php echo ($takes_van_display === 'on') ? 'checked' : ''; ?>>
                         <label for="takes_van" class="ml-2 block text-sm font-medium text-gray-700">Student takes Van Service</label>
                     </div>
                     <!-- No takes_van_err variable was created -->
                 </div>
                  <!-- Add an empty div here if you want to maintain 2 columns in this row -->
                  <div></div>
            </div>
            <!-- Removed: Default Monthly Fee, Default Van Fee, Other One-Time Fees inputs -->


            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Update Student Details</button>
                 <!-- Link back to view page if ID exists, otherwise dashboard -->
                 <?php if ($student_id_to_edit): ?>
                    <a href="view_student.php?id=<?php echo htmlspecialchars($student_id_to_edit); ?>" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
                 <?php else: ?>
                     <a href="admin_dashboard.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Back to Dashboard</a>
                 <?php endif; ?>
            </div>
        </form>

         <!-- Section: Monthly Fee Status & Payments Table -->
         <!-- This section remains UNCHANGED -->
         <div class="mt-8 pt-8 border-t border-gray-200">
             <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Monthly Fee Status & Payments</h3>

             <!-- Button to toggle the 'Add Monthly Fee' form -->
              <?php if ($student_id_to_edit): // Only show button if student ID is valid ?>
                  <button id="toggleAddFeeForm" type="button" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm font-medium">
                      Add New Monthly Fee Record
                  </button>
              <?php else: ?>
                   <p class="text-yellow-600 text-sm italic">Save student details first to add monthly fees.</p>
              <?php endif; ?>


             <!-- Collapsible form container (Initially hidden, but JS checks for errors to show) -->
             <div id="addMonthlyFeeFormContainer" class="hidden bg-gray-50 p-6 rounded-md mt-4 shadow-inner">
                 <h4 class="text-md font-semibold text-gray-700 mb-4">Add New Monthly Fee Record</h4>

                  <?php
                  // Display general fee form error if exists
                  // This error is now handled by the toast, so this block can be removed or kept as fallback
                  // if (!empty($new_monthly_fee_general_err)) { echo "<p class='text-red-600'>" . htmlspecialchars($new_monthly_fee_general_err) . "</p>"; }
                  ?>

                 <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                     <input type="hidden" name="student_id_for_fee" value="<?php echo htmlspecialchars($student_id_to_edit ?? ''); ?>">
                     <input type="hidden" name="form_type" value="add_monthly_fee"> <!-- Identify this form -->

                     <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                         <div>
                             <label for="new_fee_month" class="block text-sm font-medium text-gray-700 mb-1">Month <span class="text-red-500">*</span></label>
                             <select name="new_fee_month" id="new_fee_month" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_fee_month_err)) ? 'is-invalid' : ''; ?>">
                                 <option value="">Select Month</option>
                                 <?php
                                 $month_names_select = [
                                     1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                     5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                     9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                 ];
                                 for ($m = 1; $m <= 12; $m++) {
                                     // Retain selected month on error
                                     $selected = ((int)($new_fee_month_display ?? 0) === $m) ? 'selected' : '';
                                     echo "<option value='" . $m . "'" . $selected . ">" . htmlspecialchars($month_names_select[$m]) . "</option>";
                                 }
                                 ?>
                             </select>
                             <span class="form-error"><?php echo htmlspecialchars($new_fee_month_err ?? ''); ?></span>
                         </div>
                         <div>
                             <label for="new_fee_year" class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                             <input type="number" name="new_fee_year" id="new_fee_year" step="1" min="2000" max="2100" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_fee_year_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($new_fee_year_display ?? date('Y')); ?>" placeholder="e.g., 2024">
                             <span class="form-error"><?php echo htmlspecialchars($new_fee_year_err ?? ''); ?></span>
                         </div>
                          <!-- The third column div was removed in the previous step if it was here. -->
                         </div>
                         <!-- New row for fee breakdown inputs -->
                          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                              <div>
                                  <label for="new_base_monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Base Monthly Fee <span class="text-red-500">*</span></label>
                                  <input type="number" name="new_base_monthly_fee" id="new_base_monthly_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_base_monthly_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($new_base_monthly_fee_display ?? ''); ?>" placeholder="e.g., 1200.00">
                                  <span class="form-error"><?php echo htmlspecialchars($new_base_monthly_fee_err ?? ''); ?></span>
                             </div>
                              <div>
                                  <label for="new_monthly_van_fee" class="block text-sm font-medium text-gray-700 mb-1">Van Fee (Optional)</label>
                                  <input type="number" name="new_monthly_van_fee" id="new_monthly_van_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_monthly_van_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($new_monthly_van_fee_display ?? ''); ?>" placeholder="e.g., 300.00">
                                  <span class="form-error"><?php echo htmlspecialchars($new_monthly_van_fee_err ?? ''); ?></span>
                              </div>
                          </div>
                           <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                              <div>
                                  <label for="new_monthly_exam_fee" class="block text-sm font-medium text-gray-700 mb-1">Exam Fee (Optional)</label>
                                  <input type="number" name="new_monthly_exam_fee" id="new_monthly_exam_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_monthly_exam_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($new_monthly_exam_fee_display ?? ''); ?>" placeholder="e.g., 100.00">
                                  <span class="form-error"><?php echo htmlspecialchars($new_monthly_exam_fee_err ?? ''); ?></span>
                              </div>
                              <div>
                                  <label for="new_monthly_electricity_fee" class="block text-sm font-medium text-gray-700 mb-1">Electricity Fee (Optional)</label>
                                  <input type="number" name="new_monthly_electricity_fee" id="new_monthly_electricity_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($new_monthly_electricity_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($new_monthly_electricity_fee_display ?? ''); ?>" placeholder="e.g., 50.00">
                                  <span class="form-error"><?php echo htmlspecialchars($new_monthly_electricity_fee_err ?? ''); ?></span>
                              </div>
                          </div>
                          <!-- Removed the single new_amount_due input field -->


                     <div class="flex items-center justify-end gap-4 mt-4">
                          <?php if ($student_id_to_edit): // Only show Add button if we have a student ID ?>
                             <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-sm font-medium">Add Fee Record</button>
                             <button type="button" class="cancel-add-fee px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">Cancel</button>
                         <?php endif; ?>
                     </div>
                 </form>
             </div>


              <?php if (!empty($monthly_fee_records)): ?>
                 <div class="overflow-x-auto mt-6"> <!-- Added mt-6 for spacing -->
                     <table class="monthly-fee-table">
                         <thead>
                             <tr>
                                 <th>Month</th>
                                 <th>Year</th>
                                 <th>Base Fee</th> <!-- Added -->
                                 <th>Van Fee</th> <!-- Added -->
                                 <th>Exam Fee</th> <!-- Added -->
                                 <th>Electricity Fee</th> <!-- Added -->
                                 <th>Total Due</th>
                                 <th>Amount Paid</th>
                                 <th>Amount Due</th>
                                 <th>Status</th>
                                 <th>Payment Date</th>
                                 <th>Notes</th>
                                 <th>Actions</th> <!-- Added Actions column -->
                             </tr>
                         </thead>
                         <tbody>
                             <?php
                              $month_names_display = [
                                  1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                                  5 => 'May', 6 => 'June', 7 => 'Jul', 8 => 'Aug',
                                  9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                              ];
                             foreach ($monthly_fee_records as $record):
                                  // Calculate Amount Due display (Total Due - Amount Paid)
                                  // Note: amount_due in the DB for monthly fees is the *total* due for that month's record,
                                  // based on the sum of breakdown fees at the time of insertion.
                                  $amount_due_display = ($record['amount_due'] ?? 0.00) - ($record['amount_paid'] ?? 0.00);
                                  // Format currency display
                                  $amount_due_formatted = number_format($record['amount_due'] ?? 0.00, 2);
                                  $amount_paid_formatted = number_format($record['amount_paid'] ?? 0.00, 2);
                                  $amount_remaining_formatted = number_format($amount_due_display, 2);

                                  // Format breakdown fees
                                   $base_fee_formatted = number_format($record['base_monthly_fee'] ?? 0.00, 2);
                                   $van_fee_formatted = number_format($record['monthly_van_fee'] ?? 0.00, 2);
                                   $exam_fee_formatted = number_format($record['monthly_exam_fee'] ?? 0.00, 2);
                                   $electricity_fee_formatted = number_format($record['monthly_electricity_fee'] ?? 0.00, 2);


                                  // Determine status class
                                  $status_class = ($record['is_paid'] == 1) ? 'status-paid' : 'status-due';
                                  $status_text = ($record['is_paid'] == 1) ? 'Paid' : 'Due';

                                  // Format Payment Date
                                   $payment_date_display = (!empty($record['payment_date']) && $record['payment_date'] !== '0000-00-00') ? date("Y-m-d", strtotime($record['payment_date'])) : 'N/A';

                              ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($month_names_display[$record['fee_month']] ?? 'N/A'); ?></td>
                                     <td><?php echo htmlspecialchars($record['fee_year'] ?? 'N/A'); ?></td>
                                     <td><?php echo htmlspecialchars($base_fee_formatted); ?></td> <!-- Display Base Fee -->
                                     <td><?php echo htmlspecialchars($van_fee_formatted); ?></td> <!-- Display Van Fee -->
                                     <td><?php echo htmlspecialchars($exam_fee_formatted); ?></td> <!-- Display Exam Fee -->
                                     <td><?php echo htmlspecialchars($electricity_fee_formatted); ?></td> <!-- Display Electricity Fee -->
                                     <td><?php echo htmlspecialchars($amount_due_formatted); ?></td> <!-- Display Total Due -->
                                     <td><?php echo htmlspecialchars($amount_paid_formatted); ?></td>
                                     <td><?php echo htmlspecialchars($amount_remaining_formatted); ?></td>
                                     <td class="<?php echo $status_class; ?>"><?php echo $status_text; ?></td>
                                     <td><?php echo htmlspecialchars($payment_date_display); ?></td>
                                     <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                     <td>
                                         <!-- Actions -->
                                         <!-- Example: Link to a page to record a payment or edit the record -->
                                          <?php if ($record['is_paid'] == 0): ?>
                                               <a href="record_payment.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="action-link mr-2">Record Payment</a>
                                          <?php endif; ?>
                                         <a href="edit_monthly_fee.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="action-link">Edit Record</a>
                                         <!-- Add a delete link/form later if needed -->
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
              <?php else: ?>
                  <p class="text-gray-600 italic mt-4">No monthly fee records found for this student.</p>
              <?php endif; ?>

         </div> <!-- End of Monthly Fee Status section -->


    </div> <!-- End of main content wrapper -->

</body>
</html>