<?php
// School/teacher/edit_student_teacher.php

session_start();

// Include database configuration file
// Path from 'teacher/' up to 'School/' is '../'
require_once "../config.php"; // Required for database operations

// Check if the user is logged in and is specifically a Teacher
// We now ONLY check for $_SESSION['role'] === 'teacher'
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'teacher') {
    // Store plain text message in session
    $_SESSION['operation_message'] = "Access denied. Only Teachers can edit limited student records.";
    // Path from 'teacher/' up to 'School/' is '../'
    header("location: ../login.php"); // Redirect to login
    exit;
}

// Define variables and initialize
$user_id = null;
$current_class = $current_marks = ""; // Only these fields will be edited
$full_name_display = ""; // To display the student's name for context

$current_class_err = ""; // Validation error for current_class
$current_marks_err = ""; // Validation error for current_marks
$edit_message = ""; // To display success or error messages on this page

// Processing form data when form is submitted (UPDATE operation)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get student user_id from hidden field
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    // Get name for display context - important to fetch fresh data or validate if user_id is crucial
    // For security, it's best to refetch full_name from DB using user_id rather than trusting hidden field
    // However, keeping it simple as per original logic for now, just ensure it's trimmed/sanitized if used
    $full_name_display = trim($_POST['full_name_display'] ?? ''); // Get name for display context


    if ($user_id === false || $user_id <= 0) {
        // Invalid user_id, cannot proceed with update
        $edit_message = "<p class='text-red-600'>Invalid student ID provided for update.</p>";
        // Keep submitted values for repopulation
        $current_class = trim($_POST['current_class'] ?? '');
        $current_marks = $_POST['current_marks'] ?? ''; // Keep as string for display
    } else {
        // Sanitize and validate editable inputs

        // Current Class (Required)
        if (empty(trim($_POST["current_class"] ?? ''))) {
            $current_class_err = "Please enter the current class.";
        } else {
            $current_class = trim($_POST["current_class"] ?? '');
        }

        // Current Marks (Can be null, but validate format if provided)
         $current_marks_input = trim($_POST['current_marks'] ?? '');
         if ($current_marks_input === '') {
             $current_marks = null; // Allow null if empty
         } else {
             // Validate as a float between 0 and 100
             $current_marks_filtered = filter_var($current_marks_input, FILTER_VALIDATE_FLOAT);
              if ($current_marks_filtered === false || $current_marks_filtered < 0 || $current_marks_filtered > 100) {
                  $current_marks_err = "Please enter a valid percentage between 0 and 100.";
                  $current_marks = $current_marks_input; // Keep original invalid input for display
              } else {
                   $current_marks = $current_marks_filtered; // Use filtered float for DB
              }
         }


        // Check input errors before updating in database
        // Note: Only check errors for the fields the teacher can edit
        if (empty($current_class_err) && empty($current_marks_err)) {

            // Prepare an update statement for ONLY current_class and current_marks
            $sql_update = "UPDATE students SET current_class=?, current_marks=? WHERE user_id=?";

            if ($link === false) {
                 $edit_message = "<p class='text-red-600'>Database connection error. Could not save changes.</p>";
                 error_log("Teacher edit DB connection failed: " . mysqli_connect_error());
                 // Keep submitted values for repopulation on error
                $current_class = trim($_POST['current_class'] ?? '');
                $current_marks = $_POST['current_marks'] ?? ''; // Keep as string
            } elseif ($stmt_update = mysqli_prepare($link, $sql_update)) {
                 // Bind variables: s for current_class (string), d for current_marks (double), i for user_id (integer)
                // Note: current_marks can be null, but mysqli_stmt_bind_param treats null floats/decimals correctly with 'd'.
                // Use mysqli_stmt_bind_param correctly - the second argument is a string of types
                mysqli_stmt_bind_param($stmt_update, "sdi",
                    $current_class,
                    $current_marks, // This will be null or float/double
                    $user_id
                );

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt_update)) {
                    // Refetch student name for the success message to be safe, or rely on the hidden field if okay
                    // For a simple update on the same page, hidden field is often acceptable for the *message* display
                    // Set success message (plain text) in session and redirect back to staff dashboard
                    $_SESSION['operation_message'] = "Student record for " . htmlspecialchars($full_name_display) . " updated successfully.";
                    // --- CORRECTED REDIRECT PATH ---
                    // Path from 'teacher/' up to 'School/' is '../'
                    header("location: ../staff_dashboard.php"); // Redirect back to staff dashboard
                    exit();
                } else {
                     $edit_message = "<p class='text-red-600'>Error: Could not update student record. " . mysqli_stmt_error($stmt_update) . "</p>";
                      // Keep submitted values for repopulation on error
                     $current_class = trim($_POST['current_class'] ?? '');
                     $current_marks = $_POST['current_marks'] ?? ''; // Keep as string
                     error_log("Teacher edit update query failed: " . mysqli_stmt_error($stmt_update));
                }

                // Close statement
                mysqli_stmt_close($stmt_update);
            } else {
                 $edit_message = "<p class='text-red-600'>Error: Could not prepare update statement. " . mysqli_error($link) . "</p>";
                 // Keep submitted values for repopulation on error
                 $current_class = trim($_POST['current_class'] ?? '');
                 $current_marks = $_POST['current_marks'] ?? ''; // Keep as string
                 error_log("Teacher edit prepare update statement failed: " . mysqli_error($link));
            }
        } else {
             // Validation errors occurred
             $edit_message = "<p class='text-yellow-600'>Please correct the errors below.</p>";
             // Submitted values are already kept in variables above in the validation blocks
        }
    }

} else { // GET request - Display the form with existing data

    // Check if id parameter exists
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter and validate as integer
        $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($user_id === false || $user_id <= 0) {
            // URL doesn't contain valid id parameter. Redirect to dashboard with error.
            // Store plain text message in session
            $_SESSION['operation_message'] = "Invalid student ID provided for editing.";
            // Path from 'teacher/' up to 'School/' is '../'
            header("location: ../staff_dashboard.php"); // Redirect back to staff dashboard
            exit();
        } else {
            // Prepare a select statement to fetch ONLY the relevant fields + name for display
            $sql_fetch = "SELECT user_id, full_name, current_class, current_marks FROM students WHERE user_id = ?";

            if ($link === false) {
                $edit_message = "<p class='text-red-600'>Database connection error. Could not load student data.</p>";
                 error_log("Teacher edit fetch DB connection failed: " . mysqli_connect_error());
            } elseif ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt_fetch)) {
                    $result_fetch = mysqli_stmt_get_result($stmt_fetch);

                    if ($result_fetch && mysqli_num_rows($result_fetch) == 1) {
                        // Fetch result row as an associative array
                        $student = mysqli_fetch_assoc($result_fetch);

                        // Retrieve individual field values
                        $full_name_display = $student['full_name']; // Get name for display
                        $current_class = $student["current_class"];
                        $current_marks = $student["current_marks"]; // Can be null

                    } else {
                        // Record not found. Redirect.
                        // Store plain text message in session
                        $_SESSION['operation_message'] = "Student record not found.";
                        // Path from 'teacher/' up to 'School/' is '../'
                        header("location: ../staff_dashboard.php"); // Redirect back to staff dashboard
                        exit();
                    }
                    if ($result_fetch) mysqli_free_result($result_fetch); // Free result set
                } else {
                    $edit_message = "<p class='text-red-600'>Oops! Something went wrong. Could not fetch student data. Please try again later.</p>";
                     error_log("Teacher edit fetch query failed: " . mysqli_stmt_error($stmt_fetch));
                }

                // Close statement
                mysqli_stmt_close($stmt_fetch);
            } else {
                 $edit_message = "<p class='text-red-600'>Oops! Something went wrong. Could not prepare fetch statement. Please try again later.</p>";
                 error_log("Teacher edit prepare fetch statement failed: " . mysqli_error($link));
            }
        }
    } else {
        // URL doesn't contain id parameter. Redirect to dashboard.
        // Store plain text message in session
        $_SESSION['operation_message'] = "No student ID provided for editing.";
        // Path from 'teacher/' up to 'School/' is '../'
        header("location: ../staff_dashboard.php");  // Redirect back to staff dashboard
        exit();
    }
}

// Close connection (Only if it was opened and still open)
if (isset($link) && is_object($link) && mysqli_ping($link)) { // Check if $link is a valid connection object before closing
     mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student Data (Teacher)</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
        /* Optional: Style for error messages */
        .form-error {
            color: #dc3545; /* Red */
            font-size: 0.875em;
            margin-top: 0.25em;
            display: block; /* Ensures it's on its own line */
        }
         .form-control.is-invalid {
             border-color: #dc3545; /* Red border */
         }
          .message-box {
               padding: 0.75rem 1.25rem;
               margin-bottom: 1rem;
               border: 1px solid transparent;
               border-radius: 0.25rem;
           }
           .message-box.error {
               color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;
           }
           .message-box.success {
               color: #155724; background-color: #d4edda; border-color: #c3e6cb;
           }
            .message-box.warning {
               color: #856404; background-color: #fff3cd; border-color: #ffeeba;
           }
            .message-box.info { /* Added info style */
                color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb;
            }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center py-8 px-4">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-xl font-semibold mb-6 text-center">Edit Student Data (Teacher)</h2>

        <?php
        // Display edit message on this page
        if (!empty($edit_message)) {
             $message_class = 'message-box info'; // Default to info for non-specific messages
              // Check message content for type (simple check)
             $msg_lower = strtolower(strip_tags($edit_message));
             if (strpos($msg_lower, 'successfully') !== false) {
                 $message_class = 'message-box success';
             } elseif (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'database connection') !== false) {
                  $message_class = 'message-box error';
             } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'please correct') !== false) {
                 $message_class = 'message-box warning';
             }
            echo "<div class='mb-4 text-center " . $message_class . "' role='alert'>" . $edit_message . "</div>";
        }
        ?>

        <?php
        if ($user_id !== null):
        ?>

        <p class="text-center text-gray-600 mb-4">Editing record for: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($full_name_display); ?></span> (ID: <?php echo htmlspecialchars($user_id); ?>)</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">

            <!-- Hidden field to pass user_id and full_name_display -->
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
             <!-- It's safer to refetch full_name_display on POST based on user_id from the DB, but keeping this for consistency -->
             <input type="hidden" name="full_name_display" value="<?php echo htmlspecialchars($full_name_display); ?>">


             <div>
                <label for="current_class" class="block text-sm font-medium text-gray-700">Current Class <span class="text-red-500">*</span></label>
                <input type="text" name="current_class" id="current_class" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($current_class_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($current_class ?? ''); ?>">
                <?php if (!empty($current_class_err)): ?><span class="form-error"><?php echo $current_class_err; ?></span><?php endif; ?>
            </div>

            <div>
                <label for="current_marks" class="block text-sm font-medium text-gray-700">Current Marks (%)</label>
                <input type="number" name="current_marks" id="current_marks" step="0.01" min="0" max="100" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($current_marks_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($current_marks ?? ''); ?>">
                 <?php if (!empty($current_marks_err)): ?><span class="form-error"><?php echo $current_marks_err; ?></span><?php endif; ?>
            </div>


            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Save Changes</button>
                 <!-- Path from 'teacher/' up to 'School/' is '../' -->
                 <a href="./staff_dashboard.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
            </div>
        </form>

         <?php else: ?>
            <!-- If user_id is null (meaning invalid ID from GET or POST, or initial fetch failed) -->
            <div class="mt-6 text-center">
                 <!-- Path from 'teacher/' up to 'School/' is '../' -->
                <a href="../staff_dashboard.php" class="text-blue-600 hover:underline font-medium">Back to Dashboard</a>
            </div>
         <?php endif; ?>


    </div>

</body>
</html>