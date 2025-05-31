<?php
// School/admin/all_student_results.php

// Start the session
session_start();

// Require database configuration
require_once "../config.php";

// --- Authentication/Authorization Check ---
// Check if user is logged in and is ADMIN or Principal
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
    // Set a message to be displayed via the toast system on the dashboard
    $_SESSION['operation_message'] = "<p class='text-red-600'>Access denied. Only Admins and Principals can view all student results.</p>";
    header("location: admin_dashboard.php");
    exit;
}

// Set the page title *before* including the header (used in admin_header.php)
$pageTitle = "All Student Exam Results";

// --- Variables for Messages (using the toast system) ---
$toast_message = "";
$toast_type = ""; // 'success', 'error', 'warning', 'info'

// Check for operation messages set in other pages (like add result, edit result)
// This block processes a message from a previous page load/redirect
if (isset($_SESSION['operation_message'])) {
    $msg = $_SESSION['operation_message'];
    $msg_lower = strtolower(strip_tags($msg)); // Use strip_tags for safety before checking keywords

     // Determine message type based on keywords
     if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
          $toast_type = 'success';
     } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'problem') !== false) {
          $toast_type = 'error';
     } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) {
          $toast_type = 'warning';
     } else {
          $toast_type = 'info'; // Default to info if no specific keyword matches
     }
    $toast_message = strip_tags($msg); // Pass the stripped message to JS
    unset($_SESSION['operation_message']); // Clear the session message
}


// --- Filtering Logic ---
$available_years = [];
$available_classes = [];
// Get selected year and class from GET request, defaulting to empty
$selected_year = $_GET['academic_year'] ?? '';
$selected_class = $_GET['current_class'] ?? '';

// Check database connection status before proceeding with DB operations
if ($link === false) {
     // Handle database connection error early for filters and data fetch
     // Set a prominent error message if DB connection failed
     $toast_message = "Database connection error. Cannot load filters or results.";
     $toast_type = 'error';
     error_log("All Results DB connection failed: " . mysqli_connect_error());
     // No filters will be fetched if connection fails, $available_years and $available_classes remain empty
} else {
    // Fetch distinct academic years from results that have max_marks > 0
    // This list is primarily used for filtering and determining the "current" year for toppers
    $sql_years = "SELECT DISTINCT academic_year FROM student_exam_results WHERE max_marks > 0 ORDER BY academic_year DESC";
    if ($result_years = mysqli_query($link, $sql_years)) {
        while ($row = mysqli_fetch_assoc($result_years)) {
            $available_years[] = htmlspecialchars($row['academic_year']);
        }
        mysqli_free_result($result_years);
    } else {
         error_log("Error fetching years for filter: " . mysqli_error($link));
    }

    // Fetch distinct classes from students table for filtering
    // This list shows all classes that *have* students, even if those students don't have results yet
    $sql_classes = "SELECT DISTINCT current_class FROM students WHERE current_class IS NOT NULL AND current_class != '' ORDER BY current_class ASC";
     if ($result_classes = mysqli_query($link, $sql_classes)) {
         while ($row = mysqli_fetch_assoc($result_classes)) {
             $available_classes[] = htmlspecialchars($row['current_class']);
         }
         mysqli_free_result($result_classes);
     } else {
          error_log("Error fetching classes for filter: " . mysqli_error($link));
     }

     // Determine the latest academic year found in results with max_marks > 0
     // This is used for the "Topper" calculation section heading
     $current_academic_year = '';
     if (!empty($available_years)) {
         $current_academic_year = $available_years[0]; // Assuming they are fetched in DESC order
     }
}


// --- Main Results Fetch ---
$student_results_raw = []; // Array to hold the raw data fetched from the database
$fetch_results_message = ""; // Message about the result fetching status (will be displayed)

// Only attempt to fetch results if the database connection is successful
if ($link === false) {
    // Message already set in the filter logic block if DB connection failed
} else {
    // Base SQL query to fetch all necessary data
    // We JOIN students to student_exam_results to get student details alongside results
    // We need s.current_class to group by the student's *actual* current class
    $sql_fetch_results = "SELECT
                             s.user_id,
                             s.full_name,
                             s.roll_number,
                             s.current_class, -- Select student's current class from the students table
                             r.academic_year,
                             r.exam_name,
                             r.subject_name,
                             r.marks_obtained,
                             r.max_marks
                         FROM students s
                         JOIN student_exam_results r ON s.user_id = r.student_id"; // Use JOIN to only include students who have results

    $where_clauses = []; // Array to build dynamic WHERE clause
    $param_types = ""; // String for mysqli_stmt_bind_param types
    $param_values = []; // Array for mysqli_stmt_bind_param values

    // Add filters to WHERE clause if they are selected
    // Note: Filters are applied to the student record (s.current_class) and result record (r.academic_year)
    // If you needed to filter by the class the student was *in* during the exam (if stored in results),
    // you would need a field like r.class_at_exam and use that in the where clause.
    // Current filter uses s.current_class as per the form/GET parameters.
    if (!empty($selected_year)) {
        $where_clauses[] = "r.academic_year = ?";
        $param_types .= "s"; // 's' for string
        $param_values[] = $selected_year; // Add the selected year to parameters
    }
    if (!empty($selected_class)) {
        // IMPORTANT: Filtering by s.current_class means the result records fetched will ONLY be for students
        // whose *current* class matches the filter. This is the expected behavior for the filter dropdown.
        $where_clauses[] = "s.current_class = ?";
        $param_types .= "s"; // 's' for string
        $param_values[] = $selected_class; // Add the selected class to parameters
    }

    // Build the final query by adding the WHERE clause if needed
    if (!empty($where_clauses)) {
        $sql_fetch_results .= " WHERE " . implode(" AND ", $where_clauses); // Join clauses with AND
    }

    // Add ordering for logical processing (Year DESC, Student Name ASC, Exam ASC, Subject ASC)
    // Ordering by s.current_class isn't necessary for the new grouping logic, as it uses a lookup
    // but sorting by student name first helps when aggregating.
    $sql_fetch_results .= " ORDER BY r.academic_year DESC, s.full_name ASC, r.exam_name ASC, r.subject_name ASC";

    // Prepare the SQL statement for security against SQL injection
    if ($stmt_results = mysqli_prepare($link, $sql_fetch_results)) {
         // Bind parameters if there are any WHERE clauses
         if (!empty($param_types)) {
             // Need to use call_user_func_array because the number of parameters is variable
             // Parameters for mysqli_stmt_bind_param must be passed by reference, hence the '&'
             $bind_params = [$param_types]; // Start with the type string
             foreach ($param_values as &$value) { // Loop through values, get reference
                 $bind_params[] = &$value; // Add reference to bind_params array
             }
             // Call bind_param dynamically, passing $stmt_results first, then the bind_params array
             call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_results], $bind_params));
         }

         // Execute the prepared statement
         if (mysqli_stmt_execute($stmt_results)) {
             // Get the result set from the executed statement
             $result_results = mysqli_stmt_get_result($stmt_results);

             // Check if we got results
             if ($result_results) { // Check result_results object exists
                 $num_rows = mysqli_num_rows($result_results);
                 if ($num_rows > 0) {
                     // Fetch all results as an associative array
                     $student_results_raw = mysqli_fetch_all($result_results, MYSQLI_ASSOC);
                     // Set the success message including the count
                     $fetch_results_message = "Fetched " . $num_rows . " subject results matching filters.";
                 } else {
                     // No results found matching the criteria
                     $fetch_results_message = "No exam results found matching the selected filters.";
                     $student_results_raw = []; // Ensure the array is empty
                 }
                mysqli_free_result($result_results); // Free the result set memory

             } else {
                 // Handle errors getting result set
                 $fetch_results_message = "<p class='text-red-600'>Error retrieving result set: " . mysqli_stmt_error($stmt_results) . "</p>";
                  error_log("All Results get result failed: " . mysqli_stmt_error($stmt_results));
                  $student_results_raw = []; // Ensure empty on error
             }

         } else {
             // Handle statement execution errors
             $fetch_results_message = "<p class='text-red-600'>Error fetching exam results: " . mysqli_stmt_error($stmt_results) . "</p>";
              error_log("All Results fetch query failed: " . mysqli_stmt_error($stmt_results));
              $student_results_raw = []; // Ensure empty on error
         }
         // Close the prepared statement
         mysqli_stmt_close($stmt_results);
    } else {
        // Handle statement preparation errors
        $fetch_results_message = "<p class='text-red-600'>Error preparing results fetch statement: " . mysqli_error($link) . "</p>";
         error_log("All Results prepare fetch statement failed: " . mysqli_error($link));
         $student_results_raw = []; // Ensure empty on error
    }
}


// --- Data Processing & Grouping for Class-Based Tables (FIXED LOGIC) ---
// Process the raw data to first aggregate results per student per exam,
// then group these aggregated results by the student's CURRENT class (from the students table).
$results_structured_by_current_class = []; // The final structure for display
$temp_student_exam_agg = []; // Temporary structure to aggregate subject marks/totals per student per exam
$student_info_lookup = []; // Lookup to store student details including their current_class (from students table)

if (!empty($student_results_raw)) {
     foreach ($student_results_raw as $row) {
         $year = $row['academic_year'];
         $exam = $row['exam_name'];
         $student_id = $row['user_id'];
         $subject = $row['subject_name'];
         $marks = (float)($row['marks_obtained'] ?? 0);
         $max_marks = (float)($row['max_marks'] ?? 0);
         // Crucially, get the current_class as selected from the students table (s.current_class)
         $student_actual_current_class = $row['current_class'];

         // Store student info (name, roll, current_class) based on user_id lookup
         // This assumes s.current_class is consistent for a user_id in the result set from this query
         if (!isset($student_info_lookup[$student_id])) {
             $student_info_lookup[$student_id] = [
                 'user_id' => $student_id,
                 'full_name' => $row['full_name'],
                 'roll_number' => $row['roll_number'],
                 'current_class' => $student_actual_current_class // This is the key current class from students table
             ];
         }

         // Aggregate subject marks and totals per student per exam in a temporary structure
         if (!isset($temp_student_exam_agg[$year][$exam][$student_id])) {
             $temp_student_exam_agg[$year][$exam][$student_id] = [
                 'subject_marks' => [], // To store marks keyed by subject name for this student in THIS exam
                 'total_marks_obtained' => 0,
                 'total_max_marks' => 0, // Total max marks for THIS EXAM for THIS STUDENT (sum of max_marks of subjects)
             ];
         }
         // Store the mark for this subject for this student in this exam
         // Only add subject/mark if max_marks > 0, otherwise it won't contribute to the percentage anyway
         if ($max_marks > 0) {
             $temp_student_exam_agg[$year][$exam][$student_id]['subject_marks'][$subject] = $marks;
             // Accumulate total marks and max marks for this student in this exam from relevant subjects
             $temp_student_exam_agg[$year][$exam][$student_id]['total_marks_obtained'] += $marks;
             $temp_student_exam_agg[$year][$exam][$student_id]['total_max_marks'] += $max_marks;
         } else {
             // Handle subjects with 0 max_marks if needed (e.g., just list them with their score)
             // For now, we skip them for percentage calculation and column headers derived from $temp_student_exam_agg
             // but might still show them if added to subject_marks here
              $temp_student_exam_agg[$year][$exam][$student_id]['subject_marks'][$subject] = $marks; // Still store the mark
         }


     } // End foreach $student_results_raw

     // --- Build the Final Grouped Structure using the student's CURRENT class ---
     // Iterate through the temporary aggregated data per student per exam
     if (!empty($temp_student_exam_agg)) {
         foreach ($temp_student_exam_agg as $year => $exams_in_year_agg) {
             foreach ($exams_in_year_agg as $exam_name => $students_in_exam_agg) {
                 foreach ($students_in_exam_agg as $student_id => $agg_data) {

                     // Get the student's actual current class from the lookup table
                     // Fallback to a default if for some reason the student info wasn't stored
                     $student_info = $student_info_lookup[$student_id] ?? ['full_name' => 'N/A', 'roll_number' => 'N/A', 'current_class' => 'Unknown Class'];
                     $student_actual_current_class = $student_info['current_class'];

                     // Initialize the final structure entry for this year, current class, and exam
                     if (!isset($results_structured_by_current_class[$year])) $results_structured_by_current_class[$year] = [];
                     // Grouping by the student's current class
                     if (!isset($results_structured_by_current_class[$year][$student_actual_current_class])) $results_structured_by_current_class[$year][$student_actual_current_class] = [];
                     if (!isset($results_structured_by_current_class[$year][$student_actual_current_class][$exam_name])) {
                          $results_structured_by_current_class[$year][$student_actual_current_class][$exam_name] = [
                              'subjects_in_exam' => [], // Subjects list for the header of THIS table
                              'students' => [] // Students data for THIS table
                          ];
                     }

                     // Calculate percentage for the student for this exam
                     $total_obtained = $agg_data['total_marks_obtained'];
                     $total_max = $agg_data['total_max_marks'];
                     // Only calculate percentage if total max marks for the exam is > 0
                     $percentage = ($total_max > 0) ? ($total_obtained / $total_max) * 100 : 0;

                     // Add the student's aggregated data to the final structure under their current class
                     $results_structured_by_current_class[$year][$student_actual_current_class][$exam_name]['students'][$student_id] = [
                         'user_id' => $student_id,
                         'full_name' => $student_info['full_name'],
                         'roll_number' => $student_info['roll_number'],
                         'subject_marks' => $agg_data['subject_marks'], // Aggregated subject marks
                         'total_marks_obtained' => $total_obtained,
                         'total_max_marks' => $total_max,
                         'percentage' => $percentage
                     ];

                     // Collect subjects for the header of THIS table (based on student's current class)
                     // This ensures the header includes all subjects taken by *any* student whose results
                     // are grouped into this specific Year/Current_Class/Exam table.
                     foreach (array_keys($agg_data['subject_marks']) as $subject) {
                          $results_structured_by_current_class[$year][$student_actual_current_class][$exam_name]['subjects_in_exam'][$subject] = $subject;
                     }

                 } // End student_id loop (temp_student_exam_agg)
             } // End exam_name loop (temp_student_exam_agg)
         } // End year loop (temp_student_exam_agg)
     }
}


// --- Finalize Grouped Data (Sort Subjects and Students) ---
// The variable used for display in the HTML is $results_structured.
// Replace it with the new structure grouped by current class.
$results_structured = $results_structured_by_current_class;

// Sort the final structure for consistent display order
krsort($results_structured); // Sort years descending
foreach ($results_structured as $year => &$classes_in_year) {
    ksort($classes_in_year); // Sort classes ascending
    foreach ($classes_in_year as $class => &$exams_in_class) {
         ksort($exams_in_class); // Sort exams ascending
         foreach ($exams_in_class as $exam => &$exam_data) {

             // Sort subjects alphabetically for consistent column order in the table header
             ksort($exam_data['subjects_in_exam']);
             // Convert the keys (which are the subject names) into a simple list for display
             $exam_data['subjects_in_exam'] = array_keys($exam_data['subjects_in_exam']);


             // Sort students within this exam table (e.g., by name)
              usort($exam_data['students'], function($a, $b) {
                  // Sort primarily by name, secondary by roll number if names are same
                  $nameComparison = strcmp($a['full_name'], $b['full_name']);
                  if ($nameComparison === 0) {
                      // Handle potential non-numeric roll numbers safely with strnatcmp
                      return strnatcmp($a['roll_number'] ?? '', $b['roll_number'] ?? '');
                  }
                  return $nameComparison;
              });
         }
         unset($exam_data); // Unset the reference
    }
    unset($classes_in_year); // Unset the reference
}
// $results_structured is now finalized and ready for display.


// --- Topper Calculation (for the current academic year) ---
// This section calculates school-wide toppers based on the average percentage across all exams
// recorded for each student within the 'current' academic year (the latest one found).
$school_toppers = [];
$topper_message = ''; // Message displayed in the topper section header

// Only attempt to calculate toppers if DB connection is successful and a current academic year was found
if ($link === false) {
     // Message already set if DB failed
} else if (!empty($current_academic_year)) {
     // SQL query to calculate sum of obtained and max marks per student for the current year,
     // then calculate the average percentage and get the top 3.
     // We include current_class and roll_number for display in the topper list.
     $sql_toppers = "SELECT
                         s.user_id,
                         s.full_name,
                         s.roll_number,
                         s.current_class,
                         SUM(r.marks_obtained) AS total_obtained_year,
                         SUM(r.max_marks) AS total_max_year,
                         -- Calculate average percentage for the year (sum_obtained / sum_max)
                         (SUM(r.marks_obtained) / NULLIF(SUM(r.max_marks), 0)) * 100 AS average_percentage_year -- Use NULLIF to avoid division by zero
                     FROM students s
                     JOIN student_exam_results r ON s.user_id = r.student_id
                     WHERE r.academic_year = ? -- Filter for the current academic year
                     AND r.max_marks > 0 -- Only include subject results where max marks are recorded (needed for percentage)
                     GROUP BY s.user_id, s.full_name, s.roll_number, s.current_class -- Group by student and their current class/name
                     HAVING SUM(r.max_marks) > 0 -- Ensure total max marks for the year is > 0
                     ORDER BY average_percentage_year DESC -- Order by percentage descending to get toppers
                     LIMIT 3"; // Limit to the top 3 students

     // Prepare the topper statement
     if ($stmt_toppers = mysqli_prepare($link, $sql_toppers)) {
         // Bind the current academic year parameter
         mysqli_stmt_bind_param($stmt_toppers, "s", $current_academic_year);

         if (mysqli_stmt_execute($stmt_toppers)) {
             // Get the result set
             $result_toppers = mysqli_stmt_get_result($stmt_toppers);
             // Fetch all top students
             while ($row = mysqli_fetch_assoc($result_toppers)) {
                 $school_toppers[] = $row;
             }
             mysqli_free_result($result_toppers);

             // Set the topper message based on whether toppers were found
             if (!empty($school_toppers)) {
                 $topper_message = "Top 3 students in Academic Year " . htmlspecialchars($current_academic_year);
             } else {
                  // If no toppers were found, check if there were *any* results for the year
                  // If there were results but no toppers (e.g., all max_marks were 0),
                  // show a specific message. Otherwise, the main "No results found" message is sufficient.
                   if ($link !== false) { // Ensure link is still valid
                       $sql_check_results_current_year = "SELECT 1 FROM student_exam_results WHERE academic_year = ? LIMIT 1";
                        if ($stmt_check = mysqli_prepare($link, $sql_check_results_current_year)) {
                             mysqli_stmt_bind_param($stmt_check, "s", $current_academic_year);
                             mysqli_stmt_execute($stmt_check);
                             mysqli_stmt_store_result($stmt_check);
                             if (mysqli_stmt_num_rows($stmt_check) > 0) {
                                  // There are results for the year, but none met the topper criteria (max_marks > 0 per student year total)
                                  $topper_message = "No topper data available for Academic Year " . htmlspecialchars($current_academic_year) . " (check if Max Marks are recorded for results).";
                             } else {
                                  // No results at all for the current year
                                  $topper_message = "No exam results recorded for Academic Year " . htmlspecialchars($current_academic_year) . ".";
                             }
                            mysqli_stmt_close($stmt_check);
                        } else {
                             // Error checking results for the year
                             $topper_message = "Could not check for results in Academic Year " . htmlspecialchars($current_academic_year) . ".";
                             error_log("Topper check results prepare failed: " . mysqli_error($link));
                        }
                   } else {
                        $topper_message = "Cannot check for results due to database connection issue."; // Fallback if link failed
                   }
             }

         } else {
              // Handle topper query execution errors
              error_log("Topper query failed for year " . $current_academic_year . ": " . mysqli_stmt_error($stmt_toppers));
             $topper_message = "<p class='text-red-600'>Error fetching topper data.</p>";
         }
         mysqli_stmt_close($stmt_toppers); // Close the topper statement
     } else { // Check if the link was valid before prepare failed
         // Handle topper statement preparation errors
         error_log("Topper prepare statement failed: " . mysqli_error($link));
         $topper_message = "<p class='text-red-600'>Error preparing topper query.</p>";
     }
     // If link was false, the error was set earlier
} else {
     // This case occurs if there are NO academic years found in the student_exam_results table at all
     $topper_message = "Cannot calculate toppers. No exam results found in the database.";
}


// --- CSV Download Logic ---
// Check if the 'download=csv' GET parameter is set
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    // Re-check authentication for the download request
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'principal')) {
         header("HTTP/1.1 403 Forbidden"); // Send Forbidden status
         echo "Access Denied.";
         exit; // Stop script execution
    }

     // Check DB connection specifically for download
     if ($link === false) {
         error_log("CSV Download DB connection failed: " . mysqli_connect_error());
         header("Content-Type: text/plain"); // Send plain text error
         echo "Error: Database connection failed for download.";
         exit;
     }

    // Re-run the SAME query used for fetching results for display (the raw subject-level data)
    // Downloading raw data is often more useful for analysis than the summarized table view
     $sql_download = $sql_fetch_results; // Use the query string built earlier

    $download_data = []; // Array to hold data for CSV

    // Prepare and execute the download query (identical to the display query)
    if ($stmt_download = mysqli_prepare($link, $sql_download)) {
         if (!empty($param_types)) {
             // Bind parameters again for the download query using call_user_func_array
             $bind_params = [$param_types];
             foreach ($param_values as &$value) {
                 $bind_params[] = &$value;
             }
             call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt_download], $bind_params));
         }
        if (mysqli_stmt_execute($stmt_download)) {
            $result_download = mysqli_stmt_get_result($stmt_download);
            // Fetch data as a simple list of rows (not grouped)
            $download_data = mysqli_fetch_all($result_download, MYSQLI_ASSOC);
            mysqli_free_result($result_download);
        } else {
            error_log("CSV Download fetch query failed: " . mysqli_stmt_error($stmt_download));
            header("Content-Type: text/plain");
            echo "Error fetching data for download.";
            exit;
        }
        mysqli_stmt_close($stmt_download);
    } else {
         error_log("CSV Download prepare fetch statement failed: " . mysqli_error($link));
         header("Content-Type: text/plain");
         echo "Error preparing data query for download.";
         exit;
    }

    // Set HTTP headers for CSV download
    $filename = 'student_results';
    // Add filter details to filename only if filters were applied
    if(!empty($selected_year)) $filename .= '_Year_' . str_replace('-', '_', $selected_year); // Add year to filename
    if(!empty($selected_class)) $filename .= '_Class_' . str_replace([' ', '/'], ['_', '-'], $selected_class); // Add class, replace spaces/slashes
    $filename .= '_' . date('Ymd_His') . '.csv'; // Add timestamp for uniqueness

    header('Content-Type: text/csv'); // Specify CSV content type
    // Force download with the specified filename
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // CSV Header row - these match the selected columns in the SQL query
    fputcsv($output, ['User ID', 'Full Name', 'Roll Number', 'Current Class', 'Academic Year', 'Exam Name', 'Subject Name', 'Marks Obtained', 'Max Marks']);

    // Output the raw data rows
    foreach ($download_data as $row) {
         fputcsv($output, [
             $row['user_id'] ?? '', // Use empty string for null values in CSV
             $row['full_name'] ?? '',
             $row['roll_number'] ?? '',
             $row['current_class'] ?? '',
             $row['academic_year'] ?? '',
             $row['exam_name'] ?? '',
             $row['subject_name'] ?? '',
             $row['marks_obtained'] ?? '',
             $row['max_marks'] ?? ''
         ]);
    }

    fclose($output);

    exit; // Stop execution after sending the CSV file
}


// Close database connection (if it was successfully opened and not closed yet by the download logic or errors)
if (isset($link) && is_object($link) && mysqli_ping($link)) {
     mysqli_close($link);
}

?>

<?php
// Include the header file. This file should:
// - Contain the opening HTML tags (<DOCTYPE html>, <html>, <head>)
// - Set up the fixed header bar (or include sidebar logic that handles it)
// - Define body styles like margin-top for the fixed header
// - Include necessary CSS (like the styles defined below, or link to them)
// - Potentially define helper functions like htmlspecialchars if not using PHP's default
require_once "./admin_header.php";
?>

     <!-- Custom Styles for this page -->
     <style>
         /* Basic body padding to prevent content from being hidden under the fixed header */
         body {
             padding-top: 4.5rem; /* Adjust based on your fixed header height */
             background-color: #f3f4f6; /* Default subtle gray background */
             min-height: 100vh;
              transition: padding-left 0.3s ease; /* Smooth transition for padding when sidebar opens/closes */
         }
         /* This style is controlled by JavaScript to add padding when the sidebar is open */
         body.sidebar-open {
             padding-left: 16rem; /* Adjust based on your sidebar width (e.g., md:w-64 in Tailwind = 16rem) */
         }


         /* Style for the fixed header */
         .fixed-header {
              position: fixed;
              top: 0;
              left: 0; /* Default to left 0 */
              right: 0;
              height: 4.5rem; /* Must match body padding-top */
              background-color: #ffffff;
              box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); /* Standard shadow */
              padding: 1rem;
              display: flex;
              align-items: center; /* Vertically align items in the header */
              z-index: 10; /* Ensure header stays on top */
              transition: left 0.3s ease; /* Smooth transition when sidebar opens/closes */
         }
          /* Adjust header position when the sidebar is open */
          body.sidebar-open .fixed-header {
              left: 16rem; /* Must match sidebar width */
          }

         /* Main content wrapper, centered with max width */
          .main-content-wrapper {
              width: 100%;
              max-width: 1280px; /* Equivalent to Tailwind screen-xl */
              margin-left: auto;
              margin-right: auto;
              padding-left: 1rem; /* px-4 */
              padding-right: 1rem; /* px-4 */
              padding-top: 2rem; /* py-8 - adjust as needed below header */
              padding-bottom: 2rem; /* py-8 */
              /* No need for margin-top here if body padding-top is set correctly */
              /* Margin top can be added to the first element INSIDE the wrapper if needed */
          }
           @media (min-width: 768px) { /* md breakpoint */
                .main-content-wrapper {
                    padding-left: 2rem; /* md:px-8 */
                    padding-right: 2rem; /* md:px-8 */
                }
           }


         /* Styles for the main results container */
         .results-container {
             margin-top: 1.5rem; /* Add some space below the filter/topper sections and message */
         }

         /* Styles for each Academic Year section */
         .year-section {
             background-color: #ffffff; /* White background */
             padding: 1.5rem;
             border-radius: 0.5rem; /* rounded-lg */
             box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Standard shadow */
             margin-bottom: 2rem; /* Space between year blocks */
         }

         .year-section h3 {
             font-size: 1.5rem; /* text-2xl */
             font-weight: 700; /* bold */
             color: #1f2937; /* gray-900 */
             margin-bottom: 1.5rem;
             padding-bottom: 0.75rem;
             border-bottom: 2px solid #e5e7eb; /* gray-200 border */
         }

         /* Styles for each Class section within a year */
         .class-section {
             margin-bottom: 1.5rem; /* Space between classes within a year */
         }

         .class-section h4 {
             font-size: 1.25rem; /* text-xl */
             font-weight: 600; /* semibold */
             color: #374151; /* gray-700 */
             margin-bottom: 1rem;
             padding-bottom: 0.5rem;
             border-bottom: 1px dashed #d1d5db; /* gray-300 dashed border */
         }

          /* Style for the Exam section within a class */
          .exam-section {
               margin-bottom: 1.5rem; /* Space between different exam tables */
               padding: 1rem;
               background-color: #f9fafb; /* gray-50 */
               border: 1px solid #e5e7eb; /* gray-200 */
               border-radius: 0.375rem; /* rounded-md */
          }
          .exam-section h5 {
               font-size: 1rem; /* text-base */
               font-weight: 700; /* bold */
               color: #1f2937; /* gray-900 */
               margin-bottom: 0.75rem;
               padding-bottom: 0.5rem;
               border-bottom: 1px solid #d1d5db; /* gray-300 border */
          }


         /* Styles for the main results table (now class/exam based) */
         .exam-table {
             width: 100%; /* Full width */
             border-collapse: collapse; /* Remove space between borders */
             margin-top: 0.75rem;
             /* margin-bottom: 1rem; */ /* Removed, margin is on the exam-section div */
             box-shadow: 0 1px 2px rgba(0,0,0,0.05); /* subtle shadow */
         }

         .exam-table th,
         .exam-table td {
             padding: 0.6rem 0.8rem; /* Vertical and horizontal padding */
             border: 1px solid #e5e7eb; /* gray-200 border */
             text-align: left;
             font-size: 0.875rem; /* text-sm */
             word-break: break-word; /* Allow long subject names/data to wrap */
         }

         .exam-table th {
             background-color: #f3f4f6; /* gray-100 background */
             font-weight: 600; /* semibold */
             color: #374151; /* gray-700 text */
         }

         .exam-table tbody tr:nth-child(even) {
             background-color: #fafafa; /* Lighter gray for striped rows */
         }
          .exam-table td {
              color: #1f2937; /* gray-900 text */
          }

          /* Styles for the table footer (Totals and Percentage) - NOT USED IN THIS TABLE STRUCTURE */
          /* .exam-table tfoot td { ... } */


         /* Styles for the Filter Form */
         .filter-form {
             background-color: #ffffff; /* White background */
             padding: 1.5rem;
             border-radius: 0.5rem; /* rounded-lg */
             box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Standard shadow */
             margin-bottom: 2rem; /* Space below the form */
             display: flex; /* Use flexbox for layout */
             flex-wrap: wrap; /* Wrap items to the next line if needed */
             gap: 1rem; /* Space between flex items */
             align-items: flex-end; /* Align items to the bottom */
         }
         .filter-form div {
             flex: 1 1 150px; /* Allow items to grow and shrink, with a base width */
             min-width: 150px; /* Ensure minimum width for smaller screens */
         }
         .filter-form label {
             display: block; /* Label on its own line */
             font-size: 0.875rem; /* text-sm */
             font-weight: 500; /* medium */
             color: #374151; /* gray-700 */
             margin-bottom: 0.25rem; /* space below label */
         }
         .filter-form select,
         .filter-form input[type="submit"] {
             width: 100%; /* Full width within their flex container */
             padding: 0.5rem 0.75rem; /* py-2 px-3 */
             border: 1px solid #d1d5db; /* gray-300 border */
             border-radius: 0.375rem; /* rounded-md */
             font-size: 0.875rem; /* text-sm */
             box-sizing: border-box; /* Include padding and border in element's total width */
         }
          /* Custom style for select dropdown arrow */
          .filter-form select {
              appearance: none; /* Remove default arrow */
               background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3e%3cpath d='M7 7l3-3 3 3m0 6l-3 3-3-3' stroke='%239ca3af' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'%3e%3c/path%3e%3c/svg%3e"); /* Custom arrow SVG */
              background-repeat: no-repeat;
              background-position: right 0.5rem center; /* Position arrow on the right */
              background-size: 1.5em auto; /* Size of the arrow */
              padding-right: 2.5rem; /* Make space for the custom arrow */
          }
          .filter-form input[type="submit"] {
              cursor: pointer; /* Indicate clickable */
              background-color: #4f46e5; /* indigo-600 */
              color: white;
              font-weight: 600;
              transition: background-color 0.15s ease-in-out; /* Smooth hover effect */
              border: none; /* Remove default border */
          }
           .filter-form input[type="submit"]:hover {
               background-color: #4338ca; /* indigo-700 */
           }
           /* Style for the Download CSV button/link */
           .download-button {
               display: inline-flex; /* Align icon and text */
               align-items: center;
               justify-content: center; /* Center content if it's the only item in flex */
               background-color: #10b981; /* green-500 */
               color: white;
               font-weight: 600;
               padding: 0.5rem 0.75rem; /* py-2 px-3 */
               border-radius: 0.375rem; /* rounded-md */
               font-size: 0.875rem; /* text-sm */
               text-decoration: none; /* Remove underline from link */
               transition: background-color 0.15s ease-in-out;
               border: none;
                min-width: 150px; /* Match filter select/input min-width */
               box-sizing: border-box; /* Include padding/border in width */
           }
            .download-button:hover {
                background-color: #059669; /* green-600 */
            }
            .filter-form .flex-grow {
                flex-grow: 1; /* Allows download button container to take available space */
            }
             @media (min-width: 768px) {
                 .filter-form .md\:flex-grow-0 { flex-grow: 0; } /* Override grow on medium screens */
                 .filter-form .md\:ml-auto { margin-left: auto; } /* Push to right on medium screens */
                 .download-button { width: auto; } /* Allow button to size based on content on medium+ */
             }


         /* Styles for the Topper Section */
          .topper-section {
              background-color: #ecfdf5; /* green-50 */
              padding: 1.5rem;
              border-radius: 0.5rem; /* rounded-lg */
              border: 1px solid #a7f3d0; /* green-200 border */
              box-shadow: 0 1px 3px rgba(0,0,0,0.1); /* Standard shadow */
              margin-bottom: 2rem; /* Space below section */
          }
          .topper-section h3 {
               font-size: 1.25rem; /* text-xl */
               font-weight: 700; /* bold */
               color: #065f46; /* green-800 text */
               margin-bottom: 1rem;
               padding-bottom: 0.5rem;
               border-bottom: 1px solid #d1fae5; /* green-100 border */
          }
           .topper-list {
               list-style: none; /* Remove bullet points */
               padding: 0;
               margin: 0;
           }
           .topper-list li {
               background-color: #d1fae5; /* green-100 background */
               padding: 0.75rem 1rem; /* py-3 px-4 */
               border-radius: 0.375rem; /* rounded-md */
               margin-bottom: 0.5rem; /* Space between list items */
               display: flex; /* Use flexbox for horizontal layout */
               justify-content: space-between; /* Space out rank, name, percentage */
               align-items: center; /* Vertically center items */
               border: 1px solid #a7f3d0; /* green-200 border */
               flex-wrap: wrap; /* Allow wrapping on very small screens */
           }
           .topper-list li:last-child {
               margin-bottom: 0; /* No bottom margin on the last item */
           }
            .topper-list .rank {
                font-weight: 700; /* bold */
                color: #047857; /* green-700 text */
                margin-right: 1rem;
                flex-shrink: 0; /* Prevent shrinking */
            }
            .topper-list .name-class {
                 flex-grow: 1; /* Allow this part to take up space */
                 color: #065f46; /* green-800 text */
                 font-weight: 600; /* semibold */
                 font-size: 0.9rem; /* text-sm */
                 word-break: break-word; /* Prevent long names from overflowing */
                 min-width: 100px; /* Allow name/class to take space */
            }
             .topper-list .percentage {
                 font-weight: 700; /* bold */
                 color: #047857; /* green-700 text */
                 margin-left: 1rem;
                 flex-shrink: 0; /* Prevent shrinking */
                 text-align: right; /* Align percentage to the right */
             }
             .topper-list .name-class a {
                  font-weight: normal; /* Normal weight for the link */
                  color: #4f46e5; /* indigo-600 */
             }
             .topper-list .name-class a:hover {
                 text-decoration: underline;
             }


        /* General message box styles (fallback/alternative to toasts) */
         .message-box {
             padding: 1rem; border-radius: 0.5rem; border: 1px solid transparent; margin-bottom: 1.5rem; text-align: center;
         }
          .message-box.success { color: #065f46; background-color: #d1fae5; border-color: #a7f3d0; } /* green */
           .message-box.error { color: #b91c1c; background-color: #fee2e2; border-color: #fca5a5; } /* red */
           .message-box.warning { color: #b45309; background-color: #fffce0; border-color: #fde68a; } /* yellow/amber */
           .message-box.info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; } /* cyan/blue */

         /* Toast Notification Styles (positioned fixed) */
         .toast-container {
             position: fixed; top: 1rem; right: 1rem; z-index: 100; display: flex; flex-direction: column; gap: 0.5rem; pointer-events: none; /* Allows clicks to pass through container */
             max-width: 90%; /* Prevent toasts from being too wide on small screens */
         }
         .toast {
             background-color: #fff; color: #333; padding: 0.75rem 1.25rem; border-radius: 0.375rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
             opacity: 0; transform: translateX(100%); transition: opacity 0.3s ease-out, transform 0.3s ease-out;
             pointer-events: auto; /* Re-enable pointer events for the toast itself */
             min-width: 200px; max-width: 350px; display: flex; align-items: center;
             word-break: break-word; /* Prevent long messages from overflowing */
         }
         .toast.show { opacity: 1; transform: translateX(0); } /* State for showing toast */
         /* Border color indicates type */
         .toast-success { border-left: 5px solid #10b981; color: #065f46; } /* green */
         .toast-error { border-left: 5px solid #ef4444; color: #991b1b; } /* red */
         .toast-warning { border-left: 5px solid #f59e0b; color: #9a3412; } /* amber */
         .toast-info { border-left: 5px solid #3b82f6; color: #1e40af; } /* blue */

         .toast .close-button {
             margin-left: auto; /* Push button to the right */
             background: none;
             border: none;
             color: inherit; /* Inherit color from parent toast */
             font-size: 1.2rem;
             cursor: pointer;
             padding: 0 0.25rem;
             line-height: 1; /* Center the '×' vertically */
             font-weight: bold;
         }

         /* Background classes */
          .gradient-background-blue-cyan { background: linear-gradient(to right, #60a5fa, #22d3ee); } /* blue-400 to cyan-400 */
          .gradient-background-purple-pink { background: linear-gradient(to right, #a78bfa, #f472b6); } /* purple-400 to pink-400 */
          .gradient-background-green-teal { background: linear-gradient(to right, #34d399, #2dd4bf); } /* green-400 to teal-400 */
          .solid-bg-gray { background-color: #f3f4f6; } /* gray-100 */
          .solid-bg-indigo { background-color: #4f46e5; } /* indigo-600 */


     </style>

      <!-- JavaScript for sidebar toggle and toast notifications -->
      <script>
         // Execute script when the DOM is fully loaded
         document.addEventListener('DOMContentLoaded', (event) => {
             // --- Sidebar Toggle JS ---
             // Find the sidebar toggle button (assuming it's placed somewhere visible, perhaps in the header)
             // and the body element.
             const sidebarToggleOpen = document.getElementById('admin-sidebar-toggle-open'); // ID of the button to open sidebar
             const body = document.body;
             const fixedHeader = document.querySelector('.fixed-header'); // Get the fixed header element

             // Check if the required elements exist before adding event listeners
             if (sidebarToggleOpen && body && fixedHeader) {
                 sidebarToggleOpen.addEventListener('click', function() {
                     // Toggle the 'sidebar-open' class on the body. CSS handles padding/position changes based on this class.
                     body.classList.toggle('sidebar-open');
                 });
             } else {
                 // Log an error if elements are not found, useful for debugging
                 console.error("Sidebar toggle button or body or fixed header element not found. Sidebar toggle might not work.");
                 // Note: The sidebar itself might add a close button with a different ID, handled in admin_sidebar.php's JS
             }

              // --- Background Changer Function ---
             // Define the setBackground function. This function changes the body's background class.
             function setBackground(className) {
                 const body = document.body;
                 // Remove all existing background classes to ensure only one is applied
                 body.classList.forEach(cls => {
                     if (cls.startsWith('gradient-background-') || cls.startsWith('solid-bg-')) {
                         body.classList.remove(cls);
                     }
                 });
                 // Add the new background class
                 body.classList.add(className);
                 // Optionally save the user's preference to the browser's local storage
                 localStorage.setItem('dashboardBackground', className); // Using a consistent key
             }

              // Load the saved background preference when the page loads
              const savedBackgroundClass = localStorage.getItem('dashboardBackground');
              if (savedBackgroundClass) {
                  // Basic validation of the saved class name
                   if (savedBackgroundClass.startsWith('gradient-background-') || savedBackgroundClass.startsWith('solid-bg-')) {
                       setBackground(savedBackgroundClass);
                   } else {
                       // If the saved class name is not recognized, remove it from storage
                       localStorage.removeItem('dashboardBackground');
                       // You could optionally set a default background here if none is saved or the saved one is invalid
                       // setBackground('solid-bg-gray'); // Example: set default gray
                   }
              } else {
                  // Optional: Set a default background if no preference is saved
                  // setBackground('gradient-background-blue-cyan'); // Example: set default gradient
                  // Note: If you set a default in CSS, you might not need this else block
              }


             // --- Toast Notification JS ---
              // Get the container where toasts will be placed
              const toastContainer = document.getElementById('toastContainer');
              if (!toastContainer) {
                  console.error('Toast container #toastContainer not found. Toast notifications will not work.');
              }

              // Function to display a toast message
              function showToast(message, type = 'info', duration = 5000) {
                  // Ensure we have a message and the container exists
                  if (!message || !toastContainer) return;

                  // Create the toast element
                  const toast = document.createElement('div');
                  toast.classList.add('toast', `toast-${type}`); // Add base class and type class
                  toast.textContent = message; // Set text content (safer than innerHTML)

                  // Create a close button for the toast
                  const closeButton = document.createElement('button');
                  closeButton.classList.add('close-button');
                  closeButton.textContent = '×'; // Multiplication sign often used for close
                  // Add click listener to remove the toast
                  closeButton.onclick = () => toast.remove();
                  // Append the close button to the toast
                  toast.appendChild(closeButton);

                  // Add the new toast to the container
                  toastContainer.appendChild(toast);

                  // Use requestAnimationFrame to wait for the element to be added to the DOM
                  // before adding the 'show' class to trigger the transition
                  requestAnimationFrame(() => {
                      toast.classList.add('show');
                  });


                  // Auto-hide the toast after the specified duration (if duration > 0)
                  if (duration > 0) {
                      setTimeout(() => {
                          // Remove the 'show' class to start the fade/slide out transition
                          toast.classList.remove('show');
                          // Remove the element from the DOM after the transition is complete
                          // The 'once: true' option ensures the listener is removed automatically
                          toast.addEventListener('transitionend', () => toast.remove(), { once: true });
                      }, duration);
                  }
              }

              // Trigger the toast display on page load if there is a message from PHP
              // The PHP variables $toast_message and $toast_type are JSON encoded here
              const phpMessage = <?php echo json_encode($toast_message ?? ''); ?>; // Get message (default to empty string if not set)
              const messageType = <?php echo json_encode($toast_type ?? 'info'); ?>; // Get type (default to 'info' if not set)

              // If a PHP message exists, show the toast
              if (phpMessage) {
                  showToast(phpMessage, messageType);
              }
              // --- End Toast Notification JS ---

         }); // End DOMContentLoaded event listener
      </script>
</head>
<!-- Apply default background class if no preference is loaded or saved -->
<!-- This ensures a background is always set, overridden by JS if a saved preference exists -->
<body class="min-h-screen solid-bg-gray"> <!-- Default background -->

    <?php
    // Include the admin sidebar.
    // Assumes admin_sidebar.php handles its own rendering and potentially positioning.
    // It should also contain the button with id="admin-sidebar-toggle-open" for the JS above to work.
    $sidebar_path = "./admin_sidebar.php";
     if (file_exists($sidebar_path)) {
         require_once $sidebar_path;
     } else {
         // Fallback/error message if sidebar file is not found
         // This block manually renders a simplified header if the sidebar is missing
         echo '<div class="fixed-header">';
         // Display a placeholder for the toggle button or just the title
         echo '<h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">All Student Exam Results (Sidebar file missing!)</h1>';
         echo '<span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold">' . htmlspecialchars($_SESSION['name'] ?? 'Admin') . '</span></span>';
         echo '<a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>';
         echo '</div>';
         // Add a message box alerting the user about the missing file
         echo '<div class="w-full max-w-screen-xl mx-auto px-4 py-8" style="margin-top: 4.5rem;">'; // Add margin to push content down
         echo '<div class="message-box error" role="alert">Admin sidebar file not found! Check path: `' . htmlspecialchars($sidebar_path) . '`</div>';
         echo '</div>';
         // Continue script execution without exiting
     }
    ?>

     <!-- Toast Container (Positioned fixed using CSS) -->
     <!-- This container will hold dynamically created toast notifications -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts will be added here by the JavaScript -->
    </div>

     <!-- Fixed Header/Navbar content -->
     <!-- This block should ONLY be rendered if admin_sidebar.php was found AND it does NOT render its own fixed header. -->
     <!-- If your admin_sidebar.php *does* render a fixed header, remove this block. -->
    <?php if (file_exists("./admin_sidebar.php")): // Check if sidebar file exists ?>
         <!-- Assuming the sidebar file *doesn't* render the fixed header itself -->
         <div class="fixed-header">
             <!-- Sidebar toggle button -->
              <!-- This button ID must match the one in the JS for the sidebar toggle to work -->
              <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-4 md:mr-6" aria-label="Toggle sidebar">
                  <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
              </button>

              <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex-grow">All Student Exam Results</h1>
              <span class="ml-auto text-sm text-gray-700 hidden md:inline">Logged in as: <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span></span>
                <a href="../logout.php" class="ml-4 text-red-600 hover:text-red-800 hover:underline transition duration-150 ease-in-out text-sm font-medium hidden md:inline">Logout</a>
         </div>
    <?php endif; ?>


    <!-- Main content wrapper -->
     <!-- This div contains all the primary content of the page (filters, toppers, results) -->
     <!-- The CSS class 'main-content-wrapper' handles its centering and responsiveness -->
     <!-- Body padding handles the space for the fixed header -->
     <div class="main-content-wrapper">

         <?php
         // Display general operation messages that weren't handled by the toast system (less likely now)
         // This acts as a fallback display for messages.
         // It checks if $operation_message is set AND it wasn't the message stored in session for toast.
          if (!empty($operation_message) && !isset($_SESSION['operation_message'])) {
             $message_type = 'info'; // Default type
             // Determine message type based on keywords in the message
             $msg_lower = strtolower(strip_tags($operation_message));
             if (strpos($msg_lower, 'successfully') !== false || strpos($msg_lower, 'added') !== false || strpos($msg_lower, 'updated') !== false || strpos($msg_lower, 'deleted') !== false) {
                   $message_type = 'success';
              } elseif (strpos($msg_lower, 'access denied') !== false || strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'failed') !== false || strpos( $msg_lower, 'could not') !== false || strpos($msg_lower, 'invalid') !== false || strpos($msg_lower, 'problem') !== false) {
                   $message_type = 'error';
              } elseif (strpos($msg_lower, 'warning') !== false || strpos($msg_lower, 'not found') !== false || strpos($msg_lower, 'correct the errors') !== false || strpos($msg_lower, 'already') !== false || strpos($msg_lower, 'please select') !== false || strpos($msg_lower, 'no records found') !== false) {
                   $message_type = 'warning';
              }
             echo "<div class='message-box " . htmlspecialchars($message_type) . "' role='alert'>" . htmlspecialchars($operation_message) . "</div>";
         }
         ?>

         <!-- Filter Form -->
         <!-- This form allows users to filter results by academic year and class -->
         <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
             <div>
                 <label for="academic_year">Filter by Year:</label>
                 <select id="academic_year" name="academic_year">
                     <option value="">All Years</option>
                     <?php foreach ($available_years as $year): ?>
                         <!-- Option value is the year, display text is the year -->
                         <!-- 'selected' attribute makes the previously selected option stay selected -->
                         <option value="<?php echo $year; ?>" <?php echo ($selected_year === $year) ? 'selected' : ''; ?>><?php echo $year; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
              <div>
                 <label for="current_class">Filter by Class:</label>
                 <select id="current_class" name="current_class">
                     <option value="">All Classes</option>
                     <?php foreach ($available_classes as $class): ?>
                         <!-- Option value is the class, display text is the class -->
                         <option value="<?php echo $class; ?>" <?php echo ($selected_class === $class) ? 'selected' : ''; ?>><?php echo $class; ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
              <div>
                  <!-- Submit button to apply the selected filters -->
                  <input type="submit" value="Apply Filters">
              </div>
              <?php if (!empty($student_results_raw)): // Only show the download button if there are results to download (after potential filtering) ?>
                  <!-- Download CSV link -->
                  <!-- This link passes the current filter parameters back to the same page -->
                  <!-- The PHP code at the top detects the 'download=csv' parameter and handles the CSV generation -->
                  <div class="flex-grow md:flex-grow-0 md:ml-auto"> <!-- Container for the download button -->
                       <a href="./all_student_results.php?download=csv&<?php echo http_build_query(['academic_year' => $selected_year, 'current_class' => $selected_class]); ?>" class="download-button">
                            <!-- Download icon SVG -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                            Download CSV
                       </a>
                  </div>
             <?php endif; ?>
         </form>

         <?php
         // --- Display the fetch results message here ---
         // This block is moved to show the message regardless of whether data was grouped into the final structure
         if (!empty($fetch_results_message)) {
             $message_type = 'info'; // Default type
             // Determine the appropriate message box style based on the message content
             $msg_lower = strtolower(strip_tags($fetch_results_message));
              if (strpos($msg_lower, 'error') !== false || strpos($msg_lower, 'could not') !== false || strpos($msg_lower, 'failed') !== false) {
                   $message_type = 'error';
              } elseif (strpos($msg_lower, 'no exam results found') !== false || strpos($msg_lower, 'no subject results') !== false) {
                  $message_type = 'warning';
              }
              // Note: The image shows a success/info type message style, so 'info' is usually appropriate here
             echo "<div class='message-box " . htmlspecialchars($message_type) . "' role='alert'>" . htmlspecialchars($fetch_results_message) . "</div>";
         }
         ?>


        <!-- Topper Section -->
         <!-- This section is shown only if no year filter is applied OR the filter is for the current academic year -->
         <?php if (empty($selected_year) || $selected_year === $current_academic_year): ?>
             <!-- Only show the section if there are toppers found OR if the message is not an error message -->
             <?php if (!empty($school_toppers) || (is_string($topper_message) && strpos(strtolower($topper_message), 'error') === false)): ?>
                 <div class="topper-section">
                      <!-- Display the topper message as the section heading -->
                      <h3><?php echo is_string($topper_message) ? htmlspecialchars(strip_tags($topper_message)) : "School Toppers"; ?></h3>
                     <?php if (!empty($school_toppers)): ?>
                         <!-- List of toppers -->
                         <ul class="topper-list">
                             <?php foreach ($school_toppers as $rank => $topper): ?>
                                 <li>
                                      <span class="rank">#<?php echo $rank + 1; ?></span> <!-- Display rank (0-indexed + 1) -->
                                      <span class="name-class">
                                          <?php echo htmlspecialchars($topper['full_name'] ?? 'N/A'); ?>
                                          (Class: <?php echo htmlspecialchars($topper['current_class'] ?? 'N/A'); ?>, Roll No: <?php echo htmlspecialchars($topper['roll_number'] ?? 'N/A'); ?>)
                                          <!-- Link to view student details -->
                                           <a href="view_student.php?id=<?php echo htmlspecialchars($topper['user_id'] ?? ''); ?>" class="text-indigo-600 hover:underline ml-2 text-xs">(View Details)</a>
                                      </span>
                                      <!-- Display percentage, formatted to 2 decimal places -->
                                      <span class="percentage"><?php echo htmlspecialchars(number_format($topper['average_percentage_year'] ?? 0, 2)); ?>%</span>
                                 </li>
                             <?php endforeach; ?>
                         </ul>
                     <?php elseif (is_string($topper_message) && strpos(strtolower($topper_message), 'error') === false): ?>
                         <!-- Display the topper message if it's not an error and no toppers were found -->
                         <p class="text-gray-600 italic text-sm"><?php echo htmlspecialchars(strip_tags($topper_message)); ?></p>
                     <?php endif; ?>
                      <?php
                       // Display an error message below the list if the topper message indicates an error
                        if (is_string($topper_message) && strpos(strtolower($topper_message), 'error') !== false) {
                            echo "<p class='text-red-600 text-sm mt-2'>" . htmlspecialchars(strip_tags($topper_message)) . "</p>";
                        }
                       ?>
                 </div>
             <?php endif; ?>
         <?php endif; ?>


        <!-- Main Results Display Area -->
         <div class="results-container">
             <?php if (!empty($results_structured)): // Check if there is any data to display after grouping ?>
                  <?php foreach ($results_structured as $year => $classes_in_year): // Loop through each academic year ?>
                      <div class="year-section">
                          <h3>Academic Year: <?php echo htmlspecialchars($year); ?></h3>

                           <?php foreach ($classes_in_year as $class => $exams_in_class): // Loop through each class in the current year (this is now student's CURRENT class) ?>
                               <div class="class-section">
                                   <h4>Class: <?php echo htmlspecialchars($class); ?></h4>

                                   <?php if (!empty($exams_in_class)): ?>
                                        <?php foreach ($exams_in_class as $exam_name => $exam_data): // Loop through each exam for this class ?>
                                             <?php if (!empty($exam_data['students'])): // Only show exam section if there are students with results in this exam ?>
                                                 <div class="exam-section">
                                                     <h5>Exam: <?php echo htmlspecialchars($exam_name); ?></h5>

                                                     <!-- Wrap table in a responsive container for small screens -->
                                                     <div class="overflow-x-auto">
                                                         <table class="exam-table">
                                                              <thead>
                                                                  <tr>
                                                                      <th>Name</th>
                                                                      <th>Roll No</th>
                                                                      <?php
                                                                      // Display subject headers dynamically based on subjects found in this exam/class grouping
                                                                      foreach (($exam_data['subjects_in_exam'] ?? []) as $subject):
                                                                           echo '<th>' . htmlspecialchars($subject) . '</th>';
                                                                      endforeach;
                                                                      ?>
                                                                      <th>Total Marks</th>
                                                                      <th>Percentage</th>
                                                                  </tr>
                                                              </thead>
                                                              <tbody>
                                                                  <?php foreach (($exam_data['students'] ?? []) as $student_id => $student_data): // Loop through each student in this exam/class grouping ?>
                                                                  <tr>
                                                                      <td>
                                                                          <?php echo htmlspecialchars($student_data['full_name'] ?? 'N/A'); ?>
                                                                           <!-- Link to view student details -->
                                                                           <a href="view_student.php?id=<?php echo htmlspecialchars($topper['user_id'] ?? ''); ?>" class="text-indigo-600 hover:underline ml-2 text-xs">(View)</a>
                                                                      </td>
                                                                      <td><?php echo htmlspecialchars($student_data['roll_number'] ?? 'N/A'); ?></td>
                                                                       <?php
                                                                       // Display marks for each subject for this student
                                                                       foreach (($exam_data['subjects_in_exam'] ?? []) as $subject):
                                                                            // Check if the student has a mark for this specific subject in this exam aggregation, otherwise show N/A or '-'
                                                                            $mark = $student_data['subject_marks'][$subject] ?? '-'; // Use '-' or 'N/A' for missing marks
                                                                            echo '<td>' . htmlspecialchars($mark) . '</td>';
                                                                       endforeach;
                                                                       ?>
                                                                       <!-- Display total marks and percentage for this exam (aggregated per student) -->
                                                                      <td><?php echo htmlspecialchars(number_format($student_data['total_marks_obtained'] ?? 0, 2)); ?></td>
                                                                      <td><?php echo htmlspecialchars(number_format($student_data['percentage'] ?? 0, 2)); ?>%</td>
                                                                  </tr>
                                                                  <?php endforeach; ?>
                                                              </tbody>
                                                              <!-- No tfoot needed for this table structure -->
                                                          </table>
                                                      </div> <!-- End overflow-x-auto -->
                                                 </div> <!-- End exam-section -->
                                             <?php endif; ?>
                                        <?php endforeach; ?>
                                   <?php else: ?>
                                        <p class="text-gray-600 italic text-sm">No exams with results found for students currently in this class for this year.</p>
                                   <?php endif; ?>
                               </div> <!-- End class-section -->
                           <?php endforeach; ?>
                       </div> <!-- End year-section -->
                   <?php endforeach; ?>
               <?php else: ?>
                   <?php
                   // If $results_structured is empty, it means no data matched filters or there was a fetch error.
                   // The message about this (from $fetch_results_message) is now displayed unconditionally above the results-container.
                   // No results to display message below the container is redundant if message above explains it.
                   ?>
               <?php endif; ?>

         </div> <!-- End results-container -->

     </div> <!-- End main-content-wrapper -->

     <!-- Optional: Add buttons for background change here -->
     <!-- These buttons use the setBackground JS function defined in the script block -->
     <div class="mt-8 text-center text-sm text-gray-700 pb-8">
         Choose Background:
         <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-blue-cyan" onclick="setBackground('gradient-background-blue-cyan')">Blue/Cyan</button>
         <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-purple-pink" onclick="setBackground('gradient-background-purple-pink')">Purple/Pink</button>
          <button class="ml-2 px-2 py-1 border rounded-md text-white text-xs gradient-background-green-teal" onclick="setBackground('gradient-background-green-teal')">Green/Teal</button>
          <button class="ml-2 px-2 py-1 border rounded-md bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" onclick="setBackground('solid-bg-gray')">Gray</button>
          <button class="ml-2 px-2 py-1 border rounded-md bg-indigo-500 hover:bg-indigo-600 text-white text-xs" onclick="setBackground('solid-bg-indigo')">Indigo</button>
     </div>

<?php
// Include the footer file. This file should:
// - Close the </body> and </html> tags
// - Potentially include closing scripts or other footer elements
require_once "./admin_footer.php";
?>