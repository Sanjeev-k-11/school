<?php

// Determine display name and role for the sidebar
$sidebar_display_name = 'Admin'; // Default
$sidebar_role_display = 'Admin'; // Default

if (isset($_SESSION['role'])) {
    $sidebar_role_display = ucfirst($_SESSION['role']);

    // Use username for admin from 'users' table, display_name for others (like Principal from 'staff')
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user' && isset($_SESSION['username'])) {
         $sidebar_display_name = $_SESSION['username'];
    } elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'staff' && isset($_SESSION['display_name'])) {
         $sidebar_display_name = $_SESSION['display_name'];
    } else if (isset($_SESSION['username'])){
        // Fallback: if user_type is not explicitly set but username is, assume username is display name
         $sidebar_display_name = $_SESSION['username'];
    }
     // If neither user_type/username/display_name nor role is set, defaults remain 'Admin'
}

// Determine the web path to the School root directory.
// This is usually the directory name on the web server that contains login.php, admin_dashboard.php, etc.
// Replace '/School/' with your actual subdirectory name if it's different.
$webroot_path = '/School/'; // <-- CORRECTED: Path to the root of your School project

// The physical path to the admin directory where this sidebar is located is C:\xampp\htdocs\School\admin\
// The web path to the admin directory is /School/admin/
$admin_web_path = $webroot_path . 'admin/'; // Web path to THIS directory


?>

<!-- Sidebar Overlay (appears when sidebar is open to dim content) -->
<div id="admin-sidebar-overlay" class="fixed inset-0 bg-black opacity-0 pointer-events-none transition-opacity duration-300 z-30"></div>

<!-- Sidebar -->
<div id="admin-sidebar" class="fixed inset-y-0 left-0 w-64 bg-gray-800 text-white transform -translate-x-full transition-transform duration-300 ease-in-out z-40">
    <div class="p-6 flex flex-col h-full">
        <!-- Sidebar Header (Admin Info) -->
        <div class="flex items-center justify-between mb-6">
             <div>
                <div class="text-xl font-semibold"><?php echo htmlspecialchars($sidebar_display_name); ?></div>
                <span class="text-sm font-medium px-2 py-1 mt-1 inline-block rounded-full bg-indigo-600 text-white">
                    <?php echo htmlspecialchars($sidebar_role_display); ?>
                </span>
             </div>
             <!-- Close Button -->
            <button id="admin-sidebar-toggle-close" class="text-gray-400 hover:text-white focus:outline-none">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-grow space-y-2">
            <!-- Links using absolute paths from the webroot $webroot_path -->

            <!-- Dashboard Link (Points to admin_dashboard.php in the root) -->
            <a href="<?php echo $webroot_path; ?>admin/admin_dashboard.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Dashboard</a>
            <a href="<?php echo $webroot_path; ?>admin/allstudentList.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Manage Students</a>

            <!-- Add Student Link (In root) -->
            <a href="<?php echo $webroot_path; ?>admin/create_student.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Add Student</a>
             <a href="<?php echo $webroot_path; ?>admin/student_monthly_fees_list.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Fee List</a>
            <!-- Manage Staff Link (In root) -->
            <a href="<?php echo $webroot_path; ?>admin/manage_staff.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Manage Staff</a>

             <!-- Create Staff Link (In root) -->
            <a href="<?php echo $webroot_path; ?>admin/create_staff.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Add Staff</a>
            <a href="<?php echo $webroot_path; ?>admin/create_event.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Announcement</a>
             <a href="<?php echo $webroot_path; ?>admin/all_student_results.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Result</a>
             <a href="<?php echo $webroot_path; ?>admin/add_bulk_monthly_fee.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Add Bulk Fee</a>
             
             <a href="<?php echo $webroot_path; ?>admin/manage_students.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Fee Due</a>
            <!-- Links to files in the teacher subdirectory (Example if needed) -->
            <!-- <a href="<?php //echo $webroot_path; ?>teacher/student-list.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Teacher Student List</a> -->
             <!-- <a href="<?php //echo $webroot_path; ?>teacher/staffProfile.php" class="block px-3 py-2 rounded-md text-gray-300 hover:bg-gray-700 hover:text-white transition duration-200">Teacher Profile</a> -->


            <!-- Add more admin specific links here -->

        </nav>

        <!-- Logout Link - Fixed at the bottom of the sidebar -->
         <div class="mt-auto">
            <a href="<?php echo $webroot_path; ?>logout.php" class="block px-3 py-2 rounded-md text-red-400 hover:bg-gray-700 hover:text-red-300 transition duration-200">Logout</a>
         </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Note: The "Open Sidebar" button HTML must be placed in your main page file (e.g., admin_dashboard.php)
        // It needs the ID 'admin-sidebar-toggle-open'.
        const toggleOpenBtn = document.getElementById('admin-sidebar-toggle-open');
        const toggleCloseBtn = document.getElementById('admin-sidebar-toggle-close');
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('admin-sidebar-overlay');

        // Function to open the sidebar
        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('opacity-0', 'pointer-events-none');
            overlay.classList.add('opacity-50', 'pointer-events-auto'); // Adjust opacity as needed
        }

        // Function to close the sidebar
        function closeSidebar() {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.remove('opacity-50', 'pointer-events-auto');
            overlay.classList.add('opacity-0', 'pointer-events-none');
        }

        // Add event listeners
        // Check if elements exist before adding listeners, as the open button is in the main page
        if (toggleOpenBtn) {
            toggleOpenBtn.addEventListener('click', openSidebar);
        }
        if (toggleCloseBtn) {
            toggleCloseBtn.addEventListener('click', closeSidebar);
        }
        if (overlay) {
            overlay.addEventListener('click', closeSidebar); // Close sidebar when clicking overlay
        }

        // Optional: Close sidebar on resize to desktop if it's open
        window.addEventListener('resize', function() {
             clearTimeout(window.resizeTimeout);
             window.resizeTimeout = setTimeout(() => {
                 if (window.innerWidth >= 768) { // Tailwind's 'md' breakpoint
                     if (sidebar && sidebar.classList.contains('translate-x-0')) {
                          closeSidebar();
                     }
                 }
             }, 250); // Adjust delay
        });


    });
</script>