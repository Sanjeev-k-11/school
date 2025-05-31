<?php
// School/admin/view_student.php

// Start the session
session_start();

require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can view student details.</p>";
    // Redirect to admin dashboard or a suitable page
    header("location: admin_dashboard.php");
    exit;
}

// Initialize variables
$student_id = 0;
$student_data = null;
$monthly_fee_records = []; // Array to hold monthly fee data
$student_results = []; // Array to hold raw result data
$student_results_grouped = []; // Array to hold grouped results

$toast_message = ""; // Use toast system
$toast_type = ""; // 'success', 'error', 'warning', 'info'


// Check for operation messages set in other pages (like edit or delete)
if (isset($_SESSION['operation_message'])) {
    // Parse message box classes to determine toast type
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg)); // Use strip_tags to ignore HTML
     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false || strpos($msg_lower, 'welcome') !== false || strpos($msg_lower, 'marked as paid') !== false) {
          $toast_type = 'success';
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'problem') !== false) { // Added problem
          $toast_type = 'error';
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) { // Added no records found
          $toast_type = 'warning';
     } else {
          $toast_type = 'info'; // Default to info
     }
    $toast_message = strip_tags($msg); // Clean HTML tags for toast display
    unset($_SESSION['operation_message']); // Clear the session message
}


// Process GET parameter
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    // Get ID from URL and sanitize it
    $student_id = trim($_GET['id']);

    // Validate ID (must be a positive integer)
    if (!filter_var($student_id, FILTER_VALIDATE_INT) || $student_id <= 0) {
        // Use toast for error and redirect
         $toast_message = "Invalid student ID provided.";
         $toast_type = 'error';
        $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>"; // Set for redirect target
        header("location: admin_dashboard.php");
        exit();
    } else {
        // Prepare a select statement to fetch student details
        // Using correct column names from your schema (with underscores)
        // REMOVED 'admission_fee' as it was not present in the students table schema provided
        $sql_student = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, address, pincode, state, is_active, created_at, photo_filename, roll_number, village, date_of_birth, takes_van, student_fees, van_fee, optional_fees FROM students WHERE user_id = ?";

        // Prepare select statement to fetch monthly fees for this student
        // Fetching breakdown columns as well for display
        $sql_monthly_fees = "SELECT id, student_id, fee_year, fee_month, base_monthly_fee, monthly_van_fee, monthly_exam_fee, monthly_electricity_fee, amount_due, amount_paid, is_paid, payment_date, notes FROM student_monthly_fees WHERE student_id = ? ORDER BY fee_year ASC, fee_month ASC";

        // Prepare select statement to fetch exam results for this student
        // Assuming table name is `student_exam_results`
        // Assuming columns: `academic_year`, `exam_name`, `subject_name`, `marks_obtained`, `max_marks`
        // Order by year (desc or asc, user might prefer latest first), then exam, then subject
        $sql_fetch_results = "SELECT academic_year, exam_name, subject_name, marks_obtained, max_marks
                              FROM student_exam_results
                              WHERE student_id = ?
                              ORDER BY academic_year DESC, exam_name ASC, subject_name ASC";


        if ($link === false) {
            $toast_message = "Database connection error. Could not retrieve student details.";
            $toast_type = 'error';
             error_log("View Student DB connection failed: " . mysqli_connect_error());
             // Set session message for redirect
             $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>";
             header("location: admin_dashboard.php");
             exit();
        } else {
            // --- Fetch Student Data ---
            if ($stmt_student = mysqli_prepare($link, $sql_student)) {
                mysqli_stmt_bind_param($stmt_student, "i", $param_id);
                $param_id = $student_id;

                if (mysqli_stmt_execute($stmt_student)) {
                    $result_student = mysqli_stmt_get_result($stmt_student);

                    if (mysqli_num_rows($result_student) == 1) {
                        $student_data = mysqli_fetch_assoc($result_student);

                        // --- Fetch Monthly Fee Data if student found ---
                        if ($stmt_monthly = mysqli_prepare($link, $sql_monthly_fees)) {
                            mysqli_stmt_bind_param($stmt_monthly, "i", $student_id); // Use the valid student_id

                            if (mysqli_stmt_execute($stmt_monthly)) {
                                $result_monthly = mysqli_stmt_get_result($stmt_monthly);
                                while ($row = mysqli_fetch_assoc($result_monthly)) {
                                    $monthly_fee_records[] = $row;
                                }
                                mysqli_free_result($result_monthly); // Free result set
                            } else {
                                 // Log error but still display student data if fetching monthly fees failed
                                 error_log("View Student monthly fees query failed for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_monthly));
                                  // Set a toast message if fetching fees fails (only if no critical error yet)
                                  if(empty($toast_message) || $toast_type !== 'error') {
                                       $toast_message = "Warning: Could not fetch monthly fee records.";
                                       $toast_type = 'warning';
                                  }
                            }
                            mysqli_stmt_close($stmt_monthly);
                        } else {
                             // Log error but still display student data if preparing monthly fees failed
                             error_log("View Student prepare monthly fees failed: " . mysqli_error($link));
                             // Set a toast message if preparing fees fails (only if no critical error yet)
                              if(empty($toast_message) || $toast_type !== 'error') {
                                   $toast_message = "Error preparing monthly fee fetch statement.";
                                   $toast_type = 'warning'; // Changed to warning as student data might still be fetched
                              }
                        }

                        // --- Fetch Exam Results Data if student found ---
                         if ($stmt_results = mysqli_prepare($link, $sql_fetch_results)) {
                             mysqli_stmt_bind_param($stmt_results, "i", $student_id); // Use the valid student_id

                             if (mysqli_stmt_execute($stmt_results)) {
                                 $result_results = mysqli_stmt_get_result($stmt_results);
                                 while ($row = mysqli_fetch_assoc($result_results)) {
                                     $student_results[] = $row; // Store raw results
                                 }
                                  mysqli_free_result($result_results); // Free result set

                                  // --- Group Results by Year and Exam ---
                                   // Sorting years for consistent display (descending as per SQL)
                                  // krsort($student_results_grouped); // Apply krsort after grouping is complete

                                   foreach ($student_results as $result) {
                                       $year = $result['academic_year'];
                                       $exam = $result['exam_name'];
                                       $marks = (float)($result['marks_obtained'] ?? 0);
                                       $max_marks = (float)($result['max_marks'] ?? 0);


                                       // Initialize year if not exists
                                       if (!isset($student_results_grouped[$year])) {
                                           $student_results_grouped[$year] = [];
                                       }

                                       // Initialize exam within the year if not exists
                                       if (!isset($student_results_grouped[$year][$exam])) {
                                           $student_results_grouped[$year][$exam] = [
                                               'subjects' => [],
                                               'total_marks_obtained' => 0,
                                               'total_max_marks' => 0 // Optional: track total max marks
                                           ];
                                       }

                                       // Add subject details
                                       $student_results_grouped[$year][$exam]['subjects'][] = [
                                           'subject_name' => $result['subject_name'],
                                           'marks_obtained' => $marks,
                                           'max_marks' => $max_marks
                                       ];

                                       // Add to total marks for this exam
                                       $student_results_grouped[$year][$exam]['total_marks_obtained'] += $marks;
                                       $student_results_grouped[$year][$exam]['total_max_marks'] += $max_marks;
                                   }

                                    // Sort years descending
                                   krsort($student_results_grouped);
                                    // Sort exams within each year by name (alphabetical)
                                   foreach ($student_results_grouped as $year => $exams) {
                                       ksort($student_results_grouped[$year]);
                                   }
                                    // Sort subjects within each exam by name (alphabetical)
                                   foreach ($student_results_grouped as $year => $exams) {
                                        foreach ($exams as $exam_name => $exam_data) {
                                            usort($student_results_grouped[$year][$exam_name]['subjects'], function($a, $b) {
                                                return strcmp($a['subject_name'], $b['subject_name']);
                                            });
                                        }
                                   }


                             } else {
                                 // Log error but still display student data if fetching results failed
                                 error_log("View Student results query failed for student ID " . $student_id . ": " . mysqli_stmt_error($stmt_results));
                                  // Set a toast message if fetching results fails (only if no critical error yet)
                                   if(empty($toast_message) || $toast_type !== 'error') {
                                       $toast_message = "Warning: Could not fetch exam results.";
                                       $toast_type = 'warning';
                                   }
                             }
                             mysqli_stmt_close($stmt_results);
                         } else {
                              // Log error but still display student data if preparing results failed
                              error_log("View Student prepare results failed: " . mysqli_error($link));
                             // Set a toast message if preparing results fails (only if no critical error yet)
                              if(empty($toast_message) || $toast_type !== 'error') {
                                   $toast_message = "Error preparing exam results fetch statement.";
                                   $toast_type = 'warning'; // Changed to warning
                              }
                         }


                    } else {
                        // Student not found with the given ID
                         $toast_message = "No student record found with ID: " . htmlspecialchars($student_id);
                         $toast_type = 'warning';
                         $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>"; // Set for redirect target
                         header("location: admin_dashboard.php"); // Redirect back to dashboard
                         exit();
                    }
                } else {
                    // Error executing student query
                    $toast_message = "Error executing student query. Please try again later.";
                    $toast_type = 'error';
                     error_log("View Student student query execution failed for ID " . $student_id . ": " . mysqli_stmt_error($stmt_student));
                     $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>"; // Set for redirect target
                     header("location: admin_dashboard.php"); // Redirect back to dashboard
                     exit();
                }
                mysqli_stmt_close($stmt_student);
            } else {
                 // Error preparing student statement
                 // THIS IS THE ERROR LOCATION based on the user's output
                 $toast_message = "Error preparing student query. Please try again later.";
                 $toast_type = 'error';
                 // The actual MySQL error is logged here. Check your server's PHP error log file for the details.
                 error_log("View Student prepare student statement failed: " . mysqli_error($link));
                  $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>"; // Set for redirect target
                  header("location: admin_dashboard.php"); // Redirect back to dashboard
                  exit();
            }
        }
    }
} else {
    // If no ID is provided in the URL
     $toast_message = "No student ID specified.";
     $toast_type = 'warning';
    $_SESSION['operation_message'] = "<p class='text-red-600'>" . htmlspecialchars($toast_message) . "</p>"; // Set for redirect target
    header("location: admin_dashboard.php");
    exit();
}

// Close connection (if it was successfully opened)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

// Default avatar path relative to the root directory (adjust if needed)
$default_student_avatar_path = '../assets/images/default_student_avatar.png';

// Define htmlspecialchars and nl2brJs if they are not included from admin_header.php
// Assuming they are defined in admin_header.php, no need to redefine here.
// If you remove admin_header.php, you'd need:
/*
if (!function_exists('htmlspecialchars')) {
    function htmlspecialchars($string, int $flags = ENT_COMPAT | ENT_HTML401, string $encoding = 'UTF-8', bool $double_encode = true): string {
        return htmlspecialchars($string, $flags, $encoding, $double_encode);
    }
}
if (!function_exists('nl2br')) { // nl2brJs is not needed for PHP rendering
    function nl2br(string $string, bool $use_xhtml = true): string {
        return nl2br($string, $use_xhtml);
    }
}
*/

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $student_data ? htmlspecialchars($student_data['full_name']) . " Results & Details" : "View Student Details"; ?> - Admin Dashboard</title> <!-- Updated title -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         /* Include dashboard styles for consistency */
         /* Make sure admin_styles.css exists or copy necessary styles */
         /* This example assumes admin_styles.css is not included and adds basic needed styles */

          body {
             /* Add padding-top to clear the fixed header. Adjust value if needed. */
             padding-top: 4.5rem; /* Adjust based on your fixed header height */
             background-color: #f3f4f6; /* Default subtle gray background */
             min-height: 100vh;
         }

         /* Basic fixed header styles */
         .fixed-header {
             position: fixed;
             top: 0;
             left: 0; /* Will be adjusted by JS for sidebar */
             right: 0;
             height: 4.5rem; /* Height of the header */
             background-color: #ffffff; /* White background */
             box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* Tailwind shadow-md */
             padding: 1rem;
             display: flex;
             align-items: center;
             z-index: 10; /* Stay on top */
             transition: left 0.3s ease; /* Smooth transition for padding/position */
         }

         /* Adjust header position when sidebar is open */
          /* Assuming sidebar width is ~16rem (64 Tailwind units) */
         /* This class will be added to the body by JS */
          body.sidebar-open .fixed-header {
             left: 16rem;
          }


         /* Styles for the details layout */
         .details-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Responsive grid */
             gap: 1rem; /* Gap between grid items */
             margin-top: 1rem; /* Adjusted margin */
         }

         .detail-item {
             background-color: #f9fafb; /* bg-gray-50 */
             padding: 0.75rem; /* py-3 px-3 */
             border-radius: 0.375rem; /* rounded-md */
             border: 1px solid #e5e7eb; /* border-gray-200 */
             word-break: break-word; /* Ensure text wraps */
             overflow-wrap: break-word; /* Ensure text wraps */
         }
         .detail-item strong {
             display: block;
             font-size: 0.875rem; /* text-sm */
             font-weight: 600; /* semibold */
             color: #4b5563; /* text-gray-600 */
             margin-bottom: 0.25rem; /* space-y-0.5 */
         }
         .detail-item p {
             font-size: 1rem; /* text-base */
             color: #1f2937; /* text-gray-900 */
             margin: 0; /* Remove default margin */
         }
          /* Special styling for photo */
          .photo-container {
              display: flex;
              flex-direction: column;
              align-items: center;
              text-align: center;
              padding-bottom: 1rem; /* Added some bottom padding */
              margin-bottom: 1rem; /* Added margin bottom */
              border-bottom: 1px solid #e5e7eb; /* Optional: Add separator */
          }
          .photo-container img {
               width: 120px; /* Larger photo */
               height: 120px;
               border-radius: 50%;
               object-fit: cover;
               margin-bottom: 0.75rem; /* space below photo */
               border: 3px solid #6366f1; /* indigo-500 */
          }
           .photo-container strong {
               font-size: 1rem;
               color: #1f2937;
           }

           /* Style for action buttons at the bottom */
           .action-buttons {
               margin-top: 2rem;
               padding-top: 1.5rem; /* Add padding top */
               border-top: 1px solid #e5e7eb; /* Optional: Add separator */
               display: flex;
               justify-content: center;
               flex-wrap: wrap; /* Allow buttons to wrap on small screens */
               gap: 1rem; /* space-x-4 */
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
                 justify-content: center; /* Center text in button */
                 text-align: center;
            }
             .action-button.primary {
                 background-color: #4f46e5; /* indigo-600 */
                 color: white;
             }
             .action-button.primary:hover {
                  background-color: #4338ca; /* indigo-700 */
             }
             .action-button.secondary {
                 background-color: #e5e7eb; /* gray-200 */
                 color: #374151; /* gray-700 */
             }
              .action-button.secondary:hover {
                  background-color: #d1d5db; /* gray-300 */
              }
             .action-button.danger {
                 background-color: #ef4444; /* red-500 */
                 color: white;
             }
              .action-button.danger:hover {
                  background-color: #dc2626; /* red-600 */
             }


         /* Message box styles - KEPT as fallback/alternative, but using Toast JS now */
         .message-box {
             padding: 1rem;
             border-radius: 0.5rem;
             border: 1px solid transparent;
             margin-bottom: 1.5rem;
         }
         .message-box p {
             margin: 0.5rem 0;
         }
         .message-box p:first-child { margin-top: 0; }
         .message-box p:last-child { margin-bottom: 0; }
         .message-box.success { background-color: #d1fae5; border-color: #34d399; color: #065f46; }
         .message-box.error { background-color: #fee2e2; border-color: #f87171; color: #991b1b; }
         .message-box.warning { background-color: #fffbe Surveyed; border-color: #fb923c; color: #9a3412; }
         .message-box.info { background-color: #e0f2f7; border-color: #22d3ee; color: #0e7490; }

        /* --- Added Styles for Background Buttons (if you keep them) --- */
         .gradient-background-blue-cyan { background: linear-gradient(to right, #4facfe, #00f2fe); }
          .gradient-background-purple-pink { background: linear-gradient(to right, #a18cd1, #fbc2eb); }
           .gradient-background-green-teal { background: linear-gradient(to right, #a8edea, #fed6e3); }
           .solid-bg-gray { background-color: #f3f4f6; }
           .solid-bg-indigo { background-color: #4f46e5; }

         /* --- Styles for the Monthly Fee Table --- */
         .monthly-fee-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 1rem;
             font-size: 0.875rem; /* text-sm */
         }
         .monthly-fee-table th,
         .monthly-fee-table td {
             padding: 0.75rem 0.5rem; /* py-3 px-2 */
             border: 1px solid #e5e7eb; /* border-gray-200 */
             text-align: left;
             vertical-align: top; /* Align content to top in cells */
         }
         .monthly-fee-table th {
             background-color: #e5e7eb; /* bg-gray-200 - slightly darker for header */
             font-weight: 600; /* semibold */
             color: #374151; /* gray-700 */
         }
         .monthly-fee-table tbody tr:nth-child(even) {
             background-color: #f9fafb; /* bg-gray-50 */
         }
          .monthly-fee-table td {
              color: #1f2937; /* gray-900 */
          }
          .status-paid {
              color: #065f46; /* green-800 */
              font-weight: 600; /* semibold */
          }
          .status-due {
               color: #b91c1c; /* red-800 */
               font-weight: 600; /* semibold */
          }
         .monthly-fee-table .action-link {
             color: #4f46e5; /* indigo-600 */
             text-decoration: none;
             font-weight: 500;
             display: inline-block; /* Allows margin/padding */
             margin-right: 0.5rem; /* Add space between links */
         }
         .monthly-fee-table .action-link:last-child {
              margin-right: 0;
         }
         .monthly-fee-table .action-link:hover {
             text-decoration: underline;
             color: #4338ca; /* indigo-700 */
         }


         /* --- Styles for Exam Results Table --- */
         .exam-results-section {
             margin-top: 2rem;
         }
          .exam-year {
              margin-bottom: 2rem; /* Space after each year */
          }
          .exam-year h4 {
              font-size: 1.125rem; /* text-lg */
              font-weight: 600; /* semibold */
              color: #4b5563; /* gray-600 */
              margin-bottom: 1rem;
              border-bottom: 1px solid #d1d5db; /* gray-300 */
              padding-bottom: 0.5rem;
          }
          .exam-details {
               margin-bottom: 1.5rem; /* Space after each exam */
          }
           .exam-details h5 {
               font-size: 1rem; /* text-base */
               font-weight: 600; /* semibold */
               color: #1f2937; /* gray-900 */
               margin-bottom: 0.5rem;
           }
            .exam-details p {
                font-size: 0.875rem; /* text-sm */
                color: #4b5563; /* gray-600 */
                margin-bottom: 0.75rem; /* space below name/roll */
            }

         .exam-subjects-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 0.5rem;
             font-size: 0.875rem; /* text-sm */
             box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* subtle shadow */
         }
          .exam-subjects-table th,
          .exam-subjects-table td {
               padding: 0.6rem 0.8rem; /* Slightly less padding than fee table */
              border: 1px solid #e5e7eb; /* border-gray-200 */
              text-align: left;
          }
           .exam-subjects-table th {
                background-color: #f3f4f6; /* bg-gray-100 */
                font-weight: 600; /* semibold */
                color: #374151; /* gray-700 */
           }
            .exam-subjects-table tbody tr:nth-child(even) {
                background-color: #fafafa; /* Lightest gray */
            }
            .exam-subjects-table td {
                color: #1f2937; /* gray-900 */
            }
             .exam-subjects-table tfoot td {
                  background-color: #e5e7eb; /* bg-gray-200 */
                  font-weight: 700; /* bold */
                  color: #1f2937; /* gray-900 */
             }


         /* --- Toast Notification Styles --- */
         /* Copy the toast styles from view_student.php or a shared CSS file */
          .toast-container {
              position: fixed;
              top: 1rem; right: 1rem;
              z-index: 100;
              display: flex;
              flex-direction: column;
              gap: 0.5rem;
              pointer-events: none;
          }
          .toast {
              background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
              opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
              pointer-events: auto; min-width: 200px; max-width: 350px; display: flex; align-items: center;
          }
          .toast.show { opacity: 1; transform: translateX(0); }
          .toast-success { border-left: 5px solid #10b981; color: #065f46; }
          .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
          .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
          .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }
          .toast .close-button { margin-left: auto; background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; padding: 0 0.25rem; line-height: 1; }


     </style>
      <script>
         // Include sidebar toggle JS (ensure admin_sidebar.php has the button with id="admin-sidebar-toggle-open")
         document.addEventListener('DOMContentLoaded', (event) => {
             // Find the toggle button
             const sidebarToggleOpen = document.getElementById('admin-sidebar-toggle-open');
             const body = document.body;
             const fixedHeader = document.querySelector('.fixed-header'); // Get the fixed header
             const mainContent = document.querySelector('.main-content-wrapper'); // Select the main content wrapper


             // Function to update padding based on sidebar state
             // Using CSS transitions now, so this function might be simplified
             // if sidebar-open class is added to body
             function updateLayoutForSidebar() {
                  // Get the sidebar width (assuming Tailwind md:w-64 == 16rem)
                  // This is a simplification; a more robust solution would measure the actual sidebar width
                  const sidebarWidth = body.classList.contains('sidebar-open') ? 16 * 16 : 0; // 16rem * 16px/rem

                  if(mainContent) {
                      // The CSS style block handles the body padding-left transition
                      // based on the `sidebar-open` class.
                  }
                  // The fixed header position is handled by the CSS rule `body.sidebar-open .fixed-header`
             }


             if (sidebarToggleOpen && body && fixedHeader && mainContent) {
                 // Initial state check (optional, if sidebar state persists)
                 // const isSidebarOpen = localStorage.getItem('sidebar-open') === 'true';
                 // if (isSidebarOpen) {
                 //     body.classList.add('sidebar-open');
                 // }
                 // updateLayoutForSidebar(); // Apply initial layout

                 sidebarToggleOpen.addEventListener('click', function() {
                     // Toggle the 'sidebar-open' class on the body
                     body.classList.toggle('sidebar-open');
                     // Save state (optional)
                     // localStorage.setItem('sidebar-open', body.classList.contains('sidebar-open'));
                     // updateLayoutForSidebar(); // CSS transition handles this now
                 });
             } else {
                 console.error("Sidebar toggle button or body or fixed header or main content not found!");
             }


             // --- Toast Notification JS ---
              const toastContainer = document.getElementById('toastContainer');
              if (!toastContainer) {
                  console.error('Toast container #toastContainer not found.');
              }

              function showToast(message, type = 'info', duration = 5000) {
                  if (!message || !toastContainer) return;

                  const toast = document.createElement('div');
                  toast.classList.add('toast', `toast-${type}`);
                  toast.textContent = message; // Use textContent for safety

                  const closeButton = document.createElement('button');
                  closeButton.classList.add('close-button');
                  closeButton.textContent = '×'; // Multiplication sign
                  closeButton.onclick = () => toast.remove();
                  toast.appendChild(closeButton);

                  toastContainer.appendChild(toast);

                  // Use requestAnimationFrame for safer DOM manipulation and smooth transition start
                  requestAnimationFrame(() => {
                      toast.classList.add('show');
                  });


                  // Auto-hide after duration
                  if (duration > 0) {
                      setTimeout(() => {
                          toast.classList.remove('show');
                          // Remove the toast element after the transition ends
                          toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                      }, duration);
                  }
              }

              // Trigger toast display on DOM load if a message exists
              // Pass the PHP message and type here
              const phpMessage = <?php echo json_encode($toast_message ?? ''); ?>; // Handle case where it's not set
              const messageType = <?php echo json_encode($toast_type ?? 'info'); ?>; // Default type to info

              if (phpMessage) {
                  showToast(phpMessage, messageType);
              }
              // --- End Toast Notification JS ---


          }); // End DOMContentLoaded

          // Function nl2brJs for modal, needs to be available globally if modal is outside DOMContentLoaded
          // Let's assume admin_header.php provides this. If not, define it here:
          // function nl2brJs(str) { return str.replace(/\n/g, '<br>'); } // Basic example
      </script>
</head>
<body class="min-h-screen">
    <?php require_once "./admin_sidebar.php"; // Include the sidebar ?>

     <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here -->
    </div>

     <!-- Fixed Header for Toggle Button and Page Title -->
     <!-- Adjusted layout slightly for header -->
    <div class="fixed-header bg-white shadow-md p-4 flex items-center top-0 left: 0; right: 0; z-10 transition-left duration-300 ease-in-out">
        <?php
           // Assuming admin_sidebar.php *only* contains the sidebar HTML
           // and the toggle button with ID 'admin-sidebar-toggle-open' is added elsewhere,
           // typically right after the opening body tag or within the main fixed header area.
           // If the button is NOT in admin_sidebar.php, and you want the sidebar toggle,
           // add it here or ensure it's present in your header structure.
           // Example (if sidebar doesn't contain the button):
           /*
           echo '<button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:hidden" aria-label="Toggle sidebar">';
           echo '<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
           echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />';
           echo '</svg>';
           echo '</button>';
            */
           // If admin_sidebar.php *does* contain the button and places it absolutely/fixed:
           // No HTML needed here, just ensure the JS listens for it.

           // If admin_sidebar.php contains the button and you need to place it here:
           // You might need to adjust admin_sidebar.php not to render the button if you want it here.
           // For now, let's assume the sidebar script handles finding the button wherever it is placed.
        ?>


         <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow"><?php echo $student_data ? htmlspecialchars($student_data['full_name']) . " Details" : "View Student Details"; ?></h1> <!-- Dynamic title -->

          <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
            <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
    </div>


    <!-- Main content wrapper -->
    <!-- Add padding-left to main content based on sidebar width. Initialize with padding for md+ screens -->
     <!-- The CSS style block handles the sidebar-open padding -->
     <div class="main-content-wrapper w-full max-w-screen-xl mx-auto px-4 py-8 md:px-8">

          <?php if ($student_data): // Only show details if data was fetched ?>
             <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full">
                 <!-- Removed the main title here as it's in the fixed header -->

                  <div class="photo-container">
                      <?php
                          $cloudinary_url = $student_data['photo_filename'] ?? '';
                          // If photo_filename exists and looks like a URL, use it, otherwise use default
                          $is_valid_url = !empty($cloudinary_url) && filter_var($cloudinary_url, FILTER_VALIDATE_URL);
                          // Assuming default avatar path is relative to the document root (e.g., /assets/...)
                          // If it's relative to this file (like ../assets/...), adjust the path accordingly
                          $default_student_avatar_path_display = '../assets/images/default_student_avatar.png'; // Path relative to this file (admin/)
                          $display_photo_url = $is_valid_url ? htmlspecialchars($cloudinary_url) : htmlspecialchars($default_student_avatar_path_display);
                      ?>
                      <img src="<?php echo $display_photo_url; ?>" alt="<?php echo htmlspecialchars($student_data['full_name'] ?? 'Student'); ?> Photo">
                      <!-- <strong>Student Photo</strong> Removed this label, photo is self-explanatory -->
                  </div>

                 <!-- Section: Personal Information -->
                 <div class="mt-6"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Personal Information</h3></div>
                 <div class="details-grid">
                      <div class="detail-item">
                         <strong>User ID:</strong>
                         <p><?php echo htmlspecialchars($student_data['user_id']); ?></p>
                     </div>
                     <div class="detail-item">
                         <strong>Virtual ID:</strong>
                         <p><?php echo htmlspecialchars($student_data['virtual_id'] ?? 'N/A'); ?></p>
                     </div>
                     <div class="detail-item">
                         <strong>Full Name:</strong>
                         <p><?php echo htmlspecialchars($student_data['full_name']); ?></p>
                     </div>
                      <div class="detail-item">
                          <strong>Father's Name:</strong>
                          <p><?php echo htmlspecialchars($student_data['father_name'] ?? 'N/A'); ?></p>
                      </div>
                     <div class="detail-item">
                          <strong>Mother's Name:</strong>
                          <p><?php echo htmlspecialchars($student_data['mother_name'] ?? 'N/A'); ?></p>
                      </div>
                     <div class="detail-item">
                         <strong>Roll Number:</strong>
                         <p><?php echo htmlspecialchars($student_data['roll_number'] ?? 'N/A'); ?></p>
                     </div>
                      <div class="detail-item">
                          <strong>Date of Birth:</strong>
                           <p><?php
                            // Format date if not null and not the zero date
                            if (!empty($student_data['date_of_birth']) && $student_data['date_of_birth'] !== '0000-00-00') {
                                echo htmlspecialchars(date('F j, Y', strtotime($student_data['date_of_birth']))); // e.g., May 30, 2025
                            } else {
                                echo 'N/A';
                            }
                           ?></p>
                      </div>
                     <div class="detail-item">
                         <strong>Village:</strong>
                         <p><?php echo htmlspecialchars($student_data['village'] ?? 'N/A'); ?></p>
                     </div>
                 </div>


                 <!-- Section: Contact Information Details -->
                 <div class="mt-6"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Contact Information</h3></div>
                 <div class="details-grid">
                      <div class="detail-item">
                          <strong>Phone Number:</strong>
                          <p><?php echo htmlspecialchars($student_data['phone_number'] ?? 'N/A'); ?></p>
                      </div>
                       <div class="detail-item">
                          <strong>WhatsApp Number:</strong>
                          <p><?php echo htmlspecialchars($student_data['whatsapp_number'] ?? 'N/A'); ?></p>
                      </div>
                       <div class="detail-item md:col-span-2" style="grid-column: 1 / -1;">
                           <strong>Address:</strong>
                           <p><?php echo nl2br(htmlspecialchars($student_data['address'] ?? 'N/A')); ?></p>
                       </div>
                       <div class="detail-item">
                          <strong>Pincode:</strong>
                          <p><?php echo htmlspecialchars($student_data['pincode'] ?? 'N/A'); ?></p>
                      </div>
                       <div class="detail-item">
                          <strong>State:</strong>
                          <p><?php echo htmlspecialchars($student_data['state'] ?? 'N/A'); ?></p>
                      </div>
                 </div>


                 <!-- Section: Academic Information Details -->
                 <div class="mt-6"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Academic Information</h3></div>
                 <div class="details-grid">
                      <div class="detail-item">
                          <strong>Current Class:</strong>
                          <p><?php echo htmlspecialchars($student_data['current_class'] ?? 'N/A'); ?></p>
                      </div>
                      <div class="detail-item">
                         <strong>Previous Class:</strong>
                         <p><?php echo htmlspecialchars($student_data['previous_class'] ?? 'N/A'); ?></p>
                     </div>
                       <div class="detail-item">
                          <strong>Previous School:</strong>
                          <p><?php echo htmlspecialchars($student_data['previous_school'] ?? 'N/A'); ?></p>
                      </div>
                       <div class="detail-item">
                          <strong>Prev Marks (%):</strong>
                           <p><?php
                             // Format marks as float with 2 decimals if available
                             $prev_marks = $student_data['previous_marks_percentage'] ?? null;
                              echo ($prev_marks !== null && $prev_marks !== '') ? htmlspecialchars(number_format((float)$prev_marks, 2) . '%') : 'N/A'; // Cast to float
                           ?></p>
                      </div>
                       <!-- Removed "Current Marks (%)" as results are shown in the new section -->
                        <?php /*
                        <div class="detail-item">
                           <strong>Current Marks (%):</strong>
                           <p><?php
                              // Format marks as float with 2 decimals if available
                              $curr_marks = $student_data['current_marks'] ?? null;
                               echo ($curr_marks !== null && $curr_marks !== '') ? htmlspecialchars(number_format((float)$curr_marks, 2) . '%') : 'N/A'; // Cast to float
                           ?></p>
                       </div>
                       */ ?>
                 </div>


                 <!-- Section: Default Fee Information -->
                  <div class="mt-6"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Fee Structure (Defaults)</h3></div>
                  <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 text-sm text-yellow-800" role="alert">
                       <p class="font-bold">Note:</p>
                       <p>These are the *default* fee amounts set for the student. Actual monthly dues and payment status are tracked in the "Monthly Fee Records" section below.</p>
                  </div>
                  <div class="details-grid">
                       <div class="detail-item">
                           <strong>Default Monthly Fee:</strong>
                            <p><?php
                              $monthly_fee = $student_data['student_fees'] ?? null; // Use 'student_fees' from schema
                               echo ($monthly_fee !== null && $monthly_fee !== '') ? '₹' . htmlspecialchars(number_format((float)$monthly_fee, 2)) : 'N/A';
                            ?></p>
                       </div>
                        <div class="detail-item">
                           <strong>Takes Van:</strong>
                           <p><?php echo (($student_data['takes_van'] ?? 0) == 1) ? 'Yes' : 'No'; ?></p>
                       </div>
                        <div class="detail-item">
                           <strong>Default Monthly Van Fee:</strong>
                            <p><?php
                              $van_fee = $student_data['van_fee'] ?? null; // Use 'van_fee' from schema
                               echo ($van_fee !== null && $van_fee !== '') ? '₹' . htmlspecialchars(number_format((float)$van_fee, 2)) : 'N/A';
                            ?></p>
                       </div>
                       <?php
                       // Check if 'admission_fee' exists in the fetched data array keys
                       // This is a defensive check in case the schema was inconsistent or changed
                       if (array_key_exists('admission_fee', $student_data ?? [])):
                       ?>
                       <div class="detail-item">
                           <strong>Admission Fee (One-Time):</strong>
                            <p><?php
                               $admission_fee = $student_data['admission_fee'] ?? null;
                                echo ($admission_fee !== null && $admission_fee !== '') ? '₹' . htmlspecialchars(number_format((float)$admission_fee, 2)) : 'N/A';
                            ?></p>
                       </div>
                       <?php endif; // End check for admission_fee key ?>

                        <div class="detail-item">
                           <strong>Other One-Time Fees:</strong>
                            <p><?php
                              $other_fees = $student_data['optional_fees'] ?? null; // Use 'optional_fees' from schema
                               echo ($other_fees !== null && $other_fees !== '') ? '₹' . htmlspecialchars(number_format((float)$other_fees, 2)) : 'N/A';
                            ?></p>
                       </div>
                        <!-- Display Estimated Total Monthly Fee -->
                         <?php
                            $base_monthly = (float)($student_data['student_fees'] ?? 0);
                            $van_monthly = (($student_data['takes_van'] ?? 0) == 1) ? (float)($student_data['van_fee'] ?? 0) : 0;
                            $estimated_monthly_total = $base_monthly + $van_monthly;
                         ?>
                         <div class="detail-item">
                             <strong>Estimated Default Monthly Total:</strong>
                             <p><?php echo '₹' . htmlspecialchars(number_format($estimated_monthly_total, 2)); ?></p>
                         </div>
                          <!-- Display Estimated Annual Fee (Based on monthly defaults) -->
                         <div class="detail-item">
                             <strong>Estimated Annual Fee (Monthly Only):</strong>
                             <p><?php echo '₹' . htmlspecialchars(number_format($estimated_monthly_total * 12, 2)); ?> <span class="text-gray-500 text-xs italic">(Estimated base annual)</span></p>
                         </div>
                  </div>


                  <!-- Section: Monthly Fee Status & Payments Table -->
                  <div class="mt-8"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Monthly Fee Records</h3></div>
                   <?php if (!empty($monthly_fee_records)): ?>
                        <div class="overflow-x-auto"> <!-- Make table scroll horizontally on small screens -->
                            <table class="monthly-fee-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Year</th>
                                        <th>Base Fee</th>
                                        <th>Van Fee</th>
                                        <th>Exam Fee</th>
                                        <th>Electricity Fee</th>
                                        <th>Total Due</th>
                                        <th>Amount Paid</th>
                                        <th>Amount Due</th>
                                        <th>Status</th>
                                        <th>Payment Date</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $month_names = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];

                                    foreach ($monthly_fee_records as $record):
                                        // Calculate amount due remaining
                                        $due_amount_calc = (float)($record['amount_due'] ?? 0) - (float)($record['amount_paid'] ?? 0);
                                        // Determine status: Paid if amount_paid >= amount_due, otherwise Due
                                        $status_text = ($due_amount_calc <= 0 && (float)($record['amount_due'] ?? 0) > 0) ? 'Paid' : 'Due'; // Marked as paid or balance settled, and there was something due
                                         // Handle the case where amount_due is 0 (e.g., fee waiver)
                                         if ((float)($record['amount_due'] ?? 0) == 0) {
                                             $status_text = 'N/A'; // Or "No Fee Due"
                                         } else if (($record['is_paid'] ?? 0) == 1) {
                                             // Explicitly check is_paid flag if you use it separately from balance
                                             $status_text = 'Paid (Flag)'; // Indicate it was marked paid via flag
                                         }


                                        $status_class = ($status_text === 'Paid' || $status_text === 'Paid (Flag)') ? 'status-paid' : 'status-due';
                                        if ($status_text === 'N/A') $status_class = ''; // No class for N/A


                                        $payment_date_display = (!empty($record['payment_date']) && $record['payment_date'] !== '0000-00-00') ? date('Y-m-d', strtotime($record['payment_date'])) : 'N/A'; // YYYY-MM-DD format


                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($month_names[$record['fee_month']] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($record['fee_year']); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['base_monthly_fee'] ?? 0), 2)); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['monthly_van_fee'] ?? 0), 2)); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['monthly_exam_fee'] ?? 0), 2)); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['monthly_electricity_fee'] ?? 0), 2)); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['amount_due'] ?? 0), 2)); ?></td>
                                        <td><?php echo '₹' . htmlspecialchars(number_format((float)($record['amount_paid'] ?? 0), 2)); ?></td>
                                         <!-- Display the calculated actual amount still due -->
                                        <td><?php echo '₹' . htmlspecialchars(number_format(max(0, $due_amount_calc), 2)); ?></td> <!-- Show 0 if amount paid >= amount due -->
                                        <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td><?php echo $payment_date_display; ?></td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                        <td>
                                            <!-- Actions -->
                                            <!-- Example: Link to a page to record a payment or edit the record -->
                                             <?php if (($record['is_paid'] ?? 0) == 0 && $due_amount_calc > 0): // Only show Mark Paid if not already marked paid AND amount is still due ?>
                                                  <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): // Only Admin/Principal can mark paid ?>
                                                      <a href="mark_monthly_fee_paid.php?monthly_fee_id=<?php echo htmlspecialchars($record['id']); ?>&student_id=<?php echo htmlspecialchars($student_data['user_id']); ?>"
                                                         class="action-link"
                                                         onclick="return confirm('Mark fee for <?php echo htmlspecialchars($month_names[$record['fee_month']] ?? '') . ' ' . htmlspecialchars($record['fee_year']); ?> as PAID?');">
                                                         Mark Paid
                                                      </a><br>
                                                  <?php endif; ?>
                                             <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): echo '<br>'; endif; ?> <!-- Add break if Mark Paid is shown -->
                                             <?php endif; ?>
                                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): // Only Admin/Principal can edit monthly fee records ?>
                                                 <a href="edit_monthly_fee.php?id=<?php echo htmlspecialchars($record['id']); ?>&student_id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="action-link">Edit Record</a>
                                            <?php endif; ?>
                                            <!-- Add a delete link/form later if needed, restricted to Admin -->
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                         </div> <!-- End overflow-x-auto -->
                       <?php else: ?>
                           <p class="text-gray-600 italic text-sm mt-2">No monthly fee records found for this student. Monthly fee records are typically generated for each academic month.</p>
                            <p class="text-gray-600 italic text-sm mt-2 font-semibold">
                                Please ensure the monthly fee generation process has been run or create records manually from the <a href="./edit_student.php?id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="text-indigo-600 hover:underline">Edit Student page</a>.
                            </p>
                       <?php endif; ?>

                 <!-- Section: Exam Results -->
                 <div class="exam-results-section">
                     <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Exam Results</h3>

                     <?php if (!empty($student_results_grouped)): ?>
                          <?php foreach ($student_results_grouped as $year => $exams_in_year): ?>
                              <div class="exam-year">
                                  <h4>Academic Year: <?php echo htmlspecialchars($year); ?></h4>

                                  <?php foreach ($exams_in_year as $exam_name => $exam_data): ?>
                                      <div class="exam-details">
                                          <h5><?php echo htmlspecialchars($exam_name); ?></h5>
                                          <p>Student: <span class="font-semibold"><?php echo htmlspecialchars($student_data['full_name'] ?? 'N/A'); ?></span> | Roll Number: <span class="font-semibold"><?php echo htmlspecialchars($student_data['roll_number'] ?? 'N/A'); ?></span></p>

                                          <?php if (!empty($exam_data['subjects'])): ?>
                                              <div class="overflow-x-auto">
                                                  <table class="exam-subjects-table">
                                                      <thead>
                                                          <tr>
                                                              <th>Subject</th>
                                                              <th>Marks Obtained</th>
                                                              <?php if ($exam_data['total_max_marks'] > 0): // Only show Max Marks column if there are max marks ?>
                                                                  <th>Max Marks</th>
                                                              <?php endif; ?>
                                                          </tr>
                                                      </thead>
                                                      <tbody>
                                                          <?php foreach ($exam_data['subjects'] as $subject): ?>
                                                          <tr>
                                                              <td><?php echo htmlspecialchars($subject['subject_name'] ?? 'N/A'); ?></td>
                                                              <td><?php echo htmlspecialchars($subject['marks_obtained'] ?? 'N/A'); ?></td>
                                                               <?php if ($exam_data['total_max_marks'] > 0): ?>
                                                                   <td><?php echo htmlspecialchars($subject['max_marks'] ?? 'N/A'); ?></td>
                                                               <?php endif; ?>
                                                          </tr>
                                                          <?php endforeach; ?>
                                                      </tbody>
                                                      <tfoot>
                                                          <tr>
                                                              <td colspan="<?php echo ($exam_data['total_max_marks'] > 0) ? 2 : 1; ?>" class="text-right font-bold">Total Marks:</td>
                                                              <td><?php echo htmlspecialchars(number_format($exam_data['total_marks_obtained'], 2)); ?></td> <!-- Format total marks -->
                                                               <?php if ($exam_data['total_max_marks'] > 0): ?>
                                                                   <td><?php echo htmlspecialchars(number_format($exam_data['total_max_marks'], 2)); ?></td> <!-- Format total max marks -->
                                                               <?php endif; ?>
                                                          </tr>
                                                      </tfoot>
                                                  </table>
                                              </div>
                                          <?php else: ?>
                                              <p class="text-gray-600 italic text-sm">No subjects recorded for this exam.</p>
                                          <?php endif; ?>

                                      </div> <!-- End exam-details -->
                                  <?php endforeach; ?>

                              </div> <!-- End exam-year -->
                          <?php endforeach; ?>
                      <?php else: ?>
                          <p class="text-gray-600 italic text-sm mt-2">No exam results found for this student.</p>
                      <?php endif; ?>

                 </div> <!-- End Exam Results Section -->


                  <!-- Section: Account Status -->
                  <div class="mt-6"><h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Account Status</h3></div>
                  <div class="details-grid">
                       <div class="detail-item">
                          <strong>Account Status:</strong>
                          <p><?php echo (($student_data['is_active'] ?? 0) == 1) ? '<span class="text-green-600 font-semibold">Active</span>' : '<span class="text-red-600 font-semibold">Inactive</span>'; ?></p>
                      </div>
                       <div class="detail-item">
                          <strong>Record Created At:</strong>
                          <p><?php
                            // Format date if not null and not the zero date
                            if (!empty($student_data['created_at']) && $student_data['created_at'] !== '0000-00-00 00:00:00') {
                                echo htmlspecialchars(date('F j, Y, g:i a', strtotime($student_data['created_at']))); // e.g., May 30, 2025, 10:43 am
                            } else {
                                echo 'N/A';
                            }
                           ?></p>
                      </div>
                  </div>


                 <div class="action-buttons">
                     <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'principal'): ?>
                         <!-- NEW LINK: Add Exam Result -->
                         <a href="./add_student_result.php?student_id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="action-button secondary">Add Exam Result</a>
                         <a href="./edit_student.php?id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="action-button primary">Edit Student Details</a>
                     <?php endif; ?>
                      <?php if ($_SESSION['role'] === 'admin'): // Only admin can toggle status or delete ?>
                          <a href="./toggle_student_status.php?id=<?php echo htmlspecialchars($student_data['user_id']); ?>"
                             class="action-button <?php echo (($student_data['is_active'] ?? 0) == 1) ? 'secondary' : 'primary'; ?>"
                             onclick="return confirm('Are you sure you want to <?php echo (($student_data['is_active'] ?? 0) == 1) ? 'DEACTIVATE' : 'ACTIVATE'; ?> this account? This will prevent login but keep data.');">
                              <?php echo (($student_data['is_active'] ?? 0) == 1) ? 'Deactivate Student' : 'Activate Student'; ?>
                          </a>
                         <!-- Added danger class for delete button -->
                         <a href="./delete_student.php?id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="action-button danger" onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY DELETE this student record? This action cannot be undone and all associated data (including fees and results) will be lost.');">Delete Student</a>
                     <?php endif; ?>
                     <a href="admin_dashboard.php" class="action-button secondary">Back to Dashboard</a>
                 </div>

             </div>
         <?php endif; ?>


     </div> <!-- End main-content-wrapper -->

     <!-- Optional: Background buttons if desired -->
     <div class="mt-8 text-center text-sm text-gray-700 pb-8">
         Choose Background:
         <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
         <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
          <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
          <button class="ml-2 px-2 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" onclick="setBackground('solid-bg-gray')">Gray</button>
          <button class="ml-2 px-2 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs" onclick="setBackground('solid-bg-indigo')">Indigo</button>
     </div>


</body>
</html>