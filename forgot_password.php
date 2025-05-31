<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Assuming roles and dashboards are handled elsewhere, redirect to a default page
    // Adjust this path if your index.php is not in the root SCHOOL directory
    header("location: index.php");
    exit;
}

require_once "config.php"; // Database connection (your config.php)
require_once "mail_config.php"; // Mailer configuration (mail_config.php from above)

// Include Composer's autoloader. This file knows where to find PHPMailer classes.
// The path is relative from this script (forgot_password.php, assumed to be in the SCHOOL root)
// to the 'vendor' directory, and then the 'autoload.php' file inside it.
// ** MAKE SURE this path is correct based on your project structure **
require __DIR__ . '/vendor/autoload.php';


// These 'use' statements are needed after the autoloader is included
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


$identifier = $role = "";
$identifier_err = $role_err = $forgot_password_err = $forgot_password_success = "";

// Initialize $stmt to false before use
$stmt = false;


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate role
    if (empty(trim($_POST["role"]))) {
        $role_err = "Please select your role.";
        // Preserve selected role if it was set before the error
        if (isset($_POST["role"])) {
            $role = trim($_POST["role"]);
        }
    } else {
        $role = trim($_POST["role"]);
    }

    // Validate identifier
    if (empty(trim($_POST["identifier"]))) {
        $identifier_err = "Please enter your identifier.";
         // Preserve entered identifier if it was set before the error
        if (isset($_POST["identifier"])) {
            $identifier = trim($_POST["identifier"]);
        }
    } else {
        $identifier = trim($_POST["identifier"]);
    }

    // Process if no input validation errors
    if (empty($identifier_err) && empty($role_err)) {
        $sql = "";
        $id_column_where = ""; // Column used in WHERE clause (username, email, virtual_id)
        // Assuming 'email' column exists in users, staff, and students tables for sending emails
        $email_column_select = "email";
        $user_id_column_select = ""; // Column name for the primary ID (id, staff_id, user_id)
        $table_name = ""; // For logging

        // Determine the SQL query based on the provided role
        switch ($role) {
            case 'admin':
                // Assumes 'users' table has 'id' and 'email' columns and uses 'username' as identifier
                $sql = "SELECT id, email FROM users WHERE username = ?";
                $id_column_where = "username";
                $user_id_column_select = "id";
                $table_name = "users";
                break;
            case 'staff':
                // Assumes 'staff' table has 'staff_id', 'email' columns and uses 'email' as identifier
                $sql = "SELECT staff_id, email FROM staff WHERE email = ?";
                $id_column_where = "email";
                $user_id_column_select = "staff_id";
                $table_name = "staff";
                break;
            case 'student':
                 // Assumes 'students' table has 'user_id', 'email' columns and uses 'virtual_id' as identifier
                $sql = "SELECT user_id, email FROM students WHERE virtual_id = ?";
                $id_column_where = "virtual_id";
                $user_id_column_select = "user_id";
                $table_name = "students";
                break;
            default:
                $forgot_password_err = "Invalid role selected.";
                break;
        }

        // Proceed only if a valid SQL query was determined and DB link is valid
        if (!empty($sql) && $link !== false) {

            // Prepare statement for user lookup
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "s", $param_identifier);
                $param_identifier = $identifier;

                // Attempt to execute the user lookup
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);

                    // Check if getting the result was successful
                    if ($result === false) {
                         $forgot_password_err = "Oops! Could not retrieve user data.";
                         error_log("Forgot Password get_result failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $table_name. Error: " . mysqli_stmt_error($stmt));
                    } elseif (mysqli_num_rows($result) == 1) {
                        // User found
                        $user = mysqli_fetch_assoc($result);
                        $user_id = $user[$user_id_column_select]; // Get user's actual ID
                        $user_email = $user[$email_column_select] ?? null; // Get the email associated with the account

                        mysqli_free_result($result); // Free result set after fetching
                        // $stmt remains open until explicitly closed later

                        if (empty($user_email)) {
                            // User found, but no email address linked in the database
                             $forgot_password_err = "No email address associated with this account for password reset.";
                             error_log("Forgot Password: User found ($role, identifier: " . htmlspecialchars($identifier) . ") but no email address in DB.");

                        } else {
                            // User and email found, proceed to generate and send OTP

                            // Generate OTP - Uses OTP_LENGTH from mail_config.php
                            $otp = str_pad(random_int(0, pow(10, OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
                            // Calculate expiry time - Uses OTP_EXPIRY_MINUTES from mail_config.php
                            $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

                            // Store OTP in password_resets table
                            // First, delete any existing *unused* requests for this user/role to avoid clutter/confusion
                             // Ensure connection $link is still valid here
                             if ($link !== false) {
                                 $delete_sql = "DELETE FROM password_resets WHERE user_id = ? AND user_role = ? AND is_used = 0 AND expires_at > NOW()";
                                 // Initialize $delete_stmt
                                 $delete_stmt = false;
                                 if ($delete_stmt = mysqli_prepare($link, $delete_sql)) {
                                     mysqli_stmt_bind_param($delete_stmt, "is", $user_id, $role);
                                     mysqli_stmt_execute($delete_stmt);
                                     // Close the delete statement
                                     mysqli_stmt_close($delete_stmt);
                                 } else {
                                    error_log("Forgot Password: Failed to prepare delete statement for old OTPs. Error: " . mysqli_error($link));
                                 }
                             }


                            // Prepare insert statement for the new OTP request
                            $insert_sql = "INSERT INTO password_resets (user_id, user_role, email, otp, expires_at) VALUES (?, ?, ?, ?, ?)";
                             // Ensure connection $link is still valid here
                             // Initialize $insert_stmt
                             $insert_stmt = false;
                            if ($link !== false) {
                                 if ($insert_stmt = mysqli_prepare($link, $insert_sql)) {
                                     mysqli_stmt_bind_param($insert_stmt, "issss", $user_id, $role, $user_email, $otp, $expires_at);

                                     // Attempt to execute the insert statement
                                     if (mysqli_stmt_execute($insert_stmt)) {
                                         // OTP stored successfully
                                         mysqli_stmt_close($insert_stmt); // Close insert statement

                                         // --- Now attempt to send the email ---

                                         // Create a new PHPMailer instance - requires the autoloader
                                         $mail = new PHPMailer(true); // Pass true to enable exceptions

                                         try {
                                             //Server settings (using constants from mail_config.php)
                                             // !! IMPORTANT: SMTP SETTINGS MUST BE CORRECT IN mail_config.php !!
                                             // !! Check PHP error log or enable DEBUG_SERVER for specific mailer errors !!
                                             $mail->SMTPDebug = SMTP::DEBUG_OFF; // Change to SMTP::DEBUG_SERVER for troubleshooting!
                                             $mail->isSMTP();
                                             $mail->Host       = SMTP_HOST;
                                             $mail->SMTPAuth   = true;
                                             $mail->Username   = SMTP_USERNAME;
                                             $mail->Password   = SMTP_PASSWORD;
                                             // Use the class constants for encryption types
                                             $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                                             $mail->Port       = SMTP_PORT;
                                             $mail->CharSet    = 'UTF-8'; // Ensure UTF-8 encoding

                                             //Recipients
                                             $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                                             $mail->addAddress($user_email); // Add a recipient (the user's email)

                                             // Content
                                             $mail->isHTML(true);
                                             $mail->Subject = 'Password Reset OTP';
                                             $mail->Body    = "Hello,<br><br>You requested a password reset. Your One-Time Password (OTP) is: <strong>" . htmlspecialchars($otp) . "</strong><br><br>This OTP is valid for " . OTP_EXPIRY_MINUTES . " minutes.<br><br>If you did not request a password reset, please ignore this email.<br><br>Thank you,<br>Your School System";
                                             $mail->AltBody = "Hello,\n\nYou requested a password reset. Your One-Time Password (OTP) is: " . $otp . "\n\nThis OTP is valid for " . OTP_EXPIRY_MINUTES . " minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\nYour School System";

                                             $mail->send();

                                             // Email sent successfully
                                             // Store necessary info in session for the next step (verify_otp.php)
                                             $_SESSION['reset_identifier'] = $identifier; // Keep identifier in session
                                             $_SESSION['reset_role'] = $role; // Keep role in session
                                             $_SESSION['otp_sent'] = true; // Flag to indicate OTP step is initiated

                                             $_SESSION['operation_message'] = "<p class='text-green-600'>A password reset OTP has been sent to your email address. Please check your inbox.</p>";
                                             // Redirect to the OTP verification page
                                             header("location: verify_otp.php");
                                             exit;

                                         } catch (Exception $e) { // Catches PHPMailer exceptions
                                             // Mailer error occurred during $mail->send()
                                             // This catch block generates the error message shown in your image
                                             $forgot_password_err = "Failed to send OTP email. Please try again later.";
                                             // !! Crucial: The detailed error is in $mail->ErrorInfo. CHECK YOUR PHP ERROR LOG! !!
                                             error_log("Mailer Error: {$mail->ErrorInfo}");

                                             // Optional: Consider deleting the stored OTP request from the DB if the email sending failed.
                                             // This prevents storing OTPs that were never successfully sent.
                                             // $delete_failed_otp_sql = "DELETE FROM password_resets WHERE otp = ? AND user_id = ? AND user_role = ?";
                                             // if ($delete_stmt_failed = mysqli_prepare($link, $delete_failed_otp_sql)) { ... }
                                             // For simplicity, we are letting the OTP expire naturally if the email fails.
                                         }
                                     } else {
                                          // Database insert error for password_resets
                                          $forgot_password_err = "Oops! Could not store OTP request. Please try again later.";
                                          error_log("Forgot Password DB Insert Failed for role: $role, identifier: " . htmlspecialchars($identifier) . ". Error: " . mysqli_stmt_error($insert_stmt));
                                     }
                                 } else {
                                     // Database prepare error for insert into password_resets table
                                     $forgot_password_err = "Oops! Could not prepare OTP storage. Please try again later.";
                                     error_log("Forgot Password DB Prepare Insert Failed. Error: " . mysqli_error($link));
                                 }
                             } else {
                                // Database connection was somehow lost after initial lookup
                                $forgot_password_err = "Database connection failed during OTP storage.";
                                error_log("Database connection was false before OTP storage.");
                             }


                        } // else: empty($user_email)


                    } else { // mysqli_num_rows is not 1 (0 or > 1)
                        // Identifier doesn't exist or is not unique for this role
                        // Display a generic message for security purposes (don't reveal if the account exists)
                        $forgot_password_success = "If your account exists, a password reset OTP has been sent to the associated email address.";
                         if ($result !== false && mysqli_num_rows($result) > 1) {
                             error_log("Forgot Password: Duplicate identifier found for role: $role, identifier: " . htmlspecialchars($identifier) . " in table: $table_name");
                         }
                         // Free result set even if 0 or >1 rows were returned
                         if ($result !== false) {
                             mysqli_free_result($result);
                         }
                    } // else: mysqli_num_rows

                } else {
                    // Database execute error for select from user table
                    $forgot_password_err = "Oops! Something went wrong with database query execution. Please try again later.";
                    error_log("Forgot Password DB Execute Failed for role: $role, identifier: " . htmlspecialchars($identifier) . ", table: $table_name. Error: " . mysqli_stmt_error($stmt));
                } // else: mysqli_stmt_execute(stmt)

            } else {
                // Database prepare error for select from user table
                 $forgot_password_err = "Oops! Could not prepare database query. Please try again later.";
                 error_log("Forgot Password DB Prepare Failed for role: $role, table: $table_name. Error: " . mysqli_error($link));
            } // else: mysqli_prepare(link, sql)

            // --- Close the user lookup statement here ONLY if it was successfully prepared ---
            if ($stmt !== false && is_object($stmt)) { // Ensure $stmt is a valid object before closing
                 mysqli_stmt_close($stmt);
                 $stmt = false; // Set to false after closing
            }
            // --- End $stmt closing ---

        } else if (empty($sql)) {
             // This case should ideally be caught by the role validation above, but good for fallback
             $forgot_password_err = "Invalid role selected during processing.";
        } else if ($link === false) {
            // Database connection already failed - this check might be redundant if config.php errors on connection failure
            $forgot_password_err = "Database connection failed before processing.";
            error_log("Database connection was false before query prepare.");
        }


    } // else: $identifier_err or $role_err not empty

     // Close database connection if it was opened successfully
     // Check for $link being a valid object type before closing
     // Removed the deprecated mysqli_ping check
    if ($link !== false && is_object($link)) {
         mysqli_close($link);
    }

} else {
    // If accessed via GET and there's an operation message (e.g. from a failed attempt on verify_otp)
     if (isset($_SESSION['operation_message'])) {
          $forgot_password_err = $_SESSION['operation_message'];
          unset($_SESSION['operation_message']); // Clear it after displaying
     }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - School Management System</title>
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
        <h2 class="text-2xl font-bold">Forgot Password</h2>
        <p class="text-gray-600 mb-6">Enter your identifier and select your role to receive a password reset OTP.</p>

        <?php
        if (!empty($forgot_password_err)) {
            echo '<div class="alert-danger">' . $forgot_password_err . '</div>';
        }
         if (!empty($forgot_password_success)) {
             echo '<div class="alert-success">' . $forgot_password_success . '</div>';
         }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

             <div class="form-group">
                <label for="role">Your Role:</label>
                <select name="role" id="role" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($role_err)) ? 'border-red-500' : ''; ?>" required>
                    <option value="">-- Select Role --</option>
                    <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo ($role == 'staff') ? 'selected' : ''; ?>>Staff (Teacher/Principal)</option>
                    <option value="student" <?php echo ($role == 'student') ? 'selected' : ''; ?>>Student</option>
                </select>
                 <?php if(!empty($role_err)): ?><span class="text-danger"><?php echo $role_err; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="identifier">Your Identifier:</label>
                <input type="text" name="identifier" id="identifier" class="w-full px-3 py-2 border rounded-md <?php echo (!empty($identifier_err)) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($identifier); ?>" placeholder="Username (Admin), Email (Staff), or Virtual ID (Student)" required>
                <?php if (!empty($identifier_err)): ?><span class="text-danger"><?php echo $identifier_err; ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary">Send OTP</button>
            </div>

            <p class="text-gray-600 text-sm mt-4">Remember your password? <a href="login.php" class="text-indigo-600 hover:underline">Login here</a>.</p>

        </form>
    </div>
</body>
</html>