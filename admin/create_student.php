<?php
// School/admin/create_student.php

// Start the session
session_start();

// Include database configuration file
// Path from 'admin/' up to 'School/' is '../'
require_once "../config.php";

// Include the Cloudinary upload helper file
// Path from 'admin/' to 'admin/' is './'
// Make sure this file exists and the path is correct
// This file should contain the function `uploadToCloudinary($file, $folder)`
require_once "./cloudinary_upload_handler.php";


// Check if the user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>You must be logged in as an admin to create student records.</p>";
    // Path from 'admin/' up to 'School/' is '../'
    header("location: ../login.php");
    exit;
}

// Define variables and initialize with empty values
// Required fields
$full_name = $father_name = $mother_name = $phone_number = "";
$current_class = "";

// Variables for display/retention for all fields
// Fields we are KEEPING:
$roll_number_display = '';
$village_display = '';
$date_of_birth_display = ''; // As string for input type="date"
$takes_van_display = ''; // Checkbox state ('on' or '')
// Removed: $default_monthly_fee_display

// Fields from previous code (retained)
$whatsapp_number_display = '';
$previous_class_display = '';
$previous_school_display = '';
$previous_marks_percentage_display = '';
$current_marks_display = '';
$address_display = '';
$pincode_display = '';
$state_display = '';
$virtual_id_display = ''; // Required Virtual ID
$password_display = '';   // Required Password (will always be empty for security)


// Variables for database storage (handling nulls and types)
// New DB variables (KEEPING these)
$roll_number_for_db = null;
$village_for_db = null;
$date_of_birth_for_db = null; // Stored as YYYY-MM-DD string or null
$takes_van_for_db = false;     // Stored as boolean (0 or 1) for DB
// Removed: $default_monthly_fee_for_db

// Existing DB variables (retained)
$whatsapp_number_for_db = null;
$previous_class_for_db = null;
$previous_school_for_db = null;
$previous_marks_percentage_for_db = null;
$current_marks_for_db = null;
$address_for_db = null;
$pincode_for_db = null;
$state_for_db = null;
$virtual_id = ''; // Will hold the sanitized virtual ID for DB
$password_hashed = null; // Will hold the hashed password for DB
$photo_url = null; // Will store the Cloudinary URL for DB

// Variables for retaining state on error
$uploaded_photo_url_on_error = ''; // To show photo preview if upload succeeded but other errors occurred

// Define error variables
// Required field errors
$full_name_err = $father_name_err = $mother_name_err = $phone_number_err = "";
$current_class_err = "";
$virtual_id_err = "";
$password_err = "";
// Removed: $default_monthly_fee_err

// Optional field errors (only if specific validation fails, not just being empty)
$roll_number_err = "";
$village_err = "";
$date_of_birth_err = "";
$takes_van_err = ""; // Unlikely for checkbox
$photo_upload_err = ""; // Specific error for photo upload issues


$insert_message = ""; // Message displayed at the top (success, error, warning)

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. Retrieve and Sanitize Required Fields ---
    // Full Name
    $full_name = trim($_POST["full_name"] ?? '');
    if (empty($full_name)) { $full_name_err = "Please enter the full name."; }

    // Father's Name
    $father_name = trim($_POST["father_name"] ?? '');
    if (empty($father_name)) { $father_name_err = "Please enter father's name."; }

    // Mother's Name
    $mother_name = trim($_POST["mother_name"] ?? '');
    if (empty($mother_name)) { $mother_name_err = "Please enter mother's name."; }

    // Phone Number
    $phone_number = trim($_POST["phone_number"] ?? '');
    if (empty($phone_number)) {
        $phone_number_err = "Please enter phone number.";
    } else {
        if (!preg_match("/^[0-9]{10,15}$/", $phone_number)) { // Basic numeric check
            $phone_number_err = "Please enter a valid phone number (10-15 digits).";
        }
    }

    // Current Class
    $current_class = trim($_POST["current_class"] ?? '');
    if (empty($current_class)) { $current_class_err = "Please enter current class."; }

    // Removed: Default Monthly Fee processing

    // --- 2. Handle Manual Virtual ID Input (Compulsory) ---
    $virtual_id_display = trim($_POST["virtual_id"] ?? '');
    $virtual_id = $virtual_id_display; // Assume valid until check

    if (empty($virtual_id)) {
        $virtual_id_err = "Please enter a Virtual ID.";
    } elseif (!preg_match("/^\d{5}$/", $virtual_id)) {
        $virtual_id_err = "Virtual ID must be exactly 5 digits.";
    } else {
        // Check for uniqueness in the database
        if ($link !== false) { // Ensure DB link is valid
            $check_sql = "SELECT virtual_id FROM students WHERE virtual_id = ?";
            if ($check_stmt = mysqli_prepare($link, $check_sql)) {
                mysqli_stmt_bind_param($check_stmt, "s", $virtual_id);
                if (mysqli_stmt_execute($check_stmt)) {
                    mysqli_stmt_store_result($check_stmt);
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $virtual_id_err = "This Virtual ID is already in use.";
                    }
                } else {
                    // Database execution error during uniqueness check
                    $virtual_id_err = "Database error during Virtual ID check: " . mysqli_stmt_error($check_stmt);
                    error_log("Database error during Virtual ID check: " . mysqli_stmt_error($check_stmt));
                }
                mysqli_stmt_close($check_stmt);
            } else {
                // Database preparation error for uniqueness check
                $virtual_id_err = "Database error preparing Virtual ID check: " . mysqli_error($link);
                 error_log("Database error preparing Virtual ID check: " . mysqli_error($link));
            }
        } else {
            // Database connection error before uniqueness check
            $virtual_id_err = "Database connection error prevented Virtual ID uniqueness check.";
        }
    }

    // --- 3. Handle Manual Password Input (Compulsory and Hashed) ---
    $password_plain = $_POST["password"] ?? ''; // Get the plain text password
    $password_display = ''; // NEVER display the submitted password

    if (empty($password_plain)) {
        $password_err = "Please enter a password.";
    } else {
        // Hash the password before storing
        $password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
        if ($password_hashed === false) {
            $password_err = "Internal error hashing password. Contact administration.";
            error_log("Password hashing failed for manual password entry during student creation.");
        }
    }


    // --- 4. Retrieve and Sanitize Optional Fields ---

    // Roll Number
    $roll_number_display = trim($_POST["roll_number"] ?? '');
    $roll_number_for_db = ($roll_number_display === '') ? null : $roll_number_display;
    // Add validation if needed, e.g., numeric only
    // if (!empty($roll_number_display) && !is_numeric($roll_number_display)) { $roll_number_err = "Roll number must be numeric."; }


    // Village
    $village_display = trim($_POST["village"] ?? '');
    $village_for_db = ($village_display === '') ? null : $village_display;


    // Date of Birth
    $date_of_birth_display = trim($_POST["date_of_birth"] ?? ''); // Expecting YYYY-MM-DD from <input type="date">
    $date_of_birth_for_db = null; // Initialize to null for database

    if (!empty($date_of_birth_display)) {
         // Validate and format the date string
         $dob_datetime = DateTime::createFromFormat('Y-m-d', $date_of_birth_display);

         // Check if DateTime object was created successfully and the original string matches the formatted string
         // This prevents invalid dates like '2023-02-30' from being considered valid
         if ($dob_datetime && $dob_datetime->format('Y-m-d') === $date_of_birth_display) {
             $date_of_birth_for_db = $dob_datetime->format('Y-m-d'); // Store as YYYY-MM-DD string for DATE column
         } else {
             $date_of_birth_err = "Invalid date format. Please use YYYY-MM-DD or date picker.";
              // The invalid date string remains in $date_of_birth_display for display
         }
     }
     // If $date_of_birth_display is empty, $date_of_birth_for_db remains null, which is correct for an optional field.


    // Takes Van (Checkbox) - KEEPING THIS
     $takes_van_display = isset($_POST['takes_van']) ? 'on' : ''; // Retain 'on' or '' for display
     $takes_van_for_db = ($takes_van_display === 'on') ? 1 : 0; // Store 1 or 0 for boolean/tinyint


    // WhatsApp Number
    $whatsapp_number_display = trim($_POST["whatsapp_number"] ?? '');
    $whatsapp_number_for_db = ($whatsapp_number_display === '') ? null : $whatsapp_number_display;

    // Previous Class
    $previous_class_display = trim($_POST["previous_class"] ?? '');
    $previous_class_for_db = ($previous_class_display === '') ? null : $previous_class_display;

    // Previous School
    $previous_school_display = trim($_POST["previous_school"] ?? '');
    $previous_school_for_db = ($previous_school_display === '') ? null : $previous_school_display;

    // Previous Marks Percentage
    $previous_marks_percentage_input = filter_input(INPUT_POST, 'previous_marks_percentage', FILTER_UNSAFE_RAW);
    $previous_marks_percentage_display = $previous_marks_percentage_input ?? ''; // Retain user input for display
    $previous_marks_percentage_for_db = null; // Initialize
    if ($previous_marks_percentage_input !== '' && $previous_marks_percentage_input !== null) {
        $filtered_marks = filter_var($previous_marks_percentage_input, FILTER_VALIDATE_FLOAT);
        if ($filtered_marks !== false && $filtered_marks >= 0 && $filtered_marks <= 100) {
            $previous_marks_percentage_for_db = $filtered_marks; // Store valid float for DB
        }
    }

    // Current Marks Percentage
    $current_marks_input = filter_input(INPUT_POST, 'current_marks', FILTER_UNSAFE_RAW);
    $current_marks_display = $current_marks_input ?? ''; // Retain user input for display
    $current_marks_for_db = null; // Initialize
    if ($current_marks_input !== '' && $current_marks_input !== null) {
        $filtered_marks = filter_var($current_marks_input, FILTER_VALIDATE_FLOAT);
        if ($filtered_marks !== false && $filtered_marks >= 0 && $filtered_marks <= 100) {
            $current_marks_for_db = $filtered_marks; // Store valid float for DB
        }
    }

    // Address
    $address_display = trim($_POST['address'] ?? '');
    $address_for_db = ($address_display === '') ? null : $address_display;

    // Pincode
    $pincode_display = trim($_POST['pincode'] ?? '');
    $pincode_for_db = ($pincode_display === '') ? null : $pincode_display;

    // State
    $state_display = trim($_POST['state'] ?? '');
    $state_for_db = ($state_display === '') ? null : $state_display;


    // --- 5. Photo Upload Handling using Cloudinary ---
    // Check if a file was uploaded for 'student_photo' AND there was no PHP file upload error
    if (isset($_FILES["student_photo"]) && $_FILES["student_photo"]["error"] != UPLOAD_ERR_NO_FILE && $_FILES["student_photo"]["error"] == UPLOAD_ERR_OK) {

        // Call the Cloudinary upload helper function
        // Make sure the 'cloudinary_upload_handler.php' file defines this function and handles credentials
        $uploadResult = uploadToCloudinary($_FILES["student_photo"], 'student_photos'); // Use 'student_photos' folder in Cloudinary

        if (isset($uploadResult['error'])) {
            $photo_upload_err = $uploadResult['error'];
            $photo_url = null; // Ensure DB URL is null on error
            error_log("Cloudinary upload error: " . $photo_upload_err); // Log the Cloudinary error
        } else {
            $photo_url = $uploadResult['secure_url']; // Get the secure URL
            $uploaded_photo_url_on_error = $photo_url; // Retain URL on other form errors
        }
    } elseif (isset($_FILES["student_photo"]) && $_FILES["student_photo"]["error"] != UPLOAD_ERR_NO_FILE) {
         // Handle potential PHP file upload errors (excluding UPLOAD_ERR_NO_FILE)
          $phpUploadErrors = [
              UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive.',
              UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive.',
              UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
              UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
              UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
              UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
          ];
         $errorCode = $_FILES["student_photo"]["error"];
          $photo_upload_err = $phpUploadErrors[$errorCode] ?? "Unknown file upload error (Code: {$errorCode}).";
          error_log("PHP File Upload Error during student creation: " . $photo_upload_err);
         $photo_url = null; // Ensure DB URL is null on error
    }
    // If $_FILES["student_photo"]["error"] == UPLOAD_ERR_NO_FILE, $photo_url remains null, which is correct.


    // --- 6. Final Check for ALL errors before DB insert ---
    // Removed error check for default_monthly_fee_err
    $has_errors = !empty($full_name_err) || !empty($father_name_err) || !empty($mother_name_err) || !empty($phone_number_err) ||
                  !empty($current_class_err) || !empty($virtual_id_err) || !empty($password_err) ||
                  !empty($photo_upload_err) || !empty($roll_number_err) || !empty($village_err) || !empty($date_of_birth_err);


    if (!$has_errors) {

        // --- 7. Database Insertion ---
        // Update the SQL query to remove default_monthly_fee from the column list
        $sql = "INSERT INTO students (full_name, father_name, mother_name, phone_number, virtual_id, password, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, address, pincode, state, photo_filename, roll_number, village, date_of_birth, takes_van) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // Removed default_monthly_fee

        // Check if database connection is valid
        if ($link !== false) {
            if ($stmt = mysqli_prepare($link, $sql)) {
                // Binding parameters - update the type string and variable list
                // s: string, i: integer, d: double/float.
                // Columns in SQL: full_name, father_name, mother_name, phone_number, virtual_id, password, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, address, pincode, state, photo_filename, roll_number, village, date_of_birth, takes_van (20 total)
                // Types needed: s, s, s, s, s, s, s, s, s, s, d, d, s, s, s, s, s, s, s, i
                // Bind Types String: ssssssssssddsssssssi (10 s, 2 d, 6 s, 1 s, 1 i = 20 characters)

                $bind_types = "ssssssssssddsssssssi"; // Updated bind types string

                // Ensure the variables listed here EXACTLY match the columns in the SQL
                // and the types in $bind_types, IN ORDER.
                mysqli_stmt_bind_param($stmt, $bind_types,
                    $full_name, // s
                    $father_name, // s
                    $mother_name, // s
                    $phone_number, // s
                    $virtual_id, // s
                    $password_hashed, // s
                    $whatsapp_number_for_db, // s
                    $current_class, // s
                    $previous_class_for_db, // s
                    $previous_school_for_db, // s
                    $previous_marks_percentage_for_db, // d
                    $current_marks_for_db, // d
                    $address_for_db, // s
                    $pincode_for_db, // s
                    $state_for_db, // s
                    $photo_url, // s
                    $roll_number_for_db, // s
                    $village_for_db, // s
                    $date_of_birth_for_db, // s (DATE is bound as string)
                    $takes_van_for_db // i
                    // default_monthly_fee is REMOVED
                );

                if (mysqli_stmt_execute($stmt)) {
                    // --- Success Message ---
                    $insert_message = "<p class='text-green-600'>Student record created successfully.</p>";
                    $insert_message .= "<p class='text-blue-800 font-semibold'>Virtual ID: <strong>" . htmlspecialchars($virtual_id) . "</strong></p>";
                    // DO NOT display the entered password!
                    $insert_message .= "<p class='text-blue-600 text-sm mt-2'>Please note down the Virtual ID and the password you entered to provide to the student.</p>";

                    // Clear form fields and display variables after successful insertion
                    $full_name = $father_name = $mother_name = $phone_number = $current_class = "";

                    // Clear display variables for fields that remain in the form
                    $roll_number_display = $village_display = $date_of_birth_display = "";
                    $takes_van_display = ""; // Clear checkbox state
                    // Removed clear for default_monthly_fee_display

                    // Clear previous display variables
                    $whatsapp_number_display = $previous_class_display = $previous_school_display = "";
                    $previous_marks_percentage_display = $current_marks_display = "";
                    $address_display = $pincode_display = $state_display = "";
                    $virtual_id_display = ''; // Clear manual ID field
                    $password_display = ''; // Stays empty
                    $uploaded_photo_url_on_error = ''; // Clear saved photo URL

                     // Reset nullable/hashed variables for next potential insert
                    $roll_number_for_db = $village_for_db = $date_of_birth_for_db = null;
                    $takes_van_for_db = false;
                    // Removed reset for default_monthly_fee_for_db

                    $whatsapp_number_for_db = $previous_class_for_db = $previous_school_for_db = null;
                    $previous_marks_percentage_for_db = $current_marks_for_db = null;
                    $address_for_db = $pincode_for_db = $state_for_db = null;
                    $photo_url = null; // Reset photo URL storage
                    $virtual_id = '';
                    $password_plain = '';
                    $password_hashed = null;

                } else {
                     // Database execution error during insert
                     $insert_message = "<p class='text-red-600'>Error inserting record: " . mysqli_stmt_error($stmt) . "</p>";
                     error_log("MySQL Error inserting student record: " . mysqli_stmt_error($stmt));
                    // Submitted values are retained
                    // $uploaded_photo_url_on_error will keep the photo URL if upload succeeded but DB failed
                }

                mysqli_stmt_close($stmt);
            } else {
                 // Database preparation error for insert
                 $insert_message = "<p class='text-red-600'>Error preparing statement: " . mysqli_error($link) . "</p>";
                 error_log("MySQL Error preparing insert statement: " . mysqli_error($link));
                // Submitted values are retained
                // $uploaded_photo_url_on_error will keep the photo URL if upload succeeded before prepare failed
            }
        } else {
             // Database connection error was already handled
             if (empty($insert_message)) {
                 $insert_message = "<p class='text-red-600'>Database connection error. Cannot save record.</p>";
             }
             // Submitted values are retained
             // $uploaded_photo_url_on_error will keep the photo URL if upload succeeded
        }


    } else {
        // If there were any validation errors
        $insert_message = "<p class='text-orange-600'>Please correct the errors below.</p>";
    }
} else {
    // GET request: variables are already initialized at the top to empty/null
    // $uploaded_photo_url_on_error is empty on first load
    // $takes_van_display is empty (false)
}

// Close database connection (if it was successfully opened)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
    mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Create Student</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Base body background will be set by JS */
        body {
             min-height: 100vh;
        }
        .form-error {
            color: #dc3545; /* Tailwind's red-600 */
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25em;
            display: block;
        }
         /* Apply invalid state styling to form inputs */
         .form-control.is-invalid {
             border-color: #dc3545; /* Tailwind's red-600 */
              padding-right: 2.25rem; /* Make space for an icon if needed */
              background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5.5 8.5v-3h1v3h-1zM6 9.5v.5h-1v-.5h1z'/%3e%3c/svg%3e");
              background-repeat: no-repeat;
              background-position: right 0.5625rem center;
              background-size: 1.125rem 1.125rem;
         }
          /* Specific styling for the custom file upload label when invalid */
         .custom-file-upload.is-invalid {
              border-color: #dc3545; /* Tailwind's red-600 */
              /* No background image needed here, error text is below */
         }

          /* Added styles for the message box */
           .message-box {
               padding: 1rem;
               border-radius: 0.5rem;
               border: 1px solid transparent;
               margin-bottom: 1.5rem;
               /* Add specific color classes based on type */
           }
           .message-box p {
               margin: 0.5rem 0;
           }
           .message-box p:first-child { margin-top: 0; }
           .message-box p:last-child { margin-bottom: 0; }

           /* Specific message box color styles (using Tailwind colors) */
           .message-box.success { background-color: #d1fae5; border-color: #34d399; color: #065f46; } /* green-100, green-400, green-800 */
           .message-box.error { background-color: #fee2e2; border-color: #f87171; color: #991b1b; } /* red-100, red-400, red-800 */
           .message-box.warning { background-color: #fffbe Surveyed; border-color: #fb923c; color: #9a3412; } /* yellow-100, yellow-400, yellow-800 */
            .message-box.info { background-color: #e0f2f7; border-color: #22d3ee; color: #0e7490; } /* cyan-100, cyan-400, cyan-800 */


         /* Styles for the photo preview container */
         #photoPreviewContainer {
             width: 120px;
             height: 120px;
             border-radius: 50%; /* Make it round */
             background-color: #e2e8f0; /* Tailwind's gray-300 - background when no image */
             background-size: cover; /* Make image cover the container */
             background-position: center; /* Center the image */
             border: 2px solid #cbd5e1; /* Tailwind's gray-400 border */
             display: flex; /* For centering the SVG icon */
             justify-content: center;
             align-items: center;
             overflow: hidden; /* Hide parts of the image that exceed the border-radius */
              flex-shrink: 0; /* Prevent shrinking in flex layout */
         }
          /* Style for the SVG icon inside the container */
          #photoPreviewContainer svg {
             display: block; /* Show SVG by default */
             color: #9ca3af; /* Tailwind's gray-400 color for the icon */
          }
          /* Hide the SVG icon when an image is present */
          #photoPreviewContainer.has-image svg {
              display: none;
          }

         /* Hide the actual file input */
         #student_photo {
             display: none;
         }

         /* Style the label to look like a button */
         .custom-file-upload {
             display: inline-block;
             padding: 8px 16px;
             cursor: pointer;
             background-color: #6366f1; /* Tailwind's indigo-500 */
             color: white;
             border-radius: 0.375rem; /* rounded-md */
             font-weight: 600; /* font-semibold */
             font-size: 0.875rem; /* text-sm */
             border: none;
             transition: background-color 0.15s ease-in-out;
         }

         .custom-file-upload:hover {
             background-color: #4f46e5; /* Tailwind's indigo-600 */
         }

         /* Style for date input placeholder color */
          input[type="date"]::placeholder {
              color: #9ca3af; /* Tailwind gray-400 */
          }

          /* Style for checkbox and its label */
         .form-check {
             display: flex;
             align-items: center;
         }
         .form-check input[type="checkbox"] {
             /* Default styling is usually fine, Tailwind handles it */
             /* margin-right: 0.5rem; Space between checkbox and label */
         }


          /* Background styles for testing */
           .gradient-background-blue-cyan { background: linear-gradient(to right, #4facfe, #00f2fe); }
            .gradient-background-purple-pink { background: linear-gradient(to right, #a18cd1, #fbc2eb); }
             .gradient-background-green-teal { background: linear-gradient(to right, #a8edea, #fed6e3); }
             .solid-bg-gray { background-color: #f3f4f6; } /* Tailwind's gray-100 */
             .solid-bg-indigo { background-color: #4f46e5; } /* Tailwind's indigo-600 */

    </style>
     <!-- Script for background change and photo preview -->
    <script>
        // Function to set the body background class
        function setBackground(className) {
            const body = document.body;
            // Remove existing background classes
            body.classList.forEach(cls => {
                if (cls.startsWith('gradient-background-') || cls.startsWith('solid-bg-')) {
                    body.classList.remove(cls);
                }
            });
            // Add the selected background class
            body.classList.add(className);

            // Save preference to local storage (optional)
            localStorage.setItem('backgroundPreference', className);
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            // Load saved background preference on page load
            const savedBackground = localStorage.getItem('backgroundPreference');
            if (savedBackground) {
                 // Simple check to see if the saved class looks like one of our background classes
                 if (savedBackground.startsWith('gradient-background-') || savedBackground.startsWith('solid-bg-')) {
                     setBackground(savedBackground);
                 } else {
                     // If saved preference is invalid, remove it and set default
                      localStorage.removeItem('backgroundPreference');
                      setBackground('gradient-background-blue-cyan'); // Set default
                 }
            } else {
                // Set a default background if no preference is saved
                setBackground('gradient-background-blue-cyan'); // Default
            }


            const photoInput = document.getElementById('student_photo');
            const photoPreviewContainer = document.getElementById('photoPreviewContainer');
            // Get the error span specifically for the photo input (using a data attribute for robustness)
            const photoErrorSpan = document.querySelector('span.form-error[data-for="student_photo"]');
            const photoLabel = document.querySelector('label[for="student_photo"]');


            // Function to update photo preview
            function updatePhotoPreview(url) {
                 if (url) {
                     photoPreviewContainer.style.backgroundImage = `url('${url}')`;
                     photoPreviewContainer.classList.add('has-image'); // Add class to hide SVG
                 } else {
                      photoPreviewContainer.style.backgroundImage = `none`;
                      photoPreviewContainer.classList.remove('has-image'); // Remove class to show SVG
                 }
            }

             // --- Initial preview check for retained value after error ---
             // If PHP set a URL on error ($uploaded_photo_url_on_error), display it.
             // Use JSON encoding to safely pass the PHP string to JavaScript
             const retainedPhotoUrl = <?php echo json_encode($uploaded_photo_url_on_error); ?>;
             if (retainedPhotoUrl) {
                 updatePhotoPreview(retainedPhotoUrl);
             } else {
                  // Ensure it shows the SVG if no URL is present initially
                  updatePhotoPreview(null);
             }


            // Event listener for file input change
            if (photoInput && photoPreviewContainer && photoErrorSpan && photoLabel) {
                photoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];

                    // Clear previous error message and invalid state
                     photoErrorSpan.textContent = '';
                     photoLabel.classList.remove('is-invalid'); // Remove invalid class from the label
                     e.target.classList.remove('is-invalid'); // Remove invalid class from the hidden input (less visible but good practice)
                     updatePhotoPreview(null); // Clear current preview

                    if (file) {
                         // Basic client-side validation (server-side is required for security)
                         const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                         const maxSize = 10 * 1024 * 1024; // 10MB client-side check (match server-side)

                         if (!allowedTypes.includes(file.type)) {
                             photoErrorSpan.textContent = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP.';
                             photoLabel.classList.add('is-invalid');
                             e.target.value = ''; // Clear the selected file
                             return; // Stop processing
                         }

                         if (file.size > maxSize) {
                             photoErrorSpan.textContent = 'File is too large (max 10MB).';
                             photoLabel.classList.add('is-invalid');
                             e.target.value = ''; // Clear the selected file
                             return; // Stop processing
                         }

                        const reader = new FileReader();

                        reader.onload = function(e) {
                            updatePhotoPreview(e.target.result); // Use data URL for local preview
                        };

                        reader.onerror = function() {
                             photoErrorSpan.textContent = 'Error reading file.';
                             photoLabel.classList.add('is-invalid');
                             e.target.value = ''; // Clear the selected file
                             updatePhotoPreview(null);
                        }

                        reader.readAsDataURL(file); // Read file as data URL for preview
                    } else {
                        // No file selected, clear preview and error message
                        updatePhotoPreview(null);
                         photoErrorSpan.textContent = ''; // Clear message if user cleared input
                    }
                });
            } else {
                console.error("Photo elements not found!");
            }
        });

    </script>
</head>
<body class="min-h-screen flex flex-col items-center py-8 px-4"> <!-- Background set by JS -->

     <header class="w-full max-w-3xl mx-auto mb-8">
         <nav class="flex justify-between items-center p-4 bg-white rounded-lg shadow-md">
              <!-- Path from 'admin/' up to 'School/' is '../' -->
              <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline transition duration-150 ease-in-out text-sm font-medium">
                 ← Back to Dashboard
              </a>
               <!-- Path from 'admin/' up to 'School/' is '../' -->
              <a href="../logout.php" class="text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium">
                 Logout
              </a>
         </nav>
     </header>

    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-3xl mx-auto">
        <h2 class="text-2xl font-bold mb-8 text-center text-gray-800">Create New Student Record</h2>

        <?php
        // Display insertion messages
        if (!empty($insert_message)) {
             $message_class = 'message-box ';
             // Determine class based on message content
             if (strpos($insert_message, 'successfully') !== false) {
                 $message_class .= 'success';
             } elseif (strpos($insert_message, 'Error') !== false) {
                  $message_class .= 'error';
             } else { // Covers 'Please correct errors' and other warnings/infos
                 $message_class .= 'warning';
             }
            echo "<div class='" . $message_class . "'>";
            // The message already contains <p> tags and htmlspecialchars where needed in PHP
            echo $insert_message; // Output the raw message HTML
            echo "</div>";
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">

            <!-- Photo Upload Section -->
            <div class="md:col-span-2 flex flex-col items-center gap-4 mb-4">
                <label class="block text-sm font-medium text-gray-700 text-center w-full">Student Photo (Optional)</label>
                 <!-- Photo Preview Container -->
                 <div id="photoPreviewContainer" class="relative flex-shrink-0"
                      style="<?php echo (!empty($uploaded_photo_url_on_error)) ? 'background-image: url(\'' . htmlspecialchars($uploaded_photo_url_on_error) . '\');' : ''; ?>">
                     <!-- Default SVG icon -->
                     <svg class="h-full w-full text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                         <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"/>
                     </svg>
                 </div>

                <div>
                    <!-- Custom styled label acts as the button -->
                    <label for="student_photo" class="custom-file-upload <?php echo (!empty($photo_upload_err)) ? 'is-invalid' : ''; ?>">
                       Choose Photo
                    </label>
                     <!-- Hidden file input -->
                     <input type="file" name="student_photo" id="student_photo" accept="image/jpeg, image/png, image/gif, image/webp">
                </div>

                <!-- Specific error span for photo upload -->
                <span class="form-error text-xs text-center" data-for="student_photo"><?php echo htmlspecialchars($photo_upload_err); ?></span>
                <span class="text-gray-500 text-xs italic block text-center mt-1">Optional. Max 10MB, JPG, PNG, GIF, WEBP formats.</span>
            </div>

            <!-- Section: Personal Information -->
            <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Personal Information</h3></div>

            <!-- Row: Name, Father, Mother -->
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" id="full_name" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($full_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($full_name); ?>" placeholder="Enter student's full name" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($full_name_err); ?></span>
            </div>

            <div>
                <label for="father_name" class="block text-sm font-medium text-gray-700 mb-1">Father's Name <span class="text-red-500">*</span></label>
                <input type="text" name="father_name" id="father_name" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($father_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($father_name); ?>" placeholder="Enter father's name" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($father_name_err); ?></span>
            </div>

            <div>
                <label for="mother_name" class="block text-sm font-medium text-gray-700 mb-1">Mother's Name <span class="text-red-500">*</span></label>
                <input type="text" name="mother_name" id="mother_name" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($mother_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($mother_name); ?>" placeholder="Enter mother's name" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($mother_name_err); ?></span>
            </div>

            <div>
                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth (Optional)</label>
                <input type="date" name="date_of_birth" id="date_of_birth" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($date_of_birth_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($date_of_birth_display); ?>">
                <span class="form-error text-xs"><?php echo htmlspecialchars($date_of_birth_err); ?></span>
            </div>

             <!-- Row: Village -->
             <div class="md:col-span-2">
                 <label for="village" class="block text-sm font-medium text-gray-700 mb-1">Village (Optional)</label>
                 <input type="text" name="village" id="village" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($village_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($village_display); ?>" placeholder="Optional village name">
                 <span class="form-error text-xs"><?php echo htmlspecialchars($village_err); ?></span>
            </div>


            <!-- Section: Contact Information -->
            <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-6">Contact Information</h3></div>

             <!-- Row: Phone Number, Whatsapp Number -->
             <div>
                 <label for="phone_number" class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                 <input type="text" name="phone_number" id="phone_number" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="e.g., 01234567890" required>
                 <span class="form-error text-xs"><?php echo htmlspecialchars($phone_number_err); ?></span>
             </div>

             <div>
                <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number (Optional)</label>
                <input type="text" name="whatsapp_number" id="whatsapp_number" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($whatsapp_number_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic"></span>
            </div>

            <!-- Row: Address spans two columns -->
             <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address (Optional)</label>
                <textarea name="address" id="address" rows="3" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Optional"><?php echo htmlspecialchars($address_display); ?></textarea>
                 <span class="text-gray-500 text-xs italic"></span>
            </div>

            <!-- Row: Pincode, State -->
             <div>
                <label for="pincode" class="block text-sm font-medium text-gray-700 mb-1">Pincode (Optional)</label>
                <input type="text" name="pincode" id="pincode" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($pincode_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic"></span>
            </div>

             <div>
                <label for="state" class="block text-sm font-medium text-gray-700 mb-1">State (Optional)</label>
                <input type="text" name="state" id="state" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($state_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic"></span>
            </div>


            <!-- Section: Academic Information -->
             <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-6">Academic Information</h3></div>

            <!-- Row: Current Class, Roll Number -->
             <div>
                <label for="current_class" class="block text-sm font-medium text-gray-700 mb-1">Current Class <span class="text-red-500">*</span></label>
                <input type="text" name="current_class" id="current_class" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($current_class_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($current_class); ?>" placeholder="e.g., Class 5, UKG" required>
                <span class="form-error text-xs"><?php echo htmlspecialchars($current_class_err); ?></span>
            </div>

             <div>
                 <label for="roll_number" class="block text-sm font-medium text-gray-700 mb-1">Roll Number (Optional)</label>
                 <input type="text" name="roll_number" id="roll_number" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($roll_number_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($roll_number_display); ?>" placeholder="Optional roll number">
                 <span class="form-error text-xs"><?php echo htmlspecialchars($roll_number_err); ?></span>
            </div>


            <!-- Row: Previous Class, Previous School -->
             <div>
                <label for="previous_class" class="block text-sm font-medium text-gray-700 mb-1">Previous Class (Optional)</label>
                <input type="text" name="previous_class" id="previous_class" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_class_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic"></span>
            </div>

             <div>
                <label for="previous_school" class="block text-sm font-medium text-gray-700 mb-1">Previous School (Optional)</label>
                <input type="text" name="previous_school" id="previous_school" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_school_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic"></span>
            </div>

             <!-- Row: Previous Marks, Current Marks -->
            <div>
                <label for="previous_marks_percentage" class="block text-sm font-medium text-gray-700 mb-1">Previous Marks (%) (Optional)</label>
                <input type="number" name="previous_marks_percentage" id="previous_marks_percentage" step="0.01" min="0" max="100" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($previous_marks_percentage_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic">Enter percentage (0-100).</span>
            </div>

            <div>
                <label for="current_marks" class="block text-sm font-medium text-gray-700 mb-1">Current Marks (%) (Optional)</label>
                <input type="number" name="current_marks" id="current_marks" step="0.01" min="0" max="100" class="block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($current_marks_display); ?>" placeholder="Optional">
                 <span class="text-gray-500 text-xs italic">Enter percentage (0-100).</span>
            </div>


            <!-- Section: Fee Information (Simplified) -->
             <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-6">Fee Information</h3></div>

            <!-- Removed: Default Monthly Fee input field -->

             <!-- Row: Takes Van checkbox - KEEPING THIS -->
            <div class="md:col-span-2"> <!-- Make the checkbox span both columns -->
                <div class="form-check flex items-center pt-2">
                     <input type="checkbox" name="takes_van" id="takes_van" value="on" class="form-checkbox h-4 w-4 text-indigo-600 transition duration-150 ease-in-out border-gray-300 rounded focus:ring-indigo-500"
                            <?php echo ($takes_van_display === 'on') ? 'checked' : ''; ?>>
                     <label for="takes_van" class="ml-2 block text-sm font-medium text-gray-700">Student takes Van Service</label>
                 </div>
                 <span class="form-error text-xs"><?php echo htmlspecialchars($takes_van_err); ?></span>
            </div>

            <!-- Removed: Default Van Fee, Admission Fee, Other One-Time Fees inputs -->


             <!-- Section: Login Credentials -->
             <div class="md:col-span-2"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4 mt-6">Login Credentials</h3></div>

            <!-- Row: Virtual ID, Password -->
            <div>
                 <label for="virtual_id" class="block text-sm font-medium text-gray-700 mb-1">Virtual ID (5 Digits) <span class="text-red-500">*</span></label>
                 <input type="text" name="virtual_id" id="virtual_id" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($virtual_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($virtual_id_display); ?>" placeholder="Enter 5-digit ID" required maxlength="5" pattern="\d{5}" title="Virtual ID must be exactly 5 digits">
                 <span class="form-error text-xs"><?php echo htmlspecialchars($virtual_id_err); ?></span>
            </div>

            <div>
                 <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                 <!-- NOTE: value attribute is intentionally left empty for password fields for security -->
                 <input type="password" name="password" id="password" class="form-control block w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter password" required>
                 <span class="form-error text-xs"><?php echo htmlspecialchars($password_err); ?></span>
                  <span class="text-gray-500 text-xs italic block mt-1">Password will be securely stored (hashed).</span>
            </div>


            <!-- Button Row spans two columns -->
            <div class="md:col-span-2 flex items-center justify-center mt-8">
                <button type="submit" class="w-full md:w-auto px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">Create Student Record</button>
            </div>

             <!-- Required fields note spans two columns -->
             <div class="md:col-span-2 text-center text-sm text-gray-600 mt-4">
                 Fields marked with <span class="text-red-500">*</span> are required.
             </div>

        </form>
    </div>

    <!-- Optional: Add buttons for background change here -->
    <div class="mt-8 text-center text-sm text-gray-700">
        Choose Background:
        <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
        <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
         <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
         <button class="ml-2 px-2 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" onclick="setBackground('solid-bg-gray')">Gray</button>
         <button class="ml-2 px-2 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs" onclick="setBackground('solid-bg-indigo')">Indigo</button>
    </div>


</body>
</html>