<?php
// School/admin/create_staff.php

// Start the session
session_start();

// Include database configuration file
// Path from 'admin/' up to 'School/' is '../'
require_once "../config.php";

// Include the Cloudinary upload helper file
// Path from 'admin/' to 'admin/' is './'
// ** Make sure cloudinary_upload_handler.php is in the same directory as this file (admin/) **
require_once "./cloudinary_upload_handler.php"; // This file contains the uploadToCloudinary function

// Check if the user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    // Store the message in session before redirecting
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can manage staff records.</p>"; // Updated message
    // Path from 'admin/' up to 'School/' is '../'
    header("location: ../login.php"); // Redirect to login if not logged in or not admin
    exit;
}

// Define variables and initialize with empty values
$staff_name = $mobile_number = $unique_id = $email = $password = $confirm_password = "";
$role = $subject_taught = "";
$classes_taught_input = ""; // To store the raw POST input for classes_taught array
$salary = ""; // Initialize salary as empty string to show placeholder

$staff_name_err = $mobile_number_err = $unique_id_err = $email_err = $password_err = $confirm_password_err = "";
$role_err = $salary_err = "";
$classes_taught_err = ""; // For validation errors specific to classes_taught (e.g., format if manually typed)

$insert_message = ""; // To display success or error messages

// Variables for optional fields to retain values on error
$subject_taught_display = '';

// Cloudinary variables
$photo_url = null; // Will store the Cloudinary URL for the database
$photo_upload_err = ""; // For Cloudinary upload errors
$uploaded_photo_url_on_error = ''; // To retain preview image if upload succeeds but DB fails

// Allowed roles (for validation and dropdown)
$allowed_roles = ['teacher', 'principal', 'admin', 'non-teaching']; // Added 'non-teaching' as an example

// Available classes for the selection (you can customize this list)
$available_classes = ['Nursery', 'LKG', 'UKG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
$classes_taught_array = []; // Array to hold selected classes for repopulation


// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Sanitize and Validate Inputs ---

    // Staff Name (Required)
    if (empty(trim($_POST["staff_name"] ?? ''))) { // Use ?? '' for robustness
        $staff_name_err = "Please enter the staff name.";
    } else {
        $staff_name = trim($_POST["staff_name"]);
    }

    // Mobile Number (Required)
    if (empty(trim($_POST["mobile_number"] ?? ''))) {
        $mobile_number_err = "Please enter mobile number.";
    } else {
        $mobile_number = trim($_POST["mobile_number"]);
         if (!preg_match("/^[0-9]{10,15}$/", $mobile_number)) {
             $mobile_number_err = "Please enter a valid mobile number (10-15 digits).";
         }
    }

    // Unique ID (4 digits, Required, Uniqueness Check)
    if (empty(trim($_POST["unique_id"] ?? ''))) {
        $unique_id_err = "Please enter the unique ID.";
    } else {
        $unique_id = trim($_POST["unique_id"]);
        if (!preg_match("/^\d{4}$/", $unique_id)) {
            $unique_id_err = "Unique ID must be exactly 4 digits.";
        } else {
            // Check if unique_id already exists (only if DB connection is valid)
            if ($link !== false) {
                $sql_check_unique_id = "SELECT staff_id FROM staff WHERE unique_id = ?";
                if ($stmt_check = mysqli_prepare($link, $sql_check_unique_id)) {
                    mysqli_stmt_bind_param($stmt_check, "s", $unique_id);
                    if (mysqli_stmt_execute($stmt_check)) {
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $unique_id_err = "This unique ID is already taken.";
                        }
                    } else {
                         error_log("DB Error checking unique ID: " . mysqli_stmt_error($stmt_check));
                         // Don't show raw error to user, set a generic message
                         if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Error checking unique ID availability. Please try again.</p>";
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                     error_log("DB Error preparing unique ID check: " . mysqli_error($link));
                     if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Error preparing unique ID check statement.</p>";
                }
            } else {
                 if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Database connection error prevents unique ID check.</p>";
            }
        }
    }

    // Email (Required, Format, Uniqueness Check)
    if (empty(trim($_POST["email"] ?? ''))) {
        $email_err = "Please enter the email.";
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Please enter a valid email format.";
        } else {
            // Check if email already exists (only if DB connection is valid)
             if ($link !== false) {
                $sql_check_email = "SELECT staff_id FROM staff WHERE email = ?";
                 if ($stmt_check = mysqli_prepare($link, $sql_check_email)) {
                    mysqli_stmt_bind_param($stmt_check, "s", $email);
                    if (mysqli_stmt_execute($stmt_check)) {
                        mysqli_stmt_store_result($stmt_check);
                        if (mysqli_stmt_num_rows($stmt_check) > 0) {
                            $email_err = "This email is already registered.";
                        }
                    } else {
                         error_log("DB Error checking email: " . mysqli_stmt_error($stmt_check));
                         if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Error checking email availability. Please try again.</p>";
                    }
                    mysqli_stmt_close($stmt_check);
                } else {
                     error_log("DB Error preparing email check: " . mysqli_error($link));
                     if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Error preparing email check statement.</p>";
                }
             } else {
                 if (empty($insert_message)) $insert_message = "<p class='text-red-600'>Database connection error prevents email check.</p>";
             }
        }
    }

    // Password (Required, Length, Hashing)
    $password = $_POST["password"] ?? ''; // Keep raw input for comparison, NOT repopulation
    if (empty($password)) {
        $password_err = "Please enter a password.";
    } else {
         if (strlen($password) < 6) {
             $password_err = "Password must have at least 6 characters.";
         }
         // Complexity checks could go here
    }

    // Confirm Password (Required, Match Password)
    $confirm_password = $_POST["confirm_password"] ?? ''; // Keep raw input for comparison, NOT repopulation
    if (empty($confirm_password)) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        // Only compare if the first password has no length errors
        if (empty($password_err) && ($password !== $confirm_password)) {
            $confirm_password_err = "Password and confirmation do not match.";
        }
    }

    // Role (Required, Allowed List)
    $role = trim($_POST["role"] ?? '');
     if (empty($role)) {
         $role_err = "Please select a role.";
     } elseif (!in_array($role, $allowed_roles)) {
         // This should ideally be caught client-side with a dropdown, but good server-side check
         $role_err = "Invalid role selected.";
     }

    // Salary (Optional, Numeric)
    $salary_input = filter_input(INPUT_POST, 'salary', FILTER_UNSAFE_RAW);
    $salary_for_db = null; // Default to NULL
    $salary = $salary_input ?? ''; // Keep raw input string for repopulation

    if ($salary_input !== '' && $salary_input !== null) {
         $filtered_salary = filter_var($salary_input, FILTER_VALIDATE_FLOAT);
         if ($filtered_salary === false || $filtered_salary < 0) {
              $salary_err = "Please enter a valid positive number for salary.";
         } else {
              $salary_for_db = $filtered_salary; // Use filtered value for DB
         }
    } // If empty, $salary_for_db remains null, which is correct for an optional field


    // Subject Taught (Optional)
    $subject_taught = trim($_POST["subject_taught"] ?? ''); // Keep for repopulation
    $subject_taught_for_db = ($subject_taught === '') ? null : $subject_taught; // Convert empty string to NULL for DB

    // Classes Taught (Optional, Multi-select)
    $classes_taught_for_db = null; // Default to NULL
    // Check if classes_taught is set and is an array (checkboxes submit an array if any are checked)
    if (isset($_POST["classes_taught"]) && is_array($_POST["classes_taught"])) {
        $selected_classes = $_POST["classes_taught"];
        $sanitized_classes_array = [];
        // Validate that selected classes are from the allowed list ($available_classes)
        foreach($selected_classes as $class_item) {
            $trimmed_class = trim($class_item);
            if (in_array($trimmed_class, $available_classes)) {
                 $sanitized_classes_array[] = $trimmed_class;
            } else {
                 // Log unexpected input, but don't necessarily set a user error if it's just invalid data
                 error_log("Attempted to submit invalid class in classes_taught: " . htmlspecialchars($trimmed_class));
            }
        }

        if (!empty($sanitized_classes_array)) {
             // Sort based on $available_classes order for consistency in DB string
             usort($sanitized_classes_array, function($a, $b) use ($available_classes) {
                 $pos_a = array_search($a, $available_classes);
                 $pos_b = array_search($b, $available_classes);
                 if ($pos_a === false) return 1; // Should not happen with validation above
                 if ($pos_b === false) return -1; // Should not happen
                 return $pos_a - $pos_b;
             });
             // Join the sanitized classes into a comma-separated string for the database
             $classes_taught_for_db = implode(', ', $sanitized_classes_array);
             // Keep the array for checkbox repopulation on error
             $classes_taught_array = $sanitized_classes_array;
        } else {
             $classes_taught_for_db = null; // No valid classes selected
             // $classes_taught_array remains empty
        }
         // $classes_taught_err is not set unless you need a specific validation rule (e.g., minimum 1 class for teachers)
    } else {
         // No checkboxes were selected, $classes_taught_for_db remains null, array remains empty
    }


    // --- Handle Photo Upload using Cloudinary ---
    // Check if a file was uploaded for 'staff_photo' (using the name from the form input)
    if (isset($_FILES["staff_photo"]) && $_FILES["staff_photo"]["error"] != UPLOAD_ERR_NO_FILE) {

        // Call the Cloudinary upload helper function
        // Use 'staff_photos' folder in Cloudinary account
        $uploadResult = uploadToCloudinary($_FILES["staff_photo"], 'staff_photos');

        if ($uploadResult === false) {
             // This should not happen if $_FILES error is checked, but as a fallback
             $photo_upload_err = "Photo upload failed due to an unknown issue.";
             error_log("uploadToCloudinary returned false for file " . ($_FILES['staff_photo']['name'] ?? 'N/A'));

        } elseif (isset($uploadResult['error'])) {
            // An error occurred during the upload or validation within the helper
            $photo_upload_err = $uploadResult['error'];
            $photo_url = null; // Ensure URL is null on error

        } else {
            // Upload was successful! Store the secure URL
            $photo_url = $uploadResult['secure_url'];
            // Store the URL in a temporary variable to display preview if other errors occur
             $uploaded_photo_url_on_error = $photo_url;
        }
    }
    // If UPLOAD_ERR_NO_FILE, $_FILES error will be 4, the outer if condition is false,
    // and $photo_url remains null, which is correct for an optional photo field.


    // --- Final Check for ALL errors before DB insert ---
    // Check if any of the error variables are non-empty
    if (empty($staff_name_err) && empty($mobile_number_err) && empty($unique_id_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err) && empty($salary_err) && empty($classes_taught_err) && empty($photo_upload_err)) {

        // Hash the password AFTER confirming there are no validation errors on password fields
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
         if ($hashed_password === false) {
             // Hashing failed - critical error
             $insert_message = "<p class='text-red-600'>Internal error hashing password.</p>";
             error_log("Password hashing failed right before DB insert for staff creation.");
             // Do NOT proceed with DB insert if hashing failed
             $has_critical_error = true; // Use a flag to skip insert
         } else {
             $has_critical_error = false;
         }


        // Proceed with Database Insertion only if no validation errors and no hashing error
        if ($link !== false && !$has_critical_error) {
            // Prepare an insert statement
            // Note: salary_for_db, subject_taught_for_db, classes_taught_for_db, photo_url can be NULL
            // Bind types: s = string, d = double/float, i = integer
            // staff_name, mobile_number, unique_id, email, password (hashed), role, subject_taught, classes_taught, photo_filename are strings/VARCHAR/TEXT (bind as 's')
            // salary is double/float (bind as 'd')
            // Total 9 parameters: s s s s s s d s s (Updated based on 9 columns and types)
            $sql = "INSERT INTO staff (staff_name, mobile_number, unique_id, email, password, role, salary, subject_taught, classes_taught, photo_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Added photo_filename column

            if ($stmt = mysqli_prepare($link, $sql)) {
                // Bind variables to the prepared statement as parameters
                // s s s s s s d s s s -> Total 10 parameters
                mysqli_stmt_bind_param($stmt, "ssssssdsss",
                    $staff_name,
                    $mobile_number,
                    $unique_id,
                    $email,
                    $hashed_password,   // Use hashed password (string)
                    $role,              // Role (string)
                    $salary_for_db,     // Salary (double or NULL) - mysqli_stmt_bind_param handles NULL for numeric
                    $subject_taught_for_db, // Subject Taught (string or NULL)
                    $classes_taught_for_db, // Classes Taught (string or NULL)
                    $photo_url          // Photo URL (string or NULL)
                );

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt)) {
                    $insert_message = "<p class='text-green-600 font-semibold'>Staff record created successfully.</p>";
                    // Clear form fields after successful insertion
                    $staff_name = $mobile_number = $unique_id = $email = $password = $confirm_password = "";
                    $role = $subject_taught = "";
                    $classes_taught_input = ""; // Clear raw input
                    $classes_taught_array = []; // Clear array for checkboxes
                    $salary = ""; // Reset salary for display
                    $subject_taught_display = ''; // Reset optional text display
                    $uploaded_photo_url_on_error = ''; // Clear retained photo URL


                    // Clear errors too after success
                    $staff_name_err = $mobile_number_err = $unique_id_err = $email_err = $password_err = $confirm_password_err = "";
                    $role_err = $salary_err = $classes_taught_err = $photo_upload_err = "";

                } else {
                     // Handle execution errors (e.g., actual DB errors)
                     $error_text = mysqli_stmt_error($stmt);
                     // Check for specific errors, like duplicate unique_id or email if not caught before
                     if (strpos($error_text, 'Duplicate entry') !== false) {
                         if (strpos($error_text, "'unique_id'") !== false) {
                              $insert_message = "<p class='text-red-600 font-semibold'>Error: Unique ID is already taken.</p>";
                              $unique_id_err = "Unique ID is already taken."; // Also set field error
                         } elseif (strpos($error_text, "'email'") !== false) {
                             $insert_message = "<p class='text-red-600 font-semibold'>Error: Email is already registered.</p>";
                             $email_err = "Email is already registered."; // Also set field error
                         } else {
                             $insert_message = "<p class='text-red-600 font-semibold'>Error: Could not create staff record due to a conflict (e.g., duplicate data).</p>";
                             error_log("MySQL Insert Error (Duplicate other): " . $error_text);
                         }
                     } else {
                         $insert_message = "<p class='text-red-600 font-semibold'>Error inserting record: " . $error_text . "</p>";
                         error_log("MySQL Insert Error: " . $error_text);
                     }

                     // Re-populate form fields on error (password fields excluded for security)
                     $staff_name = $_POST["staff_name"] ?? '';
                     $mobile_number = $_POST["mobile_number"] ?? '';
                     $unique_id = $_POST["unique_id"] ?? ''; // Keep unique_id for user to correct
                     $email = $_POST["email"] ?? ''; // Keep email for user to correct
                     $role = $_POST["role"] ?? '';
                     $salary = $_POST["salary"] ?? ''; // Keep original submitted value string
                     $subject_taught = $_POST["subject_taught"] ?? ''; // Keep for repopulation
                     // classes_taught_array is already populated above from $_POST if it existed
                     // $uploaded_photo_url_on_error retains photo URL if upload succeeded
                }

                // Close statement
                mysqli_stmt_close($stmt);
            } else {
                 $insert_message = "<p class='text-red-600 font-semibold'>Error: Could not prepare insert statement. " . mysqli_error($link) . "</p>";
                 error_log("MySQL Error preparing insert statement: " . mysqli_error($link));

                 // Re-populate form fields on prepare error
                 $staff_name = $_POST["staff_name"] ?? '';
                 $mobile_number = $_POST["mobile_number"] ?? '';
                 $unique_id = $_POST["unique_id"] ?? '';
                 $email = $_POST["email"] ?? '';
                 $role = $_POST["role"] ?? '';
                 $salary = $_POST["salary"] ?? '';
                 $subject_taught = $_POST["subject_taught"] ?? '';
                 // classes_taught_array is already populated above from $_POST if it existed
                 // $uploaded_photo_url_on_error retains photo URL if upload succeeded
            }
        } else if ($link === false) {
             // DB connection error already handled, message is set
             // Re-populate forms (minus passwords)
             $staff_name = $_POST["staff_name"] ?? '';
             $mobile_number = $_POST["mobile_number"] ?? '';
             $unique_id = $_POST["unique_id"] ?? '';
             $email = $_POST["email"] ?? '';
             $role = $_POST["role"] ?? '';
             $salary = $_POST["salary"] ?? '';
             $subject_taught = $_POST["subject_taught"] ?? '';
             // classes_taught_array is already populated above from $_POST if it existed
             // $uploaded_photo_url_on_error retains photo URL if upload succeeded
        } else { // $has_critical_error is true (e.g., hashing failed)
            // Message is already set, fields are repopulated (minus passwords)
             $staff_name = $_POST["staff_name"] ?? '';
             $mobile_number = $_POST["mobile_number"] ?? '';
             $unique_id = $_POST["unique_id"] ?? '';
             $email = $_POST["email"] ?? '';
             $role = $_POST["role"] ?? '';
             $salary = $_POST["salary"] ?? '';
             $subject_taught = $_POST["subject_taught"] ?? '';
             // classes_taught_array is already populated above from $_POST if it existed
             // $uploaded_photo_url_on_error retains photo URL if upload succeeded
        }


    } else {
        // If there were any validation errors
        if (empty($insert_message)) { // Only set this general message if no specific DB check error message was already set
            $insert_message = "<p class='text-orange-600 font-semibold'>Please correct the errors below.</p>";
        }
        // Keep submitted values in variables for form repopulation on error (already done above)
         // Staff name, mobile, unique id, email, role, salary, subject taught
         // Classes taught array populated from $_POST if it was set
         // $uploaded_photo_url_on_error retains photo URL if upload succeeded before validation errors
    }
} else {
    // If not a POST request (page loaded initially), initialize $classes_taught_array as empty
    $classes_taught_array = [];
    // $uploaded_photo_url_on_error is empty on first load
}


// Close connection at the very end if it was opened and is valid
if (isset($link) && is_object($link) && mysqli_ping($link)) {
    mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Create Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Custom styles for form elements and layout */
        .form-control {
            /* General styles applied by Tailwind utility classes in HTML */
        }
        .form-error {
            color: #dc3545; /* Red */
            font-size: 0.875em; /* text-xs */
            margin-top: 0.25em;
            display: block; /* Ensure it takes its own line */
        }
         .form-control.is-invalid {
             border-color: #dc3545; /* Red border */
         }

           /* Custom background style - Define multiple options */
           /* Default gradient if none saved or set */
             body {
                 background: linear-gradient(to right, #4facfe, #00f2fe); /* Default background */
                 min-height: 100vh; /* Ensure body takes at least full viewport height */
                 display: flex; /* Use flexbox for layout */
                 flex-direction: column; /* Stack children vertically */
                 align-items: center; /* Center content horizontally */
                 padding-top: 2rem; /* Add padding at the top */
                 padding-bottom: 2rem; /* Add padding at the bottom */
             }

            /* Optional background styles for testing / picker */
            .gradient-background-blue-cyan { background: linear-gradient(to right, #4facfe, #00f2fe); }
            .gradient-background-purple-pink { background: linear-gradient(to right, #a18cd1, #fbc2eb); }
            .gradient-background-green-teal { background: linear-gradient(to right, #a8edea, #fed6e3); }
            .solid-bg-gray { background-color: #f3f4f6; }
            .solid-bg-indigo { background-color: #4f46e5; }


         /* Styles for the message box */
           .message-box {
               padding: 1rem;
               border-radius: 0.5rem;
               border: 1px solid transparent;
               margin-bottom: 1.5rem;
               text-align: center; /* Center the text inside */
                /* Add specific color classes based on type in HTML/PHP */
           }
           .message-box p {
               margin: 0; /* Remove default paragraph margins */
           }
            /* Specific message box color styles (using utility classes in PHP) */
            /* .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; } */
            /* .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; } */
            /* .message-box.warning { color: #b45309; background-color: #fffce0; border-color: #fde68a; } */
            /* .message-box.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; } */


         /* Photo Upload Specific Styles */
         #photoPreviewContainer {
             width: 120px;
             height: 120px;
             border-radius: 50%; /* Circular shape */
             background-color: #e2e8f0; /* gray-300 fallback */
             background-size: cover; /* Cover the area */
             background-position: center; /* Center the image */
             border: 2px solid #cbd5e1; /* gray-400 border */
             display: flex; /* Use flexbox to center SVG */
             justify-content: center; /* Center horizontally */
             align-items: center; /* Center vertically */
             overflow: hidden; /* Hide parts of image outside the circle */
             flex-shrink: 0; /* Prevent shrinking in flex container */
         }
          #photoPreviewContainer svg {
             display: block;
             color: #9ca3af; /* gray-400 for icon */
             width: 50%; /* Make icon smaller */
             height: 50%; /* Make icon smaller */
          }
          #photoPreviewContainer.has-image svg {
              display: none; /* Hide SVG when image is present */
          }

         #staff_photo {
             display: none; /* Hide the default file input */
         }

         .custom-file-upload {
             display: inline-block;
             padding: 8px 16px;
             cursor: pointer;
             background-color: #6366f1; /* indigo-500 */
             color: white;
             border-radius: 0.375rem; /* rounded-md */
             font-weight: 600; /* font-semibold */
             font-size: 0.875rem; /* text-sm */
             border: none;
             transition: background-color 0.15s ease-in-out;
         }

         .custom-file-upload:hover {
             background-color: #4f46e5; /* indigo-600 */
         }

         /* No specific is-invalid style needed for custom upload button usually */


    </style>
    <script>
        // JavaScript to handle dynamic background changes (Optional, requires UI elements)
        function setBackground(className) {
            const body = document.body;
            // Remove all existing background classes that start with gradient-background- or solid-bg-
            body.classList.forEach(cls => {
                if (cls.startsWith('gradient-background-') || cls.startsWith('solid-bg-')) {
                    body.classList.remove(cls);
                }
            });
            // Add the selected class
            body.classList.add(className);

            // Optional: Save preference to local storage
            localStorage.setItem('backgroundPreference', className);
        }

        // Optional: Apply background preference on page load
        document.addEventListener('DOMContentLoaded', (event) => {
            const savedBackground = localStorage.getItem('backgroundPreference');
            if (savedBackground) {
                // Apply saved background only if it looks like a valid background class
                 if (savedBackground.startsWith('gradient-background-') || savedBackground.startsWith('solid-bg-')) {
                     setBackground(savedBackground);
                 } else {
                     // Clear invalid saved preference and use default
                      localStorage.removeItem('backgroundPreference');
                      // Default background is set by CSS body rule
                 }
            } else {
                // Default background is set by CSS body rule, no action needed here
            }


            // --- JavaScript for Photo Preview ---
            const photoInput = document.getElementById('staff_photo'); // Use 'staff_photo' as per form name
            const photoPreviewContainer = document.getElementById('photoPreviewContainer');
            const photoErrorSpan = document.querySelector('span.form-error[data-for="staff_photo"]'); // Get the specific error span

            // Function to update photo preview container background
            function updatePhotoPreview(url) {
                 if (url) {
                     photoPreviewContainer.style.backgroundImage = `url('${url}')`;
                     photoPreviewContainer.classList.add('has-image');
                 } else {
                      photoPreviewContainer.style.backgroundImage = `none`;
                      photoPreviewContainer.classList.remove('has-image');
                 }
            }

             // --- Initial preview check for retained value after error ---
             // If PHP set a URL on error, display it.
             const retainedPhotoUrl = '<?php echo htmlspecialchars($uploaded_photo_url_on_error); ?>';
             if (retainedPhotoUrl) {
                 updatePhotoPreview(retainedPhotoUrl);
             } else {
                  // Ensure it shows the SVG if no URL is present initially or after successful upload
                  updatePhotoPreview(null);
             }


            // Event listener for file input change
            if (photoInput && photoPreviewContainer) {
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];

                    // Clear previous error message and invalid state
                     if(photoErrorSpan) photoErrorSpan.textContent = '';
                     // No need to add 'is-invalid' class to the hidden input or label
                     // e.target.classList.remove('is-invalid');

                     updatePhotoPreview(null); // Clear current preview immediately

                    if (file) {
                         const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                         const maxSize = 10 * 1024 * 1024; // Match server-side check (10MB)

                         // Basic client-side validation
                         if (!allowedTypes.includes(file.type)) {
                             if(photoErrorSpan) photoErrorSpan.textContent = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.';
                             // e.target.classList.add('is-invalid'); // Apply to label if needed
                              e.target.value = ''; // Clear the selected file
                             return;
                         }

                         if (file.size > maxSize) {
                             if(photoErrorSpan) photoErrorSpan.textContent = 'File is too large (max 10MB).';
                             // e.target.classList.add('is-invalid'); // Apply to label if needed
                              e.target.value = ''; // Clear the selected file
                             return;
                         }

                        // Use FileReader to display a local preview
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            updatePhotoPreview(e.target.result); // Use data URL for local preview
                        };

                        reader.onerror = function() {
                             if(photoErrorSpan) photoErrorSpan.textContent = 'Error reading file.';
                             // e.target.classList.add('is-invalid'); // Apply to label if needed
                              e.target.value = ''; // Clear the selected file
                             updatePhotoPreview(null); // Clear preview
                             alert('Error reading file.'); // Optional alert
                        }

                        reader.readAsDataURL(file); // Read file as data URL for preview
                    } else {
                        // No file selected, clear preview
                        updatePhotoPreview(null);
                         if(photoErrorSpan) photoErrorSpan.textContent = ''; // Clear message if user cleared input
                    }
                });
            }
            // --- End JavaScript for Photo Preview ---


            // --- JavaScript for Classes Taught toggle ---
            const toggleButton = document.getElementById('toggleClassesButton');
            const classesDiv = document.getElementById('classesTaughtDiv');

            if(toggleButton && classesDiv) {
                // Check if any checkboxes are initially checked (due to repopulation on error)
                 const checkboxes = classesDiv.querySelectorAll('input[type="checkbox"]');
                 let anyChecked = false;
                 checkboxes.forEach(checkbox => {
                     if (checkbox.checked) {
                         anyChecked = true;
                     }
                 });

                // If any checkboxes are checked or there's an error message for classes, show the div initially
                 if (anyChecked || '<?php echo !empty($classes_taught_err); ?>') { // Check PHP error flag
                     classesDiv.classList.remove('hidden');
                     toggleButton.textContent = 'Hide Classes Taught';
                 } else {
                     // Otherwise, ensure it's hidden and button text is correct
                     classesDiv.classList.add('hidden');
                     toggleButton.textContent = 'Show Classes Taught';
                 }


                toggleButton.addEventListener('click', function() {
                    classesDiv.classList.toggle('hidden');
                    if (classesDiv.classList.contains('hidden')) {
                        toggleButton.textContent = 'Show Classes Taught';
                    } else {
                        toggleButton.textContent = 'Hide Classes Taught';
                    }
                });
            }
             // --- End JavaScript for Classes Taught toggle ---
        });


    </script>
</head>
<!-- Apply an enhanced background class - Default background set in CSS body rule -->
<body class="min-h-screen flex flex-col items-center py-8 px-4">

    <!-- Back to Dashboard / Logout Links (Optional Header style) -->
    <!-- Based on user request, using footer-like nav instead -->

    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-3xl mx-auto"> <!-- Increased max-w to max-w-3xl for better layout -->
        <h2 class="text-2xl font-bold mb-8 text-center text-gray-800">Create New Staff Record</h2>

        <?php
        // Display insertion message with improved styling based on message type
        if (!empty($insert_message)) {
             $message_class = 'message-box ';
             // Determine class based on content - this is a basic check
             $msg_lower = strtolower(strip_tags($insert_message)); // Use strip_tags to avoid issues with HTML in message
             if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false) {
                 $message_class .= 'success';
             } elseif (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'taken') !== false || strpos($msg_lower, 'registered') !== false || strpos($msg_lower, 'could not') !== false) {
                  $message_class .= 'error';
             } elseif (strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'please') !== false || strpos($msg_lower, 'warning') !== false) { // Added 'please' to catch validation prompts
                 $message_class .= 'warning';
             } else {
                $message_class .= 'info'; // Default info style
             }

            echo "<div class='" . $message_class . "'>";
            // The message already contains <p> tags and htmlspecialchars where needed in PHP
            echo $insert_message; // Output the raw message HTML
            echo "</div>";
        }
        ?>

        <!-- Use grid for two columns on medium screens and above -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">

            <!-- Photo Upload Section -->
            <div class="md:col-span-2 flex flex-col items-center gap-4 mb-4">
                <label class="block text-sm font-medium text-gray-700 text-center">Staff Photo (Optional)</label>
                 <!-- Use $uploaded_photo_url_on_error to display preview if upload succeeded but DB insert failed -->
                 <!-- Style attribute is set by JavaScript on load/change -->
                 <div id="photoPreviewContainer" class="relative flex-shrink-0">
                     <!-- SVG for placeholder -->
                     <svg class="h-full w-full text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                         <path d="M24 20.993V24H0v-2.996A14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"/>
                     </svg>
                 </div>

                <div>
                    <label for="staff_photo" class="custom-file-upload">
                       Choose Photo
                    </label>
                     <input type="file" name="staff_photo" id="staff_photo" accept="image/jpeg, image/png, image/gif, image/webp">
                </div>

                <!-- Use the specific photo upload error variable -->
                <span class="form-error text-xs" data-for="staff_photo"><?php echo htmlspecialchars($photo_upload_err); ?></span>
                <span class="text-gray-500 text-xs italic block text-center mt-1">Optional. Max 10MB, JPG, PNG, GIF, WEBP formats.</span>
            </div>


            <!-- Row 1 -->
            <div>
                <label for="staff_name" class="block text-sm font-medium text-gray-700 mb-1">Staff Name <span class="text-red-500">*</span></label>
                <input type="text" name="staff_name" id="staff_name" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($staff_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($staff_name); ?>" placeholder="Enter full name" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($staff_name_err); ?></span>
            </div>

            <div>
                <label for="mobile_number" class="block text-sm font-medium text-gray-700 mb-1">Mobile Number <span class="text-red-500">*</span></label>
                <input type="text" name="mobile_number" id="mobile_number" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($mobile_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($mobile_number); ?>" placeholder="e.g., 01234567890" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($mobile_number_err); ?></span>
            </div>

             <div>
                <label for="unique_id" class="block text-sm font-medium text-gray-700 mb-1">Unique ID (4 Digits) <span class="text-red-500">*</span></label>
                <input type="text" name="unique_id" id="unique_id" maxlength="4" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($unique_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($unique_id); ?>" placeholder="e.g., 1234" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($unique_id_err); ?></span>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email (for login) <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="email" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($email); ?>" placeholder="name@example.com" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($email_err); ?></span>
            </div>

             <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                <!-- NOTE: value attribute is intentionally left empty for password fields for security -->
                <input type="password" name="password" id="password" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Minimum 6 characters" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($password_err); ?></span>
                 <span class="text-gray-500 text-xs italic block mt-1">Password will be securely stored (hashed).</span>
            </div>

             <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" placeholder="Confirm your password" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($confirm_password_err); ?></span>
            </div>

             <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                <select name="role" id="role" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($role_err)) ? 'is-invalid' : ''; ?>" required>
                    <option value="">-- Select Role --</option>
                     <?php
                        // Loop through allowed roles for the dropdown
                        foreach ($allowed_roles as $allowed_role) {
                             echo '<option value="' . htmlspecialchars($allowed_role) . '"' . (($role === $allowed_role) ? ' selected' : '') . '>' . htmlspecialchars(ucfirst($allowed_role)) . '</option>';
                        }
                     ?>
                </select>
                <span class="form-error text-xs"><?php echo htmlspecialchars($role_err); ?></span>
            </div>

            <div>
                <label for="salary" class="block text-sm font-medium text-gray-700 mb-1">Salary</label>
                <input type="number" name="salary" id="salary" step="0.01" min="0" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($salary_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($salary); ?>" placeholder="e.g., 50000.00">
                 <span class="form-error text-xs"><?php echo htmlspecialchars($salary_err); ?></span>
                 <span class="text-gray-500 text-xs italic">Optional</span>
            </div>

            <!-- Subject Taught spans two columns on medium+ screens -->
            <div class="md:col-span-2">
                <label for="subject_taught" class="block text-sm font-medium text-gray-700 mb-1">Subject Taught</label>
                <input type="text" name="subject_taught" id="subject_taught" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($subject_taught); ?>" placeholder="e.g., Mathematics, Science">
                 <span class="text-gray-500 text-xs italic">Optional</span>
            </div>

            <!-- Classes Taught Section - Initially Hidden -->
            <div class="md:col-span-2">
                <button type="button" id="toggleClassesButton" class="text-indigo-600 hover:text-indigo-800 hover:underline focus:outline-none text-sm mb-2">
                    Show Classes Taught
                </button>

                <div id="classesTaughtDiv" class="hidden bg-gray-50 p-4 rounded-md border border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Classes Taught</label>
                    <!-- Grid layout for checkboxes -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-x-4 gap-y-2">
                        <?php
                        // Loop through available classes and create checkboxes
                        // $classes_taught_array holds selected values on error or initial load if relevant data existed
                        // Use in_array to check if the current class should be checked
                        ?>
                        <?php foreach ($available_classes as $class): ?>
                            <div class="flex items-center">
                                <input type="checkbox" name="classes_taught[]" id="class_<?php echo str_replace(' ', '_', htmlspecialchars($class)); ?>" value="<?php echo htmlspecialchars($class); ?>" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                    <?php echo in_array($class, $classes_taught_array) ? 'checked' : ''; ?>>
                                <label for="class_<?php echo str_replace(' ', '_', htmlspecialchars($class)); ?>" class="ml-2 block text-sm text-gray-700 cursor-pointer">
                                    <?php echo htmlspecialchars($class); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <span class="form-error text-xs"><?php echo htmlspecialchars($classes_taught_err); ?></span>
                    <span class="text-gray-500 text-xs italic">Optional: Select one or more classes taught.</span>
                </div>
            </div>


            <!-- Button Group spans two columns -->
            <div class="md:col-span-2 flex flex-col md:flex-row md:justify-end items-center space-y-4 md:space-y-0 md:space-x-4 mt-4">
                <button type="submit" class="w-full md:w-auto px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                    Create Staff Record
                </button>
            </div>

             <!-- Required fields note spans two columns -->
             <div class="md:col-span-2 text-center mt-4 text-sm text-gray-600">
                 Fields marked with <span class="text-red-500">*</span> are required.
             </div>

        </form>
    </div>

    <!-- Navigation Links (Footer-like) -->
     <footer class="mt-8 text-center text-sm text-gray-700">
         <nav class="flex flex-wrap justify-center gap-4">
             <!-- Path from 'admin/' up to 'School/' is '../' -->
             <a href="admin_dashboard.php" class="text-indigo-700 hover:text-indigo-900 hover:underline transition duration-150 ease-in-out">
                 ← Back to Dashboard
             </a>
             <span class="text-gray-400">|</span>
             <!-- Path from 'admin/' to 'admin/' is './' -->
             <a href="./manage_staff.php" class="text-indigo-700 hover:text-indigo-900 hover:underline transition duration-150 ease-in-out">
                 Manage Staff
             </a>
              <span class="text-gray-400">|</span>
               <!-- Path from 'admin/' up to 'School/' is '../' -->
              <a href="../logout.php" class="text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out">
                 Logout
             </a>
         </nav>
          <!-- Optional: Add buttons for background change here -->
          <div class="mt-6 text-center text-gray-700 text-xs">
              Choose Background:
              <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
              <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
               <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
               <button class="ml-2 px-2 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" onclick="setBackground('solid-bg-gray')">Gray</button>
               <button class="ml-2 px-2 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs" onclick="setBackground('solid-bg-indigo')">Indigo</button>
          </div>
     </footer>


</body>
</html>