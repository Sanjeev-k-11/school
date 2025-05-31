<?php
// School/admin/manage_students.php

// Start the session
session_start();

// Include the database configuration
require_once "../config.php";

// Check if user is logged in and is ADMIN or Principal
// Redirect to login if not authorized
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can access this page.</p>";
    header("location: ../login.php");
    exit;
}

// Set the page title *before* including the header
$pageTitle = "Manage Student Records";

// Get user role for conditional display
$loggedInUserRole = $_SESSION['role'] ?? 'guest';

// --- Variables for Messages ---
$operation_message = ""; // For messages from other pages (e.g., successful save/delete)
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}

// Message specific to data fetch
$fetch_students_message = "";


// --- Pagination Setup ---
$recordsPerPage = 25; // You can change this
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// --- Filtering ---
$isFeesDueFilter = isset($_GET['filter']) && $_GET['filter'] === 'fees_due';

if ($isFeesDueFilter) {
    $pageTitle = "Students with Fees Due"; // Update title for filtered view
}

// --- SQL Query Construction (Dynamic based on filter) ---

// Base SELECT columns
$sql_select_columns = "s.user_id, s.virtual_id, s.full_name, s.phone_number, s.current_class, s.is_active, s.created_at, s.photo_filename";

// Base FROM clause
$sql_from_clause = "FROM students s";

// Base WHERE clause (e.g., only active students by default)
$sql_where_clause = "WHERE s.is_active = 1"; // Or remove WHERE if you want to show all students by default

// Initialize GROUP BY and HAVING clauses
$sql_group_by_clause = "";
$sql_having_clause = "";

// Add join and conditions for the fees_due filter
if ($isFeesDueFilter) {
    // Add SUM of outstanding fee to selected columns
    $sql_select_columns .= ", SUM(smf.amount_due - smf.amount_paid) AS total_outstanding_fee";

    // Join with student_monthly_fees table
    $sql_from_clause .= " JOIN student_monthly_fees smf ON s.user_id = smf.student_id";

    // Modify WHERE clause to include the fee condition
    // Use an explicit AND condition to combine with s.is_active
    $sql_where_clause .= " AND smf.amount_due > smf.amount_paid"; // Only consider fee records with outstanding balance

    // Group by student ID to sum fees per student
    $sql_group_by_clause = "GROUP BY s.user_id";

    // Filter the grouped results to only include students with a positive total outstanding fee
    $sql_having_clause = "HAVING SUM(smf.amount_due - smf.amount_paid) > 0";
}

// ORDER BY clause
$sql_order_by_clause = "ORDER BY s.created_at DESC"; // Default order

// --- Get Total Records Count (for pagination) ---
// This query needs to count based on the same filters/joins as the main query
$sql_count = "SELECT COUNT(DISTINCT s.user_id) AS total_records $sql_from_clause $sql_where_clause $sql_group_by_clause $sql_having_clause";

$totalRecords = 0;
if ($result_count = mysqli_query($link, $sql_count)) {
    if ($row_count = mysqli_fetch_assoc($result_count)) {
        $totalRecords = $row_count['total_records'];
    }
    mysqli_free_result($result_count);
} else {
    $fetch_students_message = "<p class='text-red-600'>Error fetching total record count: " . htmlspecialchars(mysqli_error($link)) . "</p>";
    error_log("Manage Students count query failed: " . mysqli_error($link));
}

$totalPages = ceil($totalRecords / $recordsPerPage);

// --- Get Students for Current Page ---
// Construct the final data query string with pagination limits
$final_sql_query = "SELECT $sql_select_columns $sql_from_clause $sql_where_clause $sql_group_by_clause $sql_having_clause $sql_order_by_clause LIMIT $recordsPerPage OFFSET $offset";

$students = []; // Array to hold student data for the current page
if ($stmt = mysqli_prepare($link, $final_sql_query)) {
    // No parameters to bind in this current dynamic structure, but add if needed later
    // e.g., if you added a search filter with parameters.

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $students = mysqli_fetch_all($result, MYSQLI_ASSOC);
            if (count($students) > 0) {
                 if ($isFeesDueFilter) {
                     $fetch_students_message = "Displaying " . count($students) . " student(s) with fees due on this page.";
                 } else {
                     $fetch_students_message = "Displaying " . count($students) . " student record(s) on this page.";
                 }
            } else {
                if ($isFeesDueFilter) {
                    $fetch_students_message = "No students found with fees due.";
                } else {
                    $fetch_students_message = "No student records found.";
                }
            }
            mysqli_free_result($result);
        } else {
            $fetch_students_message = "<p class='text-red-600'>Error getting result set: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
             error_log("Manage Students get_result failed: " . mysqli_stmt_error($stmt));
        }
    } else {
        $fetch_students_message = "<p class='text-red-600'>Error executing query: " . htmlspecialchars(mysqli_stmt_error($stmt)) . "</p>";
         error_log("Manage Students query failed: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
} else {
    $fetch_students_message = "<p class='text-red-600'>Error preparing statement: " . htmlspecialchars(mysqli_error($link)) . "</p>";
     error_log("Manage Students prepare statement failed: " . mysqli_error($link));
}

// Close database connection
mysqli_close($link);

?>

<?php
// Include the header file. This contains the opening HTML, HEAD, and the fixed header bar.
require_once "./admin_header.php";
?>

    <!-- Custom styles specific to this page -->
     <style>
         body {
             padding-top: 4.5rem; /* Adjust based on header height */
             transition: padding-left 0.3s ease;
         }
         body.sidebar-open {
             padding-left: 16rem; /* Adjust based on sidebar width */
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
     </style>

     <!-- Custom JavaScript specific to this page -->
     <script>
         // Assuming htmlspecialchars and nl2brJs are defined in admin_header.php or a global script

         // --- Client-Side Search JavaScript ---
         // Function to filter table rows based on search term
         function filterTableRows(tableId, searchInputId) {
             const searchInput = document.getElementById(searchInputId);
             const tableBody = document.querySelector(`#${tableId} tbody`);

             if (!searchInput || !tableBody) {
                 console.warn(`Search elements not found for table "${tableId}" and input "${searchInputId}". Skipping search setup.`);
                 return;
             }

             searchInput.addEventListener('input', function() {
                 const searchTerm = this.value.toLowerCase().trim();
                 const rows = tableBody.querySelectorAll('tr');

                 rows.forEach(row => {
                     let rowText = '';
                      // Concatenate text content of all cells in the row (excluding specific columns)
                      const cellsToSearch = row.querySelectorAll('td:not(.actions):not(.fees-action)'); // Exclude action cells
                      cellsToSearch.forEach(cell => {
                          rowText += cell.textContent ? cell.textContent.toLowerCase() + ' ' : '';
                      });

                     if (rowText.includes(searchTerm)) {
                         row.style.display = '';
                     } else {
                         row.style.display = 'none';
                     }
                 });
             });
         }


         // --- Details Modal JS (Adapted from dashboard) ---
         document.addEventListener('DOMContentLoaded', function() {
              // --- Initialize Client-Side Search ---
              filterTableRows('studentTable', 'studentSearchInput');


             // --- Details Modal Setup ---
             const modalOverlay = document.getElementById('detailsModalOverlay');
             const modalCloseButton = document.getElementById('detailsCloseButton');
             const modalLoading = document.getElementById('modalLoading');
             const modalDetails = document.getElementById('modalDetails');
             const modalError = document.getElementById('modalError');

             // Default student avatar path relative to the modal's location (admin/)
             const defaultStudentAvatarPath = '../assets/images/default_student_avatar.png';


             // Function to open the modal and fetch student data
             async function openDetailsModal(studentId) {
                 console.log(`Opening modal for Student ID: ${studentId}`); // Debugging
                 // Reset modal content
                 modalDetails.innerHTML = '';
                 modalError.textContent = '';
                 modalError.classList.add('hidden'); // Hide error initially
                 modalLoading.classList.remove('hidden'); // Show loading indicator

                 // Add 'visible' class to trigger opacity/visibility transitions
                 modalOverlay.classList.add('visible');


                 const fetchUrl = `./fetch_student_details.php?id=${studentId}`; // AJAX endpoint for student details

                 try {
                     const response = await fetch(fetchUrl);

                     if (!response.ok) {
                         const errorText = await response.text();
                         console.error(`HTTP error! status: ${response.status}`, errorText);
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }

                     const data = await response.json();
                     console.log("AJAX response data:", data); // Debugging

                     modalLoading.classList.add('hidden'); // Hide loading indicator

                     if (data.success) {
                         const item = data.data; // Student data

                         // Determine photo URL
                         const photoPath = item.photo_filename ?? ''; // Use nullish coalescing
                         const isFullUrl = photoPath.startsWith('http://') || photoPath.startsWith('https://') || photoPath.startsWith('//');
                         const photoUrl = isFullUrl ? photoPath : (photoPath !== '' ? `../${photoPath}` : defaultStudentAvatarPath);
                         const finalPhotoUrl = photoUrl || defaultStudentAvatarPath; // Final fallback


                         let detailsHtml = '';
                         let itemTitle = 'Student Details';

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


                         // Set the modal title and content
                         modalDetails.innerHTML = `
                            <h3 class="text-xl sm:text-2xl font-bold text-gray-800 mb-6 text-center">${itemTitle}</h3>
                            ${detailsHtml}
                         `;

                     } else {
                         // Handle API indicating failure
                         modalError.textContent = data.message || 'Failed to fetch student details.';
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

             // Add click listeners using event delegation to open the modal for student rows
             document.querySelector('#studentTable tbody').addEventListener('click', function(event) {
                 // Find the closest table row (<tr>) that has a data-id attribute
                 const row = event.target.closest('tr[data-id]');

                 // Check if a clickable row was clicked AND the click did NOT originate
                 // from within an action cell ('.actions') or a fee action cell ('.fees-action').
                 const clickedCell = event.target.closest('td');
                 const isActionClick = clickedCell && (clickedCell.classList.contains('actions') || clickedCell.classList.contains('fees-action'));

                 if (row && !isActionClick) {
                     const studentId = row.dataset.id; // Get the student ID from data attribute
                     openDetailsModal(studentId);
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
                     // Check if the click target is the overlay itself
                     if (event.target === modalOverlay) {
                         closeDetailsModal();
                     }
                 });
             } else {
                  console.error("Modal overlay #detailsModalOverlay not found.");
             }

              // Add keydown listener for the Escape key to close the modal
             document.addEventListener('keydown', function(event) {
                 if (event.key === 'Escape' && modalOverlay.classList.contains('visible')) {
                     closeDetailsModal();
                 }
             });
         });
         // --- End Details Modal JS ---

     </script>

    <!-- Main content wrapper -->
     <div class="w-full max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

         <!-- Operation Message Display -->
         <?php
         if (!empty($operation_message)) {
             $message_type_class = 'info';
             $msg_lower = strtolower(strip_tags($operation_message));
             if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'activated') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                  $message_type_class = 'success';
             } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                  $message_type_class = 'error';
             } elseif (strpos($msg_lower, 'warning') !== false || strpos( $msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) {
                  $message_type_class = 'warning';
             }
             $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
             switch ($message_type_class) {
                 case 'success': $message_classes .= "bg-green-100 border-green-300 text-green-800"; break;
                 case 'error':   $message_classes .= "bg-red-100 border-red-300 text-red-800"; break;
                 case 'warning': $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; break;
                 case 'info':
                 default:        $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; break;
             }
             echo "<div class='{$message_classes}' role='alert'>" . $operation_message . "</div>";
         }
         ?>

         <!-- Page Title -->
         <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-6"><?php echo htmlspecialchars($pageTitle); ?></h1>

         <!-- Options/Filter Area -->
         <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
             <!-- Link to Add New Student -->
              <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): ?>
                 <a href="./create_student.php" class="inline-flex items-center bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline text-sm transition flex-shrink-0 no-underline">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                     </svg>
                     Add New Student
                 </a>
             <?php endif; ?>

              <!-- Filter/Search Group -->
              <div class="flex flex-wrap items-center gap-4 w-full sm:w-auto">
                  <!-- Filter for Fees Due -->
                   <?php if (!$isFeesDueFilter): ?>
                       <a href="./manage_students.php?filter=fees_due" class="text-amber-600 hover:underline font-medium text-sm transition flex-shrink-0">
                           View Students with Fees Due (<?php echo $studentsWithFeesDueCount; ?>)
                       </a>
                   <?php else: ?>
                       <a href="./manage_students.php" class="text-indigo-600 hover:underline font-medium text-sm transition flex-shrink-0">
                           Show All Students
                       </a>
                   <?php endif; ?>


                  <!-- Search Input (Client-side) -->
                  <div class="flex-grow w-full sm:w-auto">
                      <label for="studentSearchInput" class="sr-only">Search table</label>
                      <input type="text" id="studentSearchInput" placeholder="Search displayed records..." class="shadow-sm appearance-none border border-gray-300 rounded-md w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring focus:ring-indigo-200 focus:ring-opacity-50 focus:border-indigo-500 text-sm">
                  </div>
              </div>
         </div>


         <!-- Fetch Message Display -->
         <?php
          if (!empty($fetch_students_message)) {
              // Determine message type
              $message_classes = "p-3 rounded-md border mb-6 text-center text-sm ";
              $msg_lower = strtolower(strip_tags($fetch_students_message));
              if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_classes .= "bg-red-100 border-red-300 text-red-800"; // Error
              } elseif (strpos($msg_lower, 'no students found') !== false) {
                   $message_classes .= "bg-yellow-100 border-yellow-300 text-yellow-800"; // Warning for no records
              } else { // Default to info for "Displaying..." message
                  $message_classes .= "bg-blue-100 border-blue-300 text-blue-800"; // Info
              }
               echo "<div class='{$message_classes}' role='alert'>" . htmlspecialchars($fetch_students_message) . "</div>";
          }
         ?>


         <?php if (!empty($students)): ?>
             <!-- Download Student CSV Button (Maybe adjust to download filtered list if applicable) -->
             <div class="flex justify-end mb-4">
                  <a href="./allstudentList.php?download=csv<?php echo $isFeesDueFilter ? '&filter=fees_due' : ''; ?>" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md font-medium text-sm transition flex-shrink-0 no-underline" title="Download CSV with <?php echo $isFeesDueFilter ? 'students with fees due' : 'all students'; ?> details">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                      Download CSV
                  </a>
             </div>

             <!-- Student Table -->
             <div class="overflow-x-auto bg-white rounded-xl shadow-md p-6">
                 <table class="min-w-full divide-y divide-gray-200 data-table" id="studentTable">
                     <thead>
                         <tr>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 rounded-tl-lg">Photo</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">User ID</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Virtual ID</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Full Name</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Phone</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Current Class</th>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Status</th>
                              <?php if ($isFeesDueFilter): // Add column only if filter is active ?>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 text-amber-800">Outstanding Fee</th>
                             <?php endif; ?>
                             <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50">Created At</th>
                             <th scope="col" class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 fees-action">Fees</th>
                             <th scope="col" class="actions px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider bg-blue-50 whitespace-nowrap rounded-tr-lg">Actions</th>
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
                                           $display_photo_url = !empty($cloudinary_url) && (strpos($cloudinary_url, 'http') === 0 || strpos($cloudinary_url, '//') === 0) ? $cloudinary_url : ($cloudinary_url !== '' ? "../" . $cloudinary_url : $default_student_avatar_path);
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
                                  <?php if ($isFeesDueFilter): // Display fee only if filter is active ?>
                                     <td class="px-4 py-3 align-top text-sm text-gray-800 font-semibold">
                                         ₹ <?php echo htmlspecialchars(number_format($student['total_outstanding_fee'], 2)); ?>
                                     </td>
                                  <?php endif; ?>
                                 <td class="px-4 py-3 align-top text-sm text-gray-600"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($student['created_at']))); ?></td>
                                  <!-- Column for Add Fee Link -->
                                  <td class="px-4 py-3 align-top text-center fees-action">
                                       <?php if ($loggedInUserRole === 'admin' || $loggedInUserRole === 'principal'): ?>
                                            <a href="./add_monthly_fee.php?student_id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-green-600 hover:underline font-medium text-xs md:text-sm">Add Fee</a>
                                       <?php else: ?>
                                            <span class="text-gray-500 text-xs">N/A</span>
                                       <?php endif; ?>
                                   </td>
                                 <!-- Actions Column -->
                                 <td class="actions px-4 py-3 align-top text-center whitespace-nowrap text-xs md:text-sm">
                                     <a href="./view_student.php?id=<?php echo htmlspecialchars($student['user_id']); ?>" class="text-blue-600 hover:underline font-medium mr-2">View</a>
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

              <!-- Pagination Links -->
              <div class="mt-6 flex flex-wrap justify-between items-center text-sm text-gray-700">
                   <div>
                       Showing <?php echo min($offset + 1, $totalRecords); ?> to <?php echo min($offset + $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> records.
                   </div>
                   <nav class="flex items-center space-x-1">
                       <?php if ($currentPage > 1): ?>
                           <a href="?page=<?php echo $currentPage - 1; ?><?php echo $isFeesDueFilter ? '&filter=fees_due' : ''; ?>" class="px-3 py-1 border rounded-md hover:bg-gray-200 transition">Previous</a>
                       <?php else: ?>
                           <span class="px-3 py-1 border rounded-md text-gray-400 cursor-not-allowed">Previous</span>
                       <?php endif; ?>

                       <?php
                       // Basic pagination links - You can enhance this to show more pages
                       $startPage = max(1, $currentPage - 2);
                       $endPage = min($totalPages, $currentPage + 2);

                       if ($startPage > 1) {
                           echo '<a href="?page=1' . ($isFeesDueFilter ? '&filter=fees_due' : '') . '" class="px-3 py-1 border rounded-md hover:bg-gray-200 transition">1</a>';
                           if ($startPage > 2) {
                               echo '<span class="px-3 py-1">...</span>';
                           }
                       }

                       for ($i = $startPage; $i <= $endPage; $i++) {
                           $activeClass = ($i === $currentPage) ? 'bg-indigo-500 text-white border-indigo-500' : 'hover:bg-gray-200';
                           echo '<a href="?page=' . $i . ($isFeesDueFilter ? '&filter=fees_due' : '') . '" class="px-3 py-1 border rounded-md transition ' . $activeClass . '">' . $i . '</a>';
                       }

                       if ($endPage < $totalPages) {
                           if ($endPage < $totalPages - 1) {
                               echo '<span class="px-3 py-1">...</span>';
                           }
                           echo '<a href="?page=' . $totalPages . ($isFeesDueFilter ? '&filter=fees_due' : '') . '" class="px-3 py-1 border rounded-md hover:bg-gray-200 transition">' . $totalPages . '</a>';
                       }
                       ?>

                       <?php if ($currentPage < $totalPages): ?>
                           <a href="?page=<?php echo $currentPage + 1; ?><?php echo $isFeesDueFilter ? '&filter=fees_due' : ''; ?>" class="px-3 py-1 border rounded-md hover:bg-gray-200 transition">Next</a>
                       <?php else: ?>
                           <span class="px-3 py-1 border rounded-md text-gray-400 cursor-not-allowed">Next</span>
                       <?php endif; ?>
                   </nav>
              </div>

         <?php else: ?>
             <!-- Display if no students are found -->
              <?php if (strpos(strtolower(strip_tags($fetch_students_message)), 'no students found') !== false && strpos(strtolower(strip_tags($fetch_students_message)), 'error') === false): ?>
                  <div class="text-center text-gray-600 p-4 border rounded-md bg-gray-50">
                      <?php echo htmlspecialchars($fetch_students_message); ?>
                  </div>
              <?php endif; ?>
         <?php endif; ?>

     </div> <!-- End main content wrapper -->

     <!-- --- Staff/Student Details Modal HTML Structure (Initially hidden) --- -->
     <!-- This modal is used to display full details when a table row is clicked. -->
     <div id="detailsModalOverlay" class="modal-overlay fixed inset-0 bg-black bg-opacity-75 z-40 flex justify-center items-center p-4 overflow-y-auto">
         <div id="detailsModalContent" class="modal-content bg-white p-8 rounded-xl shadow-xl max-w-lg w-full relative">
             <button id="detailsCloseButton" class="absolute top-4 right-4 bg-transparent border-none text-2xl cursor-pointer text-gray-500 hover:text-gray-700 focus:outline-none" aria-label="Close modal">
                 × <!-- Using HTML entity for 'x' -->
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