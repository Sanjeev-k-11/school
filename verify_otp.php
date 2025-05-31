<?php
session_start();

// Redirect if user is not in the OTP verification step
// Check for the required session variables set by forgot_password.php
if (!isset($_SESSION['otp_sent']) || $_SESSION['otp_sent'] !== true || !isset($_SESSION['reset_identifier']) || !isset($_SESSION['reset_role'])) {
    // Clear potentially stale reset session data just in case
    unset($_SESSION['otp_verified'], $_SESSION['reset_user_id'], $_SESSION['reset_user_role'], $_SESSION['reset_identifier'], $_SESSION['reset_role'], $_SESSION['otp_sent']);
    $_SESSION['operation_message'] = "<p class='text-red-600'>Please initiate the password reset process first.</p>";
    header("location: forgot_password.php");
    exit;
}

require_once "config.php"; // Database connection (your config.php)
require_once "mail_config.php"; // Mailer configuration (mail_config.php from above - needed for OTP_LENGTH if displayed, and EXPIRY)
require __DIR__ . '/vendor/autoload.php'; // Include Composer's autoloader


use PHPMailer\PHPMailer\PHPMailer; // Although not directly used for sending here, Exception is
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Needed for debug constants if used


$otp = "";
$otp_err = $verification_err = "";

// Identifier and Role should be in the session from forgot_password.php
$identifier = $_SESSION['reset_identifier']; // Assuming these are guaranteed by the initial check
$role = $_SESSION['reset_role']; // Assuming these are guaranteed by the initial check


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate OTP
    if (empty(trim($_POST["otp"]))) {
        $otp_err = "Please enter the OTP.";
        // Preserve entered OTP if it was set before error (optional, but helpful)
        if (isset($_POST["otp"])) {
            $otp = trim($_POST["otp"]);
        }
    } else {
        $otp = trim($_POST["otp"]);
    }

    // Initialize database statement variables
    $stmt_email = false; // Statement for looking up user email
    $stmt = false;       // Statement for verifying OTP

    if (empty($otp_err)) {
        // Step 1: Re-lookup user email based on identifier and role from session
        // We need the email because the password_resets table links to email
        $email_lookup_sql = "";
        $email_column_select = "email"; // Assuming 'email' column exists in all user tables
        $user_id_column_select = ""; // Primary key column name (id, staff_id, user_id)
        $user_table = ""; // For logging and potential future joins

         switch ($role) {
            case 'admin':
                $email_lookup_sql = "SELECT id, email FROM users WHERE username = ?";
                $user_id_column_select = "id";
                $user_table = "users";
                break;
            case 'staff':
                $email_lookup_sql = "SELECT staff_id, email FROM staff WHERE email = ?";
                $user_id_column_select = "staff_id";
                $user_table = "staff";
                break;
            case 'student':
                $email_lookup_sql = "SELECT user_id, email FROM students WHERE virtual_id = ?";
                $user_id_column_select = "user_id";
                $user_table = "students";
                break;
            default:
                // Should not happen due to initial session check, but as a safeguard
                $verification_err = "Invalid user role in session.";
                unset($_SESSION['reset_identifier'], $_SESSION['reset_role'], $_SESSION['otp_sent']); // Force start over
                break;
         }

        $user_email = null;
        $user_db_id = null; // User's actual ID from their table

        // Proceed with email lookup only if role was valid and DB link is valid
        if (empty($verification_err) && !empty($email_lookup_sql) && $link !== false) {
            if ($stmt_email = mysqli_prepare($link, $email_lookup_sql)) {
                mysqli_stmt_bind_param($stmt_email, "s", $identifier); // Use identifier from session

                if (mysqli_stmt_execute($stmt_email)) {
                    $result_email = mysqli_stmt_get_result($stmt_email);
                     if ($result_email === false) {
                         $verification_err = "Oops! Could not retrieve user data for email lookup.";
                         error_log("Verify OTP email lookup get_result failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $user_table. Error: " . mysqli_stmt_error($stmt_email));
                     } elseif (mysqli_num_rows($result_email) == 1) {
                        $user_data = mysqli_fetch_assoc($result_email);
                        $user_email = $user_data[$email_column_select] ?? null;
                        $user_db_id = $user_data[$user_id_column_select] ?? null;
                         mysqli_free_result($result_email); // Free result set after fetching
                     } else {
                          // User not found based on session identifier/role - inconsistent state
                          $verification_err = "User account not found for verification.";
                          error_log("Verify OTP email lookup returned " . mysqli_num_rows($result_email) . " rows for role: $role, identifier: " . htmlspecialchars($identifier) . ". Possible session inconsistency or DB issue.");
                           if ($result_email !== false) { // Free result even if 0 or > 1 rows
                               mysqli_free_result($result_email);
                           }
                          // Note: We don't clear session here immediately, as we still want to display the error.
                     }
                } else {
                    $verification_err = "Oops! Something went wrong with user email lookup execution.";
                    error_log("Verify OTP email lookup execute failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $user_table. Error: " . mysqli_stmt_error($stmt_email));
                }
            } else {
                $verification_err = "Oops! Could not prepare user email lookup.";
                 error_log("Verify OTP email lookup prepare failed for role: $role, table: $user_table. Error: " . mysqli_error($link));
            }
        }

        // --- Close the $stmt_email statement here ONLY if it was successfully prepared ---
        if ($stmt_email !== false && is_object($stmt_email)) { // Ensure it's a valid object before closing
            mysqli_stmt_close($stmt_email);
            $stmt_email = false; // Set to false after closing
        }
        // --- End $stmt_email closing ---


        // Step 2: Verify the OTP in the password_resets table
        // Proceed only if no previous errors, user email and ID were found, and DB link is valid
        if (empty($verification_err) && !empty($user_email) && !empty($user_db_id) && $link !== false) {
             // Query the password_resets table for a matching, unused, unexpired OTP for this email
             // Note: We query by email and otp, and also check is_used and expiry
             // Order by created_at DESC and limit 1 to get the most recent valid OTP if multiples exist (though delete should prevent this)
             $sql = "SELECT pr.id, pr.user_id, pr.user_role, pr.expires_at FROM password_resets pr WHERE pr.email = ? AND pr.otp = ? AND pr.is_used = 0 AND pr.expires_at > NOW() ORDER BY pr.created_at DESC LIMIT 1";

             // Prepare statement for OTP verification
             if ($stmt = mysqli_prepare($link, $sql)) {
                 // Bind email (retrieved from lookup) and submitted OTP
                 mysqli_stmt_bind_param($stmt, "ss", $user_email, $otp);

                 // Attempt to execute OTP verification
                 if (mysqli_stmt_execute($stmt)) {
                     $result = mysqli_stmt_get_result($stmt);

                     // Check if getting the result was successful
                     if ($result === false) {
                          $verification_err = "Oops! Could not retrieve verification data.";
                          error_log("Verify OTP get_result failed for email: " . htmlspecialchars($user_email) . ". Error: " . mysqli_stmt_error($stmt));
                     } elseif (mysqli_num_rows($result) == 1) {
                         // Valid, unused, unexpired OTP found
                         $reset_request = mysqli_fetch_assoc($result);
                         $reset_id = $reset_request['id']; // The ID from the password_resets table

                         mysqli_free_result($result); // Free result set after fetching
                         // $stmt remains open until explicitly closed later


                         // Step 3: Mark the OTP as used to prevent reuse
                         // Ensure connection $link is still valid here
                         if ($link !== false) {
                             $update_sql = "UPDATE password_resets SET is_used = 1 WHERE id = ?";
                             // Initialize $update_stmt
                             $update_stmt = false;
                             if ($update_stmt = mysqli_prepare($link, $update_sql)) {
                                 mysqli_stmt_bind_param($update_stmt, "i", $reset_id);

                                 // Attempt to execute the update statement
                                 if (mysqli_stmt_execute($update_stmt)) {
                                     // OTP verified and marked used successfully
                                     mysqli_stmt_close($update_stmt); // Close the update statement

                                     // Set session variables to allow password reset on the next page
                                     $_SESSION['otp_verified'] = true; // Flag for reset_password.php
                                     $_SESSION['reset_user_id'] = $user_db_id; // User's actual ID (important for the update query)
                                     $_SESSION['reset_user_role'] = $role; // User's role (important for the update query)

                                     // Clear previous session info no longer needed for reset_password
                                     unset($_SESSION['reset_identifier'], $_SESSION['otp_sent']);

                                     // Redirect to the password reset page
                                     $_SESSION['operation_message'] = "<p class='text-green-600'>OTP verified. You can now reset your password.</p>";
                                     header("location: reset_password.php");
                                     exit;

                                 } else {
                                      // Database update error
                                      $verification_err = "Failed to finalize OTP verification. Please try again.";
                                       error_log("Verify OTP Update Failed for reset_id: $reset_id. Error: " . mysqli_stmt_error($update_stmt));
                                       if ($update_stmt !== false) mysqli_stmt_close($update_stmt); // Close update statement on failure
                                 }
                             } else {
                                  // Database prepare error for update
                                  $verification_err = "Oops! Could not prepare OTP finalization.";
                                  error_log("Verify OTP Prepare Update Failed. Error: " . mysqli_error($link));
                             }
                         } else {
                              // Database connection was somehow lost after finding the OTP
                              $verification_err = "Database connection failed during OTP finalization.";
                              error_log("Database connection was false before OTP update.");
                         }


                     } else { // mysqli_num_rows is 0 or > 1 (should be 0 for invalid/expired/used)
                         // Invalid, expired, or used OTP - This is the case that generated the error image
                         $verification_err = "Invalid or expired OTP.";
                          if ($result !== false && mysqli_num_rows($result) > 1) {
                                 error_log("Verify OTP: Multiple valid OTPs found for email: " . htmlspecialchars($user_email) . ". Data issue.");
                          }
                         // Free result set even if 0 or >1 rows were returned
                         if ($result !== false) {
                             mysqli_free_result($result);
                         }
                         // The script continues to display the form and error message.
                     } // else: mysqli_num_rows for OTP

                 } else {
                     // Database execute error for OTP query
                     $verification_err = "Oops! Something went wrong with OTP query execution.";
                     error_log("Verify OTP DB Execute Failed for email: " . htmlspecialchars($user_email) . ". Error: " . mysqli_stmt_error($stmt));
                 } // else: mysqli_stmt_execute(stmt)

             } else {
                 // Database prepare error for OTP query
                 $verification_err = "Oops! Could not prepare OTP verification.";
                  error_log("Verify OTP DB Prepare Failed. Error: " . mysqli_error($link));
             }

             // --- Close the $stmt statement here ONLY if it was successfully prepared ---
             if ($stmt !== false && is_object($stmt)) { // Ensure $stmt is a valid object before closing
                 mysqli_stmt_close($stmt);
                 $stmt = false; // Set to false after closing
             }
             // --- End $stmt closing ---

        } // else: verification_err was not empty, or user email/id not found, or DB link false


    } // else: $otp_err was not empty (OTP input was empty)

    // If we reach here and verification failed, display the form and error.
    // No need to explicitly redirect unless the session is invalid (handled at the start).


    // Close database connection if it was opened successfully
    // Check for $link being a valid object type before closing
    // Removed the deprecated mysqli_ping check
    if ($link !== false && is_object($link)) {
         mysqli_close($link);
    }
}

// Display session message if any (e.g., the success message from forgot_password.php, or an error from reset_password.php redirecting back)
if (isset($_SESSION['operation_message'])) {
     // If $verification_err was set during POST, don't overwrite it
     if (empty($verification_err)) {
         $verification_err = $_SESSION['operation_message'];
     }
     unset($_SESSION['operation_message']); // Clear it after displaying
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - School Management System</title>
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
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 1rem;
            color: #2d3748;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
         input[type="text"]:focus,
         input[type="password"]:focus,
         select:focus {
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
          .alert-success {
             color: #2f855a;
             background-color: #c6f6d5;
             border: 1px solid #68d391;
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
        <h2 class="text-2xl font-bold">Verify OTP</h2>
        <p class="text-gray-600 mb-6">Enter the One-Time Password sent to your email address.</p>

        <?php
        // Display verification errors from POST attempt or session messages
        if (!empty($verification_err)) {
            echo '<div class="alert-danger">' . $verification_err . '</div>';
        }
         // Success message from previous step (forgot_password) would have been moved to $verification_err above
         // if it existed. So, only display $verification_err here.
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

            <div class="form-group">
                <label for="otp">Enter OTP:</label>
                <input type="text" name="otp" id="otp" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($otp_err)) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($otp); ?>" required maxlength="<?php echo OTP_LENGTH; ?>">
                <?php if (!empty($otp_err)): ?><span class="text-danger"><?php echo $otp_err; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary">Verify OTP</button>
            </div>

            <p class="text-gray-600 text-sm mt-4">Didn't receive OTP? <a href="forgot_password.php" class="text-indigo-600 hover:underline">Resend or try again</a>.</p>

        </form>
    </div>
</body>
</html>