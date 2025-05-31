<?php
// School/teacher/staff_dashboard.php

// Start the session
session_start();

// Assuming config.php is in the parent directory (School/)
// It contains your database connection $link
require_once "../config.php";

// --- ACCESS CONTROL ---
// Define roles that are allowed to access this staff dashboard
// These roles must match the values stored in $_SESSION['role'] by your login.php
$allowed_staff_roles_dashboard = ['teacher', 'principal', 'staff']; // Roles allowed to view this dashboard

// Check if the user is NOT logged in, OR if they are logged in but their role
// is NOT in the list of allowed staff roles.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_staff_roles_dashboard)) {
    $_SESSION['operation_message'] = "Access denied. Please log in with appropriate staff credentials."; // Store plain text message
    // Path from 'teacher/' up to 'School/' is '../'
    header("location: ../login.php"); // Assuming login.php is in the parent directory (School/)
    exit;
}

// --- RETRIEVE STAFF INFORMATION FROM SESSION ---
// Retrieve staff data from session - primarily as a fallback or for initial checks
// Use the correct session keys set by your login.php
$staff_id = $_SESSION['id'] ?? null;
$staff_display_name = $_SESSION['name'] ?? 'Staff Member'; // Corrected from $_SESSION['display_name']
$staff_role = $_SESSION['role'] ?? 'Staff'; // Correct: Gets the role set by login.php (e.g., 'teacher', 'principal')

$staff_data = null; // Variable to hold the fetched staff data
$fetch_staff_error = ""; // To display messages about fetching the profile

// --- Fetch Staff Profile Data from DB ---
// Fetching staff data is necessary to get full details for display, INCLUDING the photo_filename
// This runs IF a valid staff_id is in the session AND the DB connection is open
if ($staff_id !== null && $link !== false) {
    // Select all necessary columns for the profile section, INCLUDING photo_filename
    $sql_fetch_staff = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff WHERE staff_id = ?";

    if ($stmt_fetch = mysqli_prepare($link, $sql_fetch_staff)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $staff_id); // Assuming staff_id is INT
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if ($result_fetch && mysqli_num_rows($result_fetch) == 1) {
                $staff_data = mysqli_fetch_assoc($result_fetch);
                // Update display variables with fresh data from DB - important for accuracy
                $staff_display_name = $staff_data['staff_name'];
                $staff_role = $staff_data['role'];
                 // Optionally update session variables with fresh data too if needed,
                 // but we primarily use $staff_data for display after fetching.
                 // $_SESSION['name'] = $staff_data['staff_name']; // Uncomment if needed
                 // $_SESSION['role'] = $staff_data['role'];     // Uncomment if needed

            } else {
                // This is a serious issue: User logged in but profile not found or duplicate
                $fetch_staff_error = "Your staff profile could not be found in the database. Please contact an administrator.";
                 error_log("Staff ID from session ($staff_id) not found or duplicate in DB.");
                 // Consider logging out the user if their profile is missing from the DB
                 // header("location: ../logout.php"); exit; // Uncomment if profile missing is critical
            }
            if ($result_fetch) mysqli_free_result($result_fetch); // Free result set
        } else {
            $fetch_staff_error = "Oops! Something went wrong while fetching your profile data. Please try again later.";
            error_log("Staff profile fetch query failed: " . mysqli_stmt_error($stmt_fetch));
        }
        if ($stmt_fetch) mysqli_stmt_close($stmt_fetch); // Close statement
    } else {
         $fetch_staff_error = "Oops! Something went wrong. Could not prepare profile fetch statement.";
         error_log("Staff profile prepare fetch statement failed: " . mysqli_error($link));
    }
} else if ($link === false) {
     $fetch_staff_error = "Database connection error. Could not load staff profile.";
} else { // staff_id is null - should be caught by auth check, but defensive
    $fetch_staff_error = "Staff ID not found in session. Please try logging in again.";
}


// Check for and display messages from operations (login, edit, etc. stored in session)
$operation_message_session = ""; // Use a different variable name to avoid conflict
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear the message after displaying
}

// Close database connection at the very end if it's open and valid
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(ucfirst($staff_role)); ?> Dashboard - Profile</title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
        /* Add specific styles if needed, ensure padding for fixed navbar */
        body {
            padding-top: 4rem; /* Adjust based on navbar height */
            background-color: #f3f4f6; /* Consistent with the admin list background */
        }
         .alert {
             border-left-width: 4px; /* Tailwind border-l-4 */
             padding: 1rem; /* Tailwind p-4 */
             margin-bottom: 1.5rem; /* Tailwind mb-6 */
             border-radius: 0.25rem; /* Tailwind rounded */
         }
         .alert-info {
              background-color: #e0f2f7; /* light-blue-100 */
              border-color: #0284c7;    /* light-blue-600 */
              color: #0369a1;         /* light-blue-800 */
         }
          .alert-success {
               background-color: #d1fae5; /* green-100 */
               border-color: #059669;    /* green-600 */
               color: #065f46;         /* green-800 */
          }
           .alert-warning {
                background-color: #fff7ed; /* orange-100 */
                border-color: #f97316;    /* orange-500 */
                color: #ea580c;         /* orange-700 */
           }
            .alert-danger {
                 background-color: #fee2e2; /* red-100 */
                 border-color: #ef4444;    /* red-500 */
                 color: #b91c1c;         /* red-800 */
            }

        /* Main Profile Container Styling (Card-like) */
        .profile-container {
            background-color: #ffffff; /* White background */
            padding: 1.5rem; /* p-6 */
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
            text-align: center; /* Center content horizontally */
            margin-bottom: 2rem; /* mb-8 */
            max-width: 800px; /* Limit max width */
            margin-left: auto; /* Center the container */
            margin-right: auto; /* Center the container */
        }

        /* Staff Photo Styling */
        .profile-photo-wrapper {
             margin-bottom: 1rem; /* mb-4 */
             display: inline-block; /* Allows margin: auto to center it within text-align: center parent */
        }
        .profile-photo-container {
            width: 120px; /* Adjust size */
            height: 120px; /* Adjust size */
            border-radius: 9999px; /* rounded-full */
            overflow: hidden;
            border: 3px solid #6366f1; /* Indigo-500 border */
            margin-bottom: 0.5rem; /* Space below photo before text */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* subtle shadow */
        }

         .profile-photo-container img {
             display: block;
             width: 100%;
             height: 100%;
             object-fit: cover;
         }

         .profile-photo-placeholder {
            width: 100%;
            height: 100%;
             background-color: #e0e7ff; /* Indigo-100 */
             display: flex;
             align-items: center;
             justify-content: center;
             color: #4338ca; /* Indigo-700 */
             font-size: 3rem; /* text-5xl */
             font-weight: 700;
         }

         /* Details Grid Styling */
         .profile-details-grid {
            display: grid;
            gap: 1rem; /* Gap between grid items (cards) */
            margin-top: 1.5rem; /* Space above the grid */
             /* Define grid columns - adjust as needed for responsiveness */
             grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Auto-fit columns, min width 250px */
             text-align: left; /* Align text left inside detail cards */
         }

         /* Individual Detail Card Styling */
         .profile-card-item {
            background-color: #f9fafb; /* gray-50 */
            padding: 1rem; /* p-4 */
            border-radius: 0.375rem; /* rounded-md */
            border: 1px solid #e5e7eb; /* gray-200 */
         }
          .profile-card-item strong {
               display: block; /* Put label on its own line */
               font-size: 0.875rem; /* text-sm */
               color: #4b5563; /* gray-600 */
               margin-bottom: 0.25rem; /* space below label */
               font-weight: 600; /* semibold */
          }
           .profile-card-item span {
               font-size: 1rem; /* base text size */
               color: #1f2937; /* gray-800 */
                display: block; /* Ensure value is on its own line below label */
           }

         /* Actions Button Container */
         .profile-actions {
            margin-top: 2rem; /* Space above buttons */
            display: flex; /* Arrange buttons in a row */
            justify-content: center; /* Center buttons horizontally */
            gap: 1rem; /* Space between buttons */
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
         }

         /* Button Styling */
         .btn {
             padding: 0.75rem 1.5rem; /* py-3 px-6 */
             border-radius: 0.375rem; /* rounded-md */
             font-weight: 500; /* medium */
             transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
             text-decoration: none; /* Remove underline from links */
             display: inline-flex; /* Use flex for centering text/icon */
             align-items: center; /* Center text vertically */
             justify-content: center; /* Center text horizontally */
             cursor: pointer;
         }

         .btn-primary {
             background-color: #6366f1; /* Indigo-500 */
             color: white;
             border: 1px solid #6366f1;
         }
         .btn-primary:hover {
              background-color: #4f46e5; /* Indigo-600 */
         }

         .btn-secondary {
             background-color: #e5e7eb; /* gray-200 */
             color: #374151; /* gray-700 */
             border: 1px solid #d1d5db; /* gray-300 */
         }
          .btn-secondary:hover {
              background-color: #d1d5db; /* gray-300 */
               border-color: #9ca3af; /* gray-400 */
          }

         .btn-danger {
             background-color: #fca5a5; /* red-300 */
             color: #b91c1c; /* red-800 */
             border: 1px solid #f87171; /* red-400 */
         }
         .btn-danger:hover {
              background-color: #f87171; /* red-400 */
              color: #991b1b; /* red-900 */
         }

    </style>
</head>
<body class="bg-gray-100 min-h-screen font-sans"> <!-- Added font-sans -->

    <?php
    // --- INCLUDE NAVBAR ---
    // Assuming staff_navbar.php is in the SAME directory as staff_dashboard.php (School/teacher/)
    // If it's in the parent directory (School/), use "../staff_navbar.php"
    $navbar_path = "./staff_navbar.php"; // Path relative to THIS file (staff_dashboard.php)

    if (file_exists($navbar_path)) {
        require_once $navbar_path;
    } else {
        echo '<div class="alert alert-danger" role="alert">
                <strong class="font-bold">Error:</strong>
                <span class="block sm:inline"> Staff navbar file not found! Check path: `' . htmlspecialchars($navbar_path) . '`</span>
              </div>';
        // In a real application, you might halt execution here or provide a fallback navbar
    }
    ?>

    <!-- Main Content Area -->
    <div class="w-full max-w-screen-xl mx-auto mt-8 px-4 md:px-6 lg:px-8"> <!-- Centered container with max width, top margin, and padding -->

        <?php
            // Display messages from session (like "Welcome" or "Access denied")
            if (!empty($operation_message_session)) {
               // Determine alert class based on content (basic check)
               $alert_class = 'alert-info'; // Default info
               if (strpos($operation_message_session, 'successfully') !== false || strpos($operation_message_session, 'Welcome') !== false) {
                   $alert_class = 'alert-success'; // Success/Welcome
               } elseif (strpos($operation_message_session, 'Access denied') !== false || strpos($operation_message_session, 'Error') !== false || strpos($operation_message_session, 'Failed') !== false) {
                    $alert_class = 'alert-danger'; // Error/Danger
               } elseif (strpos($operation_message_session, 'Warning') !== false || strpos($operation_message_session, 'not found') !== false || strpos(strip_tags($operation_message_session), 'Please correct') !== false || strpos($operation_message_session, 'unavailable') !== false) {
                    $alert_class = 'alert-warning'; // Warning
               }
               echo "<div class='alert " . $alert_class . "' role='alert'>";
               echo "<p>" . htmlspecialchars($operation_message_session) . "</p>"; // htmlspecialchars the message
               echo "</div>";
           }
        ?>

        <?php
            // Display staff profile fetch error message if any
            if (!empty($fetch_staff_error)) {
                echo "<div class='alert alert-warning' role='alert'>";
                echo "<p>" . htmlspecialchars($fetch_staff_error) . "</p>";
                echo "</div>";
            }
        ?>

        <?php if ($staff_data): // Only display the profile structure if staff data was successfully fetched ?>
        <div class="profile-container"> <!-- Main container mimicking the card style -->

             <!-- Page Title -->
             <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6">
                  Details for <?php echo htmlspecialchars($staff_display_name); ?> (ID: <?php echo htmlspecialchars($staff_data['staff_id']); ?>)
             </h1>

             <!-- Photo Area -->
            <div class="profile-photo-wrapper">
                <div class="profile-photo-container">
                    <?php
                    // Get the photo URL from the fetched data
                    $photo_url = $staff_data['photo_filename'] ?? '';
                    $has_photo = !empty($photo_url); // Check if the URL exists and is not empty

                    $initials = '';
                    // Generate initials for placeholder if needed
                    if (!$has_photo && !empty($staff_display_name)) {
                         $name_parts = explode(' ', $staff_display_name);
                         if (!empty($name_parts[0])) $initials .= strtoupper(substr($name_parts[0], 0, 1));
                         // Use the last part of the name for the second initial if available
                         if (count($name_parts) > 1 && !empty($name_parts[count($name_parts)-1])) {
                            $initials .= strtoupper(substr($name_parts[count($name_parts)-1], 0, 1));
                         } elseif (count($name_parts) === 1 && strlen($name_parts[0]) > 1) {
                             // If only one word, use the first two initials if available
                             $initials = strtoupper(substr($name_parts[0], 0, 2));
                         }
                    }

                     // Path to a local default avatar relative to THIS file (teacher/staff_dashboard.php)
                     // Assuming default_avatar.png is in School/assets/images/
                     $default_avatar_path = '../assets/images/default_avatar.png'; // Path from teacher/ to assets/images/

                     // Determine the final image URL to display
                     $display_photo_url = $has_photo ? htmlspecialchars($photo_url) : $default_avatar_path;

                     // Check if the default avatar file actually exists locally before using its path
                     $show_default_avatar = !$has_photo && file_exists($default_avatar_path);

                    ?>
                    <?php if ($has_photo || $show_default_avatar): ?>
                         <!-- Display the Cloudinary photo or the local default avatar -->
                        <img src="<?php echo htmlspecialchars($display_photo_url); ?>" alt="Profile photo of <?php echo htmlspecialchars($staff_display_name); ?>">
                    <?php else: ?>
                         <!-- Display placeholder if no photo and no default avatar file -->
                        <div class="profile-photo-placeholder">
                            <?php echo htmlspecialchars($initials ? $initials : '?'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                 <p class="text-sm text-gray-600">Staff Photo</p> <!-- Text below photo -->
            </div>


            <!-- Profile Details Grid -->
            <div class="profile-details-grid">
                 <!-- Staff ID -->
                <div class="profile-card-item">
                    <strong>Staff ID:</strong>
                    <span><?php echo htmlspecialchars($staff_data['staff_id']); ?></span>
                </div>
                 <!-- Unique ID -->
                 <div class="profile-card-item">
                    <strong>Unique ID:</strong>
                    <span><?php echo htmlspecialchars($staff_data['unique_id'] ?? 'N/A'); ?></span>
                </div>
                 <!-- Name -->
                 <div class="profile-card-item">
                    <strong>Name:</strong>
                    <span><?php echo htmlspecialchars($staff_display_name); ?></span>
                </div>
                 <!-- Mobile Number -->
                 <div class="profile-card-item">
                    <strong>Mobile Number:</strong>
                    <span><?php echo htmlspecialchars($staff_data['mobile_number'] ?? 'N/A'); ?></span>
                </div>
                 <!-- Email -->
                 <div class="profile-card-item">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($staff_data['email']); ?></span>
                </div>
                 <!-- Role -->
                 <div class="profile-card-item">
                    <strong>Role:</strong>
                    <span><?php echo htmlspecialchars(ucfirst($staff_role)); ?></span>
                </div>
                 <!-- Salary (Conditional Display - Admins might see this, staff might not) -->
                 <?php // Example: Only show salary if role is 'principal' or 'admin' (though this is staff dashboard)
                       // Keeping it simple as in the image for now.
                 ?>
                 <div class="profile-card-item">
                     <strong>Salary:</strong>
                     <span><?php echo htmlspecialchars($staff_data['salary'] ?? 'N/A'); ?></span>
                 </div>
                 <!-- Subject Taught -->
                 <div class="profile-card-item">
                     <strong>Subject(s) Taught:</strong>
                     <span><?php echo htmlspecialchars($staff_data['subject_taught'] ?? 'N/A'); ?></span>
                 </div>
                 <!-- Classes Taught -->
                 <?php if (!empty($staff_data['classes_taught'])): ?>
                 <div class="profile-card-item">
                    <strong>Class(es) Taught:</strong>
                    <span><?php echo htmlspecialchars($staff_data['classes_taught']); ?></span>
                </div>
                 <?php endif; ?>
                 <!-- Created At -->
                 <div class="profile-card-item">
                     <strong>Created At:</strong>
                     <span><?php echo htmlspecialchars($staff_data['created_at']); ?></span>
                </div>

            </div> <!-- End profile-details-grid -->

            <!-- Action Buttons -->
            <div class="profile-actions">
                 <!-- Edit Staff Button (Only if user has permission, e.g., Principal or the staff member themselves) -->
                 <?php
                    // Example: Allow Principal or the staff member themselves to edit their profile
                    // You might need an 'edit_staff_profile.php' page in the appropriate directory
                    // Assuming principal can edit any staff, teacher can only edit their own
                    $current_user_id = $_SESSION['id'] ?? null;
                    $current_user_role = $_SESSION['role'] ?? null;
                    $staff_being_viewed_id = $staff_data['staff_id']; // The ID of the profile being shown

                    $can_edit = false;
                    // Allow Principal to edit any staff profile
                    if ($current_user_role === 'principal') {
                        $can_edit = true;
                        // Note: If this page only shows *the logged-in* staff's profile,
                        // this principal check might not be strictly necessary here,
                        // but it's good practice for a shared edit page.
                    }
                    // Allow a staff member to edit their *own* profile
                     if ($current_user_id !== null && (int)$current_user_id === (int)$staff_being_viewed_id) {
                        $can_edit = true;
                     }

                     // Determine the correct edit page path based on role or context
                     // If only staff can see this (their own profile), maybe just a single edit page
                     $edit_page_path = "./edit_staff_profile.php?id=" . htmlspecialchars($staff_being_viewed_id); // Assuming an edit page exists in the same 'teacher' directory

                 ?>
                 


                 <!-- Delete Staff Button (Likely only for Admin or Principal) -->
                  <?php
                     // Example: Only allow Principal or Admin to delete staff (if they were viewing another staff profile)
                     // Since this page is the *logged-in* staff's dashboard, deleting yourself isn't standard.
                     // This button is likely intended for an Admin/Principal viewing *other* staff profiles.
                     // However, to match the image, we'll add it, but note it's context-dependent.
                     // If this page is *only* for logged-in staff, delete should *not* be here.
                     // If it's possible for Principal/Admin to view staff profiles via this page, then add permissions here.
                     // Assuming this page *only* shows the logged-in staff's profile, the Delete button from the image is misleading here.
                     // I'll comment it out, as showing a "Delete Self" button is unusual.
                     // If you want to display it and handle deletion permissions elsewhere, uncomment and add logic.
                     /*
                     $can_delete = false;
                     if ($current_user_role === 'principal') { // Or check for 'admin' role if this page is accessible to them
                          $can_delete = true;
                     }
                     if ($can_delete):
                          // Path to delete script relative to THIS file (teacher/delete_staff.php?)
                          $delete_script_path = "./delete_staff.php?id=" . htmlspecialchars($staff_being_viewed_id);
                  ?>
                      <a href="<?php echo $delete_script_path; ?>" class="btn btn-danger"
                         onclick="return confirm('Are you sure you want to DELETE this staff record? This cannot be undone!');">Delete Staff</a>
                  <?php endif; */ ?>


                 <!-- Back to Dashboard Button -->
                 <!-- Path from 'teacher/' up to 'School/' is '../' -->
                 <a href="./staff_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                 <!-- Note: The current page IS the dashboard. This button is a bit redundant
                      if this page is always the landing page after login.
                      It makes more sense if staff can navigate *away* from the main dashboard
                      to view their profile on a separate page, and then use "Back to Dashboard".
                      Given the original request was to show *only* the profile on the dashboard,
                      this button might not be needed unless you add other sections to the dashboard.
                      However, to match the image, I've included it. You might reconsider its placement/necessity. -->

            </div> <!-- End profile-actions -->

        </div> <!-- End profile-container -->

         <?php else: // Display a message if staff data couldn't be fetched and no specific error was shown above ?>
              <?php if (empty($fetch_staff_error)): // Avoid duplicate messages if already shown above?>
                 <div class='alert alert-danger' role='alert'>
                     <p>Could not load staff profile data. Please try logging in again.</p>
                 </div>
              <?php endif; ?>
         <?php endif; // End if ($staff_data) ?>


         <!-- Logout Link (kept here for consistency, might also be in navbar) -->
         <!-- Path from 'teacher/' up to 'School/' is '../' -->
         <p class="mt-8 text-center"><a href="../logout.php" class="text-red-600 hover:underline font-medium">Logout</a></p>


    </div>


</body>
</html>