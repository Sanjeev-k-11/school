<?php
// School/teacher/view_my_timetable.php

// Start the session
session_start();

// Adjust path to config.php based on directory structure
// If this file is in School/teacher/, and config.php is in School/, path is "../config.php"
require_once "../config.php";

// --- ACCESS CONTROL ---
// Define roles that are allowed to access this page (any staff role)
$allowed_staff_roles = ['teacher', 'principal', 'staff', 'admin']; // Assuming admin can also view their own timetable

// Check if the user is NOT logged in, OR if they are logged in but their role
// is NOT in the list of allowed staff roles.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_staff_roles)) {
    $_SESSION['operation_message'] = "Access denied. Please log in with your staff credentials.";
    // Path from 'teacher/' up to 'School/' is '../'
    header("location: ../login.php"); // Redirect to login page
    exit;
}

// --- RETRIEVE STAFF INFORMATION FROM SESSION ---
$staff_id = $_SESSION['id'] ?? null; // Get the logged-in staff ID
$staff_display_name = $_SESSION['name'] ?? 'Staff Member'; // Get name for display
$staff_role = $_SESSION['role'] ?? 'Staff'; // Get role for display/context

// --- Fetch Staff Timetable Data for the logged-in staff ---
$my_timetable_entries = [];
$fetch_timetable_message = "";
$can_fetch_timetable = ($staff_id !== null && $link !== false);

if ($can_fetch_timetable) {
    // Select timetable details for the logged-in staff member
    // Using FIELD() to order days correctly (Monday to Sunday)
    $sql_fetch_my_timetable = "SELECT day_of_week, time_slot, class_taught, subject_taught FROM staff_timetables WHERE staff_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), time_slot ASC";

    if ($stmt_my_timetable = mysqli_prepare($link, $sql_fetch_my_timetable)) {
        mysqli_stmt_bind_param($stmt_my_timetable, "i", $staff_id);

        if (mysqli_stmt_execute($stmt_my_timetable)) {
            $result_my_timetable = mysqli_stmt_get_result($stmt_my_timetable);

            if ($result_my_timetable) {
                $my_timetable_entries = mysqli_fetch_all($result_my_timetable, MYSQLI_ASSOC);
                if (empty($my_timetable_entries)) {
                     $fetch_timetable_message = "You currently have no timetable entries assigned.";
                } else {
                     // Message included in the heading
                }
            } else {
                 $fetch_timetable_message = "Could not retrieve your timetable entries.";
                 error_log("View My Timetable: Error getting result for timetable: " . mysqli_stmt_error($stmt_my_timetable));
                 $my_timetable_entries = []; // Ensure array is empty
            }
            if ($result_my_timetable) mysqli_free_result($result_my_timetable);

        } else {
            $fetch_timetable_message = "Error fetching your timetable: " . mysqli_stmt_error($stmt_my_timetable);
             error_log("View My Timetable: Timetable fetch query failed: " . mysqli_stmt_error($stmt_my_timetable));
             $my_timetable_entries = []; // Ensure array is empty on error
        }
        if ($stmt_my_timetable) mysqli_stmt_close($stmt_my_timetable);

    } else {
         $fetch_timetable_message = "Error preparing timetable fetch statement: " . mysqli_error($link);
         error_log("View My Timetable: Could not prepare timetable statement: " . mysqli_error($link));
         $my_timetable_entries = []; // Ensure array is empty on error
    }

} else if ($link === false) {
    $fetch_timetable_message = "Database connection error. Could not load your timetable.";
} else if ($staff_id === null) {
    // This case should be caught by the main access control, but included for completeness
    $fetch_timetable_message = "Your staff ID is not available in the session. Please try logging in again.";
}


// Check for and display messages from operations (e.g., from redirection)
$operation_message_session = "";
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear the message after displaying
}


// Close database connection
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($staff_display_name); ?>'s Timetable - <?php echo htmlspecialchars(ucfirst($staff_role)); ?></title>
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
        padding-top: 4rem; /* Adjust based on navbar height */
        color: #374151; /* gray-700 */
        /* Dynamic Background Animation - Consistent with dashboard */
        background: linear-gradient(-45deg, #b5e2ff, #d9f4ff, #b5e2ff, #d9f4ff);
        background-size: 400% 400%;
        animation: gradientAnimation 15s ease infinite;
        background-attachment: fixed;
        min-height: 100vh;
    }
     @keyframes gradientAnimation {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    /* --- Reusable Card Style (from dashboard) --- */
    .content-card {
         background-color: #ffffff;
         padding: 1.5rem; /* p-6 */
         border-radius: 0.75rem; /* rounded-lg */
         box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
         border: 1px solid #e5e7eb; /* gray-200 border */
         width: 100%;
         margin-bottom: 1.5rem; /* Space below card */
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


     /* --- Timetable Table Styling (from assign_staff_timetable) --- */
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

        /* Back Link Styling */
         .back-link {
              display: inline-flex;
              align-items: center;
              gap: 0.5rem; /* space-x-2 */
              color: #2563eb; /* blue-600 */
              text-decoration: none;
              font-size: 1rem; /* text-base */
              font-weight: 500;
              margin-top: 2rem; /* mt-8 */
              transition: color 0.1s ease-in-out, text-decoration 0.1s ease-in-out;
         }
         .back-link:hover {
              color: #1d4ed8; /* blue-700 */
              text-decoration: underline;
         }
          .back-link:focus {
              outline: none;
              box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
              border-radius: 0.125rem;
          }


</style>
</head>
<body>

<?php
// INCLUDE NAVBAR
// IMPORTANT: Verify the path to your staff_navbar.php file relative to this file (teacher/view_my_timetable.php)
$navbar_path = "./staff_navbar.php"; // Assuming it's in the same directory

if (file_exists($navbar_path)) {
    // Navbar relies on session, so no need to pass data explicitly
    require_once $navbar_path;
} else {
    // Basic fallback if navbar file is missing
    echo '<div class="alert alert-danger" role="alert">
            <strong class="font-bold">Error:</strong>
            <span class="block sm:inline"> Staff navbar file not found! Please check the path: <code>' . htmlspecialchars($navbar_path) . '</code></span>
          </div>';
}
?>

<!-- Main Content Area -->
<main class="w-full max-w-screen-lg mx-auto px-4 sm:px-6 lg:px-8 mt-8 pb-8"> <!-- Adjusted max-width for content -->

    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-6 text-center">
        My Timetable
        <?php if ($staff_id !== null): ?>
            <span class="text-xl text-gray-600 block font-semibold">(<?php echo htmlspecialchars($staff_display_name); ?>)</span>
        <?php endif; ?>
    </h1>


    <?php
        // Display messages from session (e.g., access denied, etc.)
        if (!empty($operation_message_session)) {
           $alert_class = 'alert-info'; // Default
           $icon_svg = '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'; // Info icon
           $msg_lower = strtolower(strip_tags($operation_message_session));

           if (strpos($msg_lower, 'successfully') !== false) {
                $alert_class = 'alert-success';
                $icon_svg = '<svg class="alert-icon text-green-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'; // Check icon
           } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'denied') !== false) {
                $alert_class = 'alert-danger';
                $icon_svg = '<svg class="alert-icon text-red-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'; // X icon
           } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'please correct') !== false || strpos($msg_lower, 'unavailable') !== false || strpos($msg_lower, 'no ') !== false) {
                $alert_class = 'alert-warning';
                $icon_svg = '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>'; // Warning icon
           }
           echo "<div class='alert " . $alert_class . "' role='alert'>" . $icon_svg . "<p>" . nl2br(htmlspecialchars($operation_message_session)) . "</p></div>";
       }
    ?>

    <!-- My Timetable Section (Card) -->
    <section id="my-timetable-card" class="content-card">

        <?php
         // Display timetable specific message (e.g., "No entries found", DB errors during fetch)
         if (!empty($fetch_timetable_message)) {
              echo "<div class='alert alert-info' role='alert'>";
               echo '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
              echo "<p>" . nl2br(htmlspecialchars($fetch_timetable_message)) . "</p>";
              echo "</div>";
          }
        ?>

        <?php if (!empty($my_timetable_entries)): ?>
             <div class="table-responsive">
                 <table class="timetable-table">
                     <thead>
                         <tr>
                             <th>Day</th>
                             <th>Time Slot</th>
                             <th>Class</th>
                             <th>Subject</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($my_timetable_entries as $entry): ?>
                             <tr>
                                 <td><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                 <td><?php echo htmlspecialchars($entry['time_slot']); ?></td>
                                 <td><?php echo htmlspecialchars($entry['class_taught']); ?></td>
                                 <td><?php echo htmlspecialchars($entry['subject_taught']); ?></td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        <?php endif; // End if (!empty($my_timetable_entries)) ?>


    </section> <!-- End My Timetable Card -->

    <!-- Back/Cancel Link -->
    <div class="mt-8 text-center">
         <!-- Link back to the staff dashboard -->
         <!-- Path from 'teacher/' up to 'School/' is '../'. Assuming staff dashboard is at School/staff_dashboard.php -->
         <a href="./staff_dashboard.php" class="back-link">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                   <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
              </svg>
              Back to Dashboard
         </a>
    </div>

    <!-- Logout Link (Optional, usually in navbar) -->
     <p class="mt-8 text-center text-gray-600">
        <a href="../logout.php" class="text-red-600 hover:underline font-medium">Logout</a>
     </p>


</main> <!-- End Main Content Area -->

</body>
</html>