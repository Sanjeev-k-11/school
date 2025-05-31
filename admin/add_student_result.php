<?php
// School/admin/add_student_result.php

// Start the session
session_start();

require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can add student results.</p>";
    header("location: admin_dashboard.php");
    exit;
}

// Initialize variables
$student_id = null;
$student_data = null;
$academic_year = '';
$exam_name = '';
$subject_entries = []; // Array to hold submitted subject data on error
$operation_message = "";
$operation_message_type = ""; // Used to determine toast type

// Handle GET request (to display the form for a specific student)
if (isset($_GET['student_id']) && !empty(trim($_GET['student_id']))) {
    $student_id = trim($_GET['student_id']);

    // Validate student_id
    if (!filter_var($student_id, FILTER_VALIDATE_INT) || $student_id <= 0) {
        $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid student ID provided.</p>";
        header("location: admin_dashboard.php");
        exit();
    }

    // Fetch student data for confirmation
    $sql = "SELECT user_id, full_name, roll_number, current_class FROM students WHERE user_id = ?";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $param_id);
        $param_id = $student_id;
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) == 1) {
                $student_data = mysqli_fetch_assoc($result);
            } else {
                // Student not found
                $_SESSION['operation_message'] = "<p class='text-red-600'>No student found with the provided ID.</p>";
                header("location: admin_dashboard.php");
                exit();
            }
            mysqli_free_result($result);
        } else {
            error_log("Add Result - Error fetching student: " . mysqli_stmt_error($stmt));
            $_SESSION['operation_message'] = "<p class='text-red-600'>Error fetching student details.</p>";
            header("location: admin_dashboard.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Add Result - Error preparing student fetch: " . mysqli_error($link));
        $_SESSION['operation_message'] = "<p class='text-red-600'>Database error preparing student fetch.</p>";
        header("location: admin_dashboard.php");
        exit();
    }

} else if ($_SERVER["REQUEST_METHOD"] !== "POST") { // If not a POST request and no ID
    $_SESSION['operation_message'] = "<p class='text-red-600'>No student ID specified for adding results.</p>";
    header("location: admin_dashboard.php");
    exit();
}


// Handle POST request (form submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_POST['student_id'] ?? null;
    $academic_year = trim($_POST['academic_year'] ?? '');
    $exam_name = trim($_POST['exam_name'] ?? '');
    $subjects = $_POST['subjects'] ?? []; // Array of subject entries

     // Fetch student data again for confirmation/redirect
     if ($student_id) {
         $sql = "SELECT user_id, full_name, roll_number, current_class FROM students WHERE user_id = ?";
         if ($stmt = mysqli_prepare($link, $sql)) {
             mysqli_stmt_bind_param($stmt, "i", $student_id);
             if (mysqli_stmt_execute($stmt)) {
                 $result = mysqli_stmt_get_result($stmt);
                 if (mysqli_num_rows($result) == 1) {
                     $student_data = mysqli_fetch_assoc($result);
                 }
                 mysqli_free_result($result);
             } else {
                 error_log("Add Result - Error fetching student on POST: " . mysqli_stmt_error($stmt));
                  // Continue attempt to save, but note the student data fetch failed
             }
             mysqli_stmt_close($stmt);
         } else {
             error_log("Add Result - Error preparing student fetch on POST: " . mysqli_error($link));
             // Continue attempt to save, but note the student data fetch failed
         }
     }


    // Basic validation
    if (empty($student_id) || !filter_var($student_id, FILTER_VALIDATE_INT) || $student_id <= 0) {
        $operation_message = "Invalid student ID received.";
        $operation_message_type = 'error';
    } elseif (empty($academic_year)) {
        $operation_message = "Academic Year is required.";
        $operation_message_type = 'warning';
        $subject_entries = $subjects; // Preserve entered subjects on error
    } elseif (empty($exam_name)) {
        $operation_message = "Exam Name is required.";
        $operation_message_type = 'warning';
        $subject_entries = $subjects; // Preserve entered subjects on error
    } elseif (empty($subjects)) {
        $operation_message = "At least one subject entry is required.";
        $operation_message_type = 'warning';
    } else {
        // Validate each subject entry
        $all_subjects_valid = true;
        $validated_subjects = [];
        foreach ($subjects as $index => $subject) {
            $subject_name = trim($subject['subject_name'] ?? '');
            $marks_obtained = trim($subject['marks_obtained'] ?? '');
            $max_marks = trim($subject['max_marks'] ?? ''); // max_marks is optional

            if (empty($subject_name)) {
                $operation_message = "Subject name is required for all entries.";
                $operation_message_type = 'warning';
                $all_subjects_valid = false;
                break; // Stop validation on first error
            }
            // Check if marks_obtained is numeric AND not empty
             if (!is_numeric($marks_obtained) || $marks_obtained === '' || (float)$marks_obtained < 0) {
                 $operation_message = "Valid positive numeric marks are required for all subjects.";
                 $operation_message_type = 'warning';
                 $all_subjects_valid = false;
                 break; // Stop validation on first error
            }
             // max_marks is optional, but validate if provided
            if (!empty($max_marks) && (!is_numeric($max_marks) || (float)$max_marks < 0)) {
                 $operation_message = "Max marks must be a valid positive number if provided.";
                 $operation_message_type = 'warning';
                 $all_subjects_valid = false;
                 break; // Stop validation on first error
            }
             // Optional: Check if marks_obtained is greater than max_marks if max_marks is provided
            if (!empty($max_marks) && (float)$marks_obtained > (float)$max_marks) {
                 $operation_message = "Marks obtained cannot be greater than Max Marks for subject: " . htmlspecialchars($subject_name);
                 $operation_message_type = 'warning';
                 $all_subjects_valid = false;
                 break; // Stop validation on first error
            }


            // Store validated/cleaned data
            $validated_subjects[] = [
                'subject_name' => $subject_name,
                'marks_obtained' => (float)$marks_obtained,
                'max_marks' => !empty($max_marks) ? (float)$max_marks : null // Store null if empty
            ];
        }
         $subject_entries = $subjects; // Keep original submitted data to re-populate form on error

        if ($all_subjects_valid) {
            // Proceed with database insertion
            mysqli_begin_transaction($link);
            $insert_success = true;

            // Prepare the SQL INSERT statement
            $sql_insert = "INSERT INTO student_exam_results (student_id, academic_year, exam_name, subject_name, marks_obtained, max_marks) VALUES (?, ?, ?, ?, ?, ?)";

            if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {
                 foreach ($validated_subjects as $subject) {
                     // Bind parameters for each subject
                     // Use 'd' for double/float types for marks
                     // mysqli_stmt_bind_param handles null values for nullable columns
                     mysqli_stmt_bind_param($stmt_insert, "isssdd",
                         $student_id,
                         $academic_year,
                         $exam_name,
                         $subject['subject_name'],
                         $subject['marks_obtained'],
                         $subject['max_marks']
                     );

                     if (!mysqli_stmt_execute($stmt_insert)) {
                         error_log("Add Result - Insert failed for student ID " . $student_id . ", year " . $academic_year . ", exam " . $exam_name . ", subject " . $subject['subject_name'] . ": " . mysqli_stmt_error($stmt_insert));
                         $insert_success = false;
                         break; // Stop inserting on first failure
                     }
                 }
                 mysqli_stmt_close($stmt_insert);

                 if ($insert_success) {
                     mysqli_commit($link);
                     $_SESSION['operation_message'] = "<p class='text-green-600'>Exam results successfully added for student ID " . htmlspecialchars($student_id) . ".</p>";
                     header("location: view_student.php?id=" . htmlspecialchars($student_id));
                     exit();
                 } else {
                     mysqli_rollback($link);
                     $operation_message = "Error saving some or all exam results. Please check logs and try again.";
                     $operation_message_type = 'error';
                     // Do NOT redirect, stay on page to show error and possibly retry/correct
                 }

            } else {
                error_log("Add Result - Error preparing insert statement: " . mysqli_error($link));
                 $operation_message = "Database error preparing to save results.";
                 $operation_message_type = 'error';
                 // Do NOT redirect
            }
        }
    }

    // If there was an error, the script continues to display the form
    // with the $operation_message and potentially pre-filled $subject_entries

}

// Set the page title
$pageTitle = "Add Student Results" . ($student_data ? " for " . htmlspecialchars($student_data['full_name']) : "");

// Check for toast message set in THIS page's POST handling
if (!empty($operation_message)) {
     // Use the toast type already determined
     $toast_message = $operation_message; // Use the message from POST handling
     $toast_type = $operation_message_type; // Use the type from POST handling
     // Do NOT unset here, as we need it for the toast script below
} else if (isset($_SESSION['operation_message'])) {
    // Check for operation messages set in other pages (like if initial ID fetch failed and redirected back here somehow)
    // This case should ideally not happen with proper redirects, but as a fallback:
    $msg = $_SESSION['operation_message'];
     $msg_lower = strtolower(strip_tags($msg));
      if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false || strpos($msg_lower, 'welcome') !== false || strpos($msg_lower, 'marked as paid') !== false) {
           $toast_type = 'success';
      } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'problem') !== false) {
           $toast_type = 'error';
      } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) {
           $toast_type = 'warning';
      } else {
           $toast_type = 'info'; // Default to info
      }
    $toast_message = strip_tags($msg); // Clean HTML tags for toast display
    unset($_SESSION['operation_message']); // Clear session message once retrieved
}


// Close database connection (if it was successfully opened and not closed yet)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

// Default avatar path (not used on this page, but included for completeness if needed)
// $default_student_avatar_path = '../assets/images/default_student_avatar.png';

?>

<?php
// Include the header file
// This file should contain the opening HTML tags, <head>, fixed header bar, etc.
// It might also contain definitions for htmlspecialchars and nl2brJs
require_once "./admin_header.php";
?>

    <!-- Custom styles for this page -->
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

         /* Styles for the form layout */
          .form-grid {
              display: grid;
              grid-template-columns: 1fr; /* Single column by default */
              gap: 1rem;
          }
          @media (min-width: 640px) { /* Tailwind 'sm' breakpoint */
              .form-grid {
                   grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Two columns on small screens */
              }
          }

          .subject-entry {
               display: grid;
               grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Subject, Marks, Max Marks */
               gap: 0.75rem; /* Gap within a subject row */
               align-items: end; /* Align items to the bottom */
               padding: 1rem;
               border: 1px solid #d1d5db; /* gray-300 */
               border-radius: 0.375rem; /* rounded-md */
               background-color: #f9fafb; /* gray-50 */
               margin-bottom: 1rem; /* Space between entries */
          }
          .subject-entry .remove-button-container {
               grid-column: auto; /* Take one column by default */
                text-align: right; /* Align button right */
          }
           @media (min-width: 640px) {
               .subject-entry {
                   grid-template-columns: 3fr 1fr 1fr auto; /* Adjust column widths on small+ screens */
                   /* Subject (larger), Marks, Max Marks, Button */
               }
               .subject-entry .remove-button-container {
                    grid-column: auto; /* Auto column */
               }
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


              // --- Dynamic Subject Input Fields JS ---
              const subjectsContainer = document.getElementById('subjectsContainer');
              const addSubjectBtn = document.getElementById('addSubjectBtn');

              if (!subjectsContainer || !addSubjectBtn) {
                  console.error("Subject container or Add Subject button not found!");
                  return; // Stop if elements are missing
              }

              // Initialize counter. If repopulating, counter is handled below.
              // If not repopulating, we'll add the first entry and the counter will be 1.
              let subjectCounter = 0;

              function addSubjectEntry(subjectName = '', marksObtained = '', maxMarks = '') {
                  const entryDiv = document.createElement('div');
                  entryDiv.classList.add('subject-entry'); // Use the grid classes defined in CSS

                  // Use current subjectCounter for input names
                  const currentIndex = subjectCounter;

                  entryDiv.innerHTML = `
                       <div>
                            <label for="subjects_${currentIndex}_subject_name" class="block text-gray-700 text-sm font-medium mb-1">Subject Name:</label>
                            <input type="text" id="subjects_${currentIndex}_subject_name" name="subjects[${currentIndex}][subject_name]" value="${htmlspecialchars(subjectName)}" required class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                       </div>
                       <div>
                            <label for="subjects_${currentIndex}_marks_obtained" class="block text-gray-700 text-sm font-medium mb-1">Marks Obtained:</label>
                            <input type="number" id="subjects_${currentIndex}_marks_obtained" name="subjects[${currentIndex}][marks_obtained]" value="${htmlspecialchars(marksObtained)}" required min="0" step="0.01" class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                       </div>
                       <div>
                            <label for="subjects_${currentIndex}_max_marks" class="block text-gray-700 text-sm font-medium mb-1">Max Marks (Optional):</label>
                            <input type="number" id="subjects_${currentIndex}_max_marks" name="subjects[${currentIndex}][max_marks]" value="${htmlspecialchars(maxMarks)}" min="0" step="0.01" class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                       </div>
                       <div class="remove-button-container">
                           <button type="button" class="remove-subject-btn text-red-600 hover:text-red-800 text-sm font-medium focus:outline-none">Remove</button>
                       </div>
                  `;

                  subjectsContainer.appendChild(entryDiv);
                  subjectCounter++; // Increment counter for the *next* entry
              }

              // Add event listener to the "Add Subject" button
              addSubjectBtn.addEventListener('click', function() {
                  addSubjectEntry(); // Add a blank entry
              });

              // Add event delegation for "Remove" buttons
              subjectsContainer.addEventListener('click', function(event) {
                  if (event.target && event.target.classList.contains('remove-subject-btn')) {
                      // Ensure we don't remove the last entry if you want at least one always
                      if (subjectsContainer.children.length > 1) { // Keep at least one entry
                         event.target.closest('.subject-entry').remove();
                          // Note: Removing entries means indices in $_POST['subjects'] will be sparse
                          // (e.g., 0, 2, 3 if 1 was removed). PHP handles this fine when looping.
                      } else {
                           showToast("You must have at least one subject entry.", "warning");
                      }
                  }
              });

              // Helper function for htmlspecialchars in JS (if not already global)
              // Assumes admin_header.php provides this, but defining here as fallback
               if (typeof htmlspecialchars === 'undefined') {
                   window.htmlspecialchars = function(str) {
                       if (str === null || str === undefined) return '';
                       // Simple replacement, more robust needed for full HTML safety if allowing more characters
                       return str.toString().replace(/&/g, '&amp;')
                           .replace(/</g, '&lt;')
                           .replace(/>/g, '&gt;')
                           .replace(/"/g, '&quot;')
                           .replace(/'/g, '&#039;');
                   };
               }


              // --- Repopulate form fields on load if there were errors ---
              const academicYearInput = document.getElementById('academic_year');
              const examNameInput = document.getElementById('exam_name');
              const subjectEntriesData = <?php echo json_encode($subject_entries); ?>; // Data from PHP on error

               if (academicYearInput) academicYearInput.value = <?php echo json_encode($academic_year); ?>;
               if (examNameInput) examNameInput.value = <?php echo json_encode($exam_name); ?>;

              if (subjectEntriesData && subjectEntriesData.length > 0) {
                  // Clear any default entry if repopulating
                  subjectsContainer.innerHTML = '';
                   subjectCounter = 0; // Reset counter as we're building from scratch
                  subjectEntriesData.forEach(entry => {
                      // Ensure values are strings for htmlspecialchars in JS
                      addSubjectEntry(
                          String(entry.subject_name ?? ''),
                          String(entry.marks_obtained ?? ''),
                          String(entry.max_marks ?? '')
                      );
                  });
              } else {
                   // Add one empty entry by default if no data to repopulate
                   addSubjectEntry();
              }


         }); // End DOMContentLoaded
      </script>
</head>
<body class="min-h-screen">
    <?php require_once "./admin_sidebar.php"; // Include the sidebar ?>

     <!-- Toast Container (Positioned fixed) -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be dynamically added here -->
    </div>

     <!-- Fixed Header for Toggle Button and Page Title -->
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

        <!-- Back Button -->
        <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="text-indigo-600 hover:text-indigo-800 hover:underline transition duration-150 ease-in-out text-sm font-medium flex items-center mr-4 md:mr-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>

         <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow"><?php echo $pageTitle; ?></h1>

          <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
            <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
    </div>


    <!-- Main content wrapper -->
    <div class="main-content-wrapper w-full max-w-screen-xl mx-auto px-4 py-8 md:px-8">

         <?php if ($student_data): ?>
             <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full max-w-3xl mx-auto">
                 <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Add Results for Student</h2>

                 <div class="mb-6 text-center text-gray-700 text-sm">
                      Adding results for:
                     <span class="font-medium"><?php echo htmlspecialchars($student_data['full_name']); ?></span>
                     (Roll No: <span class="font-medium"><?php echo htmlspecialchars($student_data['roll_number'] ?? 'N/A'); ?></span>)
                     (Class: <span class="font-medium"><?php echo htmlspecialchars($student_data['current_class'] ?? 'N/A'); ?></span>)
                 </div>


                 <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                     <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_data['user_id']); ?>">

                      <!-- Academic Year and Exam Name -->
                     <div class="form-grid mb-6">
                          <div>
                              <label for="academic_year" class="block text-gray-700 text-sm font-medium mb-1">Academic Year:</label>
                              <input type="text" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>" required placeholder="e.g. 2023-2024" class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                          </div>
                         <div>
                              <label for="exam_name" class="block text-gray-700 text-sm font-medium mb-1">Exam Name:</label>
                              <input type="text" id="exam_name" name="exam_name" value="<?php echo htmlspecialchars($exam_name); ?>" required placeholder="e.g. Mid-Term Exam" class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                          </div>
                     </div>

                      <!-- Subject Entries Container (Dynamic) -->
                     <div id="subjectsContainer" class="border border-gray-300 rounded-md p-4 mb-6 bg-gray-100">
                         <h4 class="text-md font-semibold text-gray-700 mb-4">Subject Marks</h4>
                         <!-- Subject entries will be added here by JavaScript -->
                         <?php
                          // This div is populated by JS on DOMContentLoaded.
                          // If $subject_entries is not empty (from a POST error), JS will use that data.
                          // Otherwise, JS adds one blank row.
                         ?>
                     </div>

                     <!-- Add Subject Button -->
                     <div class="mb-6 text-center">
                         <button type="button" id="addSubjectBtn" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                             <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                             </svg>
                             Add Subject
                         </button>
                     </div>

                     <!-- Form Actions -->
                     <div class="flex justify-center gap-4">
                         <button type="submit" class="inline-flex justify-center py-2 px-6 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                              Save Results
                         </button>
                          <a href="view_student.php?id=<?php echo htmlspecialchars($student_data['user_id']); ?>" class="inline-flex justify-center py-2 px-6 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                              Cancel
                          </a>
                     </div>
                 </form>

             </div>
         <?php else: ?>
              <!-- This block is only shown if the student data couldn't be fetched (should be handled by redirects, but as a fallback) -->
             <div class="bg-white p-6 rounded-lg shadow-md text-center text-red-600">
                 <p>Could not load student details. Please return to the dashboard.</p>
                 <div class="mt-4">
                     <a href="admin_dashboard.php" class="inline-flex justify-center py-2 px-6 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                         Go to Dashboard
                     </a>
                 </div>
             </div>
         <?php endif; ?>

     </div> <!-- End main-content-wrapper -->


<?php
// Include the footer file - This will close the body and html tags
require_once "./admin_footer.php";
?>