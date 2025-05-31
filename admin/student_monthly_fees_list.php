<?php
// School/admin/student_monthly_fees_list.php

session_start();

require_once "../config.php"; // Adjust path as needed

// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can access this page.</p>";
    header("location: ../login.php"); // Redirect unauthorized users
    exit;
}

// --- Variables for Messages ---
$operation_message = ""; // For messages from other pages (e.g., successful updates/deletes)
if (isset($_SESSION['operation_message'])) {
    $operation_message = $_SESSION['operation_message'];
    unset($_SESSION['operation_message']);
}

$fetch_message = ""; // Message specifically for the fee list fetch

// --- Filter and Search Variables ---
$filter_status = $_GET['filter_status'] ?? ''; // 'all', 'paid', 'due'
$search_term = trim($_GET['search_term'] ?? '');

// --- Summary Counts ---
$total_records = 0;
$paid_count = 0;
$due_count = 0;

// --- Fee Records Array ---
$fee_records = [];

// --- Database Connection Check ---
if ($link === false) {
    $fetch_message = "<p class='text-red-600'>Database connection error. Could not load fee records.</p>";
    error_log("Monthly Fee List DB connection failed: " . mysqli_connect_error());
} else {
    // --- Fetch Summary Counts ---
    // Fetch total records
    $sql_count_total = "SELECT COUNT(*) FROM student_monthly_fees";
    if ($result_total = mysqli_query($link, $sql_count_total)) {
        $row_total = mysqli_fetch_row($result_total);
        $total_records = $row_total[0];
        mysqli_free_result($result_total);
    } else {
        error_log("Monthly Fee List count total failed: " . mysqli_error($link));
        // Continue execution even if counts fail
    }

    // Fetch paid count
    $sql_count_paid = "SELECT COUNT(*) FROM student_monthly_fees WHERE is_paid = 1";
     if ($result_paid = mysqli_query($link, $sql_count_paid)) {
         $row_paid = mysqli_fetch_row($result_paid);
         $paid_count = $row_paid[0];
         mysqli_free_result($result_paid);
     } else {
         error_log("Monthly Fee List count paid failed: " . mysqli_error($link));
          // Continue execution
     }

     // Fetch due count
     $sql_count_due = "SELECT COUNT(*) FROM student_monthly_fees WHERE is_paid = 0";
     if ($result_due = mysqli_query($link, $sql_count_due)) {
         $row_due = mysqli_fetch_row($result_due);
         $due_count = $row_due[0];
         mysqli_free_result($result_due);
     } else {
         error_log("Monthly Fee List count due failed: " . mysqli_error($link));
         // Continue execution
     }


    // --- Build Main Fetch Query with Filter and Search ---
    $sql_fetch_fees = "SELECT
                        smf.id, smf.student_id, smf.fee_year, smf.fee_month,
                        smf.base_monthly_fee, smf.monthly_van_fee, smf.monthly_exam_fee, smf.monthly_electricity_fee,
                        smf.amount_due, smf.amount_paid, smf.is_paid, smf.payment_date, smf.notes,
                        smf.created_at, smf.updated_at,
                        s.full_name, s.current_class
                       FROM student_monthly_fees smf
                       JOIN students s ON smf.student_id = s.user_id";

    $where_clauses = [];
    $param_types = "";
    $params = [];

    // Add Status Filter
    if ($filter_status === 'paid') {
        $where_clauses[] = "smf.is_paid = 1";
    } elseif ($filter_status === 'due') {
        $where_clauses[] = "smf.is_paid = 0";
    }

    // Add Search Term (Apply to relevant text/numeric fields)
    if (!empty($search_term)) {
        // Use LIKE and OR for searching across multiple columns
        // Wrap parameters in % for partial matching
        $search_like = "%" . $search_term . "%";
        $search_conditions = [];

        // Check if the search term could be a number (for IDs, fees, year, month)
        $is_numeric_search = is_numeric($search_term);

        // Search by student name, class, notes
        $search_conditions[] = "s.full_name LIKE ?";
        $param_types .= "s";
        $params[] = $search_like;

        $search_conditions[] = "s.current_class LIKE ?";
        $param_types .= "s";
        $params[] = $search_like;

        $search_conditions[] = "smf.notes LIKE ?";
        $param_types .= "s";
        $params[] = $search_like;

         // Add numeric searches only if the term is numeric
        if ($is_numeric_search) {
             $search_conditions[] = "s.user_id = ?"; // Exact match for ID
             $param_types .= "i";
             $params[] = (int)$search_term; // Cast to integer

             $search_conditions[] = "smf.fee_year = ?"; // Exact match for Year
             $param_types .= "i";
             $params[] = (int)$search_term;

             $search_conditions[] = "smf.fee_month = ?"; // Exact match for Month
             $param_types .= "i";
             $params[] = (int)$search_term;

              // Use LIKE for currency fields to find partial matches (e.g., "120" might match "1200.00")
              // Note: Direct numeric comparison might be better if exact value search is needed,
              // but LIKE is more flexible for general search input.
             $search_conditions[] = "smf.base_monthly_fee LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;

             $search_conditions[] = "smf.monthly_van_fee LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;

             $search_conditions[] = "smf.monthly_exam_fee LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;

             $search_conditions[] = "smf.monthly_electricity_fee LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;

             $search_conditions[] = "smf.amount_due LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;

             $search_conditions[] = "smf.amount_paid LIKE ?";
             $param_types .= "s";
             $params[] = $search_like;
        }


        // Combine search conditions with OR
        $where_clauses[] = "(" . implode(" OR ", $search_conditions) . ")";
    }

    // Combine all WHERE clauses with AND
    if (!empty($where_clauses)) {
        $sql_fetch_fees .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Add Ordering (Fee Year, Month, then Student Name)
    $sql_fetch_fees .= " ORDER BY smf.fee_year DESC, smf.fee_month DESC, s.full_name ASC"; // Order by latest fees first


    // --- Execute Main Fetch Query ---
    if ($stmt_fees = mysqli_prepare($link, $sql_fetch_fees)) {

        // Bind parameters if there are any
        if (!empty($params)) {
             // Use call_user_func_array to pass the array of parameters to bind_param
             mysqli_stmt_bind_param($stmt_fees, $param_types, ...$params);
        }


        if (mysqli_stmt_execute($stmt_fees)) {
            $result_fees = mysqli_stmt_get_result($stmt_fees);

            if ($result_fees) { // Check if get_result was successful
                 if (mysqli_num_rows($result_fees) > 0) {
                    $fee_records = mysqli_fetch_all($result_fees, MYSQLI_ASSOC);
                    $fetch_message = "Displaying " . count($fee_records) . " matching record(s).";
                } else {
                    $fetch_message = "No monthly fee records found matching your criteria.";
                }
                mysqli_free_result($result_fees);
            } else {
                 $fetch_message = "<p class='text-red-600'>Error getting result set: " . mysqli_stmt_error($stmt_fees) . "</p>";
                 error_log("Monthly Fee List get_result failed: " . mysqli_stmt_error($stmt_fees));
            }
        } else {
             $fetch_message = "<p class='text-red-600'>Error executing query: " . mysqli_stmt_error($stmt_fees) . "</p>";
             error_log("Monthly Fee List fetch query failed: " . mysqli_stmt_error($stmt_fees));
        }
        mysqli_stmt_close($stmt_fees);
    } else {
         $fetch_message = "<p class='text-red-600'>Error preparing statement: " . mysqli_error($link) . "</p>";
         error_log("Monthly Fee List prepare statement failed: " . mysqli_error($link));
    }
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
    <title>Monthly Fees List</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
     <style>
         body {
             background-color: #f3f4f6;
             min-height: 100vh;
         }
        .form-error {
            color: #dc3545;
            font-size: 0.75em; /* text-xs */
            margin-top: 0.25em;
            display: block;
        }
         .form-control.is-invalid {
             border-color: #dc3545;
         }
         .form-control.is-invalid:focus {
              border-color: #dc2626; /* red-600 */
              ring-color: #f87171; /* red-400 */
         }

         /* Fixed Header and body padding to prevent content overlap */
         .fixed-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 20; /* Below sidebar (z-index 30), above content */
            background-color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
         }
         body {
             /* Add padding-top to clear the fixed header. Adjust value if needed. */
             padding-top: 4.5rem;
             transition: padding-left 0.3s ease;
         }
         /* Adjust body padding when sidebar is open - assumes sidebar width is ~16rem (64 Tailwind units) */
         body.sidebar-open {
             padding-left: 16rem;
         }

         /* Style for general messages */
         .message-box {
             padding: 0.75rem 1.25rem;
             border-radius: 0.5rem;
             border: 1px solid transparent;
             margin-bottom: 1.5rem;
             text-align: center;
             font-size: 0.875rem;
         }
         .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; }
         .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; }
         .message-box.warning { color: #b45309; background-color: #fffce0; border-color: #fde68a; }
         .message-box.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }


         /* Table styles */
         .data-table th, .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; text-align: left; vertical-align: top; font-size: 0.875rem; }
          .data-table th { background-color: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; color: #374151; letter-spacing: 0.05em; }
           .data-table tbody tr:nth-child(even) { background-color: #f9fafb; }
            /* Add hover effect for rows - but disable if we implement modal */
           /* .data-table tbody tr:hover { background-color: #e5e7eb; } */
            .data-table .actions { white-space: nowrap; min-width: 180px; text-align: center; } /* Increased min-width */
            .data-table td { color: #4b5563; }
            .data-table td a { font-weight: 500; }

         /* Custom styles for action links */
          .action-link-view { color: #3b82f6; }
          .action-link-view:hover { color: #2563eb; text-decoration: underline; }
          .action-link-edit { color: #3b82f6; margin-left: 0.5rem; }
          .action-link-edit:hover { color: #2563eb; text-decoration: underline; }
          .action-link-record-payment { color: #22c55e; margin-left: 0.5rem; } /* Green */
          .action-link-record-payment:hover { color: #16a34a; text-decoration: underline; }
          .action-link-delete { color: #ef4444; margin-left: 0.5rem; }
          .action-link-delete:hover { color: #dc2626; text-decoration: underline; }

         /* Status Colors in Table */
         .status-paid { color: #065f46; font-weight: 600; } /* green-800 */
         .status-due { color: #b91c1c; font-weight: 600; } /* red-800 */


         /* Filter/Search Container */
         .filter-search-container {
             margin-bottom: 1.5rem;
             padding: 1rem;
             background-color: #ffffff;
             border-radius: 0.5rem;
             box-shadow: 0 1px 3px rgba(0,0,0,0.1);
             display: flex; /* Use flexbox for layout */
             gap: 1rem; /* Space between filter and search */
             align-items: flex-end; /* Align items to the bottom */
             flex-wrap: wrap; /* Allow wrapping on smaller screens */
         }
          .filter-group, .search-group {
              flex: 1; /* Allow groups to grow */
              min-width: 200px; /* Minimum width before wrapping */
          }

         .filter-search-container label {
             display: block;
             font-size: 0.875rem;
             font-weight: 500;
             color: #374151;
             margin-bottom: 0.25rem;
         }
          .filter-search-container select,
          .filter-search-container input[type="text"] {
              width: 100%;
              padding: 0.5rem 0.75rem;
              border: 1px solid #d1d5db;
              border-radius: 0.375rem;
              box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
              font-size: 0.875rem;
              color: #374151;
              transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
          }
           .filter-search-container select:focus,
           .filter-search-container input[type="text"]:focus {
                 outline: none;
                 border-color: #4f46e5;
                 box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            }

         .filter-search-container button {
             align-self: flex-end; /* Align button to the bottom */
             padding: 0.5rem 1rem;
             background-color: #4f46e5; /* indigo-600 */
             color: white;
             border-radius: 0.375rem;
             font-weight: 500;
             font-size: 0.875rem;
             transition: background-color 0.15s ease-in-out;
             white-space: nowrap; /* Prevent button text from wrapping */
         }
          .filter-search-container button:hover {
              background-color: #4338ca; /* indigo-700 */
          }


           /* Summary Counts Box */
           .summary-box {
                background-color: #e0f2f7; /* light blue */
                color: #0e7490; /* dark cyan */
                padding: 1rem;
                border-radius: 0.5rem;
                margin-bottom: 1.5rem;
                font-size: 0.9rem;
                display: flex;
                justify-content: space-around; /* Distribute space evenly */
                flex-wrap: wrap; /* Allow wrapping */
                gap: 1rem; /* Gap between items */
           }
            .summary-item {
                text-align: center;
            }
             .summary-item strong {
                 display: block; /* Put label above count */
                 font-weight: 600;
                 color: #083344; /* even darker cyan */
                 margin-bottom: 0.25rem;
             }
             .summary-item span {
                 font-size: 1.125rem; /* Larger count */
                 font-weight: 700; /* bold */
             }
              .summary-item .count-total span { color: #0e7490; }
              .summary-item .count-paid span { color: #065f46; } /* green-800 */
              .summary-item .count-due span { color: #b91c1c; } /* red-800 */


    </style>
     <script>
        // Sidebar Toggle JS
        document.getElementById('admin-sidebar-toggle-open')?.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-open');
        });
         // End Sidebar Toggle JS

         // No specific modal JS needed for this page based on the request,
         // as clicking rows will likely link to view/edit pages.
         // Keep a basic click handler to prevent modal if clicking actions,
         // mirroring the dashboard's behaviour.
         document.addEventListener('DOMContentLoaded', function() {
             // Add click listener using event delegation
             document.body.addEventListener('click', function(event) {
                  // Find the closest cell (td) to the clicked element
                  const targetCell = event.target.closest('td');

                  // Check if the clicked cell or its ancestor is an action cell
                  if (targetCell && targetCell.classList.contains('actions')) {
                      // If it's an action cell, stop the event propagation
                      event.stopPropagation();
                  }
             });

             // Helper function for month name display in JS (optional for future use)
             const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

         });

    </script>
</head>
<body class="min-h-screen">
    <?php
    // Assuming admin_sidebar.php is in the SAME directory as student_monthly_fees_list.php
    require_once "./admin_sidebar.php";
    ?>

    <!-- Fixed Header for Toggle Button and Page Title -->
    <div class="fixed-header">
         <!-- Open Sidebar Button (Hamburger) -->
         <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:mr-6" aria-label="Toggle sidebar">
             <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
             </svg>
         </button>

         <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">Monthly Fees List</h1>

          <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span> <!-- Using $_SESSION['name'] from login -->
            <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
    </div>

    <!-- Main content wrapper -->
     <div class="w-full max-w-screen-xl mx-auto px-4 py-8">

         <!-- Operation Message Display (From session, cleared after display) -->
         <?php
         if (!empty($operation_message)) {
             $message_type = 'info';
              $msg_lower = strtolower(strip_tags($operation_message));
               if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                   $message_type = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false) {
                   $message_type = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'duplicate') !== false || strpos($msg_lower, 'no ') !== false || strpos($msg_lower, 'validation errors') !== false) {
                   $message_type = 'warning';
              }
             echo "<div class='message-box " . $message_type . "' role='alert'>" . $operation_message . "</div>";
         }
         ?>

         <!-- Summary Counts -->
         <div class="summary-box">
              <div class="summary-item count-total">
                  <strong>Total Records</strong>
                  <span><?php echo htmlspecialchars($total_records); ?></span>
              </div>
              <div class="summary-item count-paid">
                  <strong>Total Paid</strong>
                  <span><?php echo htmlspecialchars($paid_count); ?></span>
              </div>
               <div class="summary-item count-due">
                  <strong>Total Due</strong>
                  <span><?php echo htmlspecialchars($due_count); ?></span>
              </div>
         </div>


         <!-- Filter and Search Form -->
         <div class="filter-search-container">
             <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="flex flex-grow gap-4 items-end flex-wrap"> <!-- Use GET for filters -->
                 <div class="filter-group flex-grow">
                     <label for="filter_status">Filter by Status:</label>
                     <select name="filter_status" id="filter_status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                         <option value="all" <?php echo ($filter_status === 'all' || $filter_status === '') ? 'selected' : ''; ?>>All</option>
                         <option value="paid" <?php echo ($filter_status === 'paid') ? 'selected' : ''; ?>>Paid</option>
                         <option value="due" <?php echo ($filter_status === 'due') ? 'selected' : ''; ?>>Due</option>
                     </select>
                 </div>

                 <div class="search-group flex-grow">
                     <label for="search_term">Search Records:</label>
                     <input type="text" name="search_term" id="search_term" placeholder="Search by name, class, fee amount, year, month, notes..." class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($search_term); ?>">
                 </div>

                 <div>
                     <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 text-sm font-medium">Apply Filters</button>
                 </div>
             </form>
         </div>


          <!-- Fetch Message Display -->
         <?php
          if (!empty($fetch_message)) {
               $message_type = 'info';
                $msg_lower = strtolower(strip_tags($fetch_message));
                if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'invalid') !== false) {
                    $message_type = 'error';
                } elseif (strpos($msg_lower, 'no ') !== false || strpos($msg_lower, 'matching') !== false) { // No records found or matching criteria
                     $message_type = 'warning';
                } elseif (strpos($msg_lower, 'displaying') !== false) {
                     $message_type = 'info';
                }
               echo "<div class='message-box " . $message_type . "' role='alert'>" . htmlspecialchars($fetch_message) . "</div>";
          }
         ?>


         <!-- Monthly Fee Records Table -->
         <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full">
             <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-6 text-center">All Monthly Fee Records</h2>

             <?php if (!empty($fee_records)): ?>
                 <div class="overflow-x-auto">
                     <table class="min-w-full divide-y divide-gray-200 data-table">
                         <thead>
                             <tr>
                                 <th scope="col">Record ID</th>
                                 <th scope="col">Student ID</th>
                                 <th scope="col">Student Name</th>
                                  <th scope="col">Class</th>
                                 <th scope="col">Month</th>
                                 <th scope="col">Year</th>
                                 <th scope="col">Base Fee</th>
                                 <th scope="col">Van Fee</th>
                                 <th scope="col">Exam Fee</th>
                                 <th scope="col">Electricity Fee</th>
                                 <th scope="col">Total Due</th>
                                 <th scope="col">Amount Paid</th>
                                 <th scope="col">Amount Remaining</th>
                                 <th scope="col">Status</th>
                                 <th scope="col">Payment Date</th>
                                 <th scope="col">Notes</th>
                                 <th scope="col">Created At</th>
                                 <th scope="col">Updated At</th>
                                 <th scope="col" class="text-center actions">Actions</th> <!-- Added actions column header -->
                             </tr>
                         </thead>
                         <tbody class="bg-white divide-y divide-gray-200">
                             <?php
                              $month_names_display = [
                                  1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                                  5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                                  9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
                              ];
                              $user_role = $_SESSION['role'] ?? 'guest'; // Get user role safely
                             ?>
                             <?php foreach ($fee_records as $record): ?>
                                 <tr>
                                     <td><?php echo htmlspecialchars($record['id']); ?></td>
                                     <td><?php echo htmlspecialchars($record['student_id']); ?></td>
                                     <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                     <td><?php echo htmlspecialchars($record['current_class']); ?></td>
                                     <td><?php echo htmlspecialchars($month_names_display[$record['fee_month']] ?? 'N/A'); ?></td>
                                     <td><?php echo htmlspecialchars($record['fee_year']); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['base_monthly_fee'], 2)); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['monthly_van_fee'], 2)); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['monthly_exam_fee'], 2)); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['monthly_electricity_fee'], 2)); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['amount_due'], 2)); ?></td>
                                     <td><?php echo htmlspecialchars(number_format($record['amount_paid'], 2)); ?></td>
                                     <td>
                                         <?php
                                            $amount_remaining = ($record['amount_due'] ?? 0) - ($record['amount_paid'] ?? 0);
                                            $remaining_class = ($amount_remaining > 0) ? 'text-red-800' : 'text-green-800';
                                         ?>
                                         <span class="<?php echo $remaining_class; ?>">
                                             <?php echo htmlspecialchars(number_format($amount_remaining, 2)); ?>
                                         </span>
                                     </td>
                                     <td>
                                        <?php
                                             $status_class = ($record['is_paid'] == 1) ? 'status-paid' : 'status-due';
                                             $status_text = ($record['is_paid'] == 1) ? 'Paid' : 'Due';
                                             echo '<span class="' . $status_class . '">' . $status_text . '</span>';
                                        ?>
                                    </td>
                                     <td>
                                         <?php
                                             echo (!empty($record['payment_date']) && $record['payment_date'] !== '0000-00-00') ? htmlspecialchars(date('Y-m-d', strtotime($record['payment_date']))) : 'N/A';
                                         ?>
                                     </td>
                                     <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                     <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($record['created_at']))); ?></td>
                                     <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($record['updated_at']))); ?></td>
                                     <td class="actions" onclick="event.stopPropagation();"> <!-- Prevent row click if modal JS was added later -->
                                         <?php // Action links - Path from admin/ to admin/ is ./ ?>
                                         <a href="./view_student.php?id=<?php echo htmlspecialchars($record['student_id']); ?>" class="action-link-view mr-2">View Student</a>
                                         <?php if ($user_role === 'admin'): // Only admin can edit fee records? Or admin/principal? Decide role. Let's assume admin can edit/delete fees for now. ?>
                                              <a href="./edit_monthly_fee.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="action-link-edit mr-2">Edit Record</a>
                                              <?php if ($record['is_paid'] == 0): // Only show record payment link if due ?>
                                                  <a href="./record_payment.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="action-link-record-payment mr-2">Record Payment</a>
                                              <?php endif; ?>
                                             <a href="./delete_monthly_fee.php?id=<?php echo htmlspecialchars($record['id']); ?>" class="action-link-delete" onclick="return confirm('Are you sure you want to DELETE this fee record? This cannot be undone!');">Delete</a>
                                         <?php endif; ?>
                                     </td>
                                 </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
             <?php else: ?>
                  <!-- Message displayed if no records or no matching records -->
             <?php endif; ?>
         </div>


     </div> <!-- End of main content wrapper -->

</body>
</html>
