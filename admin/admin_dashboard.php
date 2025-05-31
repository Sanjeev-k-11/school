<?php
// School/admin/admin_dashboard.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
// Redirect to login if not authorized
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can access this dashboard.</p>";
    header("location: ../login.php");
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Admin Dashboard - Overview";

// Get user role for conditional display
$loggedInUserRole = $_SESSION['role'] ?? 'guest';


// --- Variables for Messages ---
$operation_message = ""; // For messages from other pages (e.g., successful save/delete)
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}

// Messages specific to data fetches
$select_student_message = "";
$fetch_staff_message = "";
$fetch_events_message = "";
$fetch_fees_due_count_message = ""; // Message for students with fees due count
$fetch_total_due_latest_5_message = ""; // Message for total due for latest 5 students


// --- Fetch Data ---
$students = []; // Array for student data (latest 5)
$staff_members = []; // Array for staff data (latest 5)
$events = []; // Array for event data (recent)

$totalStudentsCount = 0; // Total active students count
$totalStaffCount = 0;    // Total staff count
$studentsWithFeesDueCount = 0; // Total count of active students with *any* fees due (from student_monthly_fees)
$totalOutstandingFeeForLatest5Students = 0; // Total outstanding fees *specifically for the 5 students shown*


// Database connection check
if ($link === false) {
    // Set database connection error messages for all sections if connection fails
    $select_student_message = "<p class='text-red-600'>Database connection error. Could not load student list.</p>";
    $fetch_staff_message = "<p class='text-red-600'>Database connection error. Could not load staff list.</p>";
    $fetch_events_message = "<p class='text-red-600'>Database connection error. Could not load events list.</p>";
    $fetch_fees_due_count_message = "<p class='text-red-600'>Database connection error. Could not load fee due count.</p>";
    $fetch_total_due_latest_5_message = "<p class='text-red-600'>Database connection error. Could not load total due for latest students.</p>";
    error_log("Admin Dashboard DB connection failed: " . mysqli_connect_error());

} else { // Only proceed if database connection is successful

    // --- Fetch Students (Latest 5) ---
    // This query selects the latest 5 students by creation date for the table display.
    // We will use their IDs later to find their outstanding fees.
    $sql_fetch_students = "SELECT user_id, virtual_id, full_name, father_name, mother_name, phone_number, whatsapp_number, current_class, previous_class, previous_school, previous_marks_percentage, current_marks, student_fees, optional_fees, address, pincode, state, is_active, created_at, photo_filename FROM students ORDER BY created_at DESC LIMIT 5";

    $latest_student_ids = []; // Array to hold IDs of the 5 latest students

    if ($stmt_students = mysqli_prepare($link, $sql_fetch_students)) {
        if (mysqli_stmt_execute($stmt_students)) {
            $result_students = mysqli_stmt_get_result($stmt_students);
            if ($result_students) {
                 if (mysqli_num_rows($result_students) > 0) {
                    $students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
                    // Extract user_ids for the next fee query
                    $latest_student_ids = array_column($students, 'user_id');
                    $select_student_message = "Displaying latest " . count($students) . " student record(s).";
                } else {
                    $select_student_message = "No student records found yet.";
                }
                mysqli_free_result($result_students);
            } else {
                 $select_student_message = "<p class='text-red-600'>Error getting student result set: " . htmlspecialchars(mysqli_stmt_error($stmt_students)) . "</p>";
                 error_log("Admin Dashboard student get_result failed: " . mysqli_stmt_error($stmt_students));
            }
        } else {
             $select_student_message = "<p class='text-red-600'>Error executing student query: " . htmlspecialchars(mysqli_stmt_error($stmt_students)) . "</p>";
             error_log("Admin Dashboard student fetch query failed: " . mysqli_stmt_error($stmt_students));
        }
        mysqli_stmt_close($stmt_students);
    } else {
         $select_student_message = "<p class='text-red-600'>Error preparing student statement: " . htmlspecialchars(mysqli_error($link)) . "</p>";
         error_log("Admin Dashboard prepare student statement failed: " . mysqli_error($link));
    }


     // --- Fetch Total Student Count (Active) ---
     $sql_total_students = "SELECT COUNT(*) AS total FROM students WHERE is_active = 1";
      $result_total = mysqli_query($link, $sql_total_students);
      if ($result_total) {
          if ($row_total = mysqli_fetch_assoc($result_total)) {
              $totalStudentsCount = $row_total['total'];
          } else {
              error_log("Admin Dashboard could not fetch row for total active students.");
          }
           mysqli_free_result($result_total);
      } else {
          error_log("Admin Dashboard total active students query failed: " . mysqli_error($link));
           // No user message needed for this count failure unless critical
      }

    // --- Fetch Total Staff Count ---
     $sql_total_staff = "SELECT COUNT(*) AS total FROM staff";
     $result_total_staff = mysqli_query($link, $sql_total_staff);
      if ($result_total_staff) {
           if ($row_total_staff = mysqli_fetch_assoc($result_total_staff)) {
                $totalStaffCount = $row_total_staff['total'];
           } else {
               error_log("Admin Dashboard could not fetch row for total staff.");
           }
           mysqli_free_result($result_total_staff);
      } else {
           error_log("Admin Dashboard total staff query failed: " . mysqli_error($link));
           // No user message needed for this count failure.
      }


    // --- Fetch Total Students with Fees Due (from student_monthly_fees) ---
    // Counts unique active students who have at least one monthly fee record where amount_due > amount_paid
    $sql_students_with_due = "SELECT COUNT(DISTINCT smf.student_id) AS students_with_fees_due
                              FROM student_monthly_fees smf
                              JOIN students s ON smf.student_id = s.user_id
                              WHERE s.is_active = 1 AND smf.amount_due > smf.amount_paid";

    if ($result_students_due = mysqli_query($link, $sql_students_with_due)) {
        if ($row_students_due = mysqli_fetch_assoc($result_students_due)) {
            $studentsWithFeesDueCount = $row_students_due['students_with_fees_due'] ?? 0; // Default to 0 if NULL
        } else {
            error_log("Admin Dashboard could not fetch row for students with fees due.");
             $fetch_fees_due_count_message = "<p class='text-red-600'>Error fetching fee due count row.</p>";
        }
        mysqli_free_result($result_students_due);
    } else {
        error_log("Admin Dashboard students with fees due query failed: " . mysqli_error($link));
         $fetch_fees_due_count_message = "<p class='text-red-600'>Error querying fee due count.</p>";
    }

    // --- Fetch Total Outstanding Fee for Latest 5 Students (from student_monthly_fees) ---
    // This sums the outstanding amount (amount_due - amount_paid) for the specific student IDs fetched above.
    if (!empty($latest_student_ids)) {
         // Prepare placeholders for the IN clause
         $placeholders = implode(',', array_fill(0, count($latest_student_ids), '?'));

         $sql_total_due_latest_5 = "SELECT SUM(smf.amount_due - smf.amount_paid) AS total_outstanding_due
                                   FROM student_monthly_fees smf
                                   WHERE smf.student_id IN ($placeholders) AND smf.amount_due > smf.amount_paid";

         if ($stmt_total_due = mysqli_prepare($link, $sql_total_due_latest_5)) {
             // Bind parameters (all are integers for user_id)
             $types = str_repeat('i', count($latest_student_ids));
             mysqli_stmt_bind_param($stmt_total_due, $types, ...$latest_student_ids);

             if (mysqli_stmt_execute($stmt_total_due)) {
                 $result_total_due = mysqli_stmt_get_result($stmt_total_due);
                 if ($result_total_due && $row_total_due = mysqli_fetch_assoc($result_total_due)) {
                     $totalOutstandingFeeForLatest5Students = $row_total_due['total_outstanding_due'] ?? 0; // Use 0 if sum is NULL
                 } else {
                      error_log("Admin Dashboard could not fetch row for total outstanding fees for latest 5.");
                     $fetch_total_due_latest_5_message = "<p class='text-red-600'>Error fetching total due row for latest students.</p>";
                 }
                 mysqli_free_result($result_total_due);
             } else {
                  error_log("Admin Dashboard execute query for total outstanding fees failed: " . mysqli_stmt_error($stmt_total_due));
                 $fetch_total_due_latest_5_message = "<p class='text-red-600'>Error executing total due query for latest students.</p>";
             }
             mysqli_stmt_close($stmt_total_due);
         } else {
              error_log("Admin Dashboard prepare statement for total outstanding fees failed: " . mysqli_error($link));
             $fetch_total_due_latest_5_message = "<p class='text-red-600'>Error preparing total due statement for latest students.</p>";
         }
    } else {
        // No latest students fetched, total outstanding fee remains 0. No error needed.
         $fetch_total_due_latest_5_message = "<p class='text-gray-600 text-sm'>No latest students found to calculate fees for.</p>";
    }


    // --- Fetch Staff (Latest 5) ---
    $sql_fetch_staff = "SELECT staff_id, staff_name, mobile_number, unique_id, email, role, salary, subject_taught, classes_taught, created_at, photo_filename FROM staff ORDER BY created_at DESC LIMIT 5";

    if ($stmt_staff = mysqli_prepare($link, $sql_fetch_staff)) {
        if (mysqli_stmt_execute($stmt_staff)) {
            $result_staff = mysqli_stmt_get_result($stmt_staff);

            if ($result_staff) {
                 if (mysqli_num_rows($result_staff) > 0) {
                    $staff_members = mysqli_fetch_all($result_staff, MYSQLI_ASSOC);
                    $fetch_staff_message = "Displaying latest " . count($staff_members) . " staff records.";
                } else {
                    $fetch_staff_message = "No staff records found.";
                }
                mysqli_free_result($result_staff);
            } else {
                 $fetch_staff_message = "<p class='text-red-600'>Error getting staff result set: " . htmlspecialchars(mysqli_stmt_error($stmt_staff)) . "</p>";
                 error_log("Admin Dashboard staff get_result failed: " . mysqli_stmt_error($stmt_staff));
            }
        } else {
             $fetch_staff_message = "<p class='text-red-600'>Error executing staff query: " . htmlspecialchars(mysqli_stmt_error($stmt_staff)) . "</p>";
             error_log("Admin Dashboard staff fetch query failed: " . mysqli_stmt_error($stmt_staff));
        }
        mysqli_stmt_close($stmt_staff);
    } else {
         $fetch_staff_message = "<p class='text-red-600'>Error preparing staff fetch statement: " . htmlspecialchars(mysqli_error($link)) . "</p>";
         error_log("Admin Dashboard prepare staff statement failed: " . mysqli_error($link));
    }



    // --- Fetch Events (Recent events: Last 30 days + future) ---
    $sql_fetch_events = "SELECT event_id, event_name, event_description, event_date_time, created_at, created_by_name
                         FROM events
                         WHERE event_date_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) -- Events from the last 30 days
                         ORDER BY event_date_time DESC";

    if ($stmt_events = mysqli_prepare($link, $sql_fetch_events)) {
         if (mysqli_stmt_execute($stmt_events)) {
             $result_events = mysqli_stmt_get_result($stmt_events);

             if ($result_events) {
                  $events = mysqli_fetch_all($result_events, MYSQLI_ASSOC); // Fetch all results

                  if (count($events) > 0) {
                     $fetch_events_message = "Displaying " . count($events) . " recent event announcement(s).";
                 } else {
                     // Check if there are *any* events, even older ones, just to differentiate the message
                     $sql_check_any_events = "SELECT 1 FROM events LIMIT 1";
                      if ($res_check = mysqli_query($link, $sql_check_any_events)) {
                          if (mysqli_num_rows($res_check) > 0) {
                               $fetch_events_message = "No event announcements found in the last 30 days.";
                          } else {
                               $fetch_events_message = "No event announcements found yet.";
                          }
                          mysqli_free_result($res_check);
                      } else {
                           $fetch_events_message = "No event announcements found yet (Could not check for older events).";
                      }
                 }
                 mysqli_free_result($result_events);
             } else {
                  $fetch_events_message = "<p class='text-red-600'>Error getting event result set: " . htmlspecialchars(mysqli_stmt_error($stmt_events)) . "</p>";
                  error_log("Admin Dashboard event get_result failed: " . mysqli_stmt_error($stmt_events));
             }
         } else {
              $fetch_events_message = "<p class='text-red-600'>Error executing event query: " . htmlspecialchars(mysqli_stmt_error($stmt_events)) . "</p>";
              error_log("Admin Dashboard event fetch query failed: " . mysqli_stmt_error($stmt_events));
         }
         mysqli_stmt_close($stmt_events);
    } else {
         $fetch_events_message = "<p class='text-red-600'>Error preparing event fetch statement: " . htmlspecialchars(mysqli_error($link)) . "</p>";
         error_log("Admin Dashboard prepare event statement failed: " . mysqli_error($link));
    }
}


// Close database connection at the very end
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

// Get the count of events for JS scroll logic
$eventCount = count($events);

?>

<?php
// Include the header file. This contains the opening HTML, HEAD, and the fixed header bar.
require_once "./admin_header.php";
?>

    <!-- Custom styles specific to this page -->
     <style>
         /*
         Keep body padding here as it interacts with the fixed header and sidebar.
         These styles need to be in the main page's CSS or a global file applied *after*
         the header CSS, as they modify the body tag.
         */
         body {
             /* Add padding-top to clear the fixed header. Adjust value if needed based on header height. */
             /* Assuming fixed header height is around 60-70px (approx 4.5rem) */
             padding-top: 4.5rem; /* This matches the default height of the header */
             transition: padding-left 0.3s ease; /* Smooth transition for sidebar open/close */
         }
         /* When the sidebar is open, add padding to the left */
         /* Adjust '16rem' to match the width of your sidebar */
         body.sidebar-open {
             padding-left: 16rem; /* Assuming sidebar width is 16rem (Tailwind w-64) */
         }
         /* Optional: Adjust padding-top on larger screens if header height changes */
         @media (min-width: 768px) { /* md breakpoint */
              /* If your header height is different on desktop, adjust here */
              /* body { padding-top: ...; } */
         }


          /* Custom nth-child styling for table rows (striped rows) */
           .data-table tbody tr:nth-child(even) {
               background-color: #f9fafb; /* Tailwind gray-50 */
           }

            /* Styles for the details modal overlay */
            .modal-overlay {
                visibility: hidden; /* Start hidden */
                opacity: 0; /* Start transparent */
                transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
            }
             .modal-overlay.visible {
                 visibility: visible; /* Become visible */
                 opacity: 1; /* Become opaque */
             }

             /* Styles for the details modal content */
             .modal-content {
                 transform: scale(0.95); /* Start slightly smaller */
                 transition: transform 0.3s ease-in-out;
             }
             .modal-overlay.visible .modal-content {
                 transform: scale(1); /* Scale to normal size when visible */
             }


            /* Custom Scrollbar for Event List (Optional, adjust colors as needed) */
            .custom-scrollbar::-webkit-scrollbar {
                width: 8px; /* width of the scrollbar */
            }

            .custom-scrollbar::-webkit-scrollbar-track {
                background: #f1f1f1; /* color of the track */
                border-radius: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: #888; /* color of the scroll thumb */
                border-radius: 4px;
            }

            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: #555; /* color of the scroll thumb on hover */
            }

            /* Style for clickable table rows */
            .clickable-row {
                 cursor: pointer;
            }
            .clickable-row:hover {
                 background-color: #f3f4f6; /* Tailwind gray-100 */
            }
            /* Prevent hover effect on action links within clickable rows */
            .clickable-row td.actions a:hover,
             .clickable-row td.fees-action a:hover {
                 background-color: transparent; /* Override row hover background */
             }

            /* Style for the donut chart container (if you were adding a chart) */
            .donut-chart-container {
                /* Example minimum dimensions */
                 min-width: 150px;
                 max-width: 200px;
                 margin: 0 auto; /* Center the chart */
                 display: flex; /* Use flex to center content */
                 justify-content: center;
                 align-items: center;
                 /* Add specific height/aspect ratio if needed */
            }


     </style>

     <!-- Custom JavaScript specific to this dashboard page -->
     <script>
        // JavaScript functions like htmlspecialchars and nl2brJs are expected to be
        // defined in the <script> block within admin_header.php, making them globally available.
        // The setBackground function might also be there, or defined here if it's only for this page.

        // --- Background Changer Function (Define here if not global in header) ---
         // Define the setBackground function here for reliability, or remove if global
         function setBackground(className) {
             const body = document.body;
             // Remove all potential background classes first
             // Ensure this list includes all background classes you might use
             body.classList.remove(
                 'gradient-background-blue-cyan',
                 'gradient-background-purple-pink',
                 'gradient-background-green-teal',
                 'solid-bg-gray',
                 'solid-bg-indigo'
                 // Add any other custom background classes here
             );
             // Add the selected class
             body.classList.add(className);
             // Optionally save the preference to localStorage
             localStorage.setItem('dashboardBackground', className); // Use a specific key for the dashboard
         }


         // --- Client-Side Search JavaScript ---
         // Function to filter table rows based on search term
         function filterTableRows(tableId, searchInputId) {
             const searchInput = document.getElementById(searchInputId);
             const tableBody = document.querySelector(`#${tableId} tbody`);

             // Add a check to ensure the input and table body elements exist
             if (!searchInput || !tableBody) {
                  console.warn(`Search elements not found for table "${tableId}" and input "${searchInputId}". Skipping search setup.`);
                 return; // Exit the function if elements are missing
             }

             searchInput.addEventListener('input', function() {
                 const searchTerm = this.value.toLowerCase().trim(); // Get search term, lowercase, trim whitespace
                 const rows = tableBody.querySelectorAll('tr'); // Get all table rows

                 rows.forEach(row => {
                     let rowText = '';
                      // Concatenate text content of all cells in the row (excluding specific columns)
                      // Use class names like 'actions' and 'fees-action' to exclude columns by class
                      const cellsToSearch = row.querySelectorAll('td:not(.actions):not(.fees-action)'); // Exclude the actions and fees-action cells
                      cellsToSearch.forEach(cell => {
                          rowText += cell.textContent ? cell.textContent.toLowerCase() + ' ' : ''; // Add space for better matching
                      });

                     // Check if the concatenated row text contains the search term
                     if (rowText.includes(searchTerm)) {
                         row.style.display = ''; // Show the row (reset to default display)
                     } else {
                         row.style.display = 'none'; // Hide the row
                     }
                 });
             });
         }


         // --- Details Modal JS ---
         // (Modal logic remains largely the same as in the header enhancement)

         document.addEventListener('DOMContentLoaded', function() {
             // --- Initialize Client-Side Search ---
             filterTableRows('studentTable', 'studentSearchInput');
             filterTableRows('staffTable', 'staffSearchInput');


             // --- Details Modal Setup ---
             const modalOverlay = document.getElementById('detailsModalOverlay');
             const modalContent = document.getElementById('detailsModalContent'); // Although not used directly, good to reference
             const modalCloseButton = document.getElementById('detailsCloseButton');
             const modalLoading = document.getElementById('modalLoading');
             const modalDetails = document.getElementById('modalDetails');
             const modalError = document.getElementById('modalError');

             // Default avatar paths relative to the HTML file (admin/)
             const defaultStudentAvatarPath = '../assets/images/default_student_avatar.png';
             const defaultStaffAvatarPath = '../assets/images/default_staff_avatar.png';

             // Function to open the modal and fetch data
             async function openDetailsModal(type, id) {
                 console.log(`Opening modal for ${type} ID: ${id}`); // Debugging
                 // Reset modal content
                 modalDetails.innerHTML = '';
                 modalError.textContent = '';
                 modalError.classList.add('hidden'); // Hide error initially
                 modalLoading.classList.remove('hidden'); // Show loading indicator

                 // Add 'visible' class to trigger opacity/visibility transitions
                 modalOverlay.classList.add('visible'); // Show the modal overlay (now controlled by 'visible' class)


                 let fetchUrl = '';
                 let defaultAvatar = '';

                 if (type === 'student') {
                     fetchUrl = `./fetch_student_details.php?id=${id}`; // AJAX endpoint for student details
                     defaultAvatar = defaultStudentAvatarPath;
                 } else if (type === 'staff') {
                     fetchUrl = `./fetch_staff_details.php?id=${id}`; // AJAX endpoint for staff details
                     defaultAvatar = defaultStaffAvatarPath;
                 } else {
                      // Handle invalid type
                      modalLoading.classList.add('hidden');
                      modalError.textContent = 'Invalid item type.';
                      modalError.classList.remove('hidden');
                      console.error('Attempted to open modal with invalid type:', type);
                      return;
                 }

                 try {
                     const response = await fetch(fetchUrl);

                     if (!response.ok) {
                         // Log and throw error for non-2xx responses
                         const errorText = await response.text();
                         console.error(`HTTP error! status: ${response.status}`, errorText);
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }

                     const data = await response.json();
                     console.log("AJAX response data:", data); // Debugging

                     modalLoading.classList.add('hidden'); // Hide loading indicator

                     if (data.success) {
                         const item = data.data; // Use generic 'item' for student/staff data

                         // Determine photo URL
                         const photoPath = item.photo_filename ?? ''; // Use nullish coalescing
                         // Check if it's a full URL (http, https, or //). If yes, use it directly.
                         // If not a full URL but not empty, assume it's a local path and prepend '../'.
                         // If empty or null, use the default avatar path.
                         const isFullUrl = photoPath.startsWith('http://') || photoPath.startsWith('https://') || photoPath.startsWith('//');
                         const photoUrl = isFullUrl ? photoPath : (photoPath !== '' ? `../${photoPath}` : defaultAvatar);
                         // Final fallback in case default avatar path was also empty or somehow failed
                         const finalPhotoUrl = photoUrl || defaultAvatar;

                         console.log("Photo Path from DB:", item.photo_filename);
                         console.log("Using Photo URL:", finalPhotoUrl);


                         let detailsHtml = '';
                         let itemTitle = '';

                         // Populate modal content based on item type
                         if (type === 'student') {
                             itemTitle = 'Student Details';
                             detailsHtml = `
                                 <div class="flex justify-center mb-4">
                                     <img src="${htmlspecialchars(finalPhotoUrl)}" alt="${htmlspecialchars(item.full_name || 'Student')} Photo" class="w-24 h-24 rounded-full object-cover border-2 border-indigo-500">
                                 </div>
                                 <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4 text-sm text-gray-700">
                                     <strong class="text-gray-800 font-semibold">User ID:</strong><p>${htmlspecialchars(item.user_id)}</p>
                                     <strong class="text-gray-800 font-semibold">Virtual ID:</strong><p>${htmlspecialchars(item.virtual_id || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Full Name:</strong><p>${htmlspecialchars(item.full_name)}</p>
                                     <strong class="text-gray-800 font-semibold">Father's Name:</strong><p>${htmlspecialchars(item.father_name || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Mother's Name:</strong><p>${htmlspecialchars(item.mother_name || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Phone:</strong><p>${htmlspecialchars(item.phone_number || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">WhatsApp:</strong><p>${htmlspecialchars(item.whatsapp_number || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Current Class:</strong><p>${htmlspecialchars(item.current_class || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Previous Class:</strong><p>${htmlspecialchars(item.previous_class || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Previous School:</strong><p>${htmlspecialchars(item.previous_school || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Prev Marks (%):</strong><p>${htmlspecialchars(item.previous_marks_percentage || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Current Marks (%):</strong><p>${htmlspecialchars(item.current_marks || 'N/A')}</p>
                                     <!-- Display overall assigned fees (from 'students' table) -->
                                     <strong class="text-gray-800 font-semibold">Assigned Student Fees:</strong><p>₹ ${htmlspecialchars(item.student_fees ? parseFloat(item.student_fees).toFixed(2) : '0.00')}</p> <!-- Format currency -->
                                     <strong class="text-gray-800 font-semibold">Assigned Optional Fees:</strong><p>₹ ${htmlspecialchars(item.optional_fees ? parseFloat(item.optional_fees).toFixed(2) : '0.00')}</p> <!-- Format currency -->
                                     <strong class="text-gray-800 font-semibold">Address:</strong><p class="col-span-2">${nl2brJs(htmlspecialchars(item.address || 'N/A'))}</p> <!-- Use col-span-2 for address -->
                                     <strong class="text-gray-800 font-semibold">Pincode:</strong><p>${htmlspecialchars(item.pincode || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">State:</strong><p>${htmlspecialchars(item.state || 'N/A')}</p>
                                     <strong class="text-gray-800 font-semibold">Status:</strong><p>${item.is_active == 1 ? 'Active' : 'Inactive'}</p>
                                     <strong class="text-gray-800 font-semibold">Created At:</strong><p>${htmlspecialchars(item.created_at ? new Date(item.created_at).toLocaleString() : 'N/A')}</p>
                                 </div>
                             `;
                         } else if (type === 'staff') {
                             itemTitle = 'Staff Details';
                              detailsHtml = `
                                  <div class="flex justify-center mb-4">
                                       <img src="${htmlspecialchars(finalPhotoUrl)}" alt="${htmlspecialchars(item.staff_name || 'Staff')} Photo" class="w-24 h-24 rounded-full object-cover border-2 border-indigo-500">
                                  </div>
                                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-4 text-sm text-gray-700">
                                      <strong class="text-gray-800 font-semibold">Staff ID:</strong><p>${htmlspecialchars(item.staff_id)}</p>
                                      <strong class="text-gray-800 font-semibold">Unique ID:</strong><p>${htmlspecialchars(item.unique_id || 'N/A')}</p>
                                      <strong class="text-gray-800 font-semibold">Name:</strong><p>${htmlspecialchars(item.staff_name)}</p>
                                      <strong class="text-gray-800 font-semibold">Email:</strong><p>${htmlspecialchars(item.email)}</p>
                                      <strong class="text-gray-800 font-semibold">Mobile:</strong><p>${htmlspecialchars(item.mobile_number || 'N/A')}</p>
                                      <strong class="text-gray-800 font-semibold">Role:</strong><p>${htmlspecialchars(item.role ? item.role.charAt(0).toUpperCase() + item.role.slice(1) : 'N/A')}</p>
                                      <strong class="text-gray-800 font-semibold">Salary:</strong><p>₹ ${htmlspecialchars(item.salary ? parseFloat(item.salary).toFixed(2) : '0.00')}</p> <!-- Format currency -->
                                      <strong class="text-gray-800 font-semibold">Subject(s) Taught:</strong><p>${htmlspecialchars(item.subject_taught || 'N/A')}</p>
                                      <strong class="text-gray-800 font-semibold">Class(es) Taught:</strong><p>${htmlspecialchars(item.classes_taught || 'N/A')}</p>
                                      <strong class="text-gray-800 font-semibold">Created At:</strong><p>${htmlspecialchars(item.created_at ? new Date(item.created_at).toLocaleString() : 'N/A')}</p>
                                  </div>
                              `;
                         } else {
                             detailsHtml = '<p>Unknown item type.</p>';
                         }

                         // Set the modal title and content
                         modalDetails.innerHTML = `
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-800 mb-6 text-center">${itemTitle}</h3>
                            ${detailsHtml}
                         `;

                     } else {
                         // Handle API indicating failure
                         modalError.textContent = data.message || 'Failed to fetch details.';
                         modalError.classList.remove('hidden');
                         console.error('AJAX fetch returned success: false', data);
                     }

                 } catch (error) {
                     // Handle network or parsing errors
                     modalLoading.classList.add('hidden'); // Hide loading indicator
                     modalError.textContent = 'An error occurred while fetching data. Please try again.';
                     modalError.classList.remove('hidden');
                     console.error('Fetch error:', error);
                 }
             }

             // Function to close the modal
             function closeDetailsModal() {
                 console.log("Closing modal."); // Debugging
                 // Remove 'visible' class to trigger opacity/visibility transitions
                 modalOverlay.classList.remove('visible');
                 // Clear content after transition completes (adjust timeout to match CSS duration)
                 setTimeout(() => {
                     modalDetails.innerHTML = '';
                     modalError.textContent = '';
                     modalError.classList.add('hidden');
                     modalLoading.classList.add('hidden'); // Ensure loading is hidden
                 }, 300); // 300ms matches the CSS transition duration
             }

             // Add click listeners using event delegation to open the modal
             // Listen on the document body for clicks originating from clickable rows
             document.body.addEventListener('click', function(event) {
                 // Find the closest table row (<tr>) that has both data-id and data-type attributes
                 const row = event.target.closest('tr[data-id][data-type]');

                 // Check if a clickable row was clicked AND the click did NOT originate
                 // from within an action cell ('.actions') or a fee action cell ('.fees-action').
                 // This prevents the modal from opening when clicking Edit/Delete/Add Fee links.
                 const clickedCell = event.target.closest('td');
                 const isActionClick = clickedCell && (clickedCell.classList.contains('actions') || clickedCell.classList.contains('fees-action'));

                 // Also prevent modal from opening if the click was inside the right column area
                 // or the master toggle button area.
                 const isInsideRightColumn = event.target.closest('#rightColumn');
                 const isInsideMasterToggleArea = event.target.closest('#rightColumnToggleArea');


                 if (row && !isActionClick && !isInsideRightColumn && !isInsideMasterToggleArea) {
                     const id = row.dataset.id; // Get the item ID from data attribute
                     const type = row.dataset.type; // Get the item type ('student' or 'staff') from data attribute

                     // Only open modal for recognized types ('student' or 'staff')
                     if (type === 'student' || type === 'staff') {
                        openDetailsModal(type, id);
                     } else {
                         console.warn(`Clicked row has unsupported type: ${type}`);
                     }
                 }
             });


             // Add click listener to the modal close button
             if (modalCloseButton) {
                 modalCloseButton.addEventListener('click', closeDetailsModal);
             } else {
                 console.error("Modal close button #detailsCloseButton not found.");
             }

             // Add click listener to the overlay background to close the modal
             if (modalOverlay) {
                 modalOverlay.addEventListener('click', function(event) {
                     // Check if the click target is the overlay itself (not the content inside the modal)
                     if (event.target === modalOverlay) {
                         closeDetailsModal();
                     }
                 });
             } else {
                  console.error("Modal overlay #detailsModalOverlay not found.");
             }

              // Add keydown listener for the Escape key to close the modal
             document.addEventListener('keydown', function(event) {
                 // Check if the modal is currently visible before attempting to close
                 if (event.key === 'Escape' && modalOverlay.classList.contains('visible')) {
                     closeDetailsModal();
                 }
             });

             // --- Sidebar Section Toggle Logic ---
             // Get references to elements
             const eventSection = document.getElementById('eventSection');
             const eventContent = document.getElementById('eventContent');
             const otherInfoSection = document.getElementById('otherInfoSection'); // Get the section parent div
             const otherInfoContent = document.getElementById('otherInfoContent');
             const rightColumn = document.getElementById('rightColumn'); // The parent container for events/other info

             const toggleEventsButton = document.getElementById('toggleEvents');
             const toggleOtherInfoButton = document.getElementById('toggleOtherInfo');

             const iconEventsUp = document.getElementById('iconEventsUp');
             const iconEventsDown = document.getElementById('iconEventsDown');
             const iconOtherInfoUp = document.getElementById('iconOtherInfoUp');
             const iconOtherInfoDown = document.getElementById('iconOtherInfoDown');

             // Master Right Column Toggle Elements
             // Note: There are two elements with id='toggleRightColumn' in the original code.
             // We need to target them specifically if they behave differently, or use a class.
             // Assuming they should both trigger the same behavior for now, let's select by class or querySelectorAll
             // Updated HTML below will use a class or fix the duplicate ID. Let's assume fixing the ID.
             const toggleRightColumnButton = document.getElementById('toggleRightColumn'); // Ensure only one button has this ID
             const iconRightColumnShow = document.getElementById('iconRightColumnShow');
             const iconRightColumnHide = document.getElementById('iconRightColumnHide');

             // Get event list ul for dynamic scrolling
             const eventListUl = document.querySelector('#eventContent ul');
             // Get the count of events from PHP
             const eventCount = <?php echo $eventCount; ?>;
             const scrollThreshold = 5; // Number of events before scrolling is enabled

             // Check if all necessary elements exist. If not, log an error and stop JS execution for toggles.
             if (!eventContent || !otherInfoContent || !rightColumn || !toggleEventsButton || !toggleOtherInfoButton ||
                 !iconEventsUp || !iconEventsDown || !iconOtherInfoUp || !iconOtherInfoDown ||
                 !toggleRightColumnButton || !iconRightColumnShow || !iconRightColumnHide || !eventListUl) {
                console.error("Dashboard toggle elements not found or event list missing! Toggle functionality disabled.");
                // Hide the master toggle button if elements are missing
                 // Check if the element exists before hiding it
                const masterToggleButton = document.getElementById('toggleRightColumn');
                if (masterToggleButton) {
                    masterToggleButton.style.display = 'none';
                }
                return; // Stop executing toggle-related JS
            }


             // --- Dynamic Event List Scrolling ---
             // Applies scroll classes to the event list UL if the number of events exceeds the threshold
             function applyEventScrollClasses() {
                 if (eventCount > scrollThreshold) {
                     eventListUl.classList.add('max-h-80', 'overflow-y-auto', 'custom-scrollbar');
                 } else {
                     eventListUl.classList.remove('max-h-80', 'overflow-y-auto', 'custom-scrollbar');
                 }
             }


            // Function to update the visibility of the up/down icons for a section
            function updateToggleIcon(contentElement, iconUp, iconDown) {
                if (contentElement.classList.contains('hidden')) {
                    iconUp.classList.add('hidden');
                    iconDown.classList.remove('hidden');
                } else {
                    iconUp.classList.remove('hidden');
                    iconDown.classList.add('hidden');
                }
            }

             // Function to update the visibility of the show/hide icons for the master right column toggle
             function updateMasterToggleIcon() {
                 if (rightColumn.classList.contains('hidden')) {
                     iconRightColumnShow.classList.remove('hidden');
                     iconRightColumnHide.classList.add('hidden');
                 } else {
                     iconRightColumnShow.classList.add('hidden');
                     iconRightColumnHide.classList.remove('hidden');
                 }
             }


            // Function to check if the right column *should* be hidden based on its internal sections' state
            // This function *only* hides the right column if both internal sections are hidden.
            // It does NOT show the right column; showing is only done by the master toggle button.
            function maybeHideRightColumn() {
                // Check if both internal content divs are currently hidden
                const bothInternalHidden = eventContent.classList.contains('hidden') && otherInfoContent.classList.contains('hidden');

                // If both are hidden AND the right column is currently visible, then hide the right column
                if (bothInternalHidden && !rightColumn.classList.contains('hidden')) {
                     console.log("Both sections hidden, hiding right column.");
                     rightColumn.classList.add('hidden');
                     localStorage.setItem('rightColumnHidden', 'true'); // Store master state
                     updateMasterToggleIcon(); // Update master icon
                } else if (!bothInternalHidden && rightColumn.classList.contains('hidden')) {
                     // If at least one internal section is visible, but the right column *is* currently hidden,
                     // do nothing. The user must explicitly click the master toggle to show the column again.
                      console.log("One section visible, but right column is hidden. Waiting for master toggle.");
                } else {
                     // If states are consistent (both hidden and column hidden, or at least one visible and column visible)
                     console.log("Right column state is consistent.");
                }
            }

             // Function to toggle a specific section (Event or Other Info)
            function toggleSection(contentElement, iconUp, iconDown, localStorageKey) {
                 console.log(`Toggling section: ${localStorageKey}`); // Debugging
                 const isHiddenAfterToggle = contentElement.classList.toggle('hidden'); // Toggle the 'hidden' class

                 // Update the icon for this specific section
                 updateToggleIcon(contentElement, iconUp, iconDown);

                 // Store the state of this section in local storage (true if hidden, false if visible)
                 localStorage.setItem(localStorageKey, isHiddenAfterToggle);

                 // After toggling a section, check if the *entire* right column should now be hidden
                 // (i.e., if both sections are now hidden)
                 maybeHideRightColumn();
            }

            // Function to toggle the visibility of the entire right column (master toggle)
            function toggleRightColumn() {
                 console.log("Toggling right column");
                 const isHiddenAfterToggle = rightColumn.classList.toggle('hidden'); // Toggle the 'hidden' class on the main column div

                 // Update the master icon based on the new state of the right column
                 updateMasterToggleIcon();

                 // Store the master state in local storage
                 localStorage.setItem('rightColumnHidden', isHiddenAfterToggle);

                 // If the right column was just shown (is NOT hidden after toggle),
                 // restore the visibility states of its internal sections based on their stored state.
                 // If it was hidden, we don't need to do anything specific to the internal sections
                 // as the parent 'hidden' class handles their visibility.
                 if (!isHiddenAfterToggle) {
                     console.log("Right column shown, restoring internal section states.");
                     // Restore Event section state from localStorage
                     const isEventContentHidden = localStorage.getItem('eventContentHidden') === 'true';
                     if (isEventContentHidden) {
                         eventContent.classList.add('hidden');
                     } else {
                         eventContent.classList.remove('hidden');
                     }
                     updateToggleIcon(eventContent, iconEventsUp, iconEventsDown); // Update icon for event section

                     // Restore Other Info section state from localStorage
                     const isOtherInfoContentHidden = localStorage.getItem('otherInfoContentHidden') === 'true';
                      if (isOtherInfoContentHidden) {
                         otherInfoContent.classList.add('hidden');
                     } else {
                         otherInfoContent.classList.remove('hidden');
                     }
                     updateToggleIcon(otherInfoContent, iconOtherInfoUp, iconOtherInfoDown); // Update icon for other info section

                     // Ensure the internal content divs display is reset if they were force-hidden by parent
                     // eventContent.style.display = ''; // Unnecessary with 'hidden' class and flex/grid layout
                     // otherInfoContent.style.display = ''; // Unnecessary
                 } else {
                      console.log("Right column hidden.");
                       // Optional: Ensure internal sections are marked as hidden in state if the parent was hidden
                       // This helps maintain consistency, though maybeHideRightColumn handles this
                       // eventContent.classList.add('hidden'); localStorage.setItem('eventContentHidden', 'true');
                       // otherInfoContent.classList.add('hidden'); localStorage.setItem('otherInfoContentHidden', 'true');
                 }
            }


            // Add click listeners to the individual section toggle buttons
            toggleEventsButton.addEventListener('click', function(event) {
                 event.stopPropagation(); // Prevent event from bubbling up (e.g., triggering modal)
                 toggleSection(eventContent, iconEventsUp, iconEventsDown, 'eventContentHidden');
            });

            toggleOtherInfoButton.addEventListener('click', function(event) {
                 event.stopPropagation(); // Prevent event from bubbling up
                 toggleSection(otherInfoContent, iconOtherInfoUp, iconOtherInfoDown, 'otherInfoContentHidden');
            });

             // Add click listener for the master right column toggle button
             // Select the correct element by its ID. Assuming the duplicate ID is fixed in HTML.
             const masterToggleButton = document.getElementById('toggleRightColumn');
             if(masterToggleButton) {
                 masterToggleButton.addEventListener('click', function(event) {
                      event.stopPropagation(); // Prevent event from bubbling up
                      toggleRightColumn();
                 });
             } else {
                  console.error("Master toggle button #toggleRightColumn not found!");
             }


            // --- Apply Initial State on Load ---
            // Read stored states from localStorage
            const isEventContentHidden = localStorage.getItem('eventContentHidden') === 'true';
            const isOtherInfoContentHidden = localStorage.getItem('otherInfoContentHidden') === 'true';
            const isRightColumnHidden = localStorage.getItem('rightColumnHidden') === 'true';

            console.log(`Initial state from localStorage: Event Hidden=${isEventContentHidden}, Other Info Hidden=${isOtherInfoContentHidden}, Right Column Hidden=${isRightColumnHidden}`); // Debugging initial state

            // 1. Apply state for individual sections (Event/Other Info)
            // Set initial 'hidden' class and icon for Event content
            if (isEventContentHidden) {
                eventContent.classList.add('hidden');
            } else {
                 eventContent.classList.remove('hidden');
            }
            updateToggleIcon(eventContent, iconEventsUp, iconEventsDown);

            // Set initial 'hidden' class and icon for Other Info content
            if (isOtherInfoContentHidden) {
                 otherInfoContent.classList.add('hidden');
            } else {
                 otherInfoContent.classList.remove('hidden');
            }
             updateToggleIcon(otherInfoContent, iconOtherInfoUp, iconOtherInfoDown);


            // 2. Determine the initial state of the *entire* right column
            // The right column starts hidden if the master toggle state says so OR if both internal sections are hidden.
            const shouldInitiallyHideRightColumn = isRightColumnHidden || (isEventContentHidden && isOtherInfoContentHidden);

            if (shouldInitiallyHideRightColumn) {
                 rightColumn.classList.add('hidden');
                 // If we hid it because both internals were hidden but master state wasn't true,
                 // update the master state in localStorage to reflect the *current* visual state.
                 if (!isRightColumnHidden && (isEventContentHidden && isOtherInfoContentHidden)) {
                      localStorage.setItem('rightColumnHidden', 'true');
                 }
            } else {
                 rightColumn.classList.remove('hidden');
            }


             // 3. Update the master toggle icon based on the FINAL state of the right column
             updateMasterToggleIcon();

             // 4. Apply initial scroll classes to event list based on count
             applyEventScrollClasses();

             // 5. Apply saved background preference on load
             const savedBackgroundClass = localStorage.getItem('dashboardBackground');
             if (savedBackgroundClass) {
                 setBackground(savedBackgroundClass);
             } else {
                // Apply a default background if none saved (optional)
                // Ensure this default class is defined in your CSS
                 setBackground('solid-bg-gray'); // Example default
             }


        });
         // --- End Details Modal & Toggle JS ---


    </script>


    <!-- Main content wrapper -->
     <!-- This div wraps all the content below the fixed header and sidebar -->
     <!-- Add padding to account for the header and sidebar -->
     <!-- Use max-w-screen-2xl for content width and mx-auto to center -->
     <!-- Adjust padding (pt and px) as needed based on your layout and header height -->
     <!-- The sidebar padding (pl) is handled by the body.sidebar-open CSS -->
     <div class="w-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8"> <!-- Responsive padding -->
        

         <!-- Operation Message Display (From session, cleared after display) -->
         <!-- This block displays messages like success/error from previous actions -->
         <?php
         if (!empty($operation_message)) {
             // Determine message type based on keywords for styling
             $message_type_class = 'info'; // Default to info
              $msg_lower = strtolower(strip_tags($operation_message));
              if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false || strpos($msg_lower, 'welcome') !== false) { // Added welcome
                   $message_type_class = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_type_class = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos( $msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) { // Added no records found
                   $message_type_class = 'warning';
              }
             // Use Tailwind classes for message box styling
             $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
             switch ($message_type_class) {
                 case 'success': $message_classes .= "bg-green-100 border-green-300 text-green-800"; break;
                 case 'error':   $message_classes .= "bg-red-100 border-red-300 text-red-800"; break;
                 case 'warning': $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; break;
                 case 'info':
                 default:        $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; break; // Use light blue for info
             }
             echo "<div class='{$message_classes}' role='alert'>" . $operation_message . "</div>";
         }
         ?>

         <!-- Page Title -->
         <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8"><?php echo htmlspecialchars($pageTitle); ?></h1>


         <!-- Summary Cards Section -->
         <!-- Arrange cards in a responsive grid or flex layout -->
         <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
             <!-- Total Students Card -->
             <div class="flex items-center p-6 rounded-xl shadow-md border-b-4 border-blue-500 bg-white">
                  <!-- Icon placeholder -->
                 <div class="flex-shrink-0 p-3 bg-blue-100 rounded-full mr-4">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path d="M18 18v.01M12 18v.01M6 18v.01M18 14V2a3 3 0 00-3-3h-2a3 3 0 00-3 3v12m0 0l1.5 1.5M12 14l1.5 1.5M9 14l1.5 1.5M18 18v.01M12 18v.01M6 18v.01M18 14V2a3 3 0 00-3-3h-2a3 3 0 00-3 3v12m0 0l1.5 1.5M12 14l1.5 1.5M9 14l1.5 1.5z"/>
                         <path fill="#60a5fa" d="M18 18v.01M12 18v.01M6 18v.01M18 14V2a3 3 0 00-3-3h-2a3 3 0 00-3 3v12m0 0l1.5 1.5M12 14l1.5 1.5M9 14l1.5 1.5M18 18v.01M12 18v.01M6 18v.01M18 14V2a3 3 0 00-3-3h-2a3 3 0 00-3 3v12m0 0l1.5 1.5M12 14l1.5 1.5M9 14l1.5 1.5z"/>
                     </svg>
                 </div>
                 <div>
                      <h3 class="text-base font-medium text-gray-600">Total Active Students</h3>
                      <!-- Using total student count fetched separately -->
                      <p class="text-2xl font-bold text-gray-800"><?php echo $totalStudentsCount; ?></p>
                 </div>
             </div>

             <!-- Total Staff Card -->
             <div class="flex items-center p-6 rounded-xl shadow-md border-b-4 border-emerald-500 bg-white">
                 <!-- Icon placeholder -->
                 <div class="flex-shrink-0 p-3 bg-emerald-100 rounded-full mr-4">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                         <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 00-3-3.87"></path><path d="M16 3.13a4 4 0 010 7.75"></path>
                      </svg>
                 </div>
                 <div>
                      <h3 class="text-base font-medium text-gray-600">Total Staff</h3>
                      <!-- Using total staff count fetched separately -->
                      <p class="text-2xl font-bold text-gray-800"><?php echo $totalStaffCount; ?></p>
                 </div>
             </div>

             <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): // Only show financial info to admin/principal ?>
                 <!-- Students with Fees Due Card (Total count across active students) -->
                 <div class="flex items-center p-6 rounded-xl shadow-md border-b-4 border-amber-500 bg-white">
                      <!-- Icon placeholder - Coin or similar -->
                     <div class="flex-shrink-0 p-3 bg-amber-100 rounded-full mr-4">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                     </div>
                     <div>
                          <h3 class="text-base font-medium text-gray-600">Students with Fees Due</h3>
                          <p class="text-2xl font-bold text-gray-800">
                              <?php echo $studentsWithFeesDueCount; ?>
                          </p>
                           <?php // Display fetch error message if applicable ?>
                           <?php if (!empty($fetch_fees_due_count_message)) echo $fetch_fees_due_count_message; ?>
                           <!-- Add link to view students with fees due -->
                          <?php if ($studentsWithFeesDueCount > 0): ?>
                              <p class="text-xs text-gray-500 mt-1">
                                  <a href="./manage_students.php?filter=fees_due" class="text-indigo-600 hover:underline">View List</a>
                              </p>
                           <?php endif; ?>
                     </div>
                 </div>

                 <!-- Total Fee Due (Latest 5 Students) Card -->
                 <div class="flex items-center p-6 rounded-xl shadow-md border-b-4 border-purple-500 bg-white">
                      <!-- Icon placeholder - Wallet or Bill -->
                     <div class="flex-shrink-0 p-3 bg-purple-100 rounded-full mr-4">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                           </svg>
                     </div>
                     <div>
                          <h3 class="text-base font-medium text-gray-600">Total Outstanding Fee (Latest 5 Students)</h3>
                           <!-- Format as currency, e.g., ₹ -->
                          <p class="text-2xl font-bold text-gray-800">₹ <?php echo number_format($totalOutstandingFeeForLatest5Students, 2); ?></p>
                           <?php // Display fetch message/error if applicable ?>
                           <?php if (!empty($fetch_total_due_latest_5_message)): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($fetch_total_due_latest_5_message); ?></p>
                           <?php else: ?>
                                <p class="text-xs text-gray-500 mt-1">(Sum of outstanding monthly fees for displayed students)</p>
                           <?php endif; ?>
                     </div>
                 </div>
             <?php endif; ?>
         </div>

         <!-- Master Toggle Button for Right Column (Visible on smaller screens) -->
         <!-- Positioned just before the main content flex container -->
         <!-- Use a class instead of duplicate ID -->
         <div id="rightColumnToggleArea" class="w-full flex justify-center mb-4 lg:hidden"> <!-- Hide on large screens where layout is fixed -->
              <button id="toggleRightColumn" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md flex items-center text-sm font-medium transition">
                   <!-- Icon changes based on state, managed by JS -->
                  <svg id="iconRightColumnHide" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.319-.768-2.864-.428-3.42 1.085a1.532 1.532 0 01-.526 1.74c-.952.924-.928 2.419 0 3.343a1.532 1.532 0 01.526 1.74c.556 1.513 2.091 1.854 3.42 1.085a1.532 1.532 0 012.286.948c.38 1.56 2.6 1.56 2.98 0a1.532 1.532 0 012.286-.948c1.319.768 2.864.428 3.42-1.085a1.532 1.532 0 01.526-1.74c.952-.924.928-2.419 0-3.343a1.532 1.532 0 01-.526-1.74c-.556-1.513-2.091-1.854-3.42-1.085a1.532 1.532 0 01-2.286-.948zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                  </svg>
                  <svg id="iconRightColumnShow" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                   </svg>
                  <span>Toggle Right Sidebar</span>
              </button>
          </div>


         <!-- --- Layout for Tables and Events/Other Info (Two Columns on large screens) --- -->
         <!-- flex-wrap stacks columns on small screens, lg:flex-nowrap makes them side-by-side -->
         <div class="flex flex-wrap lg:flex-nowrap gap-8">

            <!-- --- Left/Main Content Column (Student/Staff Tables) --- -->
            <!-- flex-1 allows it to grow, w-full ensures full width when wrapped, lg:w-2/3 controls width on large screens -->
            <!-- Added flex-col and gap-8 to arrange sections vertically with space -->
            <div id="leftColumn" class="flex-1 w-full lg:w-2/3 min-w-[300px] flex flex-col gap-8">

                 <!-- Student Records Section -->
                 <div class="bg-white p-6 rounded-xl shadow-md">
                      <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">Latest Student Records</h2>

                      <!-- Student Search Form (Client-side search) -->
                      <div class="mb-6">
                          <label for="studentSearchInput" class="block text-gray-700 text-sm font-medium mb-1">Search Displayed Records:</label>
                          <input type="text" id="studentSearchInput" placeholder="Search by ID, name, class, phone, etc." class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                      </div>

                       <!-- Student Fetch Message -->
                      <?php
                       // Display message related to student data fetching
                        if (!empty($select_student_message)) {
                           // Determine message type
                           $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
                           $msg_lower = strtolower(strip_tags($select_student_message));
                           if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not load') !== false) {
                                $message_classes .= "bg-red-100 border-red-300 text-red-800"; // Error
                           } elseif (strpos($msg_lower, 'no student records found') !== false) {
                                $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; // Warning for no records
                           } else { // Default to info for "Displaying..." message
                               $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; // Info
                           }
                            echo "<div class='{$message_classes}' role='alert'>" . htmlspecialchars($select_student_message) . "</div>";
                       }
                      ?>

                      <?php if (!empty($students)): ?>
                          <!-- Download Student CSV Button -->
                          <!-- Show only if there are students fetched -->
                          <div class="flex justify-end mb-4">
                               <a href="./allstudentList.php?download=csv" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md font-medium text-sm transition flex-shrink-0 no-underline" title="Download CSV with all student details">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                     <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                   </svg>
                                   Download All Student CSV
                               </a>
                          </div>

                          <!-- Student Table -->
                          <div class="overflow-x-auto">
                              <table class="min-w-full divide-y divide-gray-200 data-table" id="studentTable"> <!-- Added data-table class -->
                                  <thead>
                                      <tr>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 rounded-tl-lg">Photo</th> <!-- Rounded corner -->
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">User ID</th>
                                           <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Virtual ID</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Full Name</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Phone</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Current Class</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Status</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Created At</th>
                                           <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 fees-action">Fees</th> <!-- Added fees-action class -->
                                          <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 whitespace-nowrap actions rounded-tr-lg">Actions</th> <!-- Rounded corner -->
                                      </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200">
                                      <?php
                                       $default_student_avatar_path = '../assets/images/default_student_avatar.png'; // Path relative to admin/
                                      ?>
                                      <?php foreach ($students as $student): ?>
                                          <tr class="hover:bg-gray-100 clickable-row" data-id="<?php echo htmlspecialchars($student['user_id']); ?>" data-type="student">
                                              <td class="px-4 py-2 align-top text-sm text-gray-600">
                                                  <?php
                                                      $cloudinary_url = $student['photo_filename'] ?? '';
                                                      // If it's a full URL, use it. If it's a non-empty local path, prepend '../'. Otherwise, use default.
                                                      $display_photo_url = !empty($cloudinary_url) && (strpos($cloudinary_url, 'http') === 0 || strpos($cloudinary_url, '//') === 0) ? $cloudinary_url : ($cloudinary_url !== '' ? "../" . $cloudinary_url : $default_student_avatar_path);
                                                       // Fallback again if somehow still empty
                                                       $final_display_photo_url = $display_photo_url ?: $default_student_avatar_path;
                                                  ?>
                                                  <img src="<?php echo htmlspecialchars($final_display_photo_url); ?>" alt="<?php echo htmlspecialchars($student['full_name'] . ' Photo'); ?>" class="w-10 h-10 rounded-full object-cover border border-gray-300 mx-auto">
                                              </td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($student['user_id']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($student['virtual_id'] ?? 'N/A'); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($student['full_name']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($student['current_class']); ?></td>
                                              <td class="px-4 py-3 align-top text-sm text-gray-600">
                                                 <?php
                                                      echo $student['is_active'] == 1 ? '<span class="text-green-600 font-semibold">Active</span>' : '<span class="text-red-600 font-semibold">Inactive</span>';
                                                 ?>
                                             </td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($student['created_at']))); ?></td>

                                              <!-- Column for Add Fee Link - Clickable but does not open modal -->
                                              <td class="px-4 py-3 align-top text-center fees-action">
                                                  <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): ?>
                                                       <a href="./add_monthly_fee.php?student_id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-green-600 hover:underline font-medium text-xs md:text-sm">Add Fee</a>
                                                  <?php else: ?>
                                                       <span class="text-gray-500 text-xs">N/A</span>
                                                  <?php endif; ?>
                                              </td>

                                             <!-- Actions Column - Clickable but does not open modal -->
                                             <td class="actions px-4 py-3 align-top text-center whitespace-nowrap text-xs md:text-sm">
                                                <?php // Check role for View, Edit, etc. ?>
                                                <a href="./view_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">View</a> <!-- Added View link -->
                                                <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): ?>
                                                     <a href="./edit_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">Edit</a>
                                                <?php endif; ?>
                                                <?php if ($loggedInUserRole === 'admin'): ?>
                                                     <a href="./toggle_student_status.php?id=<?php echo htmlspecialchars($student['user_id']); ?>"
                                                       class="font-medium <?php echo $student['is_active'] == 1 ? 'text-yellow-600 hover:underline' : 'text-green-600 hover:underline'; ?> mr-2"
                                                       onclick="return confirm('Are you sure you want to <?php echo $student['is_active'] == 1 ? 'DEACTIVATE' : 'ACTIVATE'; ?> this account?');"><?php echo $student['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?></a>
                                                     <a href="./delete_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-red-600 hover:underline font-medium" onclick="return confirm('Are you sure you want to DELETE this student record? This cannot be undone!');">Delete</a>
                                                <?php endif; ?>
                                            </td>
                                          </tr>
                                      <?php endforeach; ?>
                                  </tbody>
                              </table>
                          </div>
                           <!-- Link to view all students -->
                           <div class="mt-4 text-center">
                                <a href="./manage_students.php" class="text-indigo-600 hover:underline text-sm">View All Students (<?php echo $totalStudentsCount; ?> total)</a>
                           </div>
                      <?php else: ?>
                           <!-- Display if no students are found (after the message) -->
                            <?php if (strpos(strtolower(strip_tags($select_student_message)), 'no student records found') !== false && strpos(strtolower(strip_tags($select_student_message)), 'error') === false): ?>
                                <div class="text-center text-gray-600 p-4 border rounded-md bg-gray-50">No latest student records to display.</div>
                            <?php endif; ?>
                      <?php endif; ?>
                 </div>


                 <!-- Staff Records Section -->
                 <div class="bg-white p-6 rounded-xl shadow-md">
                      <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">Latest Staff Records</h2>

                      <!-- Staff Search Form -->
                      <div class="mb-6">
                          <label for="staffSearchInput" class="block text-gray-700 text-sm font-medium mb-1">Search Displayed Records:</label>
                          <input type="text" id="staffSearchInput" placeholder="Search by ID, name, role, email, etc." class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                      </div>

                       <!-- Staff Fetch Message -->
                      <?php
                      if (!empty($fetch_staff_message)) {
                           // Determine message type
                           $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
                           $msg_lower = strtolower(strip_tags($fetch_staff_message));
                           if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not load') !== false) {
                                $message_classes .= "bg-red-100 border-red-300 text-red-800"; // Error
                           } elseif (strpos($msg_lower, 'no staff records found') !== false) {
                                $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; // Warning for no records
                           } else { // Default to info for "Displaying..." message
                               $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; // Info
                           }
                            echo "<div class='{$message_classes}' role='alert'>" . htmlspecialchars($fetch_staff_message) . "</div>";
                       }
                      ?>

                      <?php if (!empty($staff_members)): ?>
                           <!-- Download Staff CSV Button -->
                           <!-- Show only if there is staff fetched -->
                           <div class="flex justify-end mb-4">
                               <a href="./manage_staff.php?download=csv" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md font-medium text-sm transition flex-shrink-0 no-underline" title="Download CSV with all staff details">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                     <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                   </svg>
                                   Download All Staff CSV
                               </a>
                           </div>

                          <!-- Staff Table -->
                          <div class="overflow-x-auto">
                              <table class="min-w-full divide-y divide-gray-200 data-table" id="staffTable"> <!-- Added data-table class -->
                                  <thead>
                                      <tr>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 rounded-tl-lg">Photo</th> <!-- Rounded corner -->
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Staff ID</th>
                                           <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Unique ID</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Name</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Role</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Mobile</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Email</th>
                                          <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Created At</th>
                                          <th scope="col" class="actions px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 whitespace-nowrap rounded-tr-lg">Actions</th> <!-- Rounded corner, added actions class -->
                                      </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200">
                                     <?php
                                      $default_staff_avatar_path = '../assets/images/default_staff_avatar.png'; // Path relative to admin/
                                     ?>
                                      <?php foreach ($staff_members as $staff): ?>
                                          <tr class="hover:bg-gray-100 clickable-row" data-id="<?php echo htmlspecialchars($staff['staff_id']); ?>" data-type="staff">
                                              <td class="px-4 py-2 align-top text-sm text-gray-600">
                                                  <?php
                                                      $cloudinary_url = $staff['photo_filename'] ?? '';
                                                       // If it's a full URL, use it. If it's a non-empty local path, prepend '../'. Otherwise, use default.
                                                       $display_photo_url = !empty($cloudinary_url) && (strpos($cloudinary_url, 'http') === 0 || strpos($cloudinary_url, '//') === 0) ? $cloudinary_url : ($cloudinary_url !== '' ? "../" . $cloudinary_url : $default_staff_avatar_path);
                                                        // Fallback again if somehow still empty
                                                        $final_display_photo_url = $display_photo_url ?: $default_staff_avatar_path;
                                                  ?>
                                                  <img src="<?php echo htmlspecialchars($final_display_photo_url); ?>" alt="<?php echo htmlspecialchars($staff['staff_name'] . ' Photo'); ?>" class="w-10 h-10 rounded-full object-cover border border-gray-300 mx-auto">
                                              </td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($staff['unique_id']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($staff['staff_name']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars(ucfirst($staff['role'])); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($staff['mobile_number']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars($staff['email']); ?></td>
                                             <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($staff['created_at']))); ?></td>
                                              <!-- Actions Column - Clickable but does not open modal -->
                                              <td class="actions px-4 py-3 align-top text-center whitespace-nowrap text-xs md:text-sm">
                                                 <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): ?>
                                                      <a href="./view_staff.php?id=<?php echo htmlspecialchars($staff['staff_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">View</a> <!-- Added View link -->
                                                     <a href="./edit_staff.php?id=<?php echo htmlspecialchars($staff['staff_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">Edit</a>
                                                     <a href="./assign_staff_timetable.php?staff_id=<?php echo htmlspecialchars($staff['staff_id']); ?>" class="text-indigo-600 hover:underline font-medium mr-2">Timetable</a>
                                                 <?php endif; ?>
                                                 <?php if ($loggedInUserRole === 'admin'): ?>
                                                     <a href="./delete_staff.php?id=<?php echo htmlspecialchars($staff['staff_id']); ?>" class="text-red-600 hover:underline font-medium" onclick="return confirm('Are you sure you want to delete this staff record?');">Delete</a>
                                                 <?php endif; ?>
                                             </td>
                                          </tr>
                                      <?php endforeach; ?>
                                  </tbody>
                              </table>
                          </div>
                          <!-- Link to view all staff -->
                          <div class="mt-4 text-center">
                              <a href="./manage_staff.php" class="text-indigo-600 hover:underline text-sm">View All Staff (<?php echo $totalStaffCount; ?> total)</a>
                          </div>
                      <?php else: ?>
                           <!-- Display if no staff are found (after the message) -->
                            <?php if (strpos(strtolower(strip_tags($fetch_staff_message)), 'no staff records found') !== false && strpos(strtolower(strip_tags($fetch_staff_message)), 'error') === false): ?>
                                <div class="text-center text-gray-600 p-4 border rounded-md bg-gray-50">No latest staff records to display.</div>
                            <?php endif; ?>
                      <?php endif; ?>
                 </div>

                 <!-- Placeholder for Students Chart Card -->
                 <!-- Integrate this if you plan to add charting library -->
                 <!--
                  <div class="bg-white p-6 rounded-xl shadow-md text-center">
                      <h2 class="text-xl font-semibold text-gray-800 mb-4">Student Distribution</h2>
                       <div class="donut-chart-container">
                           <img src="placeholder_donut_chart.png" alt="Student Distribution Placeholder" class="w-32 h-32"> // Replace with actual chart
                       </div>
                        <p class="text-sm text-gray-600 mt-4">
                            Placeholder for Male/Female or Class-wise distribution chart.
                        </p>
                  </div>
                 -->


            </div> <!-- End Left/Main Content Column -->


            <!-- --- Right/Event/Other Info Column --- -->
            <!-- w-full ensures full width when stacked, lg:w-1/3 controls width on large screens -->
            <!-- Added flex-col and gap-8 to arrange sections vertically with space -->
            <!-- Added ID for JS toggling -->
             <!-- The 'hidden' class can be applied here initially by JS if needed -->
            <div id="rightColumn" class="w-full lg:w-1/3 min-w-[250px] max-w-[400px] flex flex-col gap-8">

                 <!-- Event Announcements Section -->
                 <!-- Added ID for JS toggling -->
                 <div id="eventSection" class="bg-white p-6 rounded-xl shadow-md flex flex-col">
                     <!-- Header with title and toggle button -->
                     <div class="flex justify-between items-center mb-4"> <!-- Added mb-4 -->
                        <h2 class="text-xl font-semibold text-gray-800">Event Announcements</h2>
                         <!-- Toggle button -->
                         <button id="toggleEvents" class="text-gray-500 hover:text-gray-700 focus:outline-none p-1 rounded-full hover:bg-gray-100" aria-label="Toggle events section"> <!-- Added padding and hover effect -->
                             <!-- Minus icon (managed by JS) -->
                             <svg id="iconEventsUp" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                             </svg>
                             <!-- Plus icon (managed by JS) -->
                              <svg id="iconEventsDown" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                             </svg>
                         </button>
                     </div>

                     <!-- Content wrapper for events - this div is toggled -->
                     <!-- Added ID for JS toggling -->
                     <div id="eventContent">
                          <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): // Only authorized roles can add events ?>
                             <a href="./create_event.php" class="block text-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm flex-shrink-0 mb-4 no-underline transition">
                                 Add New Event
                             </a>
                         <?php endif; ?>

                      <!-- Event Fetch Message (Display inside content div) -->
                     <?php
                      if (!empty($fetch_events_message)) {
                           // Determine message type
                           $message_classes = "p-3 rounded-md border text-center text-sm mb-4 ";
                           $msg_lower = strtolower(strip_tags($fetch_events_message));
                           if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not load') !== false) {
                                $message_classes .= "bg-red-100 border-red-300 text-red-800"; // Error
                           } elseif (strpos($msg_lower, 'no event announcements found') !== false || strpos( $msg_lower, 'no event announcements found in the last 30 days') !== false) {
                                $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; // warning
                           } else { // Default to info for "Displaying..." message
                               $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; // info
                           }
                            echo "<div class='{$message_classes}' role='alert'>" . htmlspecialchars($fetch_events_message) . "</div>";
                       }
                     ?>

                     <?php if (!empty($events)): ?>
                         <!-- UL content for events - max-height and overflow classes applied by JS -->
                         <!-- Added ID for JS scroll logic -->
                         <ul id="eventListUl" class="list-none p-0 m-0 divide-y divide-gray-200">
                             <?php foreach ($events as $event): ?>
                                 <li class="py-4">
                                     <h4 class="font-semibold text-gray-800 mb-1"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                     <!-- Display date/time with a coloured badge -->
                                     <div class="text-xs text-gray-600 mb-2 flex items-center">
                                         <span class="px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full font-medium mr-2">
                                            <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($event['event_date_time']))); ?>
                                         </span>
                                          <?php if (!empty($event['created_by_name'])): ?>
                                               <span class="text-gray-500">by <?php echo htmlspecialchars($event['created_by_name']); ?></span>
                                          <?php endif; ?>
                                          <!-- Removed "Posted On" date here for brevity, can add back if desired -->
                                     </div>
                                     <p class="text-sm text-gray-700 mb-2 leading-normal break-words"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></p> <!-- Use nl2br for description -->
                                     <div class="text-xs text-gray-500 event-meta">
                                          <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): // Add edit/delete links for admin/principal ?>
                                               <a href="./edit_event.php?id=<?php echo htmlspecialchars($event['event_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">Edit</a>
                                          <?php endif; ?>
                                          <?php if ($loggedInUserRole === 'admin'): // Only admin can delete ?>
                                              <a href="./delete_event.php?id=<?php echo htmlspecialchars($event['event_id']); ?>" class="text-red-600 hover:underline font-medium" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                                          <?php endif; ?>
                                     </div>
                                 </li>
                             <?php endforeach; ?>
                         </ul>
                          <?php else: ?>
                              <!-- Display if no events are found (after the message) -->
                               <?php if (strpos(strtolower(strip_tags($fetch_events_message)), 'no event announcements found') !== false && strpos(strtolower(strip_tags($fetch_events_message)), 'error') === false): ?>
                                    <div class="text-center text-gray-600 p-4 border rounded-md bg-gray-50">No recent event announcements.</div>
                               <?php endif; ?>
                          <?php endif; ?>
                     </div> <!-- End Event Content Wrapper -->
                 </div> <!-- End Event Announcements Section -->

                 <!-- --- New Section Below Events (Other Information) --- -->
                 <!-- Added ID for JS toggling -->
                 <div id="otherInfoSection" class="bg-white p-6 rounded-xl shadow-md flex flex-col">
                      <!-- Header with title and toggle button -->
                     <div class="flex justify-between items-center mb-4"> <!-- Added mb-4 -->
                        <h2 class="text-xl font-semibold text-gray-800">Other Information</h2>
                         <!-- Toggle button -->
                          <button id="toggleOtherInfo" class="text-gray-500 hover:text-gray-700 focus:outline-none p-1 rounded-full hover:bg-gray-100" aria-label="Toggle other information section"> <!-- Added padding and hover effect -->
                             <!-- Minus icon (managed by JS) -->
                             <svg id="iconOtherInfoUp" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                             </svg>
                             <!-- Plus icon (managed by JS) -->
                              <svg id="iconOtherInfoDown" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                             </svg>
                         </button>
                     </div>
                     <!-- Content wrapper for other info - this div is toggled -->
                     <!-- Added ID for JS toggling -->
                     <div id="otherInfoContent">
                         <p class="text-gray-700 text-sm leading-normal break-words mb-4">
                             This area can be used to display quick links, important messages,
                             a mini-gallery preview, upcoming tasks, or any other relevant
                             information for the admin/principal.
                         </p>
                         <ul class="list-disc list-inside text-sm text-blue-600">
                             <li><a href="#" class="hover:underline">View Monthly Reports</a></li>
                             <li><a href="#" class="hover:underline">Check System Logs</a></li>
                             <li><a href="#" class="hover:underline">Update School Profile</a></li>
                             <?php if ($loggedInUserRole === 'admin'): ?>
                                 <li><a href="#" class="hover:underline text-red-600">Perform Database Backup</a></li>
                             <?php endif; ?>
                         </ul>
                         <!-- Placeholder for Image Layout (Example) -->
                         <!-- Integrate this if you want a gallery preview -->
                         <!--
                         <div class="mt-4 grid grid-cols-2 gap-2">
                             <img src="../assets/images/placeholder1.png" alt="Gallery Image 1" class="w-full h-auto object-cover rounded-md">
                             <img src="../assets/images/placeholder2.png" alt="Gallery Image 2" class="w-full h-auto object-cover rounded-md">
                         </div>
                         -->
                     </div> <!-- End Other Info Content Wrapper -->
                 </div> <!-- End New Section -->

            </div> <!-- End Right Column -->


         </div> <!-- End dashboard-layout for tables and events -->


         <!-- Background Changer Buttons -->
         <!-- Placed towards the bottom, centered -->
         <div class="mt-8 text-center text-sm text-gray-700 pb-8">
              <span class="font-medium mr-2">Choose Background:</span>
              <!-- Added hover effects and slight styling adjustments -->
             <button class="ml-2 px-3 py-1 border rounded-md text-white text-xs font-medium gradient-background-blue-cyan hover:opacity-90 transition" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
             <button class="ml-2 px-3 py-1 border rounded-md text-white text-xs font-medium gradient-background-purple-pink hover:opacity-90 transition" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
              <button class="ml-2 px-3 py-1 border rounded-md text-white text-xs font-medium gradient-background-green-teal hover:opacity-90 transition" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
              <button class="ml-2 px-3 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs font-medium transition" onclick="setBackground('solid-bg-gray')">Gray</button>
              <button class="ml-2 px-3 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-medium transition" onclick="setBackground('solid-bg-indigo')">Indigo</button>
         </div>


     </div> <!-- End main content wrapper -->

     <!-- --- Staff/Student Details Modal HTML Structure (Initially hidden) --- -->
     <!-- This modal is used to display full details when a table row is clicked. -->
     <!-- Converted modal styles to inline Tailwind classes or specific CSS classes defined above -->
     <div id="detailsModalOverlay" class="modal-overlay fixed inset-0 bg-black bg-opacity-75 z-40 flex justify-center items-center p-4 overflow-y-auto">
         <div id="detailsModalContent" class="modal-content bg-white p-8 rounded-xl shadow-xl max-w-lg w-full relative"> <!-- Increased max-w -->
             <button id="detailsCloseButton" class="absolute top-4 right-4 bg-transparent border-none text-2xl cursor-pointer text-gray-500 hover:text-gray-700 focus:outline-none" aria-label="Close modal">
                 &times; <!-- Using HTML entity for 'x' -->
             </button>
             <div id="modalLoading" class="modal-loading hidden text-center text-lg text-gray-600">Loading details...</div>
             <div id="modalError" class="modal-error hidden p-3 rounded-md border border-red-300 bg-red-100 text-red-800 text-base text-center"></div>
             <div id="modalDetails" class="modal-details">
                 <!-- Details will be populated here by JavaScript -->
             </div>
         </div>
     </div>


<?php
// Include the footer file - This will close the body and html tags
require_once "./admin_footer.php";
?>