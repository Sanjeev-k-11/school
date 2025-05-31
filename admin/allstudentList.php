<?php
// School/admin/allstudentList.php

// Start the session
session_start();

// Include database configuration file
// Path from 'admin/' up to 'School/' is '../'
require_once "../config.php";

// Check if the user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can manage student records.</p>";
    // Path from 'admin/' up to 'School/' is '../'
    header("location: ../login.php");
    exit;
}

// --- CSV Download Logic ---
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    // Re-check auth for download request
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
         header("HTTP/1.1 403 Forbidden");
         echo "Access Denied.";
         exit;
    }

    if ($link === false) {
        error_log("CSV Download DB connection failed: " . mysqli_connect_error());
        header("Content-Type: text/plain");
        echo "Error: Database connection failed for download.";
        exit;
    }

    // Fetch data for CSV - including virtual_id and photo_filename (which is now the URL)
    $sql_fetch_students_csv = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, student_fees, optional_fees, address, pincode, state, is_active, created_at, photo_filename FROM students ORDER BY created_at DESC";
    $student_data_csv = [];

    if ($stmt_csv = mysqli_prepare($link, $sql_fetch_students_csv)) {
        if (mysqli_stmt_execute($stmt_csv)) {
            $result_csv = mysqli_stmt_get_result($stmt_csv);
            $student_data_csv = mysqli_fetch_all($result_csv, MYSQLI_ASSOC);
            mysqli_free_result($result_csv);
        } else {
            error_log("CSV Download fetch query failed: " . mysqli_stmt_error($stmt_csv));
            header("Content-Type: text/plain");
            echo "Error fetching data for download.";
            exit;
        }
        mysqli_stmt_close($stmt_csv);
    } else {
         error_log("CSV Download prepare fetch statement failed: " . mysqli_error($link));
         header("Content-Type: text/plain");
         echo "Error preparing data query for download.";
         exit;
    }

    // Set CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_data_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Header row - including 'Virtual ID' and 'Photo URL'
    fputcsv($output, ['User ID', 'Virtual ID', 'Full Name', "Father's Name", "Mother's Name", 'Phone', 'WhatsApp', 'Current Class', 'Previous Class', 'Previous School', 'Previous Marks (%)', 'Current Marks (%)', 'Student Fees', 'Optional Fees', 'Address', 'Pincode', 'State', 'Is Active', 'Created At', 'Photo URL']); // Changed 'Photo Filename' to 'Photo URL' in header

    foreach ($student_data_csv as $row) {
         fputcsv($output, [
             $row['user_id'],
             $row['virtual_id'] ?? '', // Virtual ID
             $row['full_name'],
             $row['father_name'],
             $row['mother_name'],
             $row['phone_number'],
             $row['whatsapp_number'] ?? '',
             $row['current_class'],
             $row['previous_class'] ?? '',
             $row['previous_school'] ?? '',
             $row['previous_marks_percentage'] ?? '',
             $row['current_marks'] ?? '',
             $row['student_fees'] ?? '',
             $row['optional_fees'] ?? '',
             str_replace(["\r\n", "\r", "\n"], " ", $row['address'] ?? ''), // Replace newlines in address
             $row['pincode'] ?? '',
             $row['state'] ?? '',
             $row['is_active'] == 1 ? 'Yes' : 'No',
             $row['created_at'],
             $row['photo_filename'] ?? '' // This is the Cloudinary URL
         ]);
    }

    fclose($output);

    exit; // Stop execution after sending CSV
}

// --- Main Student List Fetch Logic ---
$students = [];
$fetch_student_message = "";

// Fetch all student data for the main list view
$sql_fetch_students = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, student_fees, optional_fees, address, pincode, state, is_active, created_at, photo_filename FROM students ORDER BY created_at DESC";


if ($link === false) {
    $fetch_student_message = "<p class='text-red-600'>Database connection error. Could not load student list.</p>";
     error_log("Manage Students DB connection failed: " . mysqli_connect_error());
} else {
    if ($stmt_students = mysqli_prepare($link, $sql_fetch_students)) {
        if (mysqli_stmt_execute($stmt_students)) {
            $result_students = mysqli_stmt_get_result($stmt_students);

            if ($result_students && mysqli_num_rows($result_students) > 0) { // Check if result is valid and has rows
                $students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
            } else {
                $fetch_student_message = "No student records found yet.";
                 $students = []; // Ensure $students is empty
            }

            if ($result_students) mysqli_free_result($result_students); // Free result set

        } else {
             $fetch_student_message = "<p class='text-red-600'>Error fetching student data: " . mysqli_stmt_error($stmt_students) . "</p>";
             error_log("Manage Students fetch query failed: " . mysqli_stmt_error($stmt_students));
        }

        if ($stmt_students) mysqli_stmt_close($stmt_students); // Close statement
    } else {
         $fetch_student_message = "<p class='text-red-600'>Error preparing student fetch statement: " . mysqli_error($link) . "</p>";
         error_log("Manage Students prepare fetch statement failed: " . mysqli_error($link));
    }
}

$operation_message = "";
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}

// Close database connection if it was opened
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
         /* Keep your existing styles */
          .form-error { color: #dc3545; font-size: 0.875em; margin-top: 0.25em; display: block; }
           .form-control.is-invalid { border-color: #dc3545; }

             .gradient-background-blue-cyan { background: linear-gradient(to right, #4facfe, #00f2fe); }
              .gradient-background-purple-pink { background: linear-gradient(to right, #a18cd1, #fbc2eb); }
               .gradient-background-green-teal { background: linear-gradient(to right, #a8edea, #fed6e3); }
               .solid-bg-gray { background-color: #f3f4f6; }
               .solid-bg-indigo { background-color: #4f46e5; }
               body { background: linear-gradient(to right, #4facfe, #00f2fe); } /* Default body background */


          .message-box { padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem; text-align: center; }
          .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; }
           .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; }
           .message-box.warning { color: #b45309; background-color: #fffce0; border-color: #fde68a; }
           .message-box.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }

          .fixed-header { position: fixed; top: 0; left: 0; right: 0; z-index: 20; background-color: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 0.75rem 1.5rem; display: flex; align-items: center; }
          /* Adjusted padding-top to accommodate the fixed header */
          body { padding-top: 4.5rem; transition: padding-left 0.3s ease; }
          /* body.sidebar-open is handled by JS/sidebar file */

          .student-table th, .student-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; font-size: 0.875rem; }
           .student-table th { background-color: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; color: #374151; letter-spacing: 0.05em; }
            .student-table tbody tr:nth-child(even) { background-color: #f9fafb; }
            .student-table tbody tr:hover { background-color: #e5e7eb; }
             .student-table .actions { white-space: nowrap; min-width: 180px; text-align: center; }
             .student-table td { color: #4b5563; }
             .student-table td a { font-weight: 500; }

           .action-link-edit { color: #3b82f6; }
           .action-link-edit:hover { color: #2563eb; text-decoration: underline; }
            /* Corrected Activate/Deactivate link styles */
            .action-link-deactivate { color: #f59e0b; margin-left: 0.5rem; } /* Orange for Deactivate */
            .action-link-deactivate:hover { color: #d97706; text-decoration: underline; }
            .action-link-activate { color: #10b981; margin-left: 0.5rem; } /* Green for Activate */
            .action-link-activate:hover { color: #059669; text-decoration: underline; }
           .action-link-delete { color: #ef4444; margin-left: 0.5rem; }
           .action-link-delete:hover { color: #dc2626; text-decoration: underline; }

         .search-input-container {
             flex-grow: 1;
         }
          .search-input-container label {
              display: block;
              font-size: 0.875rem;
              font-weight: 500;
              color: #374151;
              margin-bottom: 0.25rem;
          }
           .search-input-container input[type="text"] {
               width: 100%;
               padding: 0.5rem 0.75rem;
               border: 1px solid #d1d5db;
               border-radius: 0.375rem;
               box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
               font-size: 0.875rem;
               color: #374151;
               transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
           }
            .search-input-container input[type="text"]:focus {
                 outline: none;
                 border-color: #4f46e5;
                 box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            }

            .download-button {
                display: inline-flex;
                align-items: center;
                background-color: #22c55e;
                color: white;
                padding: 0.625rem 1.25rem;
                border-radius: 0.375rem;
                font-weight: 500;
                font-size: 0.875rem;
                transition: background-color 0.15s ease-in-out;
                flex-shrink: 0;
                text-decoration: none; /* Prevent underline */
            }
            .download-button:hover {
                background-color: #16a34a;
            }
            .download-button svg {
                 margin-right: 0.5rem;
                 height: 1.25rem;
                 width: 1.25rem;
            }

             .search-and-download {
                 display: flex;
                 flex-direction: column;
                 gap: 1rem;
                 margin-bottom: 1.5rem;
             }

             @media (min-width: 768px) {
                 .search-and-download {
                     flex-direction: row;
                     align-items: flex-end;
                     gap: 1.5rem;
                 }
                  .search-input-container {
                      margin-right: 0;
                  }
             }

            .circular-photo-sm {
                 width: 30px;
                 height: 30px;
                 border-radius: 50%;
                 object-fit: cover;
                 margin-right: 0.5rem;
                 vertical-align: middle; /* Align vertically with text */
                 border: 1px solid #cbd5e1; /* gray-400 */
                 flex-shrink: 0; /* Prevent it from shrinking */
            }
             td .flex-center-y {
                  display: flex;
                  align-items: center; /* Center photo vertically with text */
             }
             td .flex-center-y > span {
                 flex-grow: 1; /* Allow text to take remaining space */
             }

    </style>
     <!-- Script for background change and search -->
     <!-- Note: Assumes admin_sidebar.js exists if you have sidebar toggling JS -->
    <script>
        function setBackground(className) {
            const body = document.body;
            body.classList.forEach(cls => {
                if (cls.startsWith('gradient-background-') || cls.startsWith('solid-bg-')) {
                    body.classList.remove(cls);
                }
            });
            body.classList.add(className);
            localStorage.setItem('backgroundPreference', className);
        }

        document.addEventListener('DOMContentLoaded', (event) => {
            const savedBackground = localStorage.getItem('backgroundPreference');
            if (savedBackground) {
                 // Check if the saved class looks like a valid background class name
                if (savedBackground.startsWith('gradient-background-') || savedBackground.startsWith('solid-bg-')) {
                     setBackground(savedBackground);
                 } else {
                     // Clear invalid saved preference
                     localStorage.removeItem('backgroundPreference');
                     // Optionally set a default here if needed
                 }
            } else {
                 // Set a default background if no preference is saved
                 setBackground('gradient-background-blue-cyan');
            }

            const searchInput = document.getElementById('studentSearchInput');
            const tableBody = document.querySelector('.student-table tbody');

            if (searchInput && tableBody) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim(); // Trim search term
                    const rows = tableBody.querySelectorAll('tr');

                    rows.forEach(row => {
                        let rowText = '';
                        // Select specific cells to include in search if needed, or include all non-action cells
                        const cellsToSearch = row.querySelectorAll('td:not(.actions)'); // Exclude the actions cell
                        cellsToSearch.forEach(cell => {
                             // Get text content and add a space to separate values
                            rowText += cell.textContent ? cell.textContent.toLowerCase() + ' ' : '';
                        });

                        if (rowText.includes(searchTerm)) {
                            row.style.display = ''; // Show the row
                        } else {
                            row.style.display = 'none'; // Hide the row
                        }
                    });
                });
            }

             // Optional: Add JS for the sidebar toggle if using admin_sidebar.php
             document.getElementById('admin-sidebar-toggle-open')?.addEventListener('click', function() {
                 document.body.classList.toggle('sidebar-open');
             });
        });
    </script>
</head>
<body class="min-h-screen">
    <?php
    // Assuming admin_sidebar.php is in the SAME directory as this file (admin/)
    // Path from 'admin/' to 'admin/' is './'
    $sidebar_path = "./admin_sidebar.php";
     if (file_exists($sidebar_path)) {
         require_once $sidebar_path;
     } else {
         echo '<div class="message-box error" role="alert">Admin sidebar file not found! Check path: `' . htmlspecialchars($sidebar_path) . '`</div>';
         // exit; // Uncomment if sidebar is critical
     }
    ?>

    <!-- Main content wrapper -->
    <div class="w-full max-w-screen-xl mx-auto px-4 py-8"> <!-- Centered container with max width and padding -->

         <!-- Fixed Header/Navbar content - this is separate from the sidebar -->
         <!-- Moved header outside the main content div to allow sidebar overlap -->
         <!-- The fixed header HTML is already in the body, above the main content div -->
         <!-- It needs to be visually styled to appear "fixed" at the top -->
         <!-- Ensure the fixed-header class is correctly styled in CSS -->
         <div class="fixed-header">
              <!-- Assuming admin_sidebar.js toggles a class like 'sidebar-open' on the body -->
             <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:mr-6" aria-label="Toggle sidebar">
                 <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                 </svg>
             </button>

             <!-- Path from 'admin/' up to 'School/' is '../' -->
             <a href="admin_dashboard.php" class="text-indigo-600 hover:text-indigo-800 hover:underline transition duration-150 ease-in-out text-sm font-medium flex items-center mr-4 md:mr-6">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                 </svg>
                 Back
             </a>

             <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">Manage Students</h1>

              <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span> <!-- Used $_SESSION['name'] based on login.php -->
               <!-- Path from 'admin/' up to 'School/' is '../' -->
                <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
         </div>
         <!-- End Fixed Header -->


         <?php
         // Display operation messages (e.g., from successful edit/delete/toggle or access denied from other pages)
         if (!empty($operation_message)) {
             $message_type = 'info';
              // Check message content for type - make checks case-insensitive and include common phrases
              $msg_lower = strtolower(strip_tags($operation_message)); // Convert to lower and remove HTML tags for checks
              if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                   $message_type = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_type = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false) {
                   $message_type = 'warning';
              }
             echo "<div class='message-box " . $message_type . "' role='alert'>" . $operation_message . "</div>"; // Output the raw message HTML (it contains <p> tags)
         }
         ?>

         <div class="search-and-download">
             <div class="search-input-container">
                 <label for="studentSearchInput">Search Records:</label> <!-- Simplified label -->
                 <input type="text" id="studentSearchInput" placeholder="Search by name, ID, class, phone, etc.">
             </div>

             <?php if (!empty($students)): // Only show download if there are students ?>
                 <!-- Link to THIS page with ?download=csv -->
                 <!-- Path from 'admin/' to 'admin/' is './' -->
                 <a href="./allstudentList.php?download=csv" class="download-button">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                       <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                     </svg>
                     Download CSV
                 </a>
             <?php endif; ?>
         </div>

        <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full overflow-x-auto"> <!-- Added overflow-x-auto for wide table -->
             <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">Student Records</h2>

             <?php
             // Display message if student list is empty or there was a fetch error
             if (empty($students)) {
                 $alert_class = 'message-box info'; // Default message style
                 if (strpos(strip_tags($fetch_student_message), 'Error') !== false || strpos(strip_tags($fetch_student_message), 'Could not') !== false || strpos(strip_tags($fetch_student_message), 'Database connection') !== false) {
                      $alert_class = 'message-box error'; // Use error style for fetch errors
                 } elseif (strpos(strip_tags($fetch_student_message), 'No student records found') !== false || strpos(strip_tags($fetch_student_message), 'yet') !== false) {
                     $alert_class = 'message-box warning'; // Use warning for empty state
                 }
                 echo "<div class='" . $alert_class . "' role='alert'>" . htmlspecialchars($fetch_student_message) . "</div>"; // htmlspecialchars for safety
             }
             ?>

             <?php if (!empty($students)): ?>
                 <table class="min-w-full divide-y divide-gray-200 student-table">
                     <thead>
                         <tr>
                             <th scope="col">Photo</th>
                             <th scope="col">User ID</th>
                             <th scope="col">Virtual ID</th> <!-- Header added -->
                             <th scope="col">Full Name</th>
                             <th scope="col">Father's Name</th>
                             <th scope="col">Mother's Name</th>
                             <th scope="col">Phone</th>
                             <th scope="col">WhatsApp</th>
                             <th scope="col">Current Class</th>
                             <th scope="col">Previous Class</th>
                             <th scope="col">Previous School</th>
                             <th scope="col">Prev Marks (%)</th>
                             <th scope="col">Current Marks (%)</th>
                             <th scope="col">Student Fees</th>
                             <th scope="col">Optional Fees</th>
                             <th scope="col">Address</th>
                             <th scope="col">Pincode</th>
                             <th scope="col">State</th>
                             <th scope="col">Status</th>
                             <th scope="col">Created At</th>
                             <th scope="col" class="text-center">Actions</th>
                         </tr>
                     </thead>
                     <tbody class="bg-white divide-y divide-gray-200">
                         <?php foreach ($students as $student): ?>
                             <tr>
                                 <td>
                                     <?php
                                         // *** CORRECTED: Use the Cloudinary URL directly from the database ***
                                         $cloudinary_url = $student['photo_filename'] ?? ''; // Get the value, default to empty string if null
                                         // Path to a local default avatar relative to THIS file (admin/allstudentList.php)
                                         // Assuming default_avatar.png is in School/assets/images/
                                         $default_avatar_path = '../../assets/images/default_avatar.png'; // Path from School/admin/ to School/assets/images/

                                         // Determine the final image URL to display
                                         $display_photo_url = !empty($cloudinary_url) ? $cloudinary_url : $default_avatar_path;
                                     ?>
                                     <img src="<?php echo htmlspecialchars($display_photo_url); ?>" alt="Student Photo" class="circular-photo-sm">
                                 </td>
                                <td><?php echo htmlspecialchars($student['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($student['virtual_id'] ?? ''); ?></td> <!-- Data cell added -->
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['mother_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['whatsapp_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['current_class']); ?></td>
                                <td><?php echo htmlspecialchars($student['previous_class'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['previous_school'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['previous_marks_percentage'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['current_marks'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['student_fees'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['optional_fees'] ?? ''); ?></td>
                                <!-- Using nl2br to display saved newlines in address -->
                                <td><?php echo nl2br(htmlspecialchars($student['address'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($student['pincode'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($student['state'] ?? ''); ?></td>
                                <td>
                                    <?php
                                         echo $student['is_active'] == 1 ? '<span class="text-green-600 font-semibold">Active</span>' : '<span class="text-red-600 font-semibold">Inactive</span>';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['created_at']); ?></td>
                                <td class="actions text-center">
                                     <!-- Link to edit page -->
                                     <!-- Path from 'admin/' to 'admin/' is './' -->
                                    <a href="./edit_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="action-link-edit">Edit</a>
                                    <?php if ($student['is_active'] == 1): ?>
                                         <!-- Link to toggle status (Deactivate) -->
                                         <!-- Path from 'admin/' to 'admin/' is './' -->
                                        <a href="./toggle_student_status.php?id=<?php echo htmlspecialchars($student['user_id']); ?>"
                                           class="action-link-deactivate"
                                           onclick="return confirm('Are you sure you want to DEACTIVATE account for <?php echo addslashes(htmlspecialchars($student['full_name'])); ?>?');">Deactivate</a> <!-- Corrected text -->
                                    <?php else: ?>
                                         <!-- Link to toggle status (Activate) -->
                                          <!-- Path from 'admin/' to 'admin/' is './' -->
                                         <a href="./toggle_student_status.php?id=<?php echo htmlspecialchars($student['user_id']); ?>"
                                            class="action-link-activate"
                                            onclick="return confirm('Are you sure you want to ACTIVATE account for <?php echo addslashes(htmlspecialchars($student['full_name'])); ?>?');">Activate</a> <!-- Corrected text -->
                                    <?php endif; ?>
                                     <!-- Link to delete record -->
                                     <!-- Path from 'admin/' to 'admin/' is './' -->
                                    <a href="./delete_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>"
                                       class="action-link-delete"
                                       onclick="return confirm('Are you sure you want to DELETE the entire record for <?php echo addslashes(htmlspecialchars($student['full_name'])); ?>? This cannot be undone!');">Delete</a>
                                </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div> <!-- End overflow-x-auto -->
         <?php endif; // End if (!empty($students)) ?>
        </div> <!-- End bg-white panel -->

     </div> <!-- End main content wrapper -->

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