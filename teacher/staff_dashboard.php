<?php
// Start the session
session_start();

// Adjust path to config.php based on directory structure
// Assuming this file is in the School/ directory (web root)
require_once "../config.php"; // Path to config.php

// --- ACCESS CONTROL ---
// Define roles that are allowed to access this staff dashboard
$allowed_staff_roles_dashboard = ['teacher', 'principal', 'staff']; // Include staff role here as well

// Check if the user is NOT logged in, OR if they are logged in but their role
// is NOT in the list of allowed staff roles.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_staff_roles_dashboard)) {
    $_SESSION['operation_message'] = "Access denied. Please log in with appropriate staff credentials.";
    // Path from School/ to login.php (assuming login.php is also in School/)
    header("location: ./login.php"); // Path to login.php
    exit;
}

// --- RETRIEVE STAFF INFORMATION FROM SESSION ---
$staff_id = $_SESSION['id'] ?? null;
$staff_display_name = $_SESSION['name'] ?? 'Staff Member';
$staff_role = $_SESSION['role'] ?? 'Staff';

$staff_data = null;
$fetch_staff_error = "";

// --- Fetch Staff Profile Data from DB ---
// Needed for auth confirmation, role-based permissions, and displaying 'Your Information' card
if ($staff_id !== null && $link !== false) {
    // Select necessary columns for auth, role check, and profile display
    $sql_fetch_staff = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at FROM staff WHERE staff_id = ?";

    if ($stmt_fetch = mysqli_prepare($link, $sql_fetch_staff)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $staff_id); // Assuming staff_id is INT
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if ($result_fetch && mysqli_num_rows($result_fetch) == 1) {
                $staff_data = mysqli_fetch_assoc($result_fetch);
                // Update display variables with fresh data from DB
                $staff_display_name = $staff_data['staff_name'];
                $staff_role = $staff_data['role'];
                 // Optionally update session variables with fresh data too
                 $_SESSION['name'] = $staff_data['staff_name'];
                 $_SESSION['role'] = $staff_data['role'];

            } else {
                $fetch_staff_error = "Your staff profile could not be found in the database. Please contact an administrator.";
                error_log("Staff Dashboard: Staff profile (ID: $staff_id) not found in DB.");
            }
            if ($result_fetch) mysqli_free_result($result_fetch);
        } else {
            $fetch_staff_error = "Oops! Something went wrong while fetching your profile data. Please try again later.";
            error_log("Staff Dashboard: Staff profile fetch query failed: " . mysqli_stmt_error($stmt_fetch));
        }
        if ($stmt_fetch) mysqli_stmt_close($stmt_fetch);
    } else {
         $fetch_staff_error = "Oops! Something went wrong. Could not prepare profile fetch statement.";
         error_log("Staff Dashboard: Could not prepare staff fetch statement: " . mysqli_error($link));
    }
} else if ($link === false) {
     $fetch_staff_error = "Database connection error. Could not load staff profile.";
} else {
    // This case should ideally be caught by the access control redirect
    $fetch_staff_error = "Staff ID not found in session. Please try logging in again.";
}


// --- Fetch Today's Staff Timetable Data ---
$today_timetable_entries = [];
$fetch_timetable_message = "";
$can_fetch_timetable = ($staff_id !== null && $link !== false);

if ($can_fetch_timetable) {
     // Get today's day of the week (e.g., "Monday")
     $today_day = date('l'); // Full day name

    // Select timetable details for the logged-in staff member for today
    // Order by time_slot
    $sql_fetch_today_timetable = "SELECT day_of_week, time_slot, class_taught, subject_taught FROM staff_timetables WHERE staff_id = ? AND day_of_week = ? ORDER BY time_slot ASC";

    if ($stmt_today_timetable = mysqli_prepare($link, $sql_fetch_today_timetable)) {
        mysqli_stmt_bind_param($stmt_today_timetable, "is", $staff_id, $today_day);

        if (mysqli_stmt_execute($stmt_today_timetable)) {
            $result_today_timetable = mysqli_stmt_get_result($stmt_today_timetable);

            if ($result_today_timetable) {
                $today_timetable_entries = mysqli_fetch_all($result_today_timetable, MYSQLI_ASSOC);
                if (empty($today_timetable_entries)) {
                     $fetch_timetable_message = "You have no timetable entries assigned for today (" . htmlspecialchars($today_day) . ").";
                } else {
                     // Message included in the heading below
                }
            } else {
                 $fetch_timetable_message = "Could not retrieve your timetable entries for today.";
                 error_log("Staff Dashboard: Today's Timetable get_result failed for ID " . $staff_id . ": " . mysqli_stmt_error($stmt_today_timetable));
                 $today_timetable_entries = []; // Ensure array is empty
            }
            if ($result_today_timetable) mysqli_free_result($result_today_timetable);

        } else {
            $fetch_timetable_message = "Error fetching your timetable for today: " . mysqli_stmt_error($stmt_today_timetable);
             error_log("Staff Dashboard: Today's Timetable fetch query failed for ID " . $staff_id . ": " . mysqli_stmt_error($stmt_today_timetable));
             $today_timetable_entries = []; // Ensure array is empty on error
        }
        if ($stmt_today_timetable) mysqli_stmt_close($stmt_today_timetable);

    } else {
         $fetch_timetable_message = "Error preparing timetable fetch statement: " . mysqli_error($link);
         error_log("Staff Dashboard: Could not prepare today's timetable statement: " . mysqli_error($link));
         $today_timetable_entries = []; // Ensure array is empty on error
    }

} else if ($link === false) {
    $fetch_timetable_message = "Database connection error. Could not load your timetable.";
} else if ($staff_id === null) {
    // This case should be caught by the main access control, but included for completeness
    $fetch_timetable_message = "Staff ID not available to fetch timetable.";
}


// --- Fetch Student Data for Table and Stats (LIMITED TO 5 LATEST) ---
// This will fetch students either filtered by teacher's classes or all students,
// and the fetched data will be used for both the stats calculation (average marks)
// and the table display.
$students = [];
$fetch_students_message = "";
$can_fetch_students = ($staff_data !== null && $link !== false); // Ensure staff data is available before fetching students

if ($can_fetch_students) {
    // Select necessary columns for the table display and stats calculation
    // Ensure all required columns are selected here, including virtual_id
    $sql_select_students_base = "SELECT user_id, photo_filename, full_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, current_marks, created_at, virtual_id, 'Active' AS status FROM students";
    // MODIFIED: Order by created_at DESC to get the latest students and LIMIT to 5
    $sql_students_order_limit = " ORDER BY created_at DESC LIMIT 5";

    $where_clauses_students = [];
    $params_students = [];
    $param_types_students = "";
    $filter_applied = false;

     // Keep teacher filtering if needed, otherwise remove this block for Principal/Staff seeing all
     if (isset($staff_data['role']) && $staff_data['role'] === 'teacher') {
         $classes_taught_string = $staff_data['classes_taught'] ?? '';
         $classes_array = array_filter(array_map('trim', explode(',', $classes_taught_string)));

         if (!empty($classes_array)) {
             $placeholder_string = implode(', ', array_fill(0, count($classes_array), '?'));
             $where_clauses_students[] = "current_class IN (" . $placeholder_string . ")";
             $params_students = $classes_array;
             $param_types_students = str_repeat('s', count($classes_array));
             $filter_applied = true;
             $fetch_students_message = "Displaying the <strong>5 latest</strong> students in your assigned classes."; // Updated message format
         } else {
             $can_fetch_students = false;
             $students = [];
             $fetch_students_message = "You have no classes assigned in the system, so no students are listed.";
         }
     } else {
          // Principal or other staff roles see all students
          // No WHERE clause needed here for full access
          $fetch_students_message = "Displaying the <strong>5 latest</strong> student records."; // Updated message format
     }


    // Only proceed with the student list query if $can_fetch_students is still true
    if ($can_fetch_students) {
        $sql_students = $sql_select_students_base;
        if (!empty($where_clauses_students)) {
            $sql_students .= " WHERE " . implode(" AND ", $where_clauses_students);
        }
        $sql_students .= $sql_students_order_limit; // Apply the new order and limit

        if ($stmt_students = mysqli_prepare($link, $sql_students)) {

            if (!empty($params_students)) {
                 mysqli_stmt_bind_param($stmt_students, $param_types_students, ...$params_students);
            }

            if (mysqli_stmt_execute($stmt_students)) {
                $result_students = mysqli_stmt_get_result($stmt_students);

                if ($result_students) { // Check if result is valid
                    $students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
                     // The message already indicates the limit, no need to append count here as it's always 5 or less.
                     // $fetch_students_message .= " (" . count($students) . " total student record(s))."; // Removed total count addition

                } else {
                     // If no results or query executed but no rows
                    if ($filter_applied) {
                         $fetch_students_message = "No students found in your assigned classes."; // Revert message if 0 found after filter
                    } else {
                         if (empty($fetch_students_message) || strpos($fetch_students_message, 'Displaying the <strong>5 latest</strong>') === false) {
                             $fetch_students_message = "No student records found in the system."; // Revert message if 0 found (all students)
                         }
                    }
                     $students = []; // Ensure students array is empty
                }

                if ($result_students) mysqli_free_result($result_students);

            } else {
                 $fetch_students_message = "Error fetching student data: " . mysqli_stmt_error($stmt_students);
                 error_log("Staff Dashboard: Student fetch query failed: " . mysqli_stmt_error($stmt_students));
                 $students = []; // Ensure empty on error
            }

            if ($stmt_students) mysqli_stmt_close($stmt_students);

        } else {
             $fetch_students_message = "Error preparing student fetch statement: " . mysqli_error($link);
             error_log("Staff Dashboard: Could not prepare student fetch statement: " . mysqli_error($link));
             $students = []; // Ensure empty on error
        }
    }

} else if ($link === false) {
    $fetch_students_message = "Database connection error. Could not load student list.";
} else if ($staff_data === null) {
    $fetch_students_message = "Could not fetch staff profile data, student list unavailable.";
}

// --- Calculate Overall Class Score (Average Marks) ---
// This calculation uses the students fetched above, whether filtered or not (now limited to 5).
// Note: Calculating overall average from only 5 latest students might not be meaningful.
// You might want to calculate average from *all* students the staff member has access to,
// regardless of the limit applied to the display table. If so, fetch *all* relevant student IDs/marks separately for the average calculation.
// For simplicity, the current logic uses the $students array which is now limited to 5.
// If you need the overall average from *all* relevant students, you'll need a second query.
$total_marks = 0;
$student_count_with_marks = 0;
$average_marks = 0;
$formatted_average_marks = "N/A";

if (!empty($students)) {
    foreach ($students as $student) {
        // Check if current_marks is set, not null, is numeric, and is not an empty string
        if (isset($student['current_marks']) && is_numeric($student['current_marks']) && $student['current_marks'] !== '') {
            $total_marks += (float) $student['current_marks']; // Cast to float for accurate sum
            $student_count_with_marks++;
        }
    }

    if ($student_count_with_marks > 0) {
        $average_marks = $total_marks / $student_count_with_marks;
        $formatted_average_marks = number_format($average_marks, 2); // Format to 2 decimal places
    }
}


// Check for and display messages from operations
$operation_message_session = "";
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}

// Close database connection at the very end
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(ucfirst($staff_role)); ?> Dashboard</title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Link Google Font (e.g., Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


     <style>
        /* Apply custom font and basic spacing */
        body {
            font-family: 'Inter', sans-serif; /* Apply chosen font */
            padding-top: 4rem; /* Adjust based on navbar height */
            color: #374151; /* Default text color (gray-700) */
            /* Dynamic Background Animation */
            background: linear-gradient(-45deg, #b5e2ff, #d9f4ff, #b5e2ff, #d9f4ff); /* Softer, more continuous gradient */
            background-size: 400% 400%; /* Size larger than viewport */
            animation: gradientAnimation 15s ease infinite; /* Animation properties */
            background-attachment: fixed; /* Keep background fixed during scroll */
        }

        /* Keyframes for background animation - Adjusted */
        @keyframes gradientAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Main content white card container (for the Student Records section) */
        .content-card {
             background-color: #ffffff;
             padding: 1.5rem; /* p-6 */
             border-radius: 0.75rem; /* rounded-lg */
             /* Softer, layered shadow - match screenshot */
             box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
             border: 1px solid #e5e7eb; /* gray-200 border */
        }

         /* Custom styles for alerts - Integrated with Tailwind utilities */
        .alert {
             border-left-width: 4px;
             padding: 1rem;
             margin-bottom: 1.5rem;
             border-radius: 0.375rem; /* rounded-md */
             box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
             display: flex; /* Use flexbox for icon and text */
             align-items: center;
             gap: 0.75rem; /* space-x-3 */
             line-height: 1.5; /* leading-relaxed */
        }
        .alert-icon {
            flex-shrink: 0; /* Prevent icon from shrinking */
            width: 1.5rem; /* w-6 */
            height: 1.5rem; /* h-6 */
        }
         .alert p {
             margin: 0; /* Remove default paragraph margin */
         }
         .alert-info {
              background-color: #e0f7fa; /* Adjusted lighter */
              border-color: #0891b2;    /* cyan-600 */
              color: #0e7490;         /* cyan-800 */
         }
          .alert-success {
               background-color: #dcfce7; /* Adjusted lighter */
               border-color: #22c55e;    /* green-500 */
               color: #15803d;         /* green-800 */
         }
           .alert-warning {
                background-color: #fff7ed;
                border-color: #f97316;
                color: #ea580c;
           }
            .alert-danger {
                 background-color: #fee2e2;
                 border-color: #ef4444;
                 color: #b91c1c;
            }

        /* Dashboard Card Base Style */
        .dashboard-stats-card {
             background-color: #ffffff;
             padding: 1.5rem;
             border-radius: 0.75rem; /* rounded-lg */
             box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.08), 0 1px 2px 0 rgba(0, 0, 0, 0.04); /* Subtle shadow */
             border: 1px solid #e5e7eb; /* gray-200 border */
             display: flex;
             flex-direction: column;
             justify-content: space-between;
        }

        /* Dashboard Card Content Styles */
        .dashboard-stats-card h3 {
            font-size: 1.125rem; /* text-lg */
            font-weight: 600; /* font-semibold */
            color: #1f2937; /* gray-800 */
            margin-bottom: 0.75rem; /* mb-3 */
        }
         .dashboard-stats-card .card-value {
             font-size: 2.25rem; /* text-4xl */
             font-weight: 700; /* font-bold */
             line-height: 1;
         }
          .dashboard-stats-card .card-description {
              font-size: 0.875rem; /* text-sm */
              color: #6b7280; /* gray-500 */
              margin-top: 0.5rem; /* mt-2 */
          }
        .stats-card-icon {
             width: 3.5rem; /* w-14 */
             height: 3.5rem; /* h-14 */
             flex-shrink: 0;
        }


        /* Table specific styles */
        .student-table, .timetable-table { /* Apply to both student and timetable tables */
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .student-table th,
        .student-table td,
        .timetable-table th, /* Apply to timetable */
        .timetable-table td /* Apply to timetable */
         {
            padding: 0.75rem 1rem; /* py-3 px-4 */
            text-align: left;
            border-bottom: 1px solid #e5e7eb; /* gray-200 */
        }
        .student-table th, .timetable-table th { /* Apply to timetable header */
            background-color: #f9fafb; /* gray-50 */
            font-size: 0.75rem; /* text-xs */
            font-weight: 600; /* font-semibold */
            color: #6b7280; /* gray-500 */
            text-transform: uppercase;
            letter-spacing: 0.05em; /* tracking-wider */
        }
         .student-table td, .timetable-table td { /* Apply to timetable data cells */
             font-size: 0.875rem; /* text-sm */
             color: #4b5563; /* gray-600 */
             line-height: 1.4; /* leading-snug */
         }
         .student-table tbody tr:hover, .timetable-table tbody tr:hover { /* Apply to timetable hover */
              background-color: #f3f4f6; /* gray-100 hover */
         }

        /* Photo Cell Styling */
         .photo-cell {
             width: 40px;
             padding-top: 0.5rem;
             padding-bottom: 0.5rem;
         }
         .photo-container {
             width: 2.5rem; /* w-10 */
             height: 2.5rem; /* h-10 */
             border-radius: 9999px; /* rounded-full */
             background-color: #d1d5db; /* gray-300 */
             display: flex;
             align-items: center;
             justify-content: center;
             overflow: hidden;
             flex-shrink: 0;
         }
          .photo-container img {
              width: 100%;
              height: 100%;
              object-fit: cover;
          }
           /* Fallback Initials Styling */
           .photo-container span {
               color: #ffffff;
               font-size: 0.875rem;
               font-weight: 500;
           }


        /* Status Text Styling */
         .status-text {
             font-size: 0.875rem;
             font-weight: 500;
         }
         .status-active { color: #10b981; } /* emerald-500 */
         .status-inactive { color: #ef4444; } /* red-500 */


         /* Action Links Styling */
          .action-links a {
               text-decoration: none;
               font-size: 0.8125rem;
               font-weight: 500;
               margin-right: 0.5rem;
               transition: color 0.1s ease-in-out, text-decoration 0.1s ease-in-out;
          }
           .action-links a:last-child {
               margin-right: 0;
           }

           /* Specific action link colors - Match screenshot */
           .action-links a.view-link { color: #2563eb; } /* blue-600 */
           .action-links a.edit-link { color: #059669; } /* green-600 */
           .action-links a.deactivate-link { color: #f97316; } /* orange-500 */
           .action-links a.delete-link { color: #dc2626; } /* red-600 */

            /* Hover/Focus states for accessibility */
             .action-links a:hover { text-decoration: underline; }
             .action-links a.view-link:hover { color: #1d4ed8; } /* blue-700 */
             .action-links a.edit-link:hover { color: #047857; } /* green-700 */
             .action-links a.deactivate-link:hover { color: #eb620b; } /* orange-600 */
             .action-links a.delete-link:hover { color: #b91c1c; } /* red-700 */

             .action-links a:focus {
                 outline: none;
                 box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5); /* blue-500 with opacity */
                 border-radius: 0.125rem; /* rounded-sm */
             }


        /* Form Element Styling (Search Input) */
        .form-label {
             display: block;
             font-size: 0.875rem;
             font-weight: 500;
             color: #374151;
             margin-bottom: 0.5rem;
         }
        .form-input {
            display: block;
            width: 100%;
            padding: 0.625rem 1rem; /* py-2.5 px-4 */
            font-size: 1rem;
            line-height: 1.5;
            color: #4b5563;
            background-color: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.075);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-input:focus {
            border-color: #60a5fa;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.25);
        }


         /* Button Styling (Download CSV) */
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
          }
           .btn-primary {
               color: #ffffff;
               background-color: #10b981;
               border: 1px solid #10b981;
               box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
          }
            .btn-primary:hover {
                 background-color: #059669;
                 border-color: #059669;
            }
            .btn-primary:focus {
                 outline: none;
                 box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.5);
            }
             .btn-primary i {
                  margin-right: 0.5rem;
             }
            .btn-secondary { /* Added secondary button style */
                 color: #374151; /* gray-700 */
                 background-color: #ffffff;
                 border-color: #d1d5db; /* gray-300 */
                 box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            }
             .btn-secondary:hover {
                  background-color: #f9fafb; /* gray-50 */
             }
             .btn-secondary:focus {
                  outline: none;
                  box-shadow: 0 0 0 0.2rem rgba(209, 213, 219, 0.5); /* gray-300 with opacity */
             }


        /* Ensure overflow for wide tables on small screens */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
             margin-bottom: 1rem;
        }

        /* Styles for the visual time slot squares */
        .time-slot-squares {
            display: flex;
            gap: 0.75rem; /* Increased gap */
            margin-bottom: 1.5rem; /* space below squares */
            overflow-x: auto; /* Allow horizontal scrolling if many slots */
            padding-bottom: 0.5rem; /* Add some padding if scrollbar appears */
            /* Hide scrollbar for cleaner look */
            scrollbar-width: none;  /* Firefox */
            -ms-overflow-style: none;  /* IE and Edge */
        }
        .time-slot-squares::-webkit-scrollbar {
             display: none; /* Chrome, Safari, Opera*/
        }

        .time-slot-square {
            flex: 0 0 200px; /* Slightly larger fixed width */
            height: 70px; /* Slightly taller height */
            background-color: #e0f2f7; /* Light blue background */
            border: 1px solid #06b6d4; /* Cyan border */
            border-radius: 0.375rem; /* rounded-md, slightly larger */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-size: 0.875rem; /* text-sm */
            font-weight: 600; /* font-semibold */
            color: #0e7490; /* text-cyan-800 */
            padding: 0.5rem;
            line-height: 1.3; /* Adjusted line height */
            box-shadow: 0 1px 3px rgba(0,0,0,0.08); /* Subtle shadow */
            transition: transform 0.1s ease-in-out, box-shadow 0.1s ease-in-out;
        }
         .time-slot-square:hover {
             transform: translateY(-2px); /* Slight lift effect */
             box-shadow: 0 3px 6px rgba(0,0,0,0.1); /* Stronger shadow on hover */
         }
         .time-slot-square .time {
             font-size: 1rem; /* text-base */
             font-weight: 700; /* font-bold */
             margin-bottom: 0.25rem;
             color: #0891b2; /* Slightly darker cyan for time */
         }


    </style>
</head>
<body>

    <?php
    // INCLUDE NAVBAR
    // IMPORTANT: Verify the path to your staff_navbar.php file
    $navbar_path = "./staff_navbar.php"; // Assuming it's in the same directory as staff_dashboard.php (School/)

    if (file_exists($navbar_path)) {
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
    <!-- Increased max-width for better use of screen space on larger monitors -->
    <main class="w-full max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pb-8">

        <?php
            // Display messages from session with icons
            if (!empty($operation_message_session)) {
               $alert_class = 'alert-info';
               $icon_svg = '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'; // Info icon
               if (strpos($operation_message_session, 'successfully') !== false || strpos($operation_message_session, 'Welcome') !== false) {
                   $alert_class = 'alert-success';
                   $icon_svg = '<svg class="alert-icon text-green-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'; // Check icon
               } elseif (strpos($operation_message_session, 'Access denied') !== false || strpos($operation_message_session, 'Error') !== false || strpos($operation_message_session, 'Failed') !== false) {
                    $alert_class = 'alert-danger';
                    $icon_svg = '<svg class="alert-icon text-red-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'; // X icon
               } elseif (strpos($operation_message_session, 'Warning') !== false || strpos($operation_message_session, 'not found') !== false || strpos($operation_message_session, 'Please correct') !== false || strpos($operation_message_session, 'unavailable') !== false || strpos($operation_message_session, 'no students listed') !== false) {
                    $alert_class = 'alert-warning';
                    $icon_svg = '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>'; // Warning icon
               }
               echo "<div class='alert " . $alert_class . "' role='alert'>" . $icon_svg;
               echo "<p>" . nl2br(htmlspecialchars($operation_message_session)) . "</p>";
               echo "</div>";
           }
        ?>

        <?php
            // Display staff profile fetch error message if any with icon
            if (!empty($fetch_staff_error)) {
                echo "<div class='alert alert-warning' role='alert'>";
                 echo '<svg class="alert-icon text-orange-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.246 3.01-1.881 3.01H4.558c-1.636 0-2.636-1.676-1.88-3.01l5.58-9.92zM10 13a1 1 0 100-2 1 1 0 000 2zm-1-8a1 1 0 112 0v4a1 1 0 11-2 0V5z" clip-rule="evenodd"></path></svg>';
                echo "<p>" . nl2br(htmlspecialchars($fetch_staff_error)) . "</p>";
                echo "</div>";
            }
        ?>

        <!-- Dashboard Header and Welcome -->
        <div class="mb-8">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-1">
                 <?php echo htmlspecialchars(ucfirst($staff_role)); ?> Dashboard
            </h1>
            <?php if ($staff_data): ?>
                 <p class="text-lg text-gray-700">
                     Welcome back, <strong class="font-semibold text-gray-800"><?php echo htmlspecialchars($staff_display_name); ?>!</strong>
                 </p>
             <?php endif; ?>
        </div>

        <!-- Dashboard Stats/Widgets Section -->
        <!-- Using the dashboard-stats-card class and grid layout -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10"> <!-- Adjusted gap and mb -->

            <!-- Card 1: Staff Profile/Info -->
             <?php if ($staff_data): ?>
             <div class="dashboard-stats-card">
                 <h3 class="text-lg font-semibold text-gray-800">Your Information</h3>
                 <div class="text-sm text-gray-700 space-y-2 mt-4"> <!-- Adjusted spacing -->
                      <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($staff_role)); ?></p>
                      <p><strong>Unique ID:</strong> <?php echo htmlspecialchars($staff_data['unique_id'] ?? 'N/A'); ?></p>
                      <p><strong>Email:</strong> <?php echo htmlspecialchars($staff_data['email'] ?? 'N/A'); ?></p>
                      <?php if (!empty($staff_data['classes_taught'])): ?>
                      <p><strong>Classes Taught:</strong> <?php echo htmlspecialchars($staff_data['classes_taught']); ?></p>
                      <?php endif; ?>
                 </div>
             </div>
             <?php endif; ?>

            <!-- Card 2: Overall Class Score (Calculated from 5 latest students) -->
            <div class="dashboard-stats-card bg-green-50 border-green-200">
                <h3 class="text-lg font-semibold text-green-800">Latest Student Avg. Score</h3> <!-- Updated Heading -->
                <div class="flex items-center justify-between flex-grow mt-4"> <!-- Adjusted spacing -->
                    <p class="card-value text-green-700">
                         <?php echo htmlspecialchars($formatted_average_marks); ?>%
                    </p>
                    <!-- Icon: Bar chart -->
                     <svg class="stats-card-icon text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
                <?php if ($student_count_with_marks > 0): ?>
                    <p class="card-description text-green-700 mt-2">Average from the <?php echo $student_count_with_marks; ?> students displayed.</p> <!-- Updated description -->
                <?php else: ?>
                     <p class="card-description text-green-700 mt-2">No student marks available for average calculation from displayed students.</p> <!-- Updated description -->
                <?php endif; ?>
            </div>

             <!-- Card 3: Students Displayed (Latest 5) -->
            <div class="dashboard-stats-card bg-blue-50 border-blue-200">
                <h3 class="text-lg font-semibold text-blue-800">Students Displayed</h3> <!-- Updated Heading -->
                 <div class="flex items-center justify-between flex-grow mt-4"> <!-- Adjusted spacing -->
                    <p class="card-value text-blue-700"><?php echo count($students); ?></p> <!-- Shows count of fetched students (max 5) -->
                     <!-- Icon: Users -->
                      <svg class="stats-card-icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M12 20.646v-1.646m0 0V14m0 0a2 2 0 100 4m0-4a2 2 0 110 4m-8-2a2 2 0 11-4 0 2 2 0 014 0zM14 15a2 2 0 10-4 0 2 2 0 014 0zm0 0v2.5a2.5 2.5 0 002.5 2.5h1a2.5 2.5 0 002.5-2.5v-1l1.42-1.42a2 2 0 000-2.84L19 9l1.42-1.42a2 2 0 000-2.84L18 3l-1.42 1.42a2 2 0 00-2.84 0L13 6l-1.42-1.42a2 2 0 00-2.84 0L8 6z"></path></svg>
                 </div>
                 <p class="card-description text-blue-700 mt-2">Showing the latest students.</p> <!-- Updated description -->
            </div>

        </div> <!-- End Dashboard Stats Section -->


        <!-- NEW: Today's Timetable Section -->
        <section id="today-timetable" class="content-card mt-8">
            <h2 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4 text-center">Today's Timetable (<?php echo htmlspecialchars(date('l')); ?>)</h2> <!-- Show today's day -->

            <?php
             // Display timetable specific message (e.g., "No entries found", DB errors during fetch)
             if (!empty($fetch_timetable_message)) {
                  echo "<div class='alert alert-info' role='alert'>";
                   echo '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
                  echo "<p>" . nl2br(htmlspecialchars($fetch_timetable_message)) . "</p>";
                  echo "</div>";
              }
            ?>

            <?php if (!empty($today_timetable_entries)): ?>
                 <!-- Visual representation of time slots (Simplified Squares) -->
                 <!-- Note: This is a basic visual. Complex time-based layout requires more CSS/JS. -->
                 <div class="time-slot-squares mb-6">
                     <?php foreach ($today_timetable_entries as $entry): ?>
                         <div class="time-slot-square">
                             <div class="time"><?php echo htmlspecialchars($entry['time_slot']); ?></div>
                             <div><?php echo htmlspecialchars($entry['class_taught']); ?></div>
                             <div><?php echo htmlspecialchars($entry['subject_taught']); ?></div>
                         </div>
                     <?php endforeach; ?>
                 </div>

                 <!-- Timetable details in a table -->
                 <div class="table-responsive">
                     <table class="timetable-table">
                         <thead>
                             <tr>
                                 <th>Time Slot</th>
                                 <th>Class</th>
                                 <th>Subject</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($today_timetable_entries as $entry): ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($entry['time_slot']); ?></td>
                                     <td><?php echo htmlspecialchars($entry['class_taught']); ?></td>
                                     <td><?php echo htmlspecialchars($entry['subject_taught']); ?></td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
            <?php endif; // End if (!empty($today_timetable_entries)) ?>

             <!-- Link to view full timetable (optional) -->
             <div class="mt-6 text-center">
                 <!-- Path from Staff Dashboard (e.g. School/) to view_my_timetable (e.g. School/teacher/) -->
                  <!-- Assuming view_my_timetable.php is in School/teacher/ -->
                 <a href="teacher/view_my_timetable.php" class="btn btn-secondary inline-flex items-center">
                     <i class="fas fa-calendar-alt mr-2"></i> View Full Timetable
                 </a>
             </div>


        </section> <!-- End Today's Timetable Section -->


        <!-- Student Records Section - Wrapped in content-card -->
        <section id="student-records" class="content-card mt-8"> <!-- Added mt-8 for space below timetable card -->

             <h2 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 text-center">Latest 5 Student Records</h2> <!-- Updated Heading -->

             <!-- Search and Download Section -->
             <!-- Moved Search and Download to be inside the Student Records card -->
             <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                 <div class="w-full sm:w-auto sm:flex-grow">
                      <label for="search" class="form-label sr-only">Search Student Records:</label> <!-- sr-only hides label visually -->
                      <input type="text" id="search" name="search" class="form-input" placeholder="Search by ID, name, class, phone, etc.">
                 </div>
                  
             </div>


            <?php
            // Display student list message (styled as in screenshot)
            // This alert is specifically for the student count message
            ?>
            <div class="alert alert-info mb-4">
                 <?php
                    // Use specific icon for the info alert message
                     echo '<svg class="alert-icon text-cyan-600" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
                 ?>
                 <p><?php echo nl2br(htmlspecialchars($fetch_students_message)); ?></p>
            </div>


            <?php if (!empty($students)): ?>
                <div class="table-responsive"> <!-- Wrapper for horizontal scrolling on small screens -->
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>PHOTO</th>
                                <th>USER ID</th>
                                <th>VIRTUAL ID</th>
                                <th>FULL NAME</th>
                                <th>PHONE</th>
                                <th>CURRENT CLASS</th>
                                <th>PREVIOUS CLASS</th> <!-- Added -->
                                <th>PREVIOUS SCHOOL</th> <!-- Added -->
                                <th>CURRENT MARKS (%)</th> <!-- Added -->
                                <th>STATUS</th>
                                <th>CREATED AT</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td class="photo-cell">
                                         <!-- Photo Container -->
                                         <div class="photo-container">
                                             <?php if (!empty($student['photo_filename'])): ?>
                                                 <!-- Display the image if photo_filename exists -->
                                                 <!-- Assume photo_filename is a full URL or path relative to web root -->
                                                 <!-- If photos are in a specific uploads folder, adjust path like: -->
                                                  <!-- <img src="./uploads/student_photos/<?php // echo htmlspecialchars($student['photo_filename']); ?>" alt="<?php // echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> Photo"> -->
                                                 <img src="<?php echo htmlspecialchars($student['photo_filename']); ?>" alt="<?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?> Photo">
                                             <?php else: ?>
                                                 <!-- Fallback: Display first initial -->
                                                 <span><?php echo htmlspecialchars(substr($student['full_name'] ?? 'S', 0, 1)); ?></span>
                                             <?php endif; ?>
                                         </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['virtual_id'] ?? 'N/A'); ?></td> <!-- Placeholder -->
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone_number'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['current_class'] ?? 'N/A'); ?></td> <!-- Added -->
                                    <td><?php echo htmlspecialchars($student['previous_class'] ?? 'N/A'); ?></td> <!-- Added -->
                                    <td><?php echo htmlspecialchars($student['previous_school'] ?? 'N/A'); ?></td> <!-- Added -->
                                    <td><?php echo htmlspecialchars($student['current_marks'] ?? 'N/A'); ?></td> <!-- Added -->
                                     <td>
                                          <!-- Status Placeholder -->
                                          <?php
                                           // Assuming 'is_active' column exists and is 1 for Active, 0 for Inactive
                                           // The SELECT query currently hardcodes 'Active' as status.
                                           // If you have an 'is_active' column in students, you should fetch it
                                           // and use it here to determine the status.
                                           // Example: $status = (isset($student['is_active']) && $student['is_active'] == 1) ? 'Active' : 'Inactive';
                                           // For now, using the hardcoded 'Active' from the query alias
                                           $status = $student['status'] ?? 'Unknown';
                                           $status_class = 'status-active'; // Default class
                                           // Add logic here if fetching actual status from DB
                                           // if (isset($student['is_active']) && $student['is_active'] == 0) $status_class = 'status-inactive';
                                          ?>
                                          <span class="status-text <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                                     </td>
                                    <td><?php echo htmlspecialchars($student['created_at']); ?></td>
                                    <td class="action-links">
                                        <?php if (isset($staff_data['role'])): ?>
                                            <?php if ($staff_data['role'] === 'teacher'): ?>
                                                 <!-- Teachers might only edit marks/class -->
                                                 <!-- Path from Staff Dashboard (e.g. School/) to edit_student_teacher (e.g. School/teacher/) -->
                                                 <a href="teacher/edit_student_teacher.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="edit-link">Edit</a>
                                            <?php elseif ($staff_data['role'] === 'principal' || $staff_data['role'] === 'staff'): ?>
                                                 <!-- Principal/Staff might edit full record and other actions -->
                                                 <!-- Path from Staff Dashboard (e.g. School/) to edit_student_principal (e.g. School/) -->
                                                 <a href="./edit_student_principal.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="edit-link">Edit</a>
                                                 <!-- Placeholder links for Deactivate/Delete - require backend logic -->
                                                 <a href="#" class="deactivate-link" onclick="alert('Deactivate Student ID: <?php echo htmlspecialchars($student['user_id']); ?> - Requires backend logic'); return false;">Deactivate</a>
                                                 <a href="#" class="delete-link" onclick="alert('Delete Student ID: <?php echo htmlspecialchars($student['user_id']); ?> - Requires backend logic'); return false;">Delete</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                 <?php
                 // No students found message is handled by the alert above the table
                 ?>
            <?php endif; ?>

             <!-- Optional: Link to view all students if list is limited -->
             <?php if (count($students) >= 5 && (isset($staff_data['role']) && ($staff_data['role'] === 'principal' || $staff_data['role'] === 'staff')) ): ?>
                  <div class="mt-6 text-center">
                      <!-- Create a view_all_students.php page or similar -->
                       <a href="./view_all_students.php" class="btn btn-secondary inline-flex items-center">
                           <i class="fas fa-users mr-2"></i> View All Students
                       </a>
                  </div>
              <?php endif; ?>


        </section> <!-- End Student Records Section -->

         <!-- Logout Link -->
         <p class="mt-8 text-center text-gray-600"> <!-- Adjusted margin -->
            <!-- Path from Staff Dashboard (e.g. School/) to logout.php (e.g. School/) -->
            <a href="./logout.php" class="text-red-600 hover:underline font-medium">Logout</a>
         </p>


    </main> <!-- End Main Content Area -->

    <!-- Note: JavaScript would be needed to make the Search input functional -->

</body>
</html>