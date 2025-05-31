<?php
$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base_directory = '/School'; // Update this as needed

if (strpos($current_path, $base_directory) === 0) {
    $current_path = substr($current_path, strlen($base_directory));
}
if ($current_path === false || $current_path === '') {
    $current_path = '/';
}

function generate_nav_link($link_text, $relative_path, $current_path, $base_directory, $role_color_class = "") {
    $full_href = rtrim($base_directory, '/') . '/' . ltrim($relative_path, '/');
    $is_active = ($current_path === '/' . ltrim($relative_path, '/'));

    if ($relative_path === 'staff_dashboard.php' && $current_path === '/') {
        $is_active = true;
    }
    if ($relative_path === 'index.php' && $current_path === '/') {
        $is_active = true;
    }

    $active_class = $is_active ? "font-semibold " . ($role_color_class ?: 'text-blue-400 md:text-blue-300') : "";
    $base_classes = "block px-3 py-2 rounded-md text-base transition-all duration-200 md:p-0 md:text-sm";
    
    $hover_class = "";
    if (!$is_active) {
        $hover_class = $role_color_class ? str_replace(['-400', '-300'], ['-500', '-400'], $role_color_class) : "hover:text-blue-400 md:hover:text-blue-300";
        if ($role_color_class === 'text-red-400') $hover_class = 'hover:text-red-500';
    }

    $combined_classes = trim("$base_classes $active_class $hover_class");

    return sprintf(
        '<a href="%s" class="%s" aria-current="%s">%s</a>',
        htmlspecialchars($full_href),
        htmlspecialchars($combined_classes),
        $is_active ? 'page' : 'false',
        htmlspecialchars($link_text)
    );
}

$current_staff_role = $_SESSION['role'] ?? 'Unknown';
$role_link_color_class = match($current_staff_role) {
    'teacher' => 'text-blue-400 md:text-blue-300',
    'principal' => 'text-green-400 md:text-green-300',
    'staff' => 'text-purple-400 md:text-purple-300',
    default => 'text-gray-400 md:text-gray-300',
};
?>

<nav class="bg-gray-900 text-white w-full rounded-3xl max-w-screen-xl mx-auto shadow-md sticky top-0 z-50" aria-label="Staff navigation">
    <div class="flex items-center justify-between py-4 px-8">
        <div class="flex items-center space-x-6">
            <a href="<?php echo rtrim($base_directory, '/') . '/staff_dashboard.php'; ?>" class="text-lg font-bold text-white hover:text-gray-300" aria-label="Home - Dashboard">
            </a>
            <div class="text-base md:text-lg font-medium hidden sm:block"><?php echo htmlspecialchars($_SESSION['display_name'] ?? 'Staff'); ?></div>
            <span class="text-xs font-semibold px-3 py-1 rounded-full shadow-sm uppercase tracking-wide
                <?php
                    echo match($current_staff_role) {
                        'teacher' => 'bg-blue-600 text-white',
                        'principal' => 'bg-green-600 text-white',
                        'staff' => 'bg-purple-600 text-white',
                        default => 'bg-gray-500 text-white',
                    };
                ?>">
                <?php echo htmlspecialchars(ucfirst($current_staff_role)); ?>
            </span>
        </div>

        <button id="staff-navbar-toggle" class="md:hidden p-2 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded" aria-label="Toggle navigation">
            <svg class="h-6 w-6 text-white transition duration-300 ease-in-out" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path id="navbar-toggle-icon-open" class="" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path id="navbar-toggle-icon-close" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <div id="staff-navbar-links" class="hidden md:flex md:items-center md:space-x-8 flex-col md:flex-row
             fixed inset-x-0 top-[64px] md:static md:top-auto md:inset-0
             bg-gray-800 md:bg-transparent p-4 md:p-0
             shadow-lg md:shadow-none z-40
             transition-all duration-300 ease-in-out
             overflow-y-auto max-h-[calc(100vh-64px)] md:max-h-none
             space-y-3 md:space-y-0 rounded-b-md md:rounded-none">

            <?php
            echo generate_nav_link("Dashboard", "teacher/staff_dashboard.php", $current_path, $base_directory, $role_link_color_class);

            if (isset($_SESSION['role'])):
                if ($_SESSION['role'] === 'teacher'):
                    echo generate_nav_link("My Students", "teacher/student-list.php", $current_path, $base_directory, $role_link_color_class);
                    echo generate_nav_link("My Profile", "teacher/staffProfile.php", $current_path, $base_directory, $role_link_color_class);

                elseif ($_SESSION['role'] === 'principal'):
                    echo generate_nav_link("Manage Students (Full)", "admin_dashboard.php", $current_path, $base_directory, $role_link_color_class);
                    echo generate_nav_link("Manage Staff", "manage_staff.php", $current_path, $base_directory, $role_link_color_class);
                    echo generate_nav_link("My Profile", "staffProfile.php", $current_path, $base_directory, $role_link_color_class);

                else:
                    echo generate_nav_link("Student List", "teacher/student-list.php", $current_path, $base_directory, $role_link_color_class);
                    echo generate_nav_link("My Profile", "teacher/staffProfile.php", $current_path, $base_directory, $role_link_color_class);
                endif;
            endif;

            echo generate_nav_link("Logout", "logout.php", $current_path, $base_directory, 'text-red-400');
            ?>
        </div>
    </div>
</nav>

<!-- Optional: Slide down animation style -->
<style>
    @keyframes slideDown {
        0% {
            opacity: 0;
            transform: translateY(-10px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .animate-slide-down {
        animation: slideDown 0.3s ease-in-out;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('staff-navbar-toggle');
        const navLinks = document.getElementById('staff-navbar-links');
        const iconOpen = document.getElementById('navbar-toggle-icon-open');
        const iconClose = document.getElementById('navbar-toggle-icon-close');

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'false');
            if (navLinks) toggleBtn.setAttribute('aria-controls', 'staff-navbar-links');
        }

        const toggleNavbar = () => {
            if (navLinks) {
                const isHidden = navLinks.classList.contains('hidden');
                navLinks.classList.toggle('hidden', !isHidden);
                navLinks.classList.toggle('flex', isHidden);
                navLinks.classList.toggle('animate-slide-down', isHidden);

                if (toggleBtn) {
                    toggleBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                }

                if (iconOpen && iconClose) {
                    iconOpen.classList.toggle('hidden', !isHidden);
                    iconClose.classList.toggle('hidden', isHidden);
                }
            }
        };

        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleNavbar);
        }

        const mediaQuery = window.matchMedia('(min-width: 768px)');

        const handleMediaQueryChange = (e) => {
            if (e.matches && navLinks.classList.contains('flex')) {
                navLinks.classList.remove('flex');
                navLinks.classList.add('hidden');
                toggleBtn.setAttribute('aria-expanded', 'false');
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
            }
        };

        handleMediaQueryChange(mediaQuery);
        mediaQuery.addListener(handleMediaQueryChange);

        if (navLinks) {
            navLinks.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 768) {
                        setTimeout(() => {
                            toggleNavbar();
                        }, 100);
                    }
                });
            });
        }
    });
</script>
