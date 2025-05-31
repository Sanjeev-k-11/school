<?php
// Start the session
session_start();

// Include database configuration file
// Path from 'teacher/' up to 'School/' is '../'
require_once "../config.php"; // Assuming config.php is in the parent directory (School/)

// Define roles that are allowed to access this staff dashboard
// These roles must match the values stored in $_SESSION['role'] by your login.php
$allowed_staff_roles_dashboard = ['teacher', 'principal', 'staff']; // Roles allowed to view this dashboard

// --- CORRECTED ACCESS CONTROL ---
// Check if the user is NOT logged in OR if they are logged in but their role
// is NOT one of the allowed staff roles.
// Removed the incorrect $_SESSION['user_type'] !== 'staff' check.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role'], $allowed_staff_roles_dashboard)) {
    $_SESSION['operation_message'] = "Access denied. Please log in with appropriate staff credentials."; // Store plain text message
    // Path from THIS file (assumed to be in teacher/) up to 'School/' is '../'
    header("location: ../login.php"); // Assuming login.php is in the parent directory (School/)
    exit; // Always exit after a header redirect
}

// --- CORRECTED RETRIEVAL OF STAFF ID, NAME, ROLE FROM SESSION ---
// Retrieve staff information from session - used primarily for initial display/fallback
// The login.php sets the ID as $_SESSION['id'] and name as $_SESSION['name']
$staff_id = $_SESSION['id'] ?? null; // <<< CORRECTED: Use $_SESSION['id']
$staff_display_name = $_SESSION['name'] ?? 'Staff Member'; // <<< CORRECTED: Use $_SESSION['name']
$staff_role_from_session = $_SESSION['role'] ?? 'Staff'; // Use session 'role' set by login.php


$staff_data = null; // Variable to hold the fetched staff data from DB
$fetch_staff_error = ""; // To display messages about fetching the profile

// Initialize statement variable for fetching staff data
$stmt_fetch = false;


// --- Fetch Staff Profile Data from DB (Recommended to get full details and actual role/classes) ---
// Fetching staff data is necessary to get details for the profile section and classes taught for filtering
// This runs IF a valid staff_id is in the session AND the DB connection is open
if ($staff_id !== null && $link !== false) {
    // Select all necessary columns for the profile section and student filtering
    $sql_fetch_staff = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at FROM staff WHERE staff_id = ?"; // Ensure your 'staff' table has these columns

    if ($stmt_fetch = mysqli_prepare($link, $sql_fetch_staff)) {
        mysqli_stmt_bind_param($stmt_fetch, "i", $staff_id); // Assuming staff_id is INT
        if (mysqli_stmt_execute($stmt_fetch)) {
            $result_fetch = mysqli_stmt_get_result($stmt_fetch);
            if ($result_fetch && mysqli_num_rows($result_fetch) == 1) {
                $staff_data = mysqli_fetch_assoc($result_fetch);
                // Update display variables and session with fresh data from DB - important for accuracy
                $staff_display_name = $staff_data['staff_name']; // Use DB data for display
                $staff_role = $staff_data['role']; // Use the actual role from the DB for filtering logic
                 // Optionally update session variables with fresh data too (can be useful)
                 $_SESSION['name'] = $staff_data['staff_name'];
                 $_SESSION['role'] = $staff_data['role'];

            } else {
                $fetch_staff_error = "Your staff profile could not be found in the database or is duplicated. Please contact an administrator.";
                 error_log("Staff ID from session ($staff_id) not found or duplicate in DB.");
                 // In a real application, you might log out the user if their profile is missing from the DB
                 // header("location: ../logout.php"); exit; // Uncomment if profile missing is critical
            }
            if ($result_fetch) mysqli_free_result($result_fetch); // Free result set
        } else {
            $fetch_staff_error = "Oops! Something went wrong while fetching your profile data. Please try again later.";
            error_log("Staff profile fetch query failed: " . mysqli_stmt_error($stmt_fetch));
        }
         // Close the staff fetch statement if it was successfully prepared
        if ($stmt_fetch !== false && is_object($stmt_fetch)) {
             mysqli_stmt_close($stmt_fetch);
             $stmt_fetch = false; // Reset variable
        }

    } else {
         $fetch_staff_error = "Oops! Something went wrong. Could not prepare profile fetch statement.";
         error_log("Staff profile prepare fetch statement failed: " . mysqli_error($link));
    }
// Handle cases where $staff_id is null or DB connection failed *before* staff fetch
} else if ($link === false) { // Condition for DB connection failure
     $fetch_staff_error = "Database connection error. Could not load staff profile.";
} else { // staff_id is null - this should be caught by auth check, but defensive
    $fetch_staff_error = "Staff ID not found in session. Please try logging in again."; // This likely means $_SESSION['id'] wasn't set correctly by login

    // --- CORRECTED error_log LINE SYNTAX ---
    // Safely log the issue using concatenation
    error_log("Staff Dashboard: Staff ID ('" . ($_SESSION['id'] ?? 'N/A') . "') not found in session for staff.");
    // --- END CORRECTED error_log LINE ---
}


// --- Fetch Student Data (Restricted View for Staff) ---
$students = []; // Initialize an empty array
$fetch_students_message = ""; // Message about the student list (e.g., no students found)
// Only attempt to fetch students if staff data was successfully retrieved AND DB link is valid
$can_fetch_students = ($staff_data !== null && $link !== false);

// Use the staff role fetched from DB for filtering logic
// If $staff_data fetch failed, $staff_role_for_filtering will effectively be null,
// which will fall into the 'else' block below, preventing student fetch.
$staff_role_for_filtering = $staff_data['role'] ?? null;


$sql_select_students_base = "SELECT user_id, full_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, current_marks, created_at FROM students"; // Ensure these columns exist in your 'students' table
$sql_students_order = " ORDER BY current_class ASC, full_name ASC"; // Order students

$where_clauses_students = [];
$params_students = [];
$param_types_students = "";
$filter_applied = false; // Track if class filter was applied (only for teacher filter)
$stmt_students = false; // Initialize statement variable for student fetch

if ($can_fetch_students) {
    // Determine filtering based on the *fetched* staff role from the database ($staff_data['role'])
    if ($staff_role_for_filtering === 'teacher') {
        // --- REINSTATED TEACHER FILTERING LOGIC ---
        $classes_taught_string = $staff_data['classes_taught'] ?? ''; // Use data from the fetched row
        // Split the comma-separated string into an array, trim spaces, and remove empty elements
        $classes_array = array_filter(array_map('trim', explode(',', $classes_taught_string)));

        if (!empty($classes_array)) {
            // Build the WHERE IN clause dynamically based on the teacher's classes
            $placeholder_string = implode(', ', array_fill(0, count($classes_array), '?'));
            $where_clauses_students[] = "current_class IN (" . $placeholder_string . ")";
            $params_students = array_values($classes_array); // Use array_values to re-index numerically for bind_param
            $param_types_students = str_repeat('s', count($params_students)); // All classes are strings
            $filter_applied = true;
            $fetch_students_message = "Showing students in your assigned classes (" . htmlspecialchars($staff_data['classes_taught']) . ").";
        } else {
            // Teacher has no classes listed in DB, so no students match their classes
            $can_fetch_students = false; // Prevent the query execution
            $students = []; // Explicitly ensure the list is empty
            $fetch_students_message = "You have no classes assigned in the system, so no students are listed.";
        }
    } else if ($staff_role_for_filtering === 'principal' || $staff_role_for_filtering === 'staff') {
         // For Principal or generic staff roles, display all students
         // No WHERE clause needed based on class
         $fetch_students_message = "Showing all student records.";
    } else {
        // Unexpected role found in DB - this shouldn't happen if login validation is correct,
        // but handle defensively.
         $can_fetch_students = false; // Prevent the query execution
         $students = [];
         $fetch_students_message = "Could not determine appropriate student list for your role.";
         error_log("Staff Dashboard: Unexpected staff role '" . htmlspecialchars($staff_role_for_filtering) . "' found for user ID: " . $staff_id);
    }
    // --- END REINSTATED TEACHER FILTERING LOGIC ---


    // Only proceed with the student list query if $can_fetch_students is still true
    if ($can_fetch_students) {
        $sql_students = $sql_select_students_base;
        if (!empty($where_clauses_students)) {
            $sql_students .= " WHERE " . implode(" AND ", $where_clauses_students);
        }
        $sql_students .= $sql_students_order;

        // Execute the select statement
        if ($stmt_students = mysqli_prepare($link, $sql_students)) {

            // Bind parameters if there are any (now only for teacher's class filtering)
            if (!empty($params_students)) {
                 // Use the splat operator (...) to pass the array elements as individual arguments
                 mysqli_stmt_bind_param($stmt_students, $param_types_students, ...$params_students);
            }

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt_students)) {
                $result_students = mysqli_stmt_get_result($stmt_students);

                // Check if there are results
                if ($result_students && mysqli_num_rows($result_students) > 0) {
                    $students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
                    // $fetch_students_message is already set above based on role/filter
                } else {
                    // If no students found AFTER applying filters (or showing all)
                    if ($filter_applied) { // Check if a filter *was* applied
                         $fetch_students_message = "No students found in your assigned classes.";
                    } else { // No filter applied, but no students found in the entire system
                         // Only set this message if $fetch_students_message wasn't already set for 'Showing all'
                         // Check for existing message content before overwriting a more specific one
                         if (empty($fetch_students_message) || strpos(strtolower($fetch_students_message), 'showing all') === false) {
                              $fetch_students_message = "No student records found in the system.";
                         }
                    }
                     $students = []; // Ensure students array is empty if no rows found
                }

                // Free result set
                if ($result_students) mysqli_free_result($result_students);

            } else {
                 // Override message with execution error
                 $fetch_students_message = "Error fetching student data: " . mysqli_stmt_error($stmt_students);
                 error_log("Staff Dashboard student fetch query failed: " . mysqli_stmt_error($stmt_students));
            }

             // Close the student fetch statement if it was successfully prepared
             if ($stmt_students !== false && is_object($stmt_students)) {
                 mysqli_stmt_close($stmt_students);
                 $stmt_students = false; // Reset variable
             }

        } else {
             // Override message with prepare error
             $fetch_students_message = "Error preparing student fetch statement: " . mysqli_error($link);
             error_log("Staff Dashboard prepare student fetch statement failed: " . mysqli_error($link));
        }
    } // End if ($can_fetch_students) for query execution

// These checks handle cases where $can_fetch_students was false before attempting the query
} else if ($link === false) { // Database connection failed case
    // This case was handled earlier ($fetch_staff_error would be set), but add student message
    // $fetch_staff_error is set, so the combined message will show that first.
    $fetch_students_message = "Database connection error. Could not load student list."; // Add a student-specific message
} else if ($staff_data === null) { // Staff data fetch failed case
    // This case was handled earlier via $fetch_staff_error
    // $fetch_staff_error is set, so the combined message will show that.
    $fetch_students_message = "Could not fetch staff profile data, student list unavailable."; // Add a student-specific message
}



// Check for and display messages from operations (login, edit, etc. stored in session)
$operation_message_session = ""; // Use a different variable name to avoid conflict
if (isset($_SESSION['operation_message'])) {
    $operation_message_session = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']); // Clear it after displaying
}

// Close database connection at the very end if it's open
// Removed the deprecated mysqli_ping check
if (isset($link) && is_object($link)) {
     mysqli_close($link);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(ucfirst($staff_data['role'] ?? $staff_role_from_session)); ?> Dashboard - Student List</title>
    <!-- Include Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <!-- Optional: Add a custom stylesheet for minor overrides or fonts if needed -->
     <!-- <link rel="stylesheet" href="../css/style.css"> -->
     <style>
        /* Add specific styles if needed, ensure padding for fixed navbar */
        body {
            padding-top: 4rem; /* Adjust based on navbar height */
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

        /* Specific style for the profile section */
        .profile-section {
            background-color: #f9fafb; /* gray-50 */
            padding: 1.5rem;
            border-radius: 0.5rem; /* rounded-lg */
            margin-bottom: 2rem; /* mb-8 */
            border: 1px solid #e5e7eb; /* gray-200 */
        }
        .profile-section h3 {
            margin-bottom: 1rem; /* mb-4 */
            color: #374151; /* gray-700 */
        }
        .profile-detail {
            margin-bottom: 0.5rem; /* mb-2 */
            color: #4b5563; /* gray-600 */
            font-size: 0.875rem; /* text-sm */
        }
        .profile-detail strong {
             color: #1f2937; /* gray-800 */
        }
         .table-action-link {
             margin-right: 0.5rem; /* space between links */
         }
         .table-action-link:last-child {
             margin-right: 0; /* no margin on the last link */
         }

    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center py-8 px-4">

    <?php
    // --- INCLUDE NAVBAR ---
    // Assuming staff_navbar.php is in the SAME directory as THIS file (staff_dashboard.php or student-list.php)
    // If THIS file is in teacher/ and staff_navbar.php is in teacher/, path is "./staff_navbar.php"
    // If THIS file is in teacher/ and staff_navbar.php is in School/, path is "../staff_navbar.php"
    // Choose the correct path based on your actual file locations
    require_once "./staff_navbar.php"; // Assuming staff_navbar.php is in the SAME directory
    ?>

    <!-- Main Content Area -->
    <div class="w-full max-w-screen-xl mx-auto mt-8 p-4"> <!-- Centered container with max width and padding -->

        <?php
            // Display combined error/operation messages
            // Build the primary message based on priority
            $primary_message = '';
            $primary_message_type = 'info'; // Default type

            if (!empty($fetch_staff_error)) {
                $primary_message = $fetch_staff_error;
                $primary_message_type = 'warning'; // Profile fetch error is usually a warning
            } elseif (!empty($fetch_students_message)) {
                $primary_message = $fetch_students_message;
                // Determine type based on student message content (simplified check)
                 if (strpos(strtolower($primary_message), 'no student records found') !== false || strpos(strtolower($primary_message), 'no students listed') !== false || strpos(strtolower($primary_message), 'unavailable') !== false || strpos(strtolower($primary_message), 'could not fetch staff data') !== false || strpos(strtolower($primary_message), 'database connection error') !== false || strpos(strtolower($primary_message), 'error fetching') !== false) {
                     $primary_message_type = 'warning'; // Treat fetching/connection/no data errors as warnings
                 } else {
                     $primary_message_type = 'info'; // E.g., "Showing all students", "Showing students in your assigned classes"
                 }
            }

            // Now check and potentially append session operation message
            $final_display_message = $primary_message;
            $final_message_type = $primary_message_type;

            if (!empty($operation_message_session)) {
                // If there's already a primary message, append the session message
                if (!empty($final_display_message)) {
                    // Append on a new line and htmlspecialchars the session message (assuming it's plain text now)
                    $final_display_message .= "<br>" . htmlspecialchars($operation_message_session);
                    // If the session message is more severe than the primary message, upgrade the type
                    $session_msg_lower = strtolower($operation_message_session);
                     if ($final_message_type !== 'danger') { // Only upgrade if not already danger
                         if (strpos($session_msg_lower, 'access denied') !== false || strpos($session_msg_lower, 'error') !== false || strpos($session_msg_lower, 'failed') !== false) {
                             $final_message_type = 'danger';
                         } elseif (strpos($session_msg_lower, 'warning') !== false || strpos($session_msg_lower, 'not found') !== false || strpos(strtolower($operation_message_session), 'please correct') !== false) {
                            if ($final_message_type !== 'warning') $final_message_type = 'warning'; // Only upgrade from info
                         } elseif (strpos($session_msg_lower, 'successfully') !== false || strpos(session_msg_lower, 'welcome') !== false || strpos(session_msg_lower, 'verified') !== false) {
                            // Success message doesn't typically upgrade an error/warning, but can be info
                             if ($final_message_type === 'info') $final_message_type = 'success'; // Only upgrade if info
                         }
                     }

                } else {
                    // If no other messages, use the session message as the primary message
                    $final_display_message = htmlspecialchars($operation_message_session);
                     // Determine type based on session message content
                     $session_msg_lower = strtolower($operation_message_session);
                     if (strpos(session_msg_lower, 'successfully') !== false || strpos(session_msg_lower, 'welcome') !== false || strpos(session_msg_lower, 'verified') !== false) {
                         $final_message_type = 'success';
                     } elseif (strpos(session_msg_lower, 'access denied') !== false || strpos(session_msg_lower, 'error') !== false || strpos(session_msg_lower, 'failed') !== false) {
                          $final_message_type = 'danger';
                     } elseif (strpos(session_msg_lower, 'warning') !== false || strpos(session_msg_lower, 'not found') !== false || strpos(session_msg_lower, 'please correct') !== false) {
                          $final_message_type = 'warning';
                     } else {
                          $final_message_type = 'info';
                     }
                }
            }

            // Display the combined message if not empty
            if (!empty($final_display_message)) {
                $alert_classes = [
                    'info' => 'bg-blue-100 border-blue-500 text-blue-700',
                    'success' => 'bg-green-100 border-green-500 text-green-700',
                    'warning' => 'bg-yellow-100 border-yellow-500 text-yellow-700',
                    'danger' => 'bg-red-100 border-red-500 text-red-800', // Increased text color for danger
                ];
                 $alert_class = $alert_classes[$final_message_type] ?? $alert_classes['info']; // Fallback to info
                echo "<div class='mb-6 border-l-4 p-4 " . $alert_class . "' role='alert'>"; // Added mb-6
                echo "<p>" . $final_display_message . "</p>"; // Message is already htmlspecialchar'd
                echo "</div>";
            }
        ?>



        <hr class="my-6 border-gray-300">

        <!-- Student List Section -->
        <div id="student-list" class="bg-white p-6 md:p-8 rounded-lg shadow-lg"> <!-- Increased padding, larger shadow -->
            <h2 class="text-xl md:text-2xl font-semibold text-gray-800 mb-6 text-center">Student List</h2> <!-- Slightly smaller heading -->

            <?php if (!empty($students)): // Check if the $students array is not empty ?>
                <div class="overflow-x-auto"> <!-- Wrapper for horizontal scrolling -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WhatsApp</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Previous Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Previous School</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Marks (%)</th>
                                <!-- Continue with missing headers -->
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-gray-50"> <!-- Hover effect -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['user_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['phone_number'] ?? 'N/A'); ?></td> <!-- Display N/A if null -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['whatsapp_number'] ?? 'N/A'); ?></td> <!-- Display N/A if null -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['current_class']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['previous_class'] ?? 'N/A'); ?></td> <!-- Display N/A if null -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['previous_school'] ?? 'N/A'); ?></td> <!-- Display N/A if null -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['current_marks'] ?? 'N/A'); ?></td> <!-- Display N/A if null -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['created_at']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium">
                                        <!-- Action Links based on role -->
                                        <?php // Use session role for display logic as it's always available after auth ?>
                                        <?php if (isset($_SESSION['role'])): ?>
                                            <?php if ($_SESSION['role'] === 'teacher'): ?>
                                                 <!-- Link to teacher-specific edit page in the teacher subdir -->
                                                 <!-- Assuming edit_student_teacher.php is in the SAME directory as THIS file -->
                                                 <a href="./edit_student_teacher.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-blue-600 hover:text-blue-900 table-action-link">Edit Marks/Class</a>
                                            <?php elseif ($_SESSION['role'] === 'principal'): ?>
                                                 <!-- Link to principal-specific edit page in the root dir -->
                                                  <!-- Path from THIS file (assumed in teacher/) up to 'School/' is '../' -->
                                                 <a href="../edit_student_principal.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-blue-600 hover:text-blue-900 table-action-link">Edit Full Record</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <!-- No delete link on staff dashboard is a reasonable security choice -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; // End if (!empty($students)) ?>

             <?php if (empty($students) && empty($fetch_students_message)): ?>
                 <!-- Fallback message if $students is empty and no specific message was set -->
                 <!-- This case should be covered by the combined message block, but left for redundancy -->
                 <!-- If the combined message is already set, this won't show -->
                 <?php if(empty($final_display_message)): ?>
                 <div class="mb-6 border-l-4 border-yellow-500 bg-yellow-100 text-yellow-700 p-4" role="alert">
                     <p>No student data is available.</p>
                 </div>
                 <?php endif; ?>
             <?php endif; ?>

        </div>
    </div>

     <!-- Logout Link (optional, often better in the navbar) -->
     <!-- Path from THIS file (assumed in teacher/) up to 'School/' is '../' -->
     <p class="mt-8 text-center"><a href="../logout.php" class="text-red-600 hover:underline font-medium">Logout</a></p>


</body>
</html>