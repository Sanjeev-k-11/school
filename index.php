<?php
// index.php - School Landing Page

// No session_start() needed here as it's a public page
// No database connection needed for this simple example

// You can define some school details here if you want to make them easy to change
$schoolName = "[Your School Name Here]"; // Replace with your school name
$schoolTagline = "[Your School Tagline/Motto]"; // Replace with your school tagline
$schoolDescription = "Welcome to {$schoolName}, where we are dedicated to fostering a nurturing and stimulating environment for students to grow academically, socially, and personally. We believe in providing quality education that prepares students for a bright future."; // Replace with your school description
$aboutUsContent = "Our school has a rich history of academic excellence and community involvement. We offer a wide range of programs, extracurricular activities, and support services designed to meet the diverse needs of our students. Our experienced faculty and staff are committed to creating a positive and engaging learning experience."; // Replace with more about your school

// Path to a school-related image (relative to this index.php file)
// Make sure you have an image at this path, or replace with a URL
$schoolImagePath = "./assets/images/school_building.jpg"; // Replace with your image path
$placeholderImagePath = "https://via.placeholder.com/800x400?text=School+Image"; // Fallback placeholder

// Check if your local image exists, otherwise use placeholder
$displayImagePath = file_exists($schoolImagePath) ? $schoolImagePath : $placeholderImagePath;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo htmlspecialchars($schoolName); ?></title>
    <!-- Include Tailwind CSS -->
    <!-- You can use a local build if you have one, or this CDN for simplicity -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Optional custom styles if needed */
        body {
            font-family: 'Arial', sans-serif; /* Fallback font */
            line-height: 1.6;
            color: #333; /* Default text color */
            background-color: #f4f4f4; /* Light gray background */
        }
        .container {
            max-width: 960px; /* Max width for main content */
        }
         /* Ensure image covers its space without distortion */
         .hero-image {
              width: 100%;
              height: auto;
              max-height: 400px; /* Limit height */
              object-fit: cover; /* Crop image to fit */
              object-position: center; /* Center the cropped part */
         }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="bg-blue-600 text-white py-4 text-center shadow-md">
        <div class="container mx-auto">
            <h1 class="text-3xl md:text-4xl font-bold mb-1"><?php echo htmlspecialchars($schoolName); ?></h1>
            <p class="text-sm md:text-base opacity-90"><?php echo htmlspecialchars($schoolTagline); ?></p>
        </div>
    </header>

    <!-- Main Content Area -->
    <div class="container mx-auto my-8 p-6 bg-white rounded-lg shadow-md">

        <!-- Welcome Section / Hero Image -->
        <div class="text-center mb-8">
            <h2 class="text-2xl md:text-3xl font-semibold text-gray-800 mb-6">Welcome to Our Community!</h2>

            <!-- School Image -->
            <img src="<?php echo htmlspecialchars($displayImagePath); ?>" alt="Image representing <?php echo htmlspecialchars($schoolName); ?>" class="hero-image mx-auto rounded-md shadow-sm mb-6">

            <p class="text-gray-700 mb-6">
                <?php echo nl2br(htmlspecialchars($schoolDescription)); // nl2br converts newlines to <br> ?>
            </p>

            <!-- Login Call to Action -->
            <div class="mt-6">
                 <p class="text-gray-800 text-lg mb-4">Current students and staff, please login here:</p>
                <a href="login.php" class="inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-lg shadow-lg transition duration-300 ease-in-out text-lg">
                    Go to Login
                </a>
            </div>
        </div>

        <hr class="my-8 border-gray-300"> <!-- Separator -->

        <!-- About Us Section -->
        <div class="mb-8">
            <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4 border-b pb-2">About Us</h3>
            <p class="text-gray-700">
                 <?php echo nl2br(htmlspecialchars($aboutUsContent)); ?>
            </p>
             <!-- Add more paragraphs or content here -->
        </div>

        <!-- Optional: More Sections like Mission, Programs, Contact Info -->
        <!--
        <div class="mb-8">
             <h3 class="text-xl md:text-2xl font-semibold text-gray-800 mb-4 border-b pb-2">Our Mission</h3>
             <p class="text-gray-700">...</p>
        </div>
        -->

    </div> <!-- End of main content container -->

    <!-- Footer -->
    <footer class="text-center py-6 text-gray-600 text-sm">
        <div class="container mx-auto">
            <p>© <?php echo date('Y'); ?> <?php echo htmlspecialchars($schoolName); ?>. All rights reserved.</p>
        </div>
    </footer>

</body>
</html>