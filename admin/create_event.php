<?php
// School/admin/create_event.php

// Start the session
session_start();

require_once "../config.php";

// Check if user is logged in and is ADMIN
// Only Admin role should be able to create events
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins can create events.</p>";
    header("location: ./admin_dashboard.php"); // Redirect to dashboard with message
    exit;
}

// Set the page title
$pageTitle = "Create New Event";

// --- Variables for Form and Messages ---
$event_name = $event_description = $event_date_time = "";
$event_name_err = $event_description_err = $event_date_time_err = "";
$operation_message = ""; // For success/error messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate event name
    if (empty(trim($_POST["event_name"]))) {
        $event_name_err = "Please enter an event name.";
    } else {
        $event_name = trim($_POST["event_name"]);
    }

    // Validate event description (optional, but good practice)
    // Making description optional for now based on sketch simplicity
    $event_description = trim($_POST["event_description"] ?? ''); // Use ?? for null coalesce

    // Validate event date and time
    if (empty(trim($_POST["event_date_time"]))) {
        $event_date_time_err = "Please select a date and time.";
    } else {
         // The datetime-local input provides YYYY-MM-DDTHH:MM format
         // We need YYYY-MM-DD HH:MM:SS for MySQL DATETIME
         $event_date_time_local = trim($_POST["event_date_time"]);
         $event_date_time_formatted = date('Y-m-d H:i:s', strtotime($event_date_time_local)); // Convert to MySQL format

         if ($event_date_time_formatted === false) { // Check if strtotime failed
              $event_date_time_err = "Invalid date or time format.";
         } else {
              $event_date_time = $event_date_time_formatted;
         }
    }


    // Check input errors before inserting into database
    if (empty($event_name_err) && empty($event_description_err) && empty($event_date_time_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO events (event_name, event_description, event_date_time, created_by_name) VALUES (?, ?, ?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssss", $param_name, $param_description, $param_date_time, $param_created_by);

            // Set parameters
            $param_name = $event_name;
            $param_description = $event_description;
            $param_date_time = $event_date_time;
            $param_created_by = $_SESSION['name'] ?? 'Admin User'; // Get admin name from session

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to dashboard with success message
                $_SESSION['operation_message'] = "<p class='text-green-600'>Event announcement added successfully.</p>";
                header("location: ./admin_dashboard.php");
                exit();
            } else {
                 // Error log for debugging
                error_log("Event creation failed: " . mysqli_stmt_error($stmt));
                $operation_message = "<p class='text-red-600'>Error adding event. Please try again.</p>";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        } else {
             // Error log for debugging
            error_log("Event create prepare failed: " . mysqli_error($link));
             $operation_message = "<p class='text-red-600'>Database error. Could not prepare event statement.</p>";
        }
    } else {
         $operation_message = "<p class='text-yellow-600'>Please correct the errors below.</p>";
    }

    // Close connection (if it wasn't closed in config.php)
    if (isset($link) && is_object($link) && mysqli_ping($link)) {
         mysqli_close($link);
    }
}
?>

<?php
// Include the header file
require_once "./admin_header.php";
?>

<style>
    /* Add any specific styles for the create event form if needed */
    /* For now, we'll use general Tailwind/existing styles */
     body {
         /* Add padding-top to clear the fixed header. Adjust value if needed based on header height. */
         padding-top: 4.5rem;
         transition: padding-left 0.3s ease;
     }
     body.sidebar-open {
         padding-left: 16rem;
     }

     /* Style for form errors */
      .form-error {
         color: #dc3545; /* red-600 */
         font-size: 0.875em; /* text-sm */
         margin-top: 0.25em;
         display: block;
     }
      .form-control.is-invalid {
          border-color: #dc3545; /* red-600 */
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

</style>

<div class="w-full max-w-screen-xl mx-auto px-4 py-8">

    <h1 class="text-xl md:text-2xl font-bold text-gray-800 mb-6 text-center">Create New Event Announcement</h1>

    <!-- Operation Message Display -->
     <?php
     if (!empty($operation_message)) {
         $message_type = 'info';
          $msg_lower = strtolower(strip_tags($operation_message));
          if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false) {
               $message_type = 'success';
          } elseif (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'access denied') !== false) {
               $message_type = 'error';
          } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'please correct the errors') !== false) {
               $message_type = 'warning';
          }
         echo "<div class='message-box " . $message_type . "' role='alert'>" . $operation_message . "</div>";
     }
     ?>

    <div class="bg-white p-6 sm:p-8 rounded-lg shadow-xl w-full max-w-md mx-auto">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-4">
                <label for="event_name" class="block text-gray-700 text-sm font-bold mb-2">Event Name:</label>
                <input type="text" name="event_name" id="event_name" class="form-control shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($event_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_name); ?>">
                <span class="form-error"><?php echo $event_name_err; ?></span>
            </div>

            <div class="mb-4">
                <label for="event_description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                <textarea name="event_description" id="event_description" rows="4" class="form-control shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($event_description_err)) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($event_description); ?></textarea>
                <span class="form-error"><?php echo $event_description_err; ?></span>
            </div>

             <div class="mb-6">
                <label for="event_date_time" class="block text-gray-700 text-sm font-bold mb-2">Date and Time:</label>
                <input type="datetime-local" name="event_date_time" id="event_date_time" class="form-control shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline <?php echo (!empty($event_date_time_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_date_time_local ?? ''); ?>"> <!-- Use $event_date_time_local for input value -->
                <span class="form-error"><?php echo $event_date_time_err; ?></span>
            </div>


            <div class="flex items-center justify-between">
                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Add Event
                </button>
                 <a href="./admin_dashboard.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>

<?php
// Include the footer file
require_once "./admin_footer.php";
?>