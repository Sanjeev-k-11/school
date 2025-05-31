<?php
// School/admin/record_payment.php

session_start();

require_once "../config.php";

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can record payments.</p>";
    header("location: ../login.php");
    exit;
}

$fee_id = null;
$student_id = null;

$fee_year = '';
$fee_month = '';
$base_monthly_fee_fetched = 0.00;
$monthly_van_fee_fetched = 0.00;
$monthly_exam_fee_fetched = 0.00;
$monthly_electricity_fee_fetched = 0.00;
$amount_due_fetched = 0.00;
$amount_paid_fetched = 0.00;
$is_paid_fetched = 0;
$payment_date_fetched = null;
$notes_fetched = '';

$amount_being_paid_input = '';
$payment_date_input = '';
$notes_input = '';


$student_full_name = 'Loading...';


$amount_being_paid_err = '';
$payment_date_err = '';
$general_error = '';


$toast_message = '';
$toast_type = '';


if ($_SERVER["REQUEST_METHOD"] == "POST") { // <-- Main POST block starts here (Line 46)

    $fee_id = filter_input(INPUT_POST, 'fee_id', FILTER_VALIDATE_INT);

     $student_id_from_post = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
     if ($student_id_from_post !== false && $student_id_from_post > 0) {
          $student_id = $student_id_from_post;
     }

    if ($fee_id === false || $fee_id <= 0) {
        $toast_message = "Invalid fee record ID provided for payment.";
        $toast_type = 'error';
        $fee_id = null;
    }


    if ($fee_id !== null) { // <-- Block starts here

        $amount_being_paid_input = trim($_POST['amount_being_paid'] ?? '');
        $payment_date_input = trim($_POST['payment_date'] ?? '');
        $notes_input = trim($_POST['notes'] ?? '');


        $amount_being_paid_for_db = null;
        $payment_date_for_db = null;
        $notes_for_db = ($notes_input === '') ? null : $notes_input;


        if ($amount_being_paid_input === '') {
             $amount_being_paid_err = "Please enter the amount being paid.";
        } else {
            $filtered_paid = filter_var($amount_being_paid_input, FILTER_VALIDATE_FLOAT);
            if ($filtered_paid === false || $filtered_paid < 0) {
                $amount_being_paid_err = "Please enter a valid non-negative number for Amount Paid.";
            } else {
                $amount_being_paid_for_db = $filtered_paid;
            }
        }

        if (!empty($payment_date_input)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $payment_date_input);
            if ($date_obj && $date_obj->format('Y-m-d') === $payment_date_input) {
                $payment_date_for_db = $date_obj->format('Y-m-d');
            } else {
                $payment_date_err = "Invalid date format. Please use YYYY-MM-DD or date picker.";
            }
        } else {
            $payment_date_for_db = null;
        }


        $has_form_errors = !empty($amount_being_paid_err) || !empty($payment_date_err);


        if (!$has_form_errors && $fee_id > 0) { // <-- Block starts here

             $sql_fetch_current = "SELECT amount_due, amount_paid, is_paid, payment_date, notes FROM student_monthly_fees WHERE id = ?";
             $current_amount_paid = 0.00;
             $current_amount_due = 0.00;
             $current_is_paid = 0;
             $current_payment_date = null;
             $current_notes = null;

             if ($link === false) {
                  $general_error = "Database connection error during record fetch.";
                  $toast_type = 'error';
             } elseif ($stmt_fetch_current = mysqli_prepare($link, $sql_fetch_current)) { // <-- Block starts here
                  mysqli_stmt_bind_param($stmt_fetch_current, "i", $fee_id);
                  if (mysqli_stmt_execute($stmt_fetch_current)) { // <-- Block starts here
                       $result_current = mysqli_stmt_get_result($stmt_fetch_current);
                       if (mysqli_num_rows($result_current) == 1) {
                            $current_record = mysqli_fetch_assoc($result_current);
                            $current_amount_due = $current_record['amount_due'] ?? 0.00;
                            $current_amount_paid = $current_record['amount_paid'] ?? 0.00;
                            $current_is_paid = $current_record['is_paid'] ?? 0;
                             $current_payment_date = $current_record['payment_date'];
                             $current_notes = $current_record['notes'];

                       } else {
                           $general_error = "Error: Fee record not found for payment.";
                           $toast_type = 'error';
                       }
                       mysqli_free_result($result_current);
                  } else { // <-- Block starts here
                       $general_error = "Database error fetching current record details.";
                       $toast_type = 'error';
                        error_log("Record Payment fetch current failed for ID " . $fee_id . ": " . mysqli_stmt_error($stmt_fetch_current));
                  } // <-- Closing brace for if (mysqli_stmt_execute)
                  mysqli_stmt_close($stmt_fetch_current);
             } else { // <-- Block starts here
                  $general_error = "Database error preparing current record fetch.";
                  $toast_type = 'error';
                  error_log("Record Payment prepare current fetch failed: " . mysqli_error($link));
             } // <-- Closing brace for elseif (mysqli_prepare)

             if (empty($general_error)) { // <-- Block starts here

                 $new_total_amount_paid = $current_amount_paid + ($amount_being_paid_for_db ?? 0.00);

                 $new_is_paid_status = ($new_total_amount_paid >= $current_amount_due) ? 1 : 0;

                 $final_payment_date_for_db = null;

                 if (!empty($amount_being_paid_for_db) && $amount_being_paid_for_db > 0 && $payment_date_for_db !== null) {
                      $final_payment_date_for_db = $payment_date_for_db;
                 }

                 if ($new_is_paid_status == 1 && empty($final_payment_date_for_db)) {
                     $final_payment_date_for_db = date('Y-m-d');
                 } elseif ($new_is_paid_status == 0 && !empty($final_payment_date_for_db)) {
                      $final_payment_date_for_db = null;
                 }


                 $sql_update = "UPDATE student_monthly_fees SET
                                 amount_paid = ?,
                                 is_paid = ?,
                                 payment_date = ?,
                                 notes = ?,
                                 updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?";

                 if ($link === false) { /* Error reported above */ }
                 elseif ($stmt_update = mysqli_prepare($link, $sql_update)) { // <-- Block starts here
                     mysqli_stmt_bind_param($stmt_update, "dissi",
                         $new_total_amount_paid,
                         $new_is_paid_status,
                         $final_payment_date_for_db,
                         $notes_for_db,
                         $fee_id
                     );

                     if (mysqli_stmt_execute($stmt_update)) { // <-- Block starts here
                         mysqli_stmt_close($stmt_update);

                         $_SESSION['operation_message'] = "<p class='text-green-600'>Payment recorded successfully.</p>";
                         if ($student_id > 0) {
                              header("location: view_student.php?id=" . htmlspecialchars($student_id));
                         } else {
                              header("location: student_monthly_fees_list.php");
                         }
                         exit(); // <-- exit() prevents further execution in this branch
                     } else { // <-- Block starts here
                          $general_error = "Error: Could not record payment.";
                          $toast_type = 'error';
                          error_log("Record Payment update query failed for ID " . $fee_id . ": " . mysqli_stmt_error($stmt_update));
                         mysqli_stmt_close($stmt_update);
                     } // <-- Closing brace for if (mysqli_stmt_execute)
                 } else { // <-- Block starts here
                      $general_error = "Error: Could not prepare payment update statement.";
                      $toast_type = 'error';
                      error_log("Record Payment prepare update failed: " . mysqli_error($link));
                 } // <-- Closing brace for elseif (mysqli_prepare)
             } // <-- Closing brace for if (empty($general_error))

        } else { // <-- Block starts here
            $general_error = "Validation errors found. Please check the form.";
            $toast_type = 'error';
        } // <-- Closing brace for if (!$has_form_errors && $fee_id > 0)
    } // <-- Closing brace for if ($fee_id !== null)


    // Re-fetch data for display after POST attempt, regardless of success/failure
    // Only fetch if fee_id is valid
    if ($fee_id > 0) { // <-- Block starts here
         $sql_fetch_fee = "SELECT smf.id, smf.student_id, smf.fee_year, smf.fee_month,
                           smf.base_monthly_fee, smf.monthly_van_fee, smf.monthly_exam_fee, smf.monthly_electricity_fee,
                           smf.amount_due, smf.amount_paid, smf.is_paid, smf.payment_date, smf.notes,
                           s.full_name
                           FROM student_monthly_fees smf
                           JOIN students s ON smf.student_id = s.user_id
                           WHERE smf.id = ?";

         if ($link === false) { /* Error reported above */ }
         elseif ($stmt_fetch_fee = mysqli_prepare($link, $sql_fetch_fee)) { // <-- Block starts here
             mysqli_stmt_bind_param($stmt_fetch_fee, "i", $fee_id);
             if (mysqli_stmt_execute($stmt_fetch_fee)) { // <-- Block starts here
                 $result_fetch_fee = mysqli_stmt_get_result($stmt_fetch_fee);
                 if (mysqli_num_rows($result_fetch_fee) == 1) {
                      $fee_record = mysqli_fetch_assoc($result_fetch_fee);

                       $student_id = $fee_record['student_id'];
                       $fee_year = $fee_record['fee_year'];
                       $fee_month = $fee_record['fee_month'];
                       $base_monthly_fee_fetched = $fee_record['base_monthly_fee'];
                       $monthly_van_fee_fetched = $fee_record['monthly_van_fee'];
                       $monthly_exam_fee_fetched = $fee_record['monthly_exam_fee'];
                       $monthly_electricity_fee_fetched = $fee_record['monthly_electricity_fee'];
                       $amount_due_fetched = $fee_record['amount_due'];
                       $amount_paid_fetched = $fee_record['amount_paid'];
                       $is_paid_fetched = $fee_record['is_paid'];
                        $payment_date_fetched = $fee_record['payment_date'];
                       $notes_fetched = $fee_record['notes'];
                       $student_full_name = htmlspecialchars($fee_record['full_name']);

                       mysqli_free_result($result_fetch_fee);

                       // Note: $amount_being_paid_input, $payment_date_input, $notes_input
                       // will retain their POST values if there were form errors,
                       // otherwise they were cleared on success redirect.

                 } else { // <-- Block starts here
                     $general_error = "Error: Fee record not found."; // Record disappeared after POST?
                     $toast_type = 'error';
                     // Clear variables as record is not available
                     $fee_id = null; $student_id = null; $fee_year = ''; $fee_month = '';
                     $base_monthly_fee_fetched = 0.00; $monthly_van_fee_fetched = 0.00;
                     $monthly_exam_fee_fetched = 0.00; $monthly_electricity_fee_fetched = 0.00;
                     $amount_due_fetched = 0.00; $amount_paid_fetched = 0.00; $is_paid_fetched = 0;
                      $payment_date_fetched = null; $notes_fetched = '';
                     $student_full_name = 'Record Not Found';
                 } // <-- Closing brace for if (mysqli_num_rows)
             } else { // <-- Block starts here
                 error_log("Record Payment fetch fee query failed during POST (refetch) for ID " . $fee_id . ": " . mysqli_stmt_error($stmt_fetch_fee));
                  if(empty($general_error)) {
                       $general_error = "Error refetching fee record data.";
                       $toast_type = 'error';
                  }
             } // <-- Closing brace for if (mysqli_stmt_execute)
             mysqli_stmt_close($stmt_fetch_fee);
        } else { // <-- Block starts here
            // $fee_id was invalid on POST, general error message already set by toast.
             // Clear display variables.
            $fee_id = null; $student_id = null; $fee_year = ''; $fee_month = '';
            $base_monthly_fee_fetched = 0.00; $monthly_van_fee_fetched = 0.00;
            $monthly_exam_fee_fetched = 0.00; $monthly_electricity_fee_fetched = 0.00;
            $amount_due_fetched = 0.00; $amount_paid_fetched = 0.00; $is_paid_fetched = 0;
             $payment_date_fetched = null; $notes_fetched = '';
            $student_full_name = 'Invalid Record ID';
        } // <-- Closing brace for elseif (mysqli_prepare)
    } else { // <-- Block starts here
         // $fee_id was invalid on POST, general error message already set by toast.
         // Clear display variables.
        $fee_id = null; $student_id = null; $fee_year = ''; $fee_month = '';
        $base_monthly_fee_fetched = 0.00; $monthly_van_fee_fetched = 0.00;
        $monthly_exam_fee_fetched = 0.00; $monthly_electricity_fee_fetched = 0.00;
        $amount_due_fetched = 0.00; $amount_paid_fetched = 0.00; $is_paid_fetched = 0;
         $payment_date_fetched = null; $notes_fetched = '';
        $student_full_name = 'Invalid Record ID';
    } // <-- Closing brace for if ($fee_id > 0) (Refetch block)


} // <-- CLOSING BRACE FOR THE MAIN POST BLOCK IS ADDED HERE


else { // <-- Main GET block starts here

    if (isset($_GET["id"]) && !empty(trim($_GET["id"]))) {
        $fee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($fee_id === false || $fee_id <= 0) {
            $_SESSION['operation_message'] = "<p class='text-red-600'>Invalid fee record ID provided.</p>";
            header("location: student_monthly_fees_list.php");
            exit();
        } else {
            $sql_fetch_fee = "SELECT smf.id, smf.student_id, smf.fee_year, smf.fee_month,
                           smf.base_monthly_fee, smf.monthly_van_fee, smf.monthly_exam_fee, smf.monthly_electricity_fee,
                           smf.amount_due, smf.amount_paid, smf.is_paid, smf.payment_date, smf.notes,
                           s.full_name
                           FROM student_monthly_fees smf
                           JOIN students s ON smf.student_id = s.user_id
                           WHERE smf.id = ?";

            if ($link === false) {
                $general_error = "Database connection error. Could not load fee data.";
                $toast_type = 'error';
                 error_log("Record Payment fetch DB connection failed (GET): " . mysqli_connect_error());
                 $fee_id = null;
            } elseif ($stmt_fetch_fee = mysqli_prepare($link, $sql_fetch_fee)) {
                mysqli_stmt_bind_param($stmt_fetch_fee, "i", $fee_id);

                if (mysqli_stmt_execute($stmt_fetch_fee)) {
                    $result_fetch_fee = mysqli_stmt_get_result($stmt_fetch_fee);

                    if (mysqli_num_rows($result_fetch_fee) == 1) {
                        $fee_record = mysqli_fetch_assoc($result_fetch_fee);

                        $student_id = $fee_record['student_id'];
                        $fee_year = $fee_record['fee_year'];
                        $fee_month = $fee_record['fee_month'];
                        $base_monthly_fee_fetched = $fee_record['base_monthly_fee'];
                        $monthly_van_fee_fetched = $fee_record['monthly_van_fee'];
                        $monthly_exam_fee_fetched = $fee_record['monthly_exam_fee'];
                        $monthly_electricity_fee_fetched = $fee_record['monthly_electricity_fee'];
                        $amount_due_fetched = $fee_record['amount_due'];
                        $amount_paid_fetched = $fee_record['amount_paid'];
                        $is_paid_fetched = $fee_record['is_paid'];
                         $payment_date_fetched = $fee_record['payment_date'];
                        $notes_fetched = $fee_record['notes'];
                        $student_full_name = htmlspecialchars($fee_record['full_name']);

                        mysqli_free_result($result_fetch_fee);

                        // Initialize form input variables for GET request (empty or default date)
                        $amount_being_paid_input = ''; // Start empty
                        $payment_date_input = date('Y-m-d'); // Default payment date to today
                        $notes_input = $notes_fetched; // Default notes input to existing notes


                    } else {
                        $_SESSION['operation_message'] = "<p class='text-red-600'>Monthly fee record not found.</p>";
                        header("location: student_monthly_fees_list.php");
                        exit();
                    }
                } else {
                    $general_error = "Oops! Something went wrong. Could not fetch fee record. Please try again later.";
                    $toast_type = 'error';
                     error_log("Record Payment fetch query failed: " . mysqli_stmt_error($stmt_fetch_fee));
                     $fee_id = null;
                }
                mysqli_stmt_close($stmt_fetch_fee);
            } else {
                 $general_error = "Oops! Something went wrong. Could not prepare fetch statement. Please try again later.";
                 $toast_type = 'error';
                 error_log("Record Payment prepare fetch statement failed: " . mysqli_error($link));
                 $fee_id = null;
            }
        }
    } else {
        $_SESSION['operation_message'] = "<p class='text-red-600'>No fee record ID provided for payment.</p>";
        header("location: student_monthly_fees_list.php");
        exit();
    }

} // <-- Main GET block ends here


$amount_remaining_fetched = ($amount_due_fetched ?? 0) - ($amount_paid_fetched ?? 0);


if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment for Monthly Fee</title>
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
           input[type="number"]::placeholder {
               color: #9ca3af; /* Tailwind gray-400 */
           }
            input[type="date"]::placeholder {
                color: #9ca3af; /* Tailwind gray-400 */
            }

         .toast-container {
             position: fixed;
             top: 1rem;
             right: 1rem;
             z-index: 100;
             display: flex;
             flex-direction: column;
             gap: 0.5rem;
             pointer-events: none;
         }

         .toast {
             background-color: #fff;
             color: #333;
             padding: 0.75rem 1.25rem;
             border-radius: 0.375rem;
             box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0;
             transform: translateX(100%);
             transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto;
             min-width: 200px;
             max-width: 300px;
             display: flex;
             align-items: center;
         }

         .toast.show {
             opacity: 1;
             transform: translateX(0);
         }

         .toast-success { border-left: 5px solid #10b981; color: #065f46; }
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; }
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; }
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; }

         .toast .close-button {
             margin-left: auto;
             background: none;
             border: none;
             color: inherit;
             font-size: 1.2rem;
             cursor: pointer;
             padding: 0 0.25rem;
             line-height: 1;
         }
    </style>
     <script>
         document.addEventListener('DOMContentLoaded', function() {
             const toastContainer = document.getElementById('toastContainer');
             if (!toastContainer) {
                 console.error('Toast container #toastContainer not found.');
             }

             function showToast(message, type = 'info', duration = 5000) {
                 if (!message || !toastContainer) return;

                 const toast = document.createElement('div');
                 toast.classList.add('toast', `toast-${type}`);
                 toast.textContent = message;

                 const closeButton = document.createElement('button');
                 closeButton.classList.add('close-button');
                 closeButton.textContent = '×';
                 closeButton.onclick = () => toast.remove();
                 toast.appendChild(closeButton);

                 toastContainer.appendChild(toast);

                 requestAnimationFrame(() => {
                     toast.classList.add('show');
                 });

                 if (duration > 0) {
                     setTimeout(() => {
                         toast.classList.remove('show');
                         toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                     }, duration);
                 }
             }

             const phpMessage = <?php echo json_encode($general_error); ?>;
             const messageType = <?php echo json_encode($toast_type); ?>;

             if (phpMessage) {
                 showToast(phpMessage, messageType);
             }


         });
     </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center py-8 px-4">

    <div id="toastContainer" class="toast-container">
    </div>

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-xl font-semibold mb-6 text-center">Record Payment for Monthly Fee</h2>

         <div class="mb-6 text-center text-gray-700">
             <?php if ($student_id > 0): ?>
                  <p>Student: <span class="font-semibold"><?php echo $student_full_name; ?></span> (ID: <?php echo htmlspecialchars($student_id); ?>)</p>
             <?php else: ?>
                  <p>Student: N/A</p>
             <?php endif; ?>
              <p>Fee Record ID: <span class="font-semibold"><?php echo htmlspecialchars($fee_id ?? 'N/A'); ?></span></p>
             <p>Fee Month/Year: <span class="font-semibold"><?php echo htmlspecialchars(($fee_month ?? 'N/A') . '/' . ($fee_year ?? 'N/A')); ?></span></p>
         </div>

        <div class="mb-6 text-left">
             <?php if ($student_id > 0): ?>
                  <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Student Fees</a>
             <?php else: ?>
                 <a href="student_monthly_fees_list.php" class="text-indigo-600 hover:text-indigo-800 hover:underline text-sm font-medium">← Back to Fee List</a>
             <?php endif; ?>
        </div>


        <?php if ($fee_id > 0): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">

                <input type="hidden" name="fee_id" value="<?php echo htmlspecialchars($fee_id); ?>">
                 <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id ?? ''); ?>">

                 <div><h3 class="text-md font-semibold text-gray-700 border-b pb-1 mb-3">Fee Details (Read-only)</h3></div>

                 <div class="grid grid-cols-2 gap-4 text-sm text-gray-700">
                      <div><strong>Base Fee:</strong> <?php echo htmlspecialchars(number_format($base_monthly_fee_fetched, 2)); ?></div>
                      <div><strong>Van Fee:</strong> <?php echo htmlspecialchars(number_format($monthly_van_fee_fetched, 2)); ?></div>
                      <div><strong>Exam Fee:</strong> <?php echo htmlspecialchars(number_format($monthly_exam_fee_fetched, 2)); ?></div>
                      <div><strong>Electricity Fee:</strong> <?php echo htmlspecialchars(number_format($monthly_electricity_fee_fetched, 2)); ?></div>
                 </div>

                 <div class="grid grid-cols-2 gap-4 mt-4 text-gray-800 font-semibold">
                      <div><strong>Total Due:</strong> <span id="amount_due_display"><?php echo htmlspecialchars(number_format($amount_due_fetched, 2)); ?></span></div>
                      <div><strong>Paid So Far:</strong> <span id="amount_paid_so_far"><?php echo htmlspecialchars(number_format($amount_paid_fetched, 2)); ?></span></div>
                      <div class="col-span-2">
                           <strong>Amount Remaining:</strong>
                           <?php
                              $amount_remaining_fetched = ($amount_due_fetched ?? 0) - ($amount_paid_fetched ?? 0);
                              $remaining_display_class = ($amount_remaining_fetched > 0) ? 'text-red-800' : 'text-green-800';
                           ?>
                          <span class="<?php echo $remaining_display_class; ?>">
                               <?php echo htmlspecialchars(number_format($amount_remaining_fetched, 2)); ?>
                          </span>
                      </div>
                       <div class="col-span-2">
                           <strong>Current Status:</strong>
                           <?php
                               $status_class = ($is_paid_fetched == 1) ? 'status-paid' : 'status-due';
                               $status_text = ($is_paid_fetched == 1) ? 'Paid' : 'Due';
                           ?>
                            <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                       </div>
                 </div>


                <div><h3 class="text-md font-semibold text-gray-700 border-b pb-1 mb-3 mt-3">Enter Payment Details</h3></div>

                 <div>
                     <label for="amount_being_paid" class="block text-sm font-medium text-gray-700 mb-1">Amount Being Paid Now <span class="text-red-500">*</span></label>
                     <input type="number" name="amount_being_paid" id="amount_being_paid" step="0.01" min="0" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($amount_being_paid_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($amount_being_paid_input ?? ''); ?>" placeholder="e.g., 500.00">
                     <span class="form-error"><?php echo htmlspecialchars($amount_being_paid_err ?? ''); ?></span>
                 </div>

                 <div>
                     <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date (Optional)</label>
                      <?php
                         $payment_date_input_value = $payment_date_input ??
                                                    ((!empty($payment_date_fetched) && $payment_date_fetched !== '0000-00-00') ? date('Y-m-d', strtotime($payment_date_fetched)) : '');
                         if (empty($payment_date_input_value) && $_SERVER["REQUEST_METHOD"] !== "POST") {
                              $payment_date_input_value = date('Y-m-d');
                         }

                      ?>
                     <input type="date" name="payment_date" id="payment_date" class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm <?php echo (!empty($payment_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($payment_date_input_value); ?>">
                     <span class="form-error"><?php echo htmlspecialchars($payment_date_err ?? ''); ?></span>
                 </div>

                 <div>
                     <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                     <textarea name="notes" id="notes" rows="3" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"><?php echo htmlspecialchars($notes_input ?? $notes_fetched ?? ''); ?></textarea>
                 </div>


                <div class="flex items-center justify-between mt-6">
                    <button type="submit" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">Record Payment</button>
                     <?php if ($student_id > 0): ?>
                         <a href="view_student.php?id=<?php echo htmlspecialchars($student_id); ?>" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</a>
                     <?php else: ?>
                         <a href="student_monthly_fees_list.php" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-base font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Back to Fee List</a>
                     <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <?php /* The error message related to not being able to load the record is handled by the toast system now */ ?>
             <?php if (!empty($general_error)): ?>
                 <?php // The toast message already shows the error ?>
             <?php else: ?>
                 <p class="text-center text-red-600">Could not load the monthly fee record to record payment.</p>
             <?php endif; ?>
        <?php endif; ?>

    </div>

</body>
</html>