<?php
session_start();

// Include database configuration file
require_once "../config.php"; // config.php is in the same directory

// Check if the user is logged in and is ADMIN
// Only users with role 'admin' can edit staff
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can edit staff records.</p>";
    header("location: ../login.php"); // Redirect to login (adjust path)
    exit;
}

// Define variables and initialize
$staff_id = null;
// Initialize all editable fields (excluding password for direct editing)
$staff_name = $mobile_number = $unique_id = $email = $role = $salary = $subject_taught = $classes_taught = "";

// Initialize error variables
$staff_name_err = $mobile_number_err = $unique_id_err = $email_err = $role_err = $salary_err = "";
// Password change requires a separate form/process or careful handling here

$edit_message = ""; // To display success or error messages on this page
$staff_full_name = ""; // To display staff name for context

// Allowed roles (for validation and dropdown)
$allowed_roles = ['teacher', 'principal', 'admin', 'staff']; // Include 'staff' if it's a valid role

// Processing form data when form is submitted (UPDATE operation)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get staff_id from hidden field
    $staff_id = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
    // Get staff name from hidden field for display context
    $staff_full_name = trim($_POST['staff_full_name'] ?? '');


    if ($staff_id === false || $staff_id <= 0) {
        // Invalid staff_id, cannot proceed with update
        $edit_message = "<p class='text-red-600'>Invalid staff ID provided for update.</p>";
        // Keep submitted data to repopulate form on this page if staff_id is bad
        $staff_name = trim($_POST["staff_name"] ?? ''); // Repopulate all fields...
        $mobile_number = trim($_POST["mobile_number"] ?? '');
        $unique_id = trim($_POST["unique_id"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $role = trim($_POST["role"] ?? '');
        $salary = $_POST["salary"] ?? ''; // Keep as string for display
        $subject_taught = trim($_POST["subject_taught"] ?? '');
        $classes_taught = trim($_POST["classes_taught"] ?? '');

    } else {
        // Validate and sanitize ALL editable inputs

        // Staff Name (Required)
        if (empty(trim($_POST["staff_name"] ?? ''))) {
            $staff_name_err = "Please enter the staff name.";
        } else {
            $staff_name = trim($_POST["staff_name"] ?? '');
        }

        // Mobile Number (Required)
        if (empty(trim($_POST["mobile_number"] ?? ''))) {
            $mobile_number_err = "Please enter mobile number.";
        } else {
            $mobile_number = trim($_POST["mobile_number"] ?? '');
            // Optional: Add more specific phone number validation
        }

        // Unique ID (4 digits)
        if (empty(trim($_POST["unique_id"] ?? ''))) {
            $unique_id_err = "Please enter the unique ID.";
        } else {
            $unique_id = trim($_POST["unique_id"] ?? '');
            // Validate format (exactly 4 digits)
            if (!preg_match("/^\d{4}$/", $unique_id)) {
                $unique_id_err = "Unique ID must be exactly 4 digits.";
            } else {
                // Check if unique_id already exists for *another* staff member
                $sql_check_unique_id = "SELECT staff_id FROM staff WHERE unique_id = ? AND staff_id != ?";
                if ($stmt_check = mysqli_prepare($link, $sql_check_unique_id)) {
                    mysqli_stmt_bind_param($stmt_check, "si", $unique_id, $staff_id);
                    if (mysqli_stmt_execute($stmt_check)) {
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $unique_id_err = "This unique ID is already taken by another staff member.";
                        }
                    } else {
                         $edit_message .= "<p class='text-red-600'>Error checking unique ID availability. Please try again.</p>";
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                     $edit_message .= "<p class='text-red-600'>Error preparing unique ID check statement.</p>";
                }
            }
        }

        // Email (Required)
        if (empty(trim($_POST["email"] ?? ''))) {
            $email_err = "Please enter the email.";
        } else {
            $email = trim($_POST["email"] ?? '');
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email_err = "Please enter a valid email format.";
            } else {
                // Check if email already exists for *another* staff member
                $sql_check_email = "SELECT staff_id FROM staff WHERE email = ? AND staff_id != ?";
                 if ($stmt_check = mysqli_prepare($link, $sql_check_email)) {
                    mysqli_stmt_bind_param($stmt_check, "si", $email, $staff_id);
                    if (mysqli_stmt_execute($stmt_check)) {
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $email_err = "This email is already registered by another staff member.";
                        }
                    } else {
                         $edit_message .= "<p class='text-red-600'>Error checking email availability. Please try again.</p>";
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                     $edit_message .= "<p class='text-red-600'>Error preparing email check statement.</p>";
                }
            }
        }

        // Role (Required, validate against allowed roles)
        $role = trim($_POST["role"] ?? '');
         if (empty($role)) {
             $role_err = "Please select a role.";
         } elseif (!in_array($role, $allowed_roles)) {
             $role_err = "Invalid role selected.";
         }

        // Salary (Required, validate format)
        $salary_input = trim($_POST['salary'] ?? '');
        if (empty($salary_input)) {
             $salary_err = "Please enter salary.";
             $salary = $salary_input; // Keep empty string for display
        } else {
            $salary_filtered = filter_var($salary_input, FILTER_VALIDATE_FLOAT);
            if ($salary_filtered === false || $salary_filtered < 0) {
                $salary_err = "Please enter a valid positive number for salary.";
                $salary = $salary_input; // Keep original invalid input for display
            } else {
                $salary = $salary_filtered; // Use filtered float for DB
            }
        }

        // Subject Taught (Optional, but sanitize)
        $subject_taught = trim($_POST["subject_taught"] ?? '');

        // Classes Taught (Optional, but sanitize)
        $classes_taught = trim($_POST["classes_taught"] ?? '');


        // Convert empty strings for nullable fields to NULL for correct DB insertion
        $subject_taught_db = ($subject_taught === '') ? null : $subject_taught;
        $classes_taught_db = ($classes_taught === '') ? null : $classes_taught;
        // Salary is handled by $salary variable
         $salary_db = empty($salary_err) ? $salary : 0.00; // Use 0.00 if validation failed


        // Check ALL input errors before updating in database
        if (empty($staff_name_err) && empty($mobile_number_err) && empty($unique_id_err) && empty($email_err) && empty($role_err) && empty($salary_err)) {

            // Prepare an update statement for ALL editable fields (excluding password)
            $sql_update = "UPDATE staff SET staff_name=?, mobile_number=?, unique_id=?, email=?, role=?, salary=?, subject_taught=?, classes_taught=? WHERE staff_id=?";

            if ($link === false) {
                 $edit_message = "<p class='text-red-600'>Database connection error. Could not save changes.</p>";
                 error_log("Edit Staff DB connection failed: " . mysqli_connect_error());
            } elseif ($stmt_update = mysqli_prepare($link, $sql_update)) {
                 // Bind variables: ssssssdss + i (for staff_id) -> sssssssdssi. Total 9 parameters.
                mysqli_stmt_bind_param($stmt_update, "sssssdssi",
                    $staff_name,
                    $mobile_number,
                    $unique_id,
                    $email,
                    $role,
                    $salary_db,         // Use DB-ready salary
                    $subject_taught_db, // Use DB-ready variable
                    $classes_taught_db, // Use DB-ready variable
                    $staff_id // Bind the staff_id for the WHERE clause
                );

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt_update)) {
                    // Set success message in session and redirect back to manage staff page
                    $_SESSION['operation_message'] = "<p class='text-green-600'>Staff record for " . htmlspecialchars($staff_full_name) . " updated successfully.</p>";
                    header("location: manage_staff.php"); // Redirect back to manage staff page
                    exit();
                } else {
                     $edit_message = "<p class='text-red-600'>Error: Could not update staff record. " . mysqli_stmt_error($stmt_update) . "</p>";
                     // Keep submitted data for repopulation on DB error (Already done above)
                }

                // Close statement
                mysqli_stmt_close($stmt_update);
            } else {
                 $edit_message = "<p class='text-red-600'>Error: Could not prepare update statement. " . mysqli_error($link) . "</p>";
                 // Keep submitted data for repopulation on DB prepare error (Already done above)
            }
        } else {
             $edit_message = "<p class='text-yellow-600'>Please correct the errors below.</p>";
             // Keep submitted data for repopulation on validation error (Already done above in validation)
        }
        // If there were errors, ensure form fields are repopulated with submitted data
        // These variables hold the original (or partially validated) input for display:
        $staff_name = trim($_POST["staff_name"] ?? '');
        $mobile_number = trim($_POST["mobile_number"] ?? '');
        $unique_id = trim($_POST["unique_id"] ?? '');
        $email = trim($_POST["email"] ?? '');
        $role = trim($_POST["role"] ?? '');
        $salary = $_POST["salary"] ?? ''; // Keep as string for display
        $subject_taught = trim($_POST["subject_taught"] ?? '');
        $classes_taught = trim($_POST["classes_taught"] ?? '');
        // staff_full_name already retrieved at the start of POST block
    }

} else { // GET request - Display the form with existing data

    // Check if id parameter exists
    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        // Get URL parameter
        $staff_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($staff_id === false || $staff_id <= 0) {
            // URL doesn't contain valid id parameter. Redirect to manage staff with error.
            $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid staff ID provided for editing.</p>";
            header("location: manage_staff.php"); // Redirect back to manage staff page
            exit();
        } else {
            // Prepare a select statement to fetch ALL staff fields for editing (excluding password)
            $sql_fetch = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught FROM staff WHERE staff_id = ?";

            if ($link === false) {
                $edit_message = "<p class='text-red-600'>Database connection error. Could not load staff data.</p>";
                 error_log("Edit Staff fetch DB connection failed: " . mysqli_connect_error());
            } elseif ($stmt_fetch = mysqli_prepare($link, $sql_fetch)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt_fetch, "i", $staff_id);

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt_fetch)) {
                    $result_fetch = mysqli_stmt_get_result($stmt_fetch);

                    if (mysqli_num_rows($result_fetch) == 1) {
                        // Fetch result row as an associative array
                        $staff = mysqli_fetch_assoc($result_fetch);

                        // Retrieve ALL field values and populate variables
                        $staff_name = $staff["staff_name"];
                        $mobile_number = $staff["mobile_number"];
                        $unique_id = $staff["unique_id"];
                        $email = $staff["email"];
                        $role = $staff["role"];
                        $salary = $staff["salary"];
                        $subject_taught = $staff["subject_taught"];
                        $classes_taught = $staff["classes_taught"];

                         // Also set the staff name for display context
                         $staff_full_name = $staff['staff_name'];


                    } else {
                        // Record not found. Redirect.
                        $_SESSION['operation_message'] = "<p class='text-red-600'>Staff record not found.</p>";
                        header("location: manage_staff.php"); // Redirect back to manage staff page
                        exit();
                    }
                } else {
                    $edit_message = "<p class='text-red-600'>Oops! Something went wrong. Could not fetch staff data. Please try again later.</p>";
                     error_log("Edit Staff fetch query failed: " . mysqli_stmt_error($stmt_fetch));
                }

                // Close statement
                mysqli_stmt_close($stmt_fetch);
            } else {
                 $edit_message = "<p class='text-red-600'>Oops! Something went wrong. Could not prepare fetch statement. Please try again later.</p>";
                 error_log("Edit Staff prepare fetch statement failed: " . mysqli_error($link));
            }
        }
    } else {
        // URL doesn't contain id parameter. Redirect to manage staff.
        $_SESSION['operation_message'] = "<p class='text-red-600'>No staff ID provided for editing.</p>";
        header("location: manage_staff.php"); // Redirect back to manage staff page
        exit();
    }
}

// Close connection (Only if it wasn't already closed during POST redirect)
if (isset($link) && is_object($link)) {
     mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Edit Staff Data</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
        /* Optional: Style for error messages */
        .form-error {
            color: #dc3545; /* Red */
            font-size: 0.875em;
            margin-top: 0.25em;
        }
         .form-control.is-invalid {
             border-color: #dc3545; /* Red border */
         }
          .alert { padding: 0.75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }
           .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
           .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
            .alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeeba; }
            .alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }

        /* Add styles for the toggle button and handle body padding */
         .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20; /* Below sidebar, above content */
            background-color: #ffffff; /* White background */
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0.75rem 1rem; /* py-3 px-4 */
            display: flex; /* Use flexbox */
            align-items: center; /* Vertically align items */
         }
         body {
             /* Add padding-top to clear the fixed header. Adjust value if needed. */
             padding-top: 4rem; /* Roughly the height of the header */
         }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center">
    <?php
    // --- INCLUDE ADMIN SIDEBAR & OVERLAY ---
    // Assuming admin_sidebar.php is in the SAME directory as edit_staff.php.
    require_once "./admin_sidebar.php";
    ?>

    <!-- Fixed Header for Toggle Button and Page Title -->
    <div class="fixed-header w-full flex items-center">
         <!-- Open Sidebar Button (Hamburger) -->
         <!-- The JS in admin_sidebar.php looks for an element with id="admin-sidebar-toggle-open" -->
         <button id="admin-sidebar-toggle-open" class="focus:outline-none mr-4 text-gray-600 hover:text-gray-800">
             <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
             </svg>
         </button>
         <h1 class="text-xl font-bold text-gray-800">Edit Staff</h1>
          <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['display_name'] ?? 'Admin'); ?> (<?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Unknown')); ?>)</span>
    </div>


    <!-- Main content wrapper -->
     <div class="w-full max-w-lg flex flex-col items-center px-4 py-8"> <!-- Use max-w-lg for forms -->

         <!-- Edit Message Display -->
         <?php
         if (!empty($edit_message)) {
             $alert_class = 'alert-warning'; // Default
             if (strpos(strip_tags($edit_message), 'successfully') !== false) {
                 $alert_class = 'alert-success';
             } elseif (strpos(strip_tags($edit_message), 'Error') !== false || strpos(strip_tags($edit_message), 'correct the errors') !== false) {
                  $alert_class = 'alert-danger';
             }
            echo "<div class='mb-4 text-center alert " . $alert_class . "' role='alert'>" . $edit_message . "</div>";
        }
        ?>

        <?php if ($staff_id !== null || ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['staff_id']))): // Only show form if staff is loaded or post attempt occurred with a staff_id ?>

        <p class="text-center text-gray-600 mb-4">Editing record for: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($staff_full_name); ?></span> (ID: <?php echo htmlspecialchars($staff_id); ?>)</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4 w-full"> <!-- Added w-full to form -->

            <!-- Hidden field to pass staff_id and staff_full_name -->
            <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_id); ?>">
            <input type="hidden" name="staff_full_name" value="<?php echo htmlspecialchars($staff_full_name); ?>">


            <!-- Display Staff ID -->
            <div>
                <label for="display_staff_id" class="block text-sm font-medium text-gray-700">Staff ID</label>
                <!-- Display staff_id, but make it non-editable -->
                <input type="text" id="display_staff_id" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 sm:text-sm" value="<?php echo htmlspecialchars($staff_id); ?>" readonly>
            </div>

             <!-- Staff Name (Required) -->
             <div>
                <label for="staff_name" class="block text-sm font-medium text-gray-700">Staff Name <span class="text-red-500">*</span></label>
                <input type="text" name="staff_name" id="staff_name" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($staff_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($staff_name); ?>">
                <span class="form-error"><?php echo $staff_name_err; ?></span>
            </div>

            <!-- Mobile Number (Required) -->
            <div>
                <label for="mobile_number" class="block text-sm font-medium text-gray-700">Mobile Number <span class="text-red-500">*</span></label>
                <input type="text" name="mobile_number" id="mobile_number" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($mobile_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($mobile_number); ?>">
                <span class="form-error"><?php echo $mobile_number_err; ?></span>
            </div>

             <!-- Unique ID (Required, validate and check uniqueness) -->
             <div>
                <label for="unique_id" class="block text-sm font-medium text-gray-700">Unique ID (4 Digits) <span class="text-red-500">*</span></label>
                <input type="text" name="unique_id" id="unique_id" maxlength="4" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($unique_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($unique_id); ?>">
                <span class="form-error"><?php echo $unique_id_err; ?></span>
            </div>

            <!-- Email (Required, validate and check uniqueness) -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>">
                <span class="form-error"><?php echo $email_err; ?></span>
            </div>

             <!-- Password Editing (Optional - Add a button/link to change password separately) -->
             <!-- For security, do NOT pre-fill the password fields -->
             <!-- You would need a separate form/modal/page to handle password changes -->
             <!-- <div>
                 <label class="block text-sm font-medium text-gray-700">Password</label>
                 <p class="mt-1 text-sm text-gray-900">Password is not edited on this page.</p>
                 <a href="change_staff_password.php?id=<?php //echo htmlspecialchars($staff_id); ?>" class="text-blue-600 hover:underline mt-2 inline-block">Change Password</a>
             </div> -->


            <!-- Role (Required) -->
             <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                <select name="role" id="role" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>">
                    <option value="">-- Select Role --</option>
                     <?php
                        foreach ($allowed_roles as $allowed_role) {
                             echo '<option value="' . htmlspecialchars($allowed_role) . '"' . (($role === $allowed_role) ? ' selected' : '') . '>' . htmlspecialchars(ucfirst($allowed_role)) . '</option>';
                        }
                     ?>
                </select>
                <span class="form-error"><?php echo $role_err; ?></span>
            </div>


            <!-- Salary (Required) -->
            <div>
                <label for="salary" class="block text-sm font-medium text-gray-700">Salary <span class="text-red-500">*</span></label>
                <input type="number" name="salary" id="salary" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($salary_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($salary); ?>">
                 <span class="form-error"><?php echo $salary_err; ?></span>
            </div>

            <!-- Subject Taught (Optional) -->
            <div>
                <label for="subject_taught" class="block text-sm font-medium text-gray-700">Subject Taught</label>
                <input type="text" name="subject_taught" id="subject_taught" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($subject_taught ?? ''); ?>">
            </div>

             <!-- Classes Taught (Optional) -->
             <div>
                <label for="classes_taught" class="block text-sm font-medium text-gray-700">Classes Taught (e.g., "Nursery, Class 1, Class 5")</label>
                <textarea name="classes_taught" id="classes_taught" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($classes_taught ?? ''); ?></textarea>
            </div>


            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Save Changes</button>
                 <a href="manage_staff.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
            </div>
        </form>

         <?php else: ?>
            <!-- If form is not shown, display a link back -->
             <div class="mt-6 text-center">
                 <a href="manage_staff.php" class="text-blue-600 hover:underline">Back to Manage Staff</a>
             </div>
         <?php endif; ?>

    </div>

</body>
</html>