<?php
session_start();

// Redirect if user is not authorized to reset password (OTP not verified)
// Check for the required session variables set by verify_otp.php
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_user_role'])) {
    // Clear potentially stale reset session data
    unset($_SESSION['otp_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_user_role'], $_SESSION['reset_identifier'], $_SESSION['reset_role'], $_SESSION['otp_sent']);
    $_SESSION['operation_message'] = "<p class='text-red-600'>You are not authorized to reset your password. Please start over.</p>";
    header("location: forgot_password.php");
    exit;
}

require_once "config.php"; // Database connection (your config.php)
// mail_config.php is not strictly needed here, but can be included if you wanted to email a success notification

// Include Composer's autoloader - required for password_hash (though it's built-in since PHP 5.5,
// including autoloader is good practice if other Composer libs are used)
// ** MAKE SURE this path is correct based on your project structure **
require __DIR__ . '/vendor/autoload.php';


// PHPMailer use statements are not strictly needed in this file unless you send emails here,
// but including Exception can be good practice if catching DB errors etc.
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
// use PHPMailer\PHPMailer\SMTP;


$new_password = $confirm_password = "";
$new_password_err = $confirm_password_err = $reset_err = "";

// Get user ID and role from the session (these are set by verify_otp.php)
$user_id = $_SESSION['reset_user_id'];
$user_role = $_SESSION['reset_user_role'];

// Initialize statement variable
$stmt = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate new password
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter a new password.";
    } elseif (strlen(trim($_POST["new_password"])) < 6) { // Example password length requirement
        $new_password_err = "Password must have at least 6 characters."; // Adjust minimum length as needed
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the new password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        // Only compare if the new password field is not empty
        if (empty($new_password_err) && ($new_password != $confirm_password)) {
            $confirm_password_err = "Passwords do not match.";
        }
    }

    // Process if no validation errors
    if (empty($new_password_err) && empty($confirm_password_err)) {
        // Password is valid, proceed to update in the database
        $sql = "";
        $user_id_column_where = ""; // Primary key column name for WHERE clause

        // Determine the SQL update query based on the user role from the session
        switch ($user_role) {
            case 'admin':
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $user_id_column_where = "id"; // Primary key column in 'users'
                break;
            case 'staff':
                $sql = "UPDATE staff SET password = ? WHERE staff_id = ?";
                $user_id_column_where = "staff_id"; // Primary key column in 'staff'
                break;
            case 'student':
                $sql = "UPDATE students SET password = ? WHERE user_id = ?";
                $user_id_column_where = "user_id"; // Primary key column in 'students'
                break;
             default:
                // Should not happen due to initial session check, but as a safeguard
                $reset_err = "Invalid user role in session.";
                // Force session clear and redirect if role is unexpected
                unset($_SESSION['otp_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_user_role']);
                $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid session role. Please start over.</p>";
                header("location: forgot_password.php");
                exit;
        }

        // Proceed only if a valid SQL query was determined and DB link is valid
        if (!empty($sql) && $link !== false) {
            // Prepare the update statement
            if ($stmt = mysqli_prepare($link, $sql)) {
                // Hash the new password before storing in the database
                // PASSWORD_DEFAULT uses the strongest hashing algorithm available (currently bcrypt)
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                // Bind the hashed password (string) and the user ID (integer)
                mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

                // Attempt to execute the update
                if (mysqli_stmt_execute($stmt)) {
                    // Password updated successfully

                    // Clear all password reset-related session variables
                    unset($_SESSION['otp_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_user_role']);
                    // Also clear previous identifier/role/otp_sent if they weren't already
                     unset($_SESSION['reset_identifier'], $_SESSION['reset_role'], $_SESSION['otp_sent']);


                    // Set a success message for the login page
                    $_SESSION['operation_message'] = "<p class='text-green-600'>Your password has been reset successfully. Please log in with your new password.</p>";
                    // Redirect user to the login page
                    header("location: login.php");
                    exit;

                } else {
                    // Database update error
                    $reset_err = "Oops! Could not update your password. Please try again later.";
                     error_log("Password Reset DB Update Failed for role: $user_role, user_id: $user_id. Error: " . mysqli_stmt_error($stmt));
                }

                 // --- Close the statement here ONLY if it was successfully prepared ---
                 if ($stmt !== false && is_object($stmt)) { // Ensure $stmt is a valid object before closing
                     mysqli_stmt_close($stmt);
                     $stmt = false; // Set to false after closing
                 }
                 // --- End $stmt closing ---

            } else {
                 // Database prepare error for update statement
                 $reset_err = "Oops! Could not prepare password update.";
                 error_log("Password Reset DB Prepare Failed for role: $user_role. Error: " . mysqli_error($link));
            }
        } else if (empty($sql)) {
             // This case should ideally be caught by the default role check above
             $reset_err = "Invalid user role during processing.";
        } else if ($link === false) {
             // Database connection already failed
             $reset_err = "Database connection failed before processing.";
             error_log("Database connection was false before query prepare.");
        }


    } // else: new_password_err or confirm_password_err was not empty

    // If we reach here and reset failed, display the form and error.


     // Close database connection if it was opened successfully
     // Check for $link being a valid object type before closing
     // Removed the deprecated mysqli_ping check
    if ($link !== false && is_object($link)) {
         mysqli_close($link);
    }
}

// Display session message if any (e.g., the success message from verify_otp.php)
if (isset($_SESSION['operation_message'])) {
     // If $reset_err was set during POST, don't overwrite it
     if (empty($reset_err)) {
        $reset_err = $_SESSION['operation_message'];
     }
     unset($_SESSION['operation_message']); // Clear it after displaying
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Reusing styles from login.php */
        body {
            background: linear-gradient(to right, #4facfe, #00f2fe);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: sans-serif;
        }
        .container {
            max-width: 400px;
            width: 95%;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        .form-group {
            margin-bottom: 1rem;
            text-align: left;
        }
        label {
            display: block;
            color: #4a5568;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 1rem;
            color: #2d3748;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
         input[type="password"]:focus {
             outline: none;
             border-color: #667eea;
             box-shadow: 0 0 0 3px rgba(102, 119, 234, 0.25);
         }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: #4f46e5;
            color: white;
            font-weight: 700;
            border-radius: 0.375rem;
            transition: background-color 0.15s ease-in-out;
            cursor: pointer;
            border: none;
        }
        .btn-primary:hover {
            background-color: #4338ca;
        }
         .alert-danger {
             color: #c53030;
             background-color: #fed7d7;
             border: 1px solid #fc8181;
             padding: 0.75rem;
             border-radius: 0.375rem;
             margin-bottom: 1.5rem;
             font-size: 0.875rem;
             text-align: left;
         }
          .text-center h2 {
             margin-bottom: 1.5rem;
             color: #2d3748;
         }
          .text-danger {
               color: #c53030;
               font-size: 0.75rem;
               margin-top: 0.25rem;
               display: block;
               text-align: left;
          }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-2xl font-bold">Reset Password</h2>
        <p class="text-gray-600 mb-6">Enter and confirm your new password.</p>

        <?php
        // Display reset errors from POST attempt or session messages
        if (!empty($reset_err)) {
            echo '<div class="alert-danger">' . $reset_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" name="new_password" id="new_password" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($new_password_err)) ? 'border-red-500' : ''; ?>" required>
                <?php if (!empty($new_password_err)): ?><span class="text-danger"><?php echo $new_password_err; ?></span><?php endif; ?>
            </div>

             <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>" required>
                <?php if (!empty($confirm_password_err)): ?><span class="text-danger"><?php echo $confirm_password_err; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary">Reset Password</button>
            </div>

        </form>
    </div>
</body>
</html>