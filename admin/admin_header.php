<?php
// School/admin/admin_header.php
// This file contains the fixed header bar for admin pages.
// It assumes session_start() and config.php have already been included
// in the main calling script (e.g., admin_dashboard.php).

// Retrieve user info from session
// Using default values in case session data is missing (shouldn't happen if login check passes)
$loggedInUserName = $_SESSION['name'] ?? 'User';
$loggedInUserRole = $_SESSION['role'] ?? 'Unknown';

// Placeholder for School Name - You can set this dynamically or just hardcode it
$schoolName = "Your School Name";

// Placeholder for Logo URL - Replace with your actual logo path or URL
$logoUrl = "../assets/images/default_logo.png"; // Example path

// Allow the main page to set the title
$pageTitle = $pageTitle ?? 'Admin Panel'; // Use a passed $pageTitle or default
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title will be set in the main page (e.g., admin_dashboard.php) -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Keep base styles like body padding and fixed header styling here */
        /* This ensures the fixed header works correctly across all pages */

        /* Custom background style - Define multiple options */
        .gradient-background-blue-cyan {
            background: linear-gradient(to right, #4facfe, #00f2fe);
        }
         .gradient-background-purple-pink {
             background: linear-gradient(to right, #a18cd1, #fbc2eb);
         }
          .gradient-background-green-teal {
             background: linear-gradient(to right, #a8edea, #fed6e3);
          }
          .solid-bg-gray {
              background-color: #f3f4f6;
          }
          .solid-bg-indigo {
              background-color: #4f46e5;
          }
          /* Default gradient if none saved or set */
          /* Applying to body in the main page's style block */
          /* body { background: linear-gradient(to right, #4facfe, #00f2fe); } */


        /* Fixed Header styles - Apply these to the div inside admin_header.php */
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
           /* Use space-between or gap depending on desired layout */
           /* justify-content: space-between; */
           gap: 1rem; /* Space between elements in the header */
        }

        /* Style for the logo */
        .header-logo {
             height: 40px; /* Adjust height as needed */
             width: auto; /* Maintain aspect ratio */
             margin-right: 0.5rem; /* Space between logo and school name */
        }

         /* Style for School Name */
         .header-school-name {
              font-size: 1.125rem; /* text-lg */
              font-weight: 600; /* semibold */
              color: #1f2937; /* gray-800 */
              flex-grow: 1; /* Allow school name to take available space */
         }


        /* Style for the user info/logout part */
        .header-user-info {
            margin-left: auto; /* Push to the right */
            display: flex;
            align-items: center;
            gap: 1rem; /* Space between user name and logout */
             font-size: 0.875rem; /* text-sm */
             white-space: nowrap; /* Prevent wrapping */
        }
         .header-user-info span {
              color: #374151; /* gray-700 */
         }
          .header-user-info span strong {
              font-weight: 600; /* semibold */
          }
          .header-user-info a {
               color: #ef4444; /* red-600 */
               text-decoration: none;
               font-weight: 500;
          }
           .header-user-info a:hover {
               text-decoration: underline;
           }
           /* Hide user info/logout on small screens */
           @media (max-width: 768px) { /* md breakpoint */
               .header-user-info {
                   display: none;
               }
                /* Reduce gap on smaller screens */
                .fixed-header {
                    gap: 0.5rem;
                }
                .header-school-name {
                    font-size: 1rem; /* text-base */
                }
                 .header-logo {
                     height: 30px; /* Smaller logo on small screens */
                 }
           }


        /* Body padding to prevent content overlap with fixed header */
        /* This padding should be in the main page's style block as it modifies the body */
        /* body { padding-top: 4.5rem; transition: padding-left 0.3s ease; } */
        /* Adjust body padding when sidebar is open - assumes sidebar width is ~16rem (64 Tailwind units) */
        /* body.sidebar-open { padding-left: 16rem; } */


        /* Style for general messages (Keep in main page's style) */
        /* Table styles (Keep in main page's style) */
        /* Search input container (Keep in main page's style) */
        /* Download button (Keep in main page's style) */
        /* Section separator (Keep in main page's style) */
        /* Staff Photo in Table (Keep in main page's style) */
        /* Modal Styles (Keep in main page's style) */


         /* Styles for the footer (Defined in admin_footer.php style or global CSS) */
         /* .app-footer { ... } */


    </style>
     <script>
         // JavaScript for dynamic background changes (Optional)
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

         // Optional: Apply background preference on page load
         document.addEventListener('DOMContentLoaded', (event) => {
             const savedBackground = localStorage.getItem('backgroundPreference');
             if (savedBackground) {
                 setBackground(savedBackground);
             }

             // Sidebar Toggle JS - Needs to be here or after the button is loaded
             // We use optional chaining (?) because the button might not exist if sidebar is disabled
             document.getElementById('admin-sidebar-toggle-open')?.addEventListener('click', function() {
                 document.body.classList.toggle('sidebar-open');
             });

             // Close sidebar if it was open on desktop and screen resized smaller
             window.addEventListener('resize', function() {
                 if (window.innerWidth < 768) { // md breakpoint
                      document.body.classList.remove('sidebar-open');
                 }
             });
         });

         // Helper function to escape HTML characters for display safety in JS (Needed for modal)
          function htmlspecialchars(str) {
              if (typeof str != 'string' && typeof str != 'number') return str ?? ''; // Return non-strings/numbers, handle null/undefined
              str = String(str); // Ensure it's a string
              const map = {
                  '&': '&', // Corrected escaping
                  '<': '<',
                  '>': '>',
                  '"': '"',
                  "'": ''' // Corrected escaping
              };
              // Use replace with a function for multiple replacements
              return str.replace(/[&<>"']/g, function(m) { return map[m]; });
          }

          // Helper function to replace newlines with <br> for display (Needed for modal)
          function nl2brJs(str) {
               if (typeof str != 'string') return str ?? '';
               return str.replace(/\r\n|\r|\n/g, '<br>');
          }

     </script>
</head>
<body class="min-h-screen">
    <?php
    // Include the sidebar navigation menu
    require_once "./admin_sidebar.php";
    ?>

    <!-- Fixed Header Bar -->
    <div class="fixed-header">
         <!-- Open Sidebar Button (Hamburger) -->
         <button id="admin-sidebar-toggle-open" class="focus:outline-none text-gray-600 hover:text-gray-800 mr-2 md:mr-4" aria-label="Toggle sidebar">
             <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
             </svg>
         </button>

         <!-- Site Title / Logo -->
         <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="School Logo" class="header-logo">
         <span class="header-school-name"><?php echo htmlspecialchars($schoolName); ?></span>


         <!-- User Info and Logout -->
         <div class="header-user-info">
             <span>Welcome, <strong><?php echo htmlspecialchars($loggedInUserName); ?></strong> (<?php echo htmlspecialchars(ucfirst($loggedInUserRole)); ?>)</span>
             <a href="../logout.php">Logout</a>
         </div>
    </div>

    <?php
    // Note: The main content wrapper div (w-full max-w-screen-xl...)
    // and the specific page title (<h1>Admin Dashboard - Overview</h1>)
    // should be in the main page file (admin_dashboard.php) after including this header.
    ?>