<?php
// Start the session
session_start();

// Check if the user is already logged in, if yes then redirect to respective dashboard
// This prevents logged-in users from seeing the login form
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // IMPORTANT: Redirect based on the role stored in the SESSION
    switch($_SESSION['role']) {
        case 'admin':
            header("location: admin/admin_dashboard.php");
            break;
        // Redirect 'teacher', 'principal', and generic 'staff' roles to the staff dashboard
        case 'staff':
        case 'teacher':
        case 'principal':
            header("location: teacher/staff_dashboard.php"); // Assuming staff dashboard is in root
            break;
        case 'student':
            header("location: student_dashboard.php"); // Assuming student dashboard is in root
            break;
        default:
             // If role is somehow invalid, maybe just log them out or redirect to a generic page
             session_unset();
             session_destroy();
             $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid session role. Please log in again.</p>";
             header("location: login.php"); // Redirect back to login
             break;
    }
    exit;
}

// Include config file - Make sure config.php is in the same directory as login.php
require_once "config.php";

// Define variables and initialize with empty values
$identifier = $password = $role = ""; // $role is from the form input
$identifier_err = $password_err = $login_err = $role_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate role - Check if 'role' key exists AND is not empty after trim
    if(!isset($_POST["role"]) || empty(trim($_POST["role"]))){
        $role_err = "Please select a role.";
        // Preserve selected role if it was set before the error
        if (isset($_POST["role"])) {
            $role = trim($_POST["role"]);
        }
    } else {
        $role = trim($_POST["role"]); // $role holds the value from the form dropdown
    }

    // Validate identifier - Check if 'identifier' key exists AND is not empty after trim
    if(!isset($_POST["identifier"]) || empty(trim($_POST["identifier"]))){
        $identifier_err = "Please enter your identifier.";
        // Preserve entered identifier if it was set before the error
        if (isset($_POST["identifier"])) {
            $identifier = trim($_POST["identifier"]);
        }
    } else {
        $identifier = trim($_POST["identifier"]);
    }

    // Validate password - Check if 'password' key exists AND is not empty after trim
    if(!isset($_POST["password"]) || empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
        // We don't preserve password for security
    } else {
        $password = trim($_POST["password"]);
    }

    // If no errors in basic input validation, proceed with login attempt
    if(empty($identifier_err) && empty($password_err) && empty($role_err)){
        $sql = ""; // SQL query will depend on the role
        $id_column_where = ""; // Column name used in the WHERE clause (username, email, virtual_id)
        $user_id_column_select = ""; // Column name for the primary ID fetched (id, staff_id, user_id)
        $name_column_select = ""; // Column name for the display name fetched
        $db_role_column_select = ""; // Column name for the role fetched (will be 'role' for staff)
        $table_name = ""; // Store table name for logging
        $stmt = false; // Initialize statement variable

        // Determine the SQL query, ID column, and name/role columns based on the FORM role
        switch($role){ // Use the form role ($role) to decide WHICH table/query to use
            case 'admin':
                // Based on your schema assumption for 'users' table (only admins)
                $sql = "SELECT id, username, password FROM users WHERE username = ?";
                $id_column_where = "username";
                $user_id_column_select = "id"; // Primary key column in 'users' table
                $name_column_select = "username"; // Using username as display name for admin
                // Admins don't have a 'role' column in this specific table based on the SQL you had
                // We'll hardcode their role later if needed, or add 'role' to the 'users' table
                $db_role_column_select = null; // No role column in DB for admin based on this SELECT
                $table_name = "users";
                break;
            case 'staff':
                // --- CORRECTED: Include the 'role' column in the SELECT statement ---
                $sql = "SELECT staff_id, email, password, staff_name, role FROM staff WHERE email = ?";
                $id_column_where = "email";
                $user_id_column_select = "staff_id";
                $name_column_select = "staff_name";
                $db_role_column_select = "role"; // The name of the role column in the staff table
                $table_name = "staff";
                break;
            case 'student':
                 // Based on your schema assumption for 'students' table
                $sql = "SELECT user_id, virtual_id, password, full_name FROM students WHERE virtual_id = ?";
                $id_column_where = "virtual_id";
                $user_id_column_select = "user_id";
                $name_column_select = "full_name";
                // Students don't have a 'role' column in this specific table based on the SQL you had
                $db_role_column_select = null; // No role column in DB for student based on this SELECT
                $table_name = "students";
                break;
            default:
                // This case should ideally not be hit if role validation works
                $login_err = "Invalid role selected during processing.";
                break;
        }

        // Proceed only if a valid role and SQL query were determined and DB link is valid
        if (!empty($sql) && $link !== false) {

            // Prepare statement
            if($stmt = mysqli_prepare($link, $sql)){
                // Bind variables to the prepared statement as parameters
                // Always bind the identifier (username, email, or virtual_id) as a string (s)
                mysqli_stmt_bind_param($stmt, "s", $param_identifier);

                // Set parameters
                $param_identifier = $identifier;

                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)){
                    // Attempt to get the result set
                    $result = mysqli_stmt_get_result($stmt);

                    // *** CHECK if getting the result was successful ***
                    if ($result === false) {
                        // Handle the error if getting the result failed
                        $login_err = "Oops! Could not retrieve results. Please try again later.";
                         error_log("Login get_result failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $table_name. Error: " . mysqli_stmt_error($stmt));

                    } elseif (mysqli_num_rows($result) == 1) { // We expect exactly one row
                        // Check if identifier exists and is unique

                        // Fetch the user row as an associative array
                        if ($user = mysqli_fetch_assoc($result)) {

                            // Fetch password from the 'password' column (assuming this column name exists in all tables)
                            $hashed_password = $user['password'];

                            // Verify the password
                            if(password_verify($password, $hashed_password)){
                                // Password is correct

                                // --- Determine the ACTUAL role based on the database row and table queried ---
                                $actual_role = '';
                                switch($role) { // Use the form role to know WHICH table's row is in $user
                                    case 'admin':
                                        // Assume admin role if logged in via the admin path
                                        $actual_role = 'admin';
                                        // You might want to check if the user has an 'is_admin' flag in the 'users' table if it exists
                                        break;
                                    case 'staff':
                                        // --- CORRECTED: Get the actual role from the 'role' column fetched from the staff table ---
                                        if (isset($user[$db_role_column_select])) { // Check if the column was selected
                                            $actual_role = $user[$db_role_column_select]; // Use the value from the DB!
                                        } else {
                                            // Fallback or Error: 'role' column wasn't selected for staff.
                                            // This should ideally not happen if SQL was correct. Log and error.
                                            error_log("Critical Error: Staff 'role' column missing from fetched data for ID: " . ($user[$user_id_column_select] ?? 'N/A') . ". SQL error likely.");
                                            $login_err = "An internal error occurred during login. Please try again.";
                                            // Skip session setting and redirect, fall through to error display
                                            $actual_role = ''; // Ensure actual_role is empty to skip session/redirect
                                        }
                                        break;
                                    case 'student':
                                         // Assume student role if logged in via the student path
                                         $actual_role = 'student';
                                        break;
                                    // default case handled earlier
                                }

                                // Only set session and redirect if we successfully determined an actual role
                                if (!empty($actual_role)) {
                                    // Store data in session variables
                                    $_SESSION["loggedin"] = true;
                                    // Use the correct ID column name ($user_id_column_select) determined earlier
                                    $_SESSION["id"] = $user[$user_id_column_select];
                                    // --- Store the ACTUAL role determined above ---
                                    $_SESSION["role"] = $actual_role;
                                    // Use the correct name column name ($name_column_select) determined earlier
                                    $_SESSION["name"] = $user[$name_column_select];

                                    // Store a welcome message in session for the dashboard
                                    $_SESSION['operation_message'] = "<p class='text-green-600'>Welcome, " . htmlspecialchars($user[$name_column_select]) . "!</p>";

                                    // Redirect user to appropriate dashboard page based on the ACTUAL role
                                    switch($actual_role) {
                                        case 'admin':
                                            header("location: admin/admin_dashboard.php");
                                            break;
                                        // Redirect all specific staff roles ('teacher', 'principal', 'staff') to the staff dashboard
                                        case 'staff':
                                        case 'teacher':
                                        case 'principal':
                                            header("location: teacher/staff_dashboard.php"); // Assuming staff dashboard is in root
                                            break;
                                        case 'student':
                                            header("location: student_dashboard.php"); // Assuming student dashboard is in root
                                            break;
                                        default:
                                             // Handle unexpected roles after successful login - should not happen with valid actual_role
                                             session_unset();
                                             session_destroy();
                                             $_SESSION['operation_message'] = "<p class='text-red-600'>Login failed: Unknown user role after authentication.</p>"; // More specific error
                                             header("location: login.php");
                                             break;
                                    }
                                    exit; // Always exit after a header redirect
                                }
                                // else: actual_role was empty, meaning an internal error occurred, $login_err is set, fall through to display error


                            } else{
                                // Password is not valid, display a generic error message
                                $login_err = "Invalid identifier or password.";
                            }
                        } else {
                             // This should ideally not happen if num_rows was 1 and get_result was successful
                             $login_err = "An unexpected error occurred during data retrieval. Please try again.";
                             error_log("Login fetch assoc failed after get_result/num_rows=1 for role: $role, identifier: " . htmlspecialchars($identifier));
                        }
                        // Free result set if it was successfully retrieved
                        if ($result !== false) { // Check if $result is valid before freeing
                            mysqli_free_result($result);
                        }


                    } else { // mysqli_num_rows is not 1 (0 or > 1)
                        // Identifier doesn't exist (num_rows is 0) OR there's a duplicate (num_rows > 1)
                        $login_err = "Invalid identifier or password.";
                         if ($result !== false && mysqli_num_rows($result) > 1) {
                             error_log("Duplicate identifier found for role: $role, identifier: " . htmlspecialchars($identifier) . " in table: $table_name");
                         }
                         // Free result set even if 0 or >1 rows were returned
                         if ($result !== false) {
                             mysqli_free_result($result);
                         }
                    }

                } else{
                    // Handle the error if executing the statement failed
                    $login_err = "Oops! Something went wrong with query execution. Please try again later.";
                     error_log("Login execute query failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $table_name. Error: " . mysqli_stmt_error($stmt));
                }

                // Close statement now if $stmt was successfully prepared
                if ($stmt !== false) {
                     mysqli_stmt_close($stmt);
                     $stmt = false; // Reset $stmt after closing
                }


            } else {
                 // Handle the error if preparing the statement failed
                 $login_err = "Oops! Could not prepare statement. Please try again later.";
                  error_log("Login prepare statement failed for role: $role, table: $table_name. Error: " . mysqli_error($link));
            }
        } else if (empty($sql)) {
             // This specific error should be caught by the role validation above, but good for fallback
             $login_err = "Invalid role selected during processing.";
        } else if ($link === false) {
             // Database connection already failed - this check might be redundant if config.php errors on connection failure
             $login_err = "Database connection failed during processing.";
              error_log("Database connection was false before login query prepare.");
        }
    }

    // Close connection if it was opened successfully and is still active
    // The mysqli_ping check confirms the connection is usable before trying to close
    if ($link !== false && is_object($link) && mysqli_ping($link)) {
         mysqli_close($link);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom background styles */
        body {
            /* Dark background gradient similar to the image */
            background: linear-gradient(135deg, #28004d 0%, #1a0033 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: sans-serif;
            padding: 20px; /* Add some padding for small screens */
        }

        /* Main container for the split layout */
        .login-split-container {
            display: flex;
            /* Stack columns on small screens, row on medium+ */
            flex-direction: column;
            @media (min-width: 768px) { /* md breakpoint */
                 flex-direction: row;
                 height: 600px; /* Fixed height for md+ screens */
            }

            max-width: 900px; /* Max width for the entire container */
            width: 100%;
            background-color: rgba(255, 255, 255, 0.1); /* Slightly translucent background */
            border-radius: 1.5rem; /* More rounded corners */
            /* Significant shadow for the "3D" lifted effect */
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(161, 140, 209, 0.3); /* Darker shadow with a subtle purple glow */
            overflow: hidden; /* Hide overflow from rounded corners */
        }

        /* Left section: Login Form */
        .login-form-section {
            background-color: #ffffff; /* White background for the form side */
            padding: 2.5rem; /* More padding */
            width: 100%;
             /* Take half width on medium+ screens */
            @media (min-width: 768px) { /* md breakpoint */
                 width: 50%;
            }
            display: flex;
            flex-direction: column;
            justify-content: center; /* Vertically center form content */
        }

        /* Right section: Welcome Message and Art */
        .login-welcome-section {
            background: linear-gradient(to right, #4facfe, #a18cd1, #fbc2eb); /* Gradient background */
             /* Add a placeholder for the abstract effect */
             /* You might replace this with a background image or more complex CSS */
             position: relative; /* Needed for potential pseudo-elements or background images */
            padding: 2.5rem;
            width: 100%;
            color: white; /* White text color */
             /* Take half width on medium+ screens */
            @media (min-width: 768px) { /* md breakpoint */
                 width: 50%;
            }
            display: flex;
            flex-direction: column;
            justify-content: center; /* Vertically center content */
            align-items: center; /* Horizontally center content */
            text-align: center;
        }

        /* Style for the large Welcome text */
        .welcome-text {
            font-size: 3rem; /* text-4xl or larger */
            font-weight: 700; /* font-bold */
            margin-bottom: 1rem;
        }

         /* Style for the smaller text below Welcome */
         .welcome-subtext {
             font-size: 1rem; /* text-base */
             opacity: 0.8; /* Slightly transparent */
             max-width: 300px;
             line-height: 1.5;
         }


        .form-group {
            margin-bottom: 1rem; /* mb-4 */
            text-align: left;
        }
        label {
            display: block;
            color: #4a5568; /* gray-700 */
            font-size: 0.875rem; /* text-sm */
            font-weight: 600; /* font-semibold */
            margin-bottom: 0.5rem; /* mb-2 */
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem 1rem; /* py-3 px-4 */
            border: 1px solid #e2e8f0; /* border-gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            font-size: 1rem; /* text-base */
            color: #2d3748; /* gray-800 */
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.075); /* shadow-sm */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
         input[type="text"]:focus,
         input[type="password"]:focus,
         select:focus {
             outline: none;
             border-color: #667eea; /* indigo-500 */
             box-shadow: 0 0 0 3px rgba(102, 119, 234, 0.25); /* indigo-500 with opacity */
         }

         /* Button style matching image (pink/purple) */
        .btn-primary {
            width: 100%;
            padding: 0.75rem; /* py-3 */
            /* Gradient background from image */
            background: linear-gradient(to right, #ff416c, #ff4b2b); /* Example Pink/Orange */
            /* Or a solid vibrant color: */
            /* background-color: #e91e63; */ /* Material Pink */
            /* background-color: #9c27b0; */ /* Material Purple */
            background-size: 200% auto; /* For hover effect */
            color: white;
            font-weight: 700; /* font-bold */
            border-radius: 0.375rem; /* rounded-md */
            transition: background-position 0.3s ease; /* Smooth transition for gradient */
            cursor: pointer;
            border: none;
            margin-top: 1rem; /* Add some space above the button */
        }
        .btn-primary:hover {
            background-position: right center; /* Shift gradient on hover */
            /* background-color: #d81b60; */ /* Darker pink */
        }
         .alert-danger {
             color: #c53030; /* red-700 */
             background-color: #fed7d7; /* red-200 */
             border: 1px solid #fc8181; /* red-400 */
             padding: 0.75rem; /* p-3 */
             border-radius: 0.375rem; /* rounded-md */
             margin-bottom: 1.5rem; /* mb-6 */
             font-size: 0.875rem; /* text-sm */
             text-align: left;
         }
          .text-center h2 {
             margin-bottom: 1.5rem; /* mb-6 */
             color: #2d3748; /* gray-800 */
             text-align: center; /* Ensure title is centered */
         }
          .text-danger {
               color: #c53030; /* red-700 */
               font-size: 0.75rem; /* text-xs */
               margin-top: 0.25rem; /* mt-1 */
               display: block; /* Ensures it's on its own line below input */
               text-align: left;
          }

           /* Hide welcome section on small screens */
          @media (max-width: 767px) { /* Below md breakpoint */
              .login-welcome-section {
                  display: none;
              }
          }
    </style>
</head>
<body>
    <!-- Main container for the split layout -->
    <div class="login-split-container">
        <!-- Left Section: Login Form -->
        <div class="login-form-section">
            <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>
            <p class="text-gray-600 mb-6 text-center">Please select your role and enter your credentials.</p>

            <?php
            // Display login error messages
            if(!empty($login_err)){
                echo '<div class="alert-danger">' . $login_err . '</div>';
            }
            // Display messages from session (like access denied from dashboard)
            if (isset($_SESSION['operation_message'])) {
                 // Add alert-danger class for session messages for consistent styling
                 echo '<div class="alert-danger">' . $_SESSION['operation_message'] . '</div>';
                 unset($_SESSION['operation_message']); // Clear it after displaying
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

                <div class="form-group">
                    <label for="role">Login As:</label>
                    <select name="role" id="role" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($role_err)) ? 'border-red-500' : ''; ?>" required>
                        <option value="">-- Select Role --</option>
                        <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="staff" <?php echo ($role == 'staff') ? 'selected' : ''; ?>>Staff (Teacher/Principal)</option>
                        <option value="student" <?php echo ($role == 'student') ? 'selected' : ''; ?>>Student</option>
                    </select>
                     <?php if(!empty($role_err)): ?><span class="text-danger"><?php echo $role_err; ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="identifier">Identifier:</label>
                    <input type="text" name="identifier" id="identifier" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($identifier_err)) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($identifier); ?>" placeholder="Username (Admin), Email (Staff), or Virtual ID (Student)" required>
                    <?php if(!empty($identifier_err)): ?><span class="text-danger"><?php echo $identifier_err; ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>" required>
                     <?php if(!empty($password_err)): ?><span class="text-danger"><?php echo $password_err; ?></span><?php endif; ?>
                </div>

                <p class="text-gray-600 text-sm mt-4 text-center"><a href="forgot_password.php" class="text-indigo-600 hover:underline">Forgot Password?</a></p>

                <div class="form-group">
                    <button type="submit" class="btn-primary">Login</button>
                </div>

                <!-- Optional: Add links for registration or forgot password -->
                <!-- <p class="text-gray-600 text-sm mt-4">Don't have an account? <a href="register.php" class="text-indigo-600 hover:underline">Sign up now</a>.</p> -->

            </form>
        </div>

        <!-- Right Section: Welcome Message and Art -->
        <div class="login-welcome-section">
            <div class="welcome-text">Welcome.</div>
            <div class="welcome-subtext">
                 <!-- You can add a motivational or school-specific message here -->
                 Log in to access the School Management System. Manage students, staff, classes, and more.
            </div>
             <!-- Placeholder for "Not a member? Sign up now" if applicable -->
             <!-- <p class="text-sm mt-8 opacity-80">Not a member? <a href="register.php" class="font-semibold underline">Sign up now</a></p> -->
        </div>
    </div>
</body>
</html>