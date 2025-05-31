<?php
// School/admin/add_monthly_fee.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN
// Only Admins should be able to add fee records directly.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can add fee records.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// Define variables and initialize with empty values for the new monthly fee record
$student_id = null; // The ID of the student this fee is for

$fee_year_display = date('Y'); // Default to current year for display
$fee_month_display = '';        // No default month
$base_monthly_fee_display = ''; // For input value
$monthly_van_fee_display = '';
$monthly_exam_fee_display = '';
$monthly_electricity_fee_display = '';

$student_full_name = 'Loading...'; // To display student name for context

// Error variables for the NEW monthly fee form submission
$fee_month_err = '';
$fee_year_err = '';
$base_monthly_fee_err = '';
$monthly_van_fee_err = '';
$monthly_exam_fee_err = '';
$monthly_electricity_fee_err = '';
$general_fee_err = ''; // For general errors like duplicate entry or student ID fetch

// Variables for the toast message system
$toast_message = '';
$toast_type = ''; // 'success', 'error', 'warning', 'info'


// --- Handle POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") { // <-- Opening brace for POST block

    // Get the student ID from the hidden input field
     $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    // Validate the student ID
     if ($student_id === false || $student_id <= 0) {
          // If the ID is invalid, set a general error toast and skip processing
          $toast_message = "Invalid student ID provided for fee submission.";
          $toast_type = 'error';
          $student_id = null; // Ensure it's null if invalid
     }


    if ($student_id !== null) { // Only proceed if we have a valid student ID from POST

        // --- Collect and Trim input values ---
        $fee_month_display = trim($_POST['fee_month'] ?? ''); // Keep for display
        $fee_year_display = trim($_POST['fee_year'] ?? ''); // Keep for display
        $base_monthly_fee_display = trim($_POST['base_monthly_fee'] ?? ''); // Keep for display
        $monthly_van_fee_display = trim($_POST['monthly_van_fee'] ?? ''); // Keep for display
        $monthly_exam_fee_display = trim($_POST['monthly_exam_fee'] ?? ''); // Keep for display
        $monthly_electricity_fee_display = trim($_POST['monthly_electricity_fee'] ?? ''); // Keep for display

        // Variables to hold validated/converted values for DB
        $fee_month_for_db = null;
        $fee_year_for_db = null;
        $base_monthly_fee_for_db = null;
        $monthly_van_fee_for_db = null;
        $monthly_exam_fee_for_db = null;
        $monthly_electricity_fee_for_db = null;
        $amount_due_calculated = 0.0;

        // Validation
        if (empty($fee_month_display)) {
            $fee_month_err = "Please select a month.";
        } else {
            $month_int = filter_var($fee_month_display, FILTER_VALIDATE_INT);
            if ($month_int === false || $month_int < 1 || $month_int > 12) {
                $fee_month_err = "Invalid month selected.";
            } else {
                $fee_month_for_db = $month_int;
            }
        }

        if (empty($fee_year_display)) {
            $fee_year_err = "Please enter a year.";
        } else {
             $year_int = filter_var($fee_year_display, FILTER_VALIDATE_INT);
             if ($year_int === false || $year_int < 2000 || $year_int > 2100) { // Simple year range validation
                  $fee_year_err = "Invalid year (e.g., 2000-2100).";
             } else {
                  $fee_year_for_db = $year_int;
             }
        }

        if (empty($base_monthly_fee_display)) {
            $base_monthly_fee_err = "Base fee is required.";
        } else {
             $base_fee_float = filter_var($base_monthly_fee_display, FILTER_VALIDATE_FLOAT);
             if ($base_fee_float === false || $base_fee_float < 0 ) {
                  $base_monthly_fee_err = "Please enter a valid non-negative number for base fee.";
             } else {
                  $base_monthly_fee_for_db = $base_fee_float;
                  $amount_due_calculated += $new_base_monthly_fee_for_db; // Add to total
             }
        }

        // Optional fee fields validation and calculation
        if (!empty($monthly_van_fee_display)) {
            $van_fee_float = filter_var($monthly_van_fee_display, FILTER_VALIDATE_FLOAT);
             if ($van_fee_float === false || $van_fee_float < 0) {
                 $monthly_van_fee_err = "Invalid Van fee.";
             } else {
                 $monthly_van_fee_for_db = $van_fee_float;
                 $amount_due_calculated += $monthly_van_fee_for_db; // Add to total
             }
        } else {
             $monthly_van_fee_for_db = 0.0; // Treat empty optional as 0
        }

         if (!empty($monthly_exam_fee_display)) {
            $exam_fee_float = filter_var($monthly_exam_fee_display, FILTER_VALIDATE_FLOAT);
             if ($exam_fee_float === false || $exam_fee_float < 0) {
                 $monthly_exam_fee_err = "Invalid Exam fee.";
             } else {
                 $monthly_exam_fee_for_db = $exam_fee_float;
                 $amount_due_calculated += $monthly_exam_fee_for_db; // Add to total
             }
        } else {
             $monthly_exam_fee_for_db = 0.0; // Treat empty optional as 0
        }

         if (!empty($monthly_electricity_fee_display)) {
            $elec_fee_float = filter_var($monthly_electricity_fee_display, FILTER_VALIDATE_FLOAT);
             if ($elec_fee_float === false || $elec_fee_float < 0) {
                 $monthly_electricity_fee_err = "Invalid Electricity fee.";
             } else {
                 $monthly_electricity_fee_for_db = $elec_fee_float;
                 $amount_due_calculated += $monthly_electricity_fee_for_db; // Add to total
             }
        } else {
             $monthly_electricity_fee_for_db = 0.0; // Treat empty optional as 0
        }


        // Check if there are any validation errors
        $has_fee_errors = !empty($fee_month_err) || !empty($fee_year_err) ||
                          !empty($base_monthly_fee_err) || !empty($monthly_van_fee_err) ||
                          !empty($monthly_exam_fee_err) || !empty($monthly_electricity_fee_err);


        if (!$has_fee_errors && $student_id > 0) { // Proceed if valid student ID and no validation errors

            // Check for duplicate entry (same student, year, and month)
            $sql_check_duplicate = "SELECT id FROM student_monthly_fees WHERE student_id = ? AND fee_year = ? AND fee_month = ?";
            if ($link === false) {
                 $general_fee_err = "Database connection error during duplicate check.";
                 $toast_type = 'error'; // Set toast type for this error
            } elseif ($stmt_check = mysqli_prepare($link, $sql_check_duplicate)) {
                mysqli_stmt_bind_param($stmt_check, "iii", $student_id, $fee_year_for_db, $fee_month_for_db);
                if (mysqli_stmt_execute($stmt_check)) {
                    mysqli_stmt_store_result($stmt_check);
                    if (mysqli_stmt_num_rows($stmt_check) > 0) {
                        $general_fee_err = "A fee record for this month and year already exists for this student.";
                        $toast_type = 'warning'; // Use warning for duplicate
                    }
                } else {
                     $general_fee_err = "Database error during duplicate check.";
                     $toast_type = 'error'; // Set toast type for this error
                     error_log("Add Monthly Fee duplicate check failed for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_check));
                }
                mysqli_stmt_close($stmt_check);
            } else {
                 $general_fee_err = "Database error preparing duplicate check.";
                 $toast_type = 'error'; // Set toast type for this error
                 error_log("Add Monthly Fee prepare duplicate check failed: " . mysqli_error($link));
            }


            // If no duplicate error and no validation errors, insert the new fee record
            if (empty($general_fee_err)) {
                // Prepare the insert statement for the student_monthly_fees table
                $sql_insert_fee = "INSERT INTO student_monthly_fees (student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"; // amount_paid and is_paid are initialized

                 if ($link === false) {
                     $general_fee_err = "Database connection error. Could not add fee record.";
                     $toast_type = 'error';
                 } elseif ($stmt_insert = mysqli_prepare($link, $sql_insert_fee)) {
                    // Bind parameters: i i i d d d d d
                    $bind_types_fee = "iiiddddd";
                    mysqli_stmt_bind_param($stmt_insert, $bind_types_fee,
                        $student_id,
                        $fee_year_for_db,
                        $fee_month_for_db,
                        $base_monthly_fee_for_db,
                        $monthly_van_fee_for_db,
                        $monthly_exam_fee_for_db,
                        $monthly_electricity_fee_for_db,
                        $amount_due_calculated // Use the calculated amount
                    );

                    if (mysqli_stmt_execute($stmt_insert)) {
                        mysqli_stmt_close($stmt_insert);
                        // Set success message for toast
                        $_SESSION['operation_message'] = "<p class='text-green-600'>Monthly fee record added successfully.</p>";
                         // Redirect back to the student's view page
                        header("location: view_student.php?id=" . htmlspecialchars($student_id));
                        exit();

                    } else {
                        // Error executing insert
                         $general_fee_err = "Error: Could not add monthly fee record."; // Don't show raw SQL error
                         $toast_type = 'error'; // Use error type for execution errors
                         error_log("Add Monthly Fee insert failed for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_insert));
                        mysqli_stmt_close($stmt_insert);
                    }
                } else {
                     // Error preparing insert statement
                     $general_fee_err = "Error: Could not prepare fee insert statement."; // Don't show raw SQL error
                     $toast_type = 'error'; // Use error type for preparation errors
                     error_log("Add Monthly Fee prepare fee insert failed: " . mysqli_error($link));
                }
            }
             // If a general error occurred (duplicate or DB error during check/insert),
             // the display variables ($fee_month_display etc.) still hold the user's input.
             // The $general_fee_err holds the specific message.
             // We need to fetch the student name to display the page again.

        } else {
            // If there were validation errors for the fee form
             if ($student_id <= 0) {
                 // This case should ideally be caught by the initial ID check, but double check
                 $general_fee_err = "Invalid student ID provided for fee submission.";
                 $toast_type = 'error';
             } else {
                 $general_fee_err = "Validation errors found. Please check the form.";
                 $toast_type = 'error'; // Use error for validation issues
             }
             // Display variables are already populated from $_POST by the validation block above.
             // Need to fetch student name to display the page again.
        }
    }
    // If $student_id was invalid from the start of POST, set general error.
    // Now, attempt to fetch the student's name IF $student_id is set (even if there were form errors)
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
                     $student_full_name = "Student Not Found"; // Should not happen if student_id came from a valid student
                 }
                 mysqli_free_result($result_fetch_student);
             } else {
                 error_log("Add Monthly Fee student fetch query failed during POST for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_fetch_student));
                 $student_full_name = "Error fetching student name";
             }
             mysqli_stmt_close($stmt_fetch_student);
         } elseif ($link !== false) {
              error_log("Add Monthly Fee prepare student fetch failed: " . mysqli_error($link));
              $student_full_name = "Error preparing student fetch";
         }
    } else {
         $student_full_name = "Invalid Student"; // If student_id was initially invalid
    }


// --- Closing brace for if ($_SERVER["REQUEST_METHOD"] == "POST") ---
} else { // GET request - Display the empty form // <-- Opening brace for GET block

    if (isset($_GET["student_id"]) && !empty(trim($_GET["student_id"]))) { // <-- Opening brace for if (isset($_GET["student_id"]))
        $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

        if ($student_id === false || $student_id <= 0) { // <-- Opening brace for if (invalid ID)
            $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid student ID provided.</p>";
            header("location: admin_dashboard.php"); // Adjust redirect
            exit();
        } else { // <-- Opening brace for else (valid ID on GET)
            // Fetch student's name for display
            $sql_fetch_student = "SELECT full_name FROM students WHERE user_id = ?";
            if ($link === false) { // <-- Opening brace for if ($link === false)
                 // Use toast for error messages
                $toast_message = "Database connection error. Could not load student data.";
                $toast_type = 'error';
                 error_log("Add Monthly Fee student fetch DB connection failed (GET): " . mysqli_connect_error());
                 $student_id = null; // Invalidate if DB connection fails
            } elseif ($stmt_fetch_student = mysqli_prepare($link, $sql_fetch_student)) { // <-- Opening brace for elseif ($stmt_fetch_student = mysqli_prepare)
                mysqli_stmt_bind_param($stmt_fetch_student, "i", $student_id);
                if (mysqli_stmt_execute($stmt_fetch_student)) {
                    $result_fetch_student = mysqli_stmt_get_result($stmt_fetch_student);
                    if (mysqli_num_rows($result_fetch_student) == 1) {
                        $student = mysqli_fetch_assoc($result_fetch_student);
                        $student_full_name = htmlspecialchars($student['full_name']);
                         // No operation message needed on successful GET fetch
                    } else {
                         $_SESSION['operation_message'] = "<p class='text-red-600'>Student record not found.</p>";
                         header("location: admin_dashboard.php"); // Redirect if student not found
                         exit();
                    }
                    mysqli_free_result($result_fetch_student);
                } else {
                     // Log the actual MySQL error, but show a generic message to the user
                    $toast_message = "Oops! Something went wrong. Could not fetch student data. Please try again later.";
                    $toast_type = 'error';
                     error_log("Add Monthly Fee student fetch query failed: " . mysqli_stmt_error($stmt_fetch_student));
                     $student_id = null; // Invalidate if fetch fails
                }
                mysqli_stmt_close($stmt_fetch_student);
            } elseif ($link !== false) { // Check link before logging prep error
                 $toast_message = "Oops! Something went wrong. Could not prepare student fetch statement. Please try again later.";
                 $toast_type = 'error';
                 error_log("Add Monthly Fee prepare student fetch statement failed: " . mysqli_error($link));
                 $student_id = null; // Invalidate if prepare fails
            }

            // Initialize form variables for GET (empty except for default year)
            $fee_year_display = date('Y');
            $fee_month_display = '';
            $base_monthly_fee_display = '';
            $monthly_van_fee_display = '';
            $monthly_exam_fee_display = '';
            $monthly_electricity_fee_display = '';

        } // --- Closing brace for else (valid ID on GET) ---
    // --- Closing brace for if (isset($_GET["student_id"])) ---
    } else { // <-- Opening brace for else (no student_id in GET)
        $_SESSION['operation_message'] = "<p class='text-red-600'>No student ID provided to add fee record.</p>";
        header("location: admin_dashboard.php"); // Redirect
        exit();
    } // --- Closing brace for else (no student_id in GET) ---

// --- Closing brace for else (GET request) ---
} // <-- This brace closes the main if ($_SERVER["REQUEST_METHOD"] == "POST") block


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
    <title>Add Monthly Fee Record</title>
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
         }
         .form-control.is-invalid:focus {
              border-color: #dc2626; /* red-600 */
              ring-color: #f87171; /* red-400 */
         }
           input[type="number"]::placeholder {
               color: #9ca3af; /* Tailwind gray-400 */
           }

         /* --- Toast Notification Styles (Copied from edit_student) --- */
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
     <script>
         // --- Toast Notification JS (Copied from edit_student) ---
         document.addEventListener('DOMContentLoaded', function() {
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
         });
     </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center py-8 px-4">

    <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here -->
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md"> <!-- Adjusted max-width -->
        <h2 class="text-xl font-semibold mb-6 text-center">Add Monthly Fee Record</h2>

         <!-- Student Context -->
         <div class="mb-6 text-center text-gray-700">
             <?php if ($student_id > 0): ?>
                  <p>Adding fee for Student: <span class="font-semibold"><?php echo $student_full_name; ?></span> (ID: <?php echo htmlspecialchars($student_id); ?>)</p>
             <?php else: ?>
                  <p>Student: N/A</p>
             <?php endif; ?>
         </div>

         <!-- Back Link -->
        <div class="mb-6 text-left">
             <?php if ($student_id > 0): ?>
                  <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Student Fees</a>
             <?php else: ?>
                  <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Dashboard</a>
             <?php endif; ?>
        </div>

         <?php
         // General Error Display (as fallback, toast is primary)
         if (!empty($general_fee_err)) {
             echo "<div class='text-red-600 mb-4 text-center'>" . htmlspecialchars($general_fee_err) . "</div>";
         }
         ?>


        <?php if ($student_id > 0): // Only show the form if we have a valid student ID ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">

                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">

                <!-- Month and Year -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="fee_month" class="block text-sm font-medium text-gray-700 mb-1">Month <span class="text-red-500">*</span></label>
                        <select name="fee_month" id="fee_month" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_month_err)) ? 'is-invalid' : ''; ?>">
                            <option value="">Select Month</option>
                            <?php
                            $month_names_select = [
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ];
                            for ($m = 1; $m <= 12; $m++) {
                                // Retain selected month on error
                                $selected = ((int)($fee_month_display ?? 0) === $m) ? 'selected' : '';
                                echo "<option value='" . $m . "'" . $selected . ">" . htmlspecialchars($month_names_select[$m]) . "</option>";
                            }
                            ?>
                        </select>
                        <span class="form-error"><?php echo htmlspecialchars($fee_month_err ?? ''); ?></span>
                    </div>
                    <div>
                        <label for="fee_year" class="block text-sm font-medium text-gray-700 mb-1">Year <span class="text-red-500">*</span></label>
                        <input type="number" name="fee_year" id="fee_year" step="1" min="2000" max="2100" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($fee_year_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($fee_year_display ?? date('Y')); ?>" placeholder="e.g., 2024">
                        <span class="form-error"><?php echo htmlspecialchars($fee_year_err ?? ''); ?></span>
                    </div>
                </div>

                <!-- Fee Breakdown (Editable) -->
                <div><h3 class="text-md font-semibold text-gray-700 border-b pb-1 mb-3 mt-3">Fee Breakdown</h3></div>

                 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                         <label for="base_monthly_fee" class="block text-sm font-medium text-gray-700 mb-1">Base Fee <span class="text-red-500">*</span></label>
                         <input type="number" name="base_monthly_fee" id="base_monthly_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($base_monthly_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($base_monthly_fee_display ?? ''); ?>" placeholder="e.g., 1200.00">
                         <span class="form-error"><?php echo htmlspecialchars($base_monthly_fee_err ?? ''); ?></span>
                     </div>
                     <div>
                         <label for="monthly_van_fee" class="block text-sm font-medium text-gray-700 mb-1">Van Fee (Optional)</label>
                         <input type="number" name="monthly_van_fee" id="monthly_van_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($monthly_van_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_van_fee_display ?? ''); ?>" placeholder="e.g., 300.00">
                         <span class="form-error"><?php echo htmlspecialchars($monthly_van_fee_err ?? ''); ?></span>
                     </div>
                 </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="monthly_exam_fee" class="block text-sm font-medium text-gray-700 mb-1">Exam Fee (Optional)</label>
                        <input type="number" name="monthly_exam_fee" id="monthly_exam_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($monthly_exam_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_exam_fee_display ?? ''); ?>" placeholder="e.g., 100.00">
                        <span class="form-error"><?php echo htmlspecialchars($monthly_exam_fee_err ?? ''); ?></span>
                    </div>
                     <div>
                         <label for="monthly_electricity_fee" class="block text-sm font-medium text-gray-700 mb-1">Electricity Fee (Optional)</label>
                         <input type="number" name="monthly_electricity_fee" id="monthly_electricity_fee" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($monthly_electricity_fee_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($monthly_electricity_fee_display ?? ''); ?>" placeholder="e.g., 50.00">
                         <span class="form-error"><?php echo htmlspecialchars($monthly_electricity_fee_err ?? ''); ?></span>
                     </div>
                </div>


                <div class="flex items-center justify-end gap-4 mt-6">
                     <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 text-sm font-medium">Add Fee Record</button>
                     <?php if ($student_id > 0): ?>
                         <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">Cancel</a>
                     <?php else: ?>
                          <a href="admin_dashboard.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm font-medium">Back to Dashboard</a>
                     <?php endif; ?>
                </div>
            </form>
         <?php else: ?>
             <p class="text-center text-red-600">Cannot add a fee record without a valid student.</p>
         <?php endif; ?>


    </div> <!-- End of main content wrapper -->

</body>
</html>