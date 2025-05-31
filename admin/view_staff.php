<?php
// School/admin/view_staff.php

// Start the session
session_start();

require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal or Staff (as view_student allowed staff)
// Allowing 'teacher', 'principal', 'staff', 'admin' to view staff details seems reasonable
$allowed_roles_view_staff = ['admin', 'principal', 'staff']; // Adjust if teachers should also view staff details
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_roles_view_staff)) {
    $_SESSION['operation_message'] = "Access denied. You do not have permission to view staff details.";
    // Redirect back up to the base staff dashboard or login if admin_dashboard isn't the default landing
    // Assuming admin_dashboard is the correct fallback if the user *is* logged in but unauthorized for *this* page
    header("location: admin_dashboard.php");
    exit;
}

// Initialize variables
$staff_id = 0;
$staff_data = null; // Holds main staff profile data
$error_message = ""; // For errors specific to fetching the staff member

// --- Fetch Staff Timetable Data ---
$staff_timetable_entries = []; // Variable to hold timetable data
$fetch_timetable_message = ""; // Message for the timetable section (e.g., "No entries")


// Process GET parameter
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    // Get ID from URL and sanitize it
    $staff_id = trim($_GET['id']);

    // Validate ID (must be a positive integer)
    if (!filter_var($staff_id, FILTER_VALIDATE_INT) || $staff_id <= 0) {
        $error_message = "Invalid staff ID provided.";
        // No need to proceed with DB fetch if ID is invalid
    } else {
        // --- Fetch Main Staff Profile ---
        // Prepare a select statement - IMPORTANT: Exclude sensitive fields like password
        $sql_staff = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff WHERE staff_id = ?";

        if ($link === false) {
            $error_message = "Database connection error. Could not retrieve staff details.";
            error_log("View Staff DB connection failed: " . mysqli_connect_error());
        } elseif ($stmt_staff = mysqli_prepare($link, $sql_staff)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt_staff, "i", $param_id);

            // Set parameters
            $param_id = $staff_id; // Use the validated $staff_id

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_staff)) {
                $result_staff = mysqli_stmt_get_result($stmt_staff);

                if (mysqli_num_rows($result_staff) == 1) {
                    // Fetch result row as an associative array
                    $staff_data = mysqli_fetch_assoc($result_staff);

                    // --- Staff Found, Now Fetch Their Timetable ---
                    // We only fetch the timetable if the staff member was successfully found.
                    $sql_timetable = "SELECT staff_timetable_id, day_of_week, time_slot, class_taught, subject_taught FROM staff_timetables WHERE staff_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), time_slot ASC";

                    if ($stmt_timetable = mysqli_prepare($link, $sql_timetable)) {
                        mysqli_stmt_bind_param($stmt_timetable, "i", $staff_id); // Use the same staff_id

                        if (mysqli_stmt_execute($stmt_timetable)) {
                            $result_timetable = mysqli_stmt_get_result($stmt_timetable);

                            if ($result_timetable) {
                                $staff_timetable_entries = mysqli_fetch_all($result_timetable, MYSQLI_ASSOC);
                                if (empty($staff_timetable_entries)) {
                                     $fetch_timetable_message = "No timetable entries found for this staff member.";
                                }
                            } else {
                                 $fetch_timetable_message = "Could not retrieve timetable entries.";
                                 error_log("View Staff: Timetable get_result failed for ID " . $staff_id . ": " . mysqli_stmt_error($stmt_timetable));
                            }
                            if ($result_timetable) mysqli_free_result($result_timetable);

                        } else {
                            $fetch_timetable_message = "Error fetching timetable: " . mysqli_stmt_error($stmt_timetable);
                             error_log("View Staff: Timetable query execution failed for ID " . $staff_id . ": " . mysqli_stmt_error($stmt_timetable));
                        }
                        if ($stmt_timetable) mysqli_stmt_close($stmt_timetable);
                    } else {
                         $fetch_timetable_message = "Error preparing timetable query: " . mysqli_error($link);
                         error_log("View Staff: Could not prepare timetable statement: " . mysqli_error($link));
                    }

                } else {
                    // Staff record not found for the given ID
                    $error_message = "No staff record found with ID: " . htmlspecialchars($staff_id);
                }
                if ($result_staff) mysqli_free_result($result_staff); // Free staff result set

            } else {
                // Error executing main staff query
                $error_message = "Error executing staff query. Please try again later.";
                 error_log("View Staff main query execution failed for ID " . $staff_id . ": " . mysqli_stmt_error($stmt_staff));
            }

            // Close main staff statement
            if ($stmt_staff) mysqli_stmt_close($stmt_staff);
        } else {
             // Error preparing main staff statement
             $error_message = "Error preparing staff query. Please try again later.";
             error_log("View Staff main prepare statement failed: " . mysqli_error($link));
        }
    }
} else {
    // No ID parameter was provided in the URL
    $error_message = "No staff ID specified.";
}


// Close connection
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

// --- Set operation message if there was an error (for redirect or display) ---
// If the main staff data fetch failed OR if no ID was provided
if (!empty($error_message)) {
    // Check if the error should cause a redirect
    // Redirect if no ID was specified, ID was invalid, or the record wasn't found
    $should_redirect = false;
     if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || intval($_GET['id']) <= 0 || $staff_data === null) {
         // $staff_data === null means the main staff fetch failed or found no rows
         $should_redirect = true;
     }

    if ($should_redirect) {
        $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($error_message) . "</p>";
        // Redirect back to admin dashboard
         // Path from 'admin/' to 'admin/' is './'
        header("location: admin_dashboard.php");
        exit;
    }
    // If not redirecting (e.g., DB error happened *after* ID was validated, but staff data was fetched),
    // the error message will NOT be displayed on the current page using the session message.
    // If you *do* want to show non-redirecting errors on the view page, you'd use a separate display variable.
    // For now, sticking to the pattern where session message is for redirects.
}

// --- Check for and display messages from operations (e.g., from redirection) ---
// This is for messages like "Access Denied" or messages set before a redirect *to* this page
$operation_message_session = ""; // Use a different variable name
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear the message after displaying
}


$default_staff_avatar_path = '../assets/images/default_staff_avatar.png'; // Path relative to admin/
// Double check this path based on your actual default image location
// If it's in School/assets/images/default_staff_avatar.png, then ../assets/images/default_staff_avatar.png is correct from School/admin/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $staff_data ? htmlspecialchars($staff_data['staff_name']) . " - Staff Details" : "View Staff Details"; ?> - Admin</title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <!-- Link Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <style>
        /* Add background animation styles */
        body {
            font-family: 'Inter', sans-serif;
            color: #374151;
            /* Remove flexbox styles used for sidebar layout */
            /* display: flex; */
            min-height: 100vh;

            background: linear-gradient(-45deg, #b5e2ff, #d9f4ff, #b5e2ff, #d9f4ff);
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            background-attachment: fixed;
             padding-top: 4rem; /* Add padding for fixed navbar */
        }
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Styles for the main content area */
        /* Revert styles from sidebar layout */
        .main-content-area {
            flex-grow: 0; /* Remove flex-grow */
            padding: 1.5rem; /* Keep padding */
            overflow-y: visible; /* Revert overflow */
            /* Add back margin and max-width for centering */
             width: 100%; /* Take full width */
             max-width: screen-xl; /* Max width from Tailwind config, or a specific px value */
             margin-left: auto;
             margin-right: auto;
             /* Remove padding-top if navbar handles spacing */
             padding-top: 1rem; /* Adjusted padding-top below navbar */
             padding-bottom: 1.5rem; /* Add padding at the bottom */
        }

        /* --- Reusable Card Style (from dashboard) --- */
        .content-card {
             background-color: #ffffff;
             padding: 1.5rem; /* p-6 */
             border-radius: 0.75rem; /* rounded-lg */
             box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
             border: 1px solid #e5e7eb; /* gray-200 border */
             width: 100%;
             margin-bottom: 1.5rem; /* Space between cards */
        }

         /* --- Alert/Message Styles (from dashboard) --- */
         .alert {
              border-left-width: 4px;
              padding: 1rem;
              margin-bottom: 1.5rem;
              border-radius: 0.375rem;
              box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
              display: flex;
              align-items: center;
              gap: 0.75rem;
              line-height: 1.5;
         }
         .alert-icon { flex-shrink: 0; width: 1.5rem; height: 1.5rem; }
          .alert p { margin: 0; }
          .alert-info { background-color: #e0f7fa; border-color: #0891b2; color: #0e7490; }
          .alert-success { background-color: #dcfce7; border-color: #22c55e; color: #15803d; }
          .alert-warning { background-color: #fff7ed; border-color: #f97316; color: #ea580c; }
          .alert-danger { background-color: #fee2e2; border-color: #ef4444; color: #b91c1c; }


         /* --- Staff Details Grid Layout --- */
         .details-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Responsive grid with minimum item width */
             gap: 1rem; /* Gap between grid items */
             margin-top: 1.5rem;
         }

         .detail-group {
             display: flex;
             flex-direction: column;
             padding-bottom: 0.5rem; /* Space below each item */
             border-bottom: 1px dashed #eee; /* Subtle separator */
         }
         .detail-group:last-child {
             border-bottom: none; /* Remove border for last item */
         }

         .detail-group strong {
             display: block;
             font-size: 0.875rem; /* text-sm */
             font-weight: 600; /* semibold */
             color: #4b5563; /* text-gray-600 */
             margin-bottom: 0.25rem; /* space-y-0.5 */
         }
         .detail-group p {
             font-size: 1rem; /* text-base */
             color: #1f2937; /* text-gray-900 */
              word-break: break-word; /* Prevent long text overflow */
             margin: 0; /* Remove default margin */
              white-space: pre-wrap; /* Handle potential line breaks (e.g., in address if added) */
         }

          /* Photo Styling */
          .photo-section {
              display: flex;
              flex-direction: column;
              align-items: center;
              text-align: center;
              margin-bottom: 2rem;
              padding-bottom: 1.5rem;
              border-bottom: 1px solid #e5e7eb;
          }
          .photo-section img {
               width: 100px; /* Larger photo */
               height: 100px;
               border-radius: 50%;
               object-fit: cover;
               margin-bottom: 0.5rem; /* space below photo */
               border: 3px solid #6366f1; /* indigo-500 */
               box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          }
           .photo-section strong {
               font-size: 1rem;
               color: #1f2937;
           }

           /* Timetable Table Styling */
            .timetable-table {
                 width: 100%;
                 border-collapse: collapse;
                 margin-top: 1rem; /* Space above table */
            }
             .timetable-table th,
             .timetable-table td {
                 padding: 0.75rem 1rem; /* py-3 px-4 */
                 text-align: left;
                 border-bottom: 1px solid #e5e7eb; /* gray-200 */
             }
             .timetable-table th {
                 background-color: #f9fafb; /* gray-50 */
                 font-size: 0.75rem; /* text-xs */
                 font-weight: 600; /* font-semibold */
                 color: #6b7280; /* gray-500 */
                 text-transform: uppercase;
                 letter-spacing: 0.05em; /* tracking-wider */
             }
              .timetable-table tbody tr:hover {
                   background-color: #f3f4f6; /* gray-100 hover */
              }

             /* Responsive table wrapper */
             .table-responsive {
                 overflow-x: auto;
                 -webkit-overflow-scrolling: touch;
                 margin-bottom: 1rem; /* Space below table */
             }


           /* Style for action buttons at the bottom */
           .action-buttons {
               margin-top: 2rem;
               display: flex;
               justify-content: center;
               gap: 1rem; /* space-x-4 */
                padding-top: 1.5rem; /* space above buttons */
                border-top: 1px solid #e5e7eb; /* separator line */
               flex-wrap: wrap; /* Allow buttons to wrap on small screens */
           }
            .action-button {
                display: inline-flex;
                align-items: center;
                padding: 0.625rem 1.25rem; /* px-5 py-2.5 */
                border-radius: 0.375rem; /* rounded-md */
                font-weight: 500; /* medium */
                font-size: 0.875rem; /* text-sm */
                transition: background-color 0.15s ease-in-out, opacity 0.15s ease-in-out;
                text-decoration: none;
                 cursor: pointer;
                 border: 1px solid transparent; /* Base border */
            }
             .action-button.primary {
                 background-color: #4f46e5; /* indigo-600 */
                 color: white;
                  border-color: #4f46e5;
             }
             .action-button.primary:hover {
                  background-color: #4338ca; /* indigo-700 */
                   border-color: #4338ca;
             }
             .action-button.secondary {
                 background-color: #e5e7eb; /* gray-200 */
                 color: #374151; /* gray-700 */
                  border-color: #d1d5db; /* gray-300 */
             }
              .action-button.secondary:hover {
                  background-color: #d1d5db; /* gray-300 */
              }
              .action-button.danger { /* Style for delete button */
                   background-color: #ef4444; /* red-500 */
                   color: white;
                    border-color: #ef4444;
              }
               .action-button.danger:hover {
                    background-color: #dc2626; /* red-600 */
                     border-color: #dc2626;
               }


     </style>
</head>
<body>
    <?php
    // Assuming admin_sidebar.php is in the SAME directory as view_staff.php
    $sidebar_path = "./admin_sidebar.php"; // Adjust if necessary
     if (file_exists($sidebar_path)) {
        require_once $sidebar_path;
    } else {
        echo '<div class="alert alert-danger" role="alert"><strong class="font-bold">Error:</strong> Admin sidebar file not found! Check path: <code>' . htmlspecialchars($sidebar_path) . '</code></div>';
    }
    ?>


     <!-- Main Content Area (Wrapper for Navbar Layout) -->
     <!-- Reverted classes for centering and max-width -->
     <main class="w-full max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pb-8">

         <!-- Operation Message Display (for messages set before redirecting here) -->
         <?php
         if (!empty($operation_message_session)) {
              $alert_class = 'alert-info'; // Default
              $icon_svg = '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'; // Info icon
              $msg_lower = strtolower(strip_tags($operation_message_session));

              if (strpos($msg_lower, 'successfully') !== false) {
                   $alert_class = 'alert-success';
                   $icon_svg = '<svg class="alert-icon text-green-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'; // Check icon
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $alert_class = 'alert-danger';
                   $icon_svg = '<svg class="alert-icon text-red-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'; // X icon
              } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'please correct') !== false || strpos($msg_lower, 'unavailable') !== false || strpos($msg_lower, 'no ') !== false) {
                   $alert_class = 'alert-warning';
                   $icon_svg = '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>'; // Warning icon
              }
              echo "<div class='alert " . $alert_class . "' role='alert'>" . $icon_svg . "<p>" . nl2br($operation_message_session) . "</p></div>";
         }
         ?>


         <?php if ($staff_data): // Only display staff details and timetable if staff data was found ?>

             <!-- Staff Profile Details Card -->
             <div class="content-card">
                 <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-6 text-center">Staff Profile Details</h2>

                  <div class="photo-section">
                      <?php
                          $photo_url = $staff_data['photo_filename'] ?? '';
                           // If photo_filename exists and looks like a URL or path, use it, otherwise use default
                          $display_photo_url = !empty($photo_url) ? htmlspecialchars($photo_url) : htmlspecialchars($default_staff_avatar_path);
                          // Adjust the path logic if photo_filename is just the filename and needs a base path prepended
                           // Example: If photo_filename is 'staff_abc.jpg' and saved in '../uploads/staff_photos/'
                           // $display_photo_url = !empty($photo_url) ? htmlspecialchars('../uploads/staff_photos/' . $photo_url) : htmlspecialchars($default_staff_avatar_path);
                      ?>
                      <img src="<?php echo $display_photo_url; ?>" alt="<?php echo htmlspecialchars($staff_data['staff_name']); ?> Photo">
                      <strong>Staff Photo</strong>
                  </div>

                 <div class="details-grid">
                     <div class="detail-group">
                         <strong>Staff ID:</strong>
                         <p><?php echo htmlspecialchars($staff_data['staff_id']); ?></p>
                     </div>
                      <div class="detail-group">
                         <strong>Unique ID:</strong>
                         <p><?php echo htmlspecialchars($staff_data['unique_id'] ?? 'N/A'); ?></p>
                     </div>
                     <div class="detail-group">
                         <strong>Name:</strong>
                         <p><?php echo htmlspecialchars($staff_data['staff_name']); ?></p>
                     </div>
                     <div class="detail-group">
                         <strong>Mobile Number:</strong>
                         <p><?php echo htmlspecialchars($staff_data['mobile_number'] ?? 'N/A'); ?></p>
                     </div>
                      <div class="detail-group"> <!-- Corrected typo detail_group to detail-group -->
                         <strong>Email:</strong>
                         <p><?php echo htmlspecialchars($staff_data['email'] ?? 'N/A'); ?></p>
                     </div>
                     <div class="detail-group">
                         <strong>Role:</strong>
                         <p><?php echo htmlspecialchars(ucfirst($staff_data['role'])); ?></p>
                     </div>
                      <div class="detail-group">
                         <strong>Salary:</strong>
                         <p><?php echo htmlspecialchars($staff_data['salary'] ?? 'N/A'); ?></p>
                     </div>
                      <div class="detail-group">
                         <strong>Subject(s) Taught:</strong>
                         <p><?php echo htmlspecialchars($staff_data['subject_taught'] ?? 'N/A'); ?></p>
                     </div>
                      <div class="detail-group">
                         <strong>Class(es) Taught:</strong>
                         <p><?php echo htmlspecialchars($staff_data['classes_taught'] ?? 'N/A'); ?></p>
                     </div>
                     <div class="detail-group">
                         <strong>Created At:</strong>
                         <p><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($staff_data['created_at']))); ?></p>
                     </div>
                     <!-- Add more staff details as needed -->
                 </div>

                  <div class="action-buttons">
                      <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): ?>
                         <a href="./edit_staff.php?id=<?php echo htmlspecialchars($staff_data['staff_id']); ?>" class="action-button primary">Edit Staff Details</a>
                     <?php endif; ?>
                     <?php if ($_SESSION['role'] === 'admin'): // Assuming only admin can delete staff ?>
                          <!-- Delete Link (Requires JavaScript confirmation and backend deletion logic) -->
                          <a href="./delete_staff.php?id=<?php echo htmlspecialchars($staff_data['staff_id']); ?>" class="action-button danger" onclick="return confirm('Are you sure you want to delete this staff record? This action cannot be undone.');">Delete Staff</a>
                      <?php endif; ?>
                      <!-- Link to assign timetable -->
                       <a href="./assign_staff_timetable.php?staff_id=<?php echo htmlspecialchars($staff_data['staff_id']); ?>" class="action-button secondary">Assign Timetable</a>
                      <a href="./admin_dashboard.php" class="action-button secondary">Back to Dashboard</a>
                 </div>

             </div> <!-- End Staff Profile Details Card -->


             <!-- Staff Timetable Card -->
             <div class="content-card">
                 <h2 class="text-xl sm:text-2xl font-bold text-gray-800 mb-4 text-center">Assigned Timetable</h2>

                 <?php
                  // Display timetable specific message (e.g., "No entries found")
                  if (!empty($fetch_timetable_message)) {
                       echo "<div class='alert alert-info' role='alert'>";
                        echo '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
                       echo "<p>" . nl2br(htmlspecialchars($fetch_timetable_message)) . "</p>";
                       echo "</div>";
                   }
                 ?>

                 <?php if (!empty($staff_timetable_entries)): ?>
                      <div class="table-responsive">
                          <table class="timetable-table">
                              <thead>
                                  <tr>
                                      <th>Day</th>
                                      <th>Time Slot</th>
                                      <th>Class</th>
                                      <th>Subject</th>
                                      <!-- Optional: Add an Actions column here for deleting entries -->
                                      <!-- <th>Actions</th> -->
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php foreach ($staff_timetable_entries as $entry): ?>
                                      <tr>
                                          <td><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                          <td><?php echo htmlspecialchars($entry['time_slot']); ?></td>
                                          <td><?php echo htmlspecialchars($entry['class_taught']); ?></td>
                                          <td><?php echo htmlspecialchars($entry['subject_taught']); ?></td>
                                           <!-- Optional: Add a delete link here -->
                                           <!-- <td><a href="./delete_timetable_entry.php?id=<?php echo htmlspecialchars($entry['staff_timetable_id']); ?>&staff_id=<?php echo htmlspecialchars($staff_data['staff_id']); ?>" class="text-red-600 hover:underline" onclick="return confirm('Are you sure?');">Delete</a></td> -->
                                      </tr>
                                  <?php endforeach; ?>
                              </tbody>
                          </table>
                      </div>
                 <?php endif; // End if (!empty($staff_timetable_entries)) ?>

                  <!-- Optional: Link to the assign timetable page specifically for this staff member -->
                  <div class="mt-6 text-center">
                       <a href="./assign_staff_timetable.php?staff_id=<?php echo htmlspecialchars($staff_data['staff_id']); ?>" class="action-button secondary">
                           <i class="fas fa-edit mr-2"></i> Manage Timetable Entries
                       </a>
                  </div>


             </div> <!-- End Staff Timetable Card -->

         <?php endif; // End if ($staff_data) ?>

     </main> <!-- End Main Content Area -->

     <!-- Optional: Logout Link (if not in navbar) -->
     <p class="mt-8 text-center text-gray-600 w-full">
        <a href="../logout.php" class="text-red-600 hover:underline font-medium">Logout</a>
     </p>


</body>
</html>