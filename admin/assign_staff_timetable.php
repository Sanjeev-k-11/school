<?php
// School/admin/assign_staff_timetable.php

session_start();

// Adjust path to config.php based on directory structure
// If this file is in School/admin/, and config.php is in School/, path is "../config.php"
require_once "../config.php";

// --- ACCESS CONTROL ---
// Define roles that are allowed to access this page (e.g., Principal, Staff, Admin)
// Added 'admin' and 'principal' as they typically handle such tasks.
$allowed_roles_assign_timetable = ['admin', 'principal'];

// Check if the user is NOT logged in, OR if they are in the session but their role
// is NOT in the list of allowed roles.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_roles_assign_timetable)) {
    // Store plain text message in session
    $_SESSION['operation_message'] = "Access denied. You do not have permission to assign staff timetables.";
    // Path from 'admin/' up to 'School/' is '../'
    header("location: ../login.php"); // Redirect to login
    exit;
}

// --- RETRIEVE USER INFORMATION FROM SESSION ---
// Used for displaying name/role if needed (though sidebar might handle this)
$logged_in_staff_name = $_SESSION['name'] ?? 'User';
$logged_in_staff_role = $_SESSION['role'] ?? 'Role Unknown';

// --- Fetch All Staff Members for Dropdown ---
$staff_list = [];
$fetch_staff_list_error = "";
$selected_staff_id = null; // Variable to hold the ID of the staff member currently being viewed/assigned

if ($link === false) {
     $fetch_staff_list_error = "Database connection error. Cannot fetch staff list.";
} else {
     $sql_fetch_staff_list = "SELECT staff_id, staff_name FROM staff ORDER BY staff_name ASC";
     if ($result_staff_list = mysqli_query($link, $sql_fetch_staff_list)) {
         if (mysqli_num_rows($result_staff_list) > 0) {
             $staff_list = mysqli_fetch_all($result_staff_list, MYSQLI_ASSOC);

             // Determine which staff member is selected: Check GET first, then POST, then default to the first
             $requested_staff_id = null;
             if (isset($_GET['staff_id'])) {
                 $requested_staff_id = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
             } elseif (isset($_POST['selected_staff_id'])) {
                 $requested_staff_id = filter_input(INPUT_POST, 'selected_staff_id', FILTER_VALIDATE_INT);
             }

             // Validate requested ID against the fetched list
             $found_valid_id = false;
             if ($requested_staff_id !== false && $requested_staff_id > 0) {
                 foreach ($staff_list as $staff) {
                     if ($staff['staff_id'] == $requested_staff_id) {
                         $selected_staff_id = $requested_staff_id;
                         $found_valid_id = true;
                         break;
                     }
                 }
             }

             // If no valid ID was found (or no ID requested), default to the first staff member
             if (!$found_valid_id && isset($staff_list[0])) {
                  $selected_staff_id = $staff_list[0]['staff_id'];
                  // Add a warning if an invalid ID was attempted
                  if ($requested_staff_id !== null) {
                       $_SESSION['operation_message'] = "Warning: Invalid staff ID selected or provided. Displaying timetable for " . htmlspecialchars($staff_list[0]['staff_name']) . ".";
                  }
             } elseif (!$found_valid_id && empty($staff_list)) {
                 // Case where there are no staff members at all - selected_staff_id remains null
             }


         } else {
              $fetch_staff_list_error = "No staff members found in the database. Please add staff first.";
              $selected_staff_id = null; // Ensure no staff is selected if list is empty
         }
         mysqli_free_result($result_staff_list);
     } else {
          $fetch_staff_list_error = "Error fetching staff list: " . mysqli_error($link);
          error_log("Assign Timetable: Error fetching staff list: " . mysqli_error($link));
          $selected_staff_id = null;
     }
}

// --- Variables for Form Input and Errors (for adding entries) ---
$day_of_week = $time_slot = $class_taught = $subject_taught = "";
$day_of_week_err = $time_slot_err = $class_taught_err = $subject_taught_err = "";
$add_entry_message = ""; // Message specifically for the add entry form submission (stays on the same page on validation error)

// --- Handle POST Request (Adding Timetable Entry) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add_timetable_entry' && $selected_staff_id !== null) {
     // Check if a staff member is actually selected before processing POST
     // Note: $selected_staff_id was already determined based on POST/GET/Default above

    // Validate Staff ID again (redundant check for safety, ensures $selected_staff_id is valid)
    $is_valid_staff_id_for_post = false;
    $selected_staff_name_for_message = "Unknown Staff";
    if ($selected_staff_id !== false && $selected_staff_id > 0) {
        foreach ($staff_list as $staff) {
            if ($staff['staff_id'] == $selected_staff_id) {
                $is_valid_staff_id_for_post = true;
                $selected_staff_name_for_message = $staff['staff_name'];
                break;
            }
        }
    }

    if (!$is_valid_staff_id_for_post) {
         $add_entry_message = "Error: Invalid or no staff member selected for adding timetable entry.";
         // Keep submitted form values for repopulation on error
         $day_of_week = trim($_POST["day_of_week"] ?? '');
         $time_slot = trim($_POST["time_slot"] ?? '');
         $class_taught = trim($_POST["class_taught"] ?? '');
         $subject_taught = trim($_POST["subject_taught"] ?? '');

    } else {
        // Staff ID is valid, proceed with other validations

        // Validate Day of Week
        if (empty(trim($_POST["day_of_week"] ?? ''))) {
            $day_of_week_err = "Please select the day.";
        } else {
            $day_of_week = trim($_POST["day_of_week"]);
             $allowed_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
             if (!in_array($day_of_week, $allowed_days)) {
                 $day_of_week_err = "Invalid day selected.";
             }
        }

        // Validate Time Slot
        if (empty(trim($_POST["time_slot"] ?? ''))) {
            $time_slot_err = "Please enter the time slot.";
        } else {
            $time_slot = trim($_POST["time_slot"]);
        }

        // Validate Class Taught
        if (empty(trim($_POST["class_taught"] ?? ''))) {
            $class_taught_err = "Please enter the class.";
        } else {
            $class_taught = trim($_POST["class_taught"]);
        }

        // Validate Subject Taught
        if (empty(trim($_POST["subject_taught"] ?? ''))) {
            $subject_taught_err = "Please enter the subject.";
        } else {
            $subject_taught = trim($_POST["subject_taught"]);
        }


        // Check input errors before inserting into database
        if (empty($day_of_week_err) && empty($time_slot_err) && empty($class_taught_err) && empty($subject_taught_err)) {

            // Prepare an insert statement for staff_timetables
            $sql_insert_entry = "INSERT INTO staff_timetables (staff_id, day_of_week, time_slot, class_taught, subject_taught) VALUES (?, ?, ?, ?, ?)";

            if ($link === false) {
                 $add_entry_message = "Database connection error. Could not add timetable entry.";
                 error_log("Assign Timetable DB connection failed during INSERT: " . mysqli_connect_error());
                  // Keep submitted values for repopulation
                 $day_of_week = trim($_POST["day_of_week"] ?? '');
                 $time_slot = trim($_POST["time_slot"] ?? '');
                 $class_taught = trim($_POST["class_taught"] ?? '');
                 $subject_taught = trim($_POST["subject_taught"] ?? '');
            } elseif ($stmt_insert_entry = mysqli_prepare($link, $sql_insert_entry)) {

                // Bind variables
                mysqli_stmt_bind_param($stmt_insert_entry, "issss",
                    $selected_staff_id, // Use the validated staff ID
                    $day_of_week,
                    $time_slot,
                    $class_taught,
                    $subject_taught
                );

                // Attempt to execute the prepared statement
                if (mysqli_stmt_execute($stmt_insert_entry)) {
                    // Entry added successfully
                     $_SESSION['operation_message'] = "Timetable entry added successfully for " . htmlspecialchars($selected_staff_name_for_message) . ": " . htmlspecialchars($day_of_week) . " at " . htmlspecialchars($time_slot) . " (" . htmlspecialchars($class_taught) . " - " . htmlspecialchars($subject_taught) . ")";
                    // Clear form fields after successful submission
                    $day_of_week = $time_slot = $class_taught = $subject_taught = "";

                    // --- Important: Redirect after POST to prevent form resubmission ---
                    // Append selected_staff_id to redirect URL to stay on the same staff member's timetable
                    header("location: " . htmlspecialchars($_SERVER["PHP_SELF"]) . "?staff_id=" . $selected_staff_id);
                    exit();

                } else {
                     // Handle insertion error
                     $add_entry_message = "Error: Could not add timetable entry. " . mysqli_stmt_error($stmt_insert_entry);
                     error_log("Assign Timetable INSERT query failed: " . mysqli_stmt_error($stmt_insert_entry));
                      // Keep submitted values for repopulation on error
                     $day_of_week = trim($_POST["day_of_week"] ?? '');
                     $time_slot = trim($_POST["time_slot"] ?? '');
                     $class_taught = trim($_POST["class_taught"] ?? '');
                     $subject_taught = trim($_POST["subject_taught"] ?? '');
                }

                // Close statement
                mysqli_stmt_close($stmt_insert_entry);
            } else {
                 $add_entry_message = "Error: Could not prepare insert statement for timetable entry. " . mysqli_error($link);
                 error_log("Assign Timetable prepare INSERT statement failed: " . mysqli_error($link));
                  // Keep submitted values for repopulation on error (same as above)
                 $day_of_week = trim($_POST["day_of_week"] ?? '');
                 $time_slot = trim($_POST["time_slot"] ?? '');
                 $class_taught = trim($_POST["class_taught"] ?? '');
                 $subject_taught = trim($_POST["subject_taught"] ?? '');
            }
        } else {
             // Validation errors occurred on POST
             $add_entry_message = "Please correct the errors in the form below.";
             // Submitted values are already kept in variables above
        }
     } // End of Valid POST Staff ID check check (though $selected_staff_id is validated earlier now)

} // End of POST request handling (only for action=add_timetable_entry)

// --- Handle POST Request (Deleting Timetable Entry - Placeholder) ---
// This is a simple placeholder. A robust solution would use a separate script or AJAX.
// We'll simulate a simple POST-based delete for demonstration, though AJAX is preferred.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_timetable_entry') {

     $entry_id_to_delete = filter_input(INPUT_POST, 'entry_id_to_delete', FILTER_VALIDATE_INT);
     $staff_id_after_delete = filter_input(INPUT_POST, 'selected_staff_id', FILTER_VALIDATE_INT); // Need to know which staff to redirect to

     // Validate inputs
     if ($entry_id_to_delete === false || $entry_id_to_delete <= 0 || $staff_id_after_delete === false || $staff_id_after_delete <= 0) {
          $_SESSION['operation_message'] = "Error: Invalid request to delete timetable entry.";
     } else {
          // Ensure the entry actually belongs to the selected staff member (important security check)
          $sql_check_ownership = "SELECT staff_id FROM staff_timetables WHERE staff_timetable_id = ?";
          if ($stmt_check = mysqli_prepare($link, $sql_check_ownership)) {
               mysqli_stmt_bind_param($stmt_check, "i", $entry_id_to_delete);
               if (mysqli_stmt_execute($stmt_check)) {
                   $result_check = mysqli_stmt_get_result($stmt_check);
                   $entry_row = mysqli_fetch_assoc($result_check);
                   mysqli_free_result($result_check);
                   mysqli_stmt_close($stmt_check);

                   if ($entry_row && $entry_row['staff_id'] == $staff_id_after_delete) {
                       // Prepare delete statement
                       $sql_delete = "DELETE FROM staff_timetables WHERE staff_timetable_id = ?";
                       if ($stmt_delete = mysqli_prepare($link, $sql_delete)) {
                           mysqli_stmt_bind_param($stmt_delete, "i", $entry_id_to_delete);
                           if (mysqli_stmt_execute($stmt_delete)) {
                               // Success
                               $_SESSION['operation_message'] = "Timetable entry deleted successfully.";
                           } else {
                               // Delete error
                               $_SESSION['operation_message'] = "Error: Could not delete timetable entry. " . mysqli_stmt_error($stmt_delete);
                               error_log("Assign Timetable DELETE query failed: " . mysqli_stmt_error($stmt_delete));
                           }
                           mysqli_stmt_close($stmt_delete);
                       } else {
                            // Prepare delete statement failed
                            $_SESSION['operation_message'] = "Error: Could not prepare delete statement. " . mysqli_error($link);
                            error_log("Assign Timetable prepare DELETE statement failed: " . mysqli_error($link));
                       }
                   } else {
                       // Ownership check failed (entry doesn't exist or doesn't belong to staff)
                       $_SESSION['operation_message'] = "Error: Timetable entry not found or does not belong to the selected staff.";
                   }
               } else {
                   // Ownership check execution failed
                   $_SESSION['operation_message'] = "Error checking timetable entry ownership. " . mysqli_stmt_error($stmt_check);
                   error_log("Assign Timetable ownership check execution failed: " . mysqli_stmt_error($stmt_check));
               }
          } else {
               // Prepare ownership check failed
               $_SESSION['operation_message'] = "Error preparing ownership check statement. " . mysqli_error($link);
               error_log("Assign Timetable prepare ownership check failed: " . mysqli_error($link));
          }
     }

     // Redirect back to the same staff member's page
     header("location: " . htmlspecialchars($_SERVER["PHP_SELF"]) . "?staff_id=" . $staff_id_after_delete);
     exit();

} // End of POST request handling (for action=delete_timetable_entry)


// --- Fetch Assigned Timetable for Selected Staff ---
$staff_assigned_timetable = [];
$fetch_assigned_timetable_message = "";
$selected_staff_name = "Select a Staff Member"; // Default display name

if ($selected_staff_id !== null && $link !== false) {
    // Find the name of the selected staff member from the fetched list
    foreach ($staff_list as $staff) {
        if ($staff['staff_id'] == $selected_staff_id) {
            $selected_staff_name = $staff['staff_name'];
            break;
        }
    }

    // Fetch timetable entries for the selected staff ID
    // Order by day (using FIELD for correct day order) and then time slot
    $sql_fetch_assigned = "SELECT staff_timetable_id, day_of_week, time_slot, class_taught, subject_taught FROM staff_timetables WHERE staff_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), time_slot ASC";

    if ($stmt_assigned = mysqli_prepare($link, $sql_fetch_assigned)) {
        mysqli_stmt_bind_param($stmt_assigned, "i", $selected_staff_id);
        if (mysqli_stmt_execute($stmt_assigned)) {
            $result_assigned = mysqli_stmt_get_result($stmt_assigned);
            if ($result_assigned) {
                $staff_assigned_timetable = mysqli_fetch_all($result_assigned, MYSQLI_ASSOC);
                if (empty($staff_assigned_timetable)) {
                    $fetch_assigned_timetable_message = "No timetable entries assigned to " . htmlspecialchars($selected_staff_name) . " yet.";
                } // No specific success message needed if entries exist, the table itself is the success display.
            } else {
                 $fetch_assigned_timetable_message = "Could not retrieve assigned timetable.";
                 error_log("Assign Timetable: Error getting result for assigned timetable: " . mysqli_stmt_error($stmt_assigned));
                 $staff_assigned_timetable = [];
            }
            if ($result_assigned) mysqli_free_result($result_assigned);
        } else {
            $fetch_assigned_timetable_message = "Error fetching assigned timetable: " . mysqli_stmt_error($stmt_assigned);
             error_log("Assign Timetable: Assigned timetable fetch query failed: " . mysqli_stmt_error($stmt_assigned));
             $staff_assigned_timetable = [];
        }
        if ($stmt_assigned) mysqli_stmt_close($stmt_assigned);
    } else {
         $fetch_assigned_timetable_message = "Error preparing assigned timetable fetch statement: " . mysqli_error($link);
         error_log("Assign Timetable: Could not prepare assigned timetable fetch statement: " . mysqli_error($link));
         $staff_assigned_timetable = [];
    }

} else {
     // If no staff members are in the list, or no staff is selected initially
     if (empty($staff_list)) {
          // Message handled by fetch_staff_list_error alert above
     } else {
         // Message "Please select a staff member" is implied by the dropdown default state and initial display
     }
}


// Check for and display messages from operations (e.g., from redirection after POST)
// Using this for success/error messages that need to survive a redirect
$operation_message_session = "";
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear the message after displaying
}


// Close database connection if it's open
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Staff Timetable - <?php echo htmlspecialchars(ucfirst($logged_in_staff_role)); ?></title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Link Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
    body {
        font-family: 'Inter', sans-serif;
        /* Remove padding-top as sidebar will be fixed/full height */
        /* Dynamic Background Animation */
        background: linear-gradient(-45deg, #b5e2ff, #d9f4ff, #b5e2ff, #d9f4ff);
        background-size: 400% 400%;
        animation: gradientAnimation 15s ease infinite;
        background-attachment: fixed;
        min-height: 100vh; /* Ensure body takes at least full viewport height */
        display: flex; /* Use flexbox for sidebar and content layout */
    }

    @keyframes gradientAnimation {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* Sidebar styling (assuming admin_sidebar.php provides this) */
     /* .admin-sidebar { ... } */

    /* Main content area styling */
    .main-content-area {
        flex-grow: 1; /* Allow main content to take remaining space */
        padding: 1.5rem; /* Add padding around the main content */
        overflow-y: auto; /* Allow scrolling if content is too long */
        /* max-width, mx-auto removed */
    }

    /* White card container */
    .content-card {
         background-color: #ffffff;
         padding: 1.5rem; /* p-6 */
         border-radius: 0.75rem; /* rounded-lg */
         box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
         border: 1px solid #e5e7eb; /* gray-200 border */
         width: 100%; /* Take full width of parent */
         margin-bottom: 2rem; /* Add space below card */
    }

    /* Custom styles for alerts - Consistent with other pages */
    .alert {
         border-left-width: 4px;
         padding: 1rem;
         margin-bottom: 1.5rem;
         border-radius: 0.375rem; /* rounded-md */
         box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
         display: flex;
         align-items: center;
         gap: 0.75rem;
         line-height: 1.5;
    }
    .alert-icon {
        flex-shrink: 0;
        width: 1.5rem;
        height: 1.5rem;
    }
    .alert p { margin: 0; }
     .alert-info { background-color: #e0f7fa; border-color: #0891b2; color: #0e7490; }
     .alert-success { background-color: #dcfce7; border-color: #22c55e; color: #15803d; }
     .alert-warning { background-color: #fff7ed; border-color: #f97316; color: #ea580c; }
     .alert-danger { background-color: #fee2e2; border-color: #ef4444; color: #b91c1c; }


     /* Form and Table Styling - Consistent with other pages */
    .form-group { margin-bottom: 1.25rem; }
    .form-label {
         display: block;
         font-size: 0.875rem;
         font-weight: 500;
         color: #374151;
         margin-bottom: 0.5rem;
    }
     .form-input, .form-select {
         display: block;
         width: 100%;
         padding: 0.625rem 1rem;
         font-size: 1rem;
         line-height: 1.5;
         color: #4b5563;
         background-color: #fff;
         border: 1px solid #d1d5db;
         border-radius: 0.375rem;
         box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.075);
         transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
     }
     .form-input:focus, .form-select:focus {
        border-color: #60a5fa;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.25);
     }
     .form-input.is-invalid, .form-select.is-invalid { border-color: #ef4444; }
    .form-error {
        color: #ef4444;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        display: block;
    }

     /* Table Styling */
     .data-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 1rem;
     }
     .data-table th,
     .data-table td {
         padding: 0.75rem 1rem;
         text-align: left;
         border-bottom: 1px solid #e5e7eb;
     }
     .data-table th {
         background-color: #f9fafb;
         font-size: 0.75rem;
         font-weight: 600;
         color: #6b7280;
         text-transform: uppercase;
         letter-spacing: 0.05em;
     }
     .data-table td {
         font-size: 0.875rem;
         color: #4b5563;
         line-height: 1.4;
     }
      .data-table tbody tr:hover {
           background-color: #f3f4f6;
      }

     /* Responsive table wrapper */
     .table-responsive {
         overflow-x: auto;
         -webkit-overflow-scrolling: touch;
         margin-bottom: 1rem;
     }

      /* Button Styling */
      .btn {
           display: inline-flex;
           align-items: center;
           justify-content: center;
           padding: 0.625rem 1.25rem;
           font-size: 1rem;
           font-weight: 500;
           line-height: 1.5;
           border-radius: 0.375rem;
           cursor: pointer;
           transition: all 0.15s ease-in-out;
           text-decoration: none;
           border: 1px solid transparent;
      }
       .btn-primary {
           color: #ffffff;
           background-color: #4f46e5;
           border-color: #4f46e5;
           box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      }
        .btn-primary:hover {
             background-color: #4338ca;
             border-color: #4338ca;
        }
        .btn-primary:focus {
             outline: none;
             box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.5);
        }

        .btn-secondary {
             color: #374151;
             background-color: #ffffff;
             border-color: #d1d5db;
             box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
         .btn-secondary:hover {
              background-color: #f9fafb;
         }
         .btn-secondary:focus {
              outline: none;
              box-shadow: 0 0 0 0.2rem rgba(209, 213, 219, 0.5);
         }

          /* Action Link (e.g., Delete Entry) */
          .action-link-danger {
              color: #dc2626; /* red-600 */
              text-decoration: none;
              font-size: 0.875rem;
              font-weight: 500;
          }
           .action-link-danger:hover {
                text-decoration: underline;
                color: #b91c1c; /* red-700 */
           }


</style>
</head>
<body>

<!-- Body now contains flex container for sidebar and main content -->
<?php
// INCLUDE SIDEBAR
// IMPORTANT: Verify the path to your admin_sidebar.php file relative to this file (admin/assign_staff_timetable.php)
// Example: If admin_sidebar.php is in the same directory as this file:
require_once "./admin_sidebar.php";
// If admin_sidebar.php is in School/includes/ e.g.:
// require_once "../includes/admin_sidebar.php";
?>

<!-- Main Content Area -->
<!-- Removed max-width and mx-auto from main tag for sidebar layout -->
<main class="main-content-area">

    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6 text-center">Assign Staff Timetable</h1>

    <?php
        // Display messages from session (e.g., from redirection)
        if (!empty($operation_message_session)) {
           $alert_class = 'alert-info';
           $icon_svg = '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'; // Info icon
           if (strpos($operation_message_session, 'successfully') !== false) {
               $alert_class = 'alert-success';
               $icon_svg = '<svg class="alert-icon text-green-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'; // Check icon
           } elseif (strpos($operation_message_session, 'Access denied') !== false || strpos($operation_message_session, 'Error') !== false || strpos($operation_message_session, 'Failed') !== false || strpos($operation_message_session, 'denied') !== false || strpos($operation_message_session, 'Could not') !== false) {
                $alert_class = 'alert-danger';
                $icon_svg = '<svg class="alert-icon text-red-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'; // X icon
           } elseif (strpos($operation_message_session, 'Warning') !== false || strpos($operation_message_session, 'not found') !== false || strpos($operation_message_session, 'Please correct') !== false || strpos($operation_message_session, 'unavailable') !== false || strpos($operation_message_session, 'no ') !== false) {
                $alert_class = 'alert-warning';
                $icon_svg = '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>'; // Warning icon
           }
           echo "<div class='alert " . $alert_class . "' role='alert'>" . $icon_svg;
           echo "<p>" . nl2br(htmlspecialchars($operation_message_session)) . "</p>";
           echo "</div>";
       }
    ?>

    <?php
        // Display fetch staff list error or empty message
        if (!empty($fetch_staff_list_error)) {
             echo "<div class='alert alert-warning' role='alert'>";
              echo '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>';
             echo "<p>" . nl2br(htmlspecialchars($fetch_staff_list_error)) . "</p>";
             echo "</div>";
        }
    ?>

     <?php if (!empty($staff_list)): ?>
     <div class="content-card mb-6"> <!-- Added margin-bottom -->
         <h2 class="text-xl font-semibold text-gray-800 mb-4">Select Staff Member</h2>
         <!-- Use GET method for staff selection to make it bookmarkable -->
         <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="flex flex-col sm:flex-row items-center gap-4">
              <div class="form-group flex-grow w-full sm:w-auto mb-0"> <!-- Removed default margin bottom, added responsive width -->
                   <label for="staff_select" class="form-label sr-only">Choose Staff:</label> <!-- sr-only hides label visually -->
                   <select name="staff_id" id="staff_select" class="form-select">
                        <?php foreach ($staff_list as $staff): ?>
                             <option value="<?php echo htmlspecialchars($staff['staff_id']); ?>" <?php echo ($selected_staff_id == $staff['staff_id']) ? 'selected' : ''; ?>>
                                  <?php echo htmlspecialchars($staff['staff_name']); ?> (ID: <?php echo htmlspecialchars($staff['staff_id']); ?>)
                             </option>
                        <?php endforeach; ?>
                   </select>
              </div>
              <div>
                   <button type="submit" class="btn btn-secondary w-full sm:w-auto">Load Timetable</button> <!-- Responsive button width -->
              </div>
         </form>
     </div>
     <?php endif; ?>


    <?php if ($selected_staff_id !== null && !empty($staff_list)): ?>
        <div class="content-card">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Timetable for <?php echo htmlspecialchars($selected_staff_name); ?></h2>

            <?php
             // Display message related to adding entries (validation errors, etc.)
             if (!empty($add_entry_message)) {
                 // Determine message box class based on content
                 $msg_class = 'alert-info'; // Default
                 if (strpos($add_entry_message, 'successfully') !== false) $msg_class = 'alert-success';
                 elseif (strpos($add_entry_message, 'Error:') !== false || strpos($add_entry_message, 'Could not') !== false) $msg_class = 'alert-danger';
                 elseif (strpos($add_entry_message, 'Please correct') !== false || strpos($add_entry_message, 'Warning:') !== false) $msg_class = 'alert-warning';

                 $icon_svg = ''; // Placeholder for icon logic if needed here
                  if ($msg_class === 'alert-success') $icon_svg = '<svg class="alert-icon text-green-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
                  elseif ($msg_class === 'alert-danger') $icon_svg = '<svg class="alert-icon text-red-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                  elseif ($msg_class === 'alert-warning') $icon_svg = '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>';
                   // Default info icon if none of the above
                   if (empty($icon_svg)) $icon_svg = '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';


                  echo "<div class='alert " . $msg_class . "' role='alert'>" . $icon_svg . "<p>" . nl2br(htmlspecialchars($add_entry_message)) . "</p></div>";
             }

             // Display fetch assigned timetable message (e.g., "No entries yet")
             if (!empty($fetch_assigned_timetable_message)) {
                 echo "<div class='alert alert-info' role='alert'>";
                  echo '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
                 echo "<p>" . nl2br(htmlspecialchars($fetch_assigned_timetable_message)) . "</p>";
                 echo "</div>";
             }
            ?>

            <?php if (!empty($staff_assigned_timetable)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time Slot</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Actions</th> <!-- Column for delete link -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_assigned_timetable as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['time_slot']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['class_taught']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['subject_taught']); ?></td>
                                    <td>
                                         <!-- Delete Link -->
                                         <!-- Uses a small form and POST for safer deletion -->
                                         <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to delete this entry for <?php echo htmlspecialchars($selected_staff_name); ?> (<?php echo htmlspecialchars($entry['day_of_week'] . ' ' . $entry['time_slot']); ?>)?');">
                                              <input type="hidden" name="action" value="delete_timetable_entry">
                                              <input type="hidden" name="entry_id_to_delete" value="<?php echo htmlspecialchars($entry['staff_timetable_id']); ?>">
                                              <input type="hidden" name="selected_staff_id" value="<?php echo htmlspecialchars($selected_staff_id); ?>">
                                              <button type="submit" class="action-link-danger bg-transparent border-none p-0 m-0 cursor-pointer">Delete</button>
                                         </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="text-lg font-semibold text-gray-800 mb-4 mt-8">Add New Entry for <?php echo htmlspecialchars($selected_staff_name); ?></h3>

            <!-- Form for Adding New Timetable Entry -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">

                 <!-- Hidden field to pass the currently selected staff_id -->
                 <input type="hidden" name="selected_staff_id" value="<?php echo htmlspecialchars($selected_staff_id); ?>">
                 <!-- Hidden field to indicate the action -->
                 <input type="hidden" name="action" value="add_timetable_entry">


                 <div class="form-group">
                     <label for="day_of_week" class="form-label">Day of Week <span class="text-red-500">*</span></label>
                     <select name="day_of_week" id="day_of_week" class="form-select <?php echo (!empty($day_of_week_err)) ? 'is-invalid' : ''; ?>" required>
                         <option value="">--Select Day--</option>
                         <?php
                          $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                          foreach ($days as $day) {
                              // Pre-select if value exists (from failed validation)
                              echo '<option value="' . htmlspecialchars($day) . '"' . (($day_of_week === $day) ? ' selected' : '') . '>' . htmlspecialchars($day) . '</option>';
                          }
                         ?>
                     </select>
                     <?php if (!empty($day_of_week_err)): ?><span class="form-error"><?php echo $day_of_week_err; ?></span><?php endif; ?>
                 </div>

                 <div class="form-group">
                     <label for="time_slot" class="form-label">Time Slot <span class="text-red-500">*</span></label>
                     <input type="text" name="time_slot" id="time_slot" class="form-input <?php echo (!empty($time_slot_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($time_slot); ?>" placeholder="e.g., 9:00 AM - 10:00 AM or Period 1" required>
                     <?php if (!empty($time_slot_err)): ?><span class="form-error"><?php echo $time_slot_err; ?></span><?php endif; ?>
                 </div>

                 <div class="form-group">
                     <label for="class_taught" class="form-label">Class Taught <span class="text-red-500">*</span></label>
                     <input type="text" name="class_taught" id="class_taught" class="form-input <?php echo (!empty($class_taught_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($class_taught); ?>" placeholder="e.g., 10A" required>
                     <?php if (!empty($class_taught_err)): ?><span class="form-error"><?php echo $class_taught_err; ?></span><?php endif; ?>
                 </div>

                 <div class="form-group">
                     <label for="subject_taught" class="form-label">Subject Taught <span class="text-red-500">*</span></label>
                     <input type="text" name="subject_taught" id="subject_taught" class="form-input <?php echo (!empty($subject_taught_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($subject_taught); ?>" placeholder="e.g., Mathematics" required>
                     <?php if (!empty($subject_taught_err)): ?><span class="form-error"><?php echo $subject_taught_err; ?></span><?php endif; ?>
                 </div>

                 <div class="md:col-span-2 flex items-center justify-start mt-4"> <!-- Full width on medium+ screens -->
                     <button type="submit" class="btn btn-primary">Add Timetable Entry</button>
                 </div>

            </form>
        </div>
    <?php elseif (empty($staff_list)): ?>
        <!-- Message handled by fetch_staff_list_error alert above -->
    <?php else: ?>
        <!-- Initial state before a staff member is selected (only happens if staff_list is NOT empty but selected_staff_id is null) -->
         <div class="content-card text-center">
             <p class="text-lg text-gray-700">Please select a staff member from the dropdown above to view and assign their timetable.</p>
         </div>
    <?php endif; ?>


    <!-- Back/Cancel Link -->
    <div class="mt-8 text-center">
         <!-- Path from 'admin/' up to 'School/' is '../' -->
         <!-- Assuming admin dashboard is at School/admin/admin_dashboard.php -->
         <a href="./admin_dashboard.php" class="btn btn-secondary inline-flex items-center">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
             </svg>
             Back to Dashboard
         </a>
    </div>

</main> <!-- End Main Content Area -->

<script>
// Optional: Add JavaScript to auto-submit the staff selection form when the dropdown changes
// This improves usability as the user doesn't have to click "Load Timetable"
const staffSelect = document.getElementById('staff_select');
if (staffSelect) {
    staffSelect.addEventListener('change', function() {
        this.form.submit();
    });
}
</script>

</body>
</html>