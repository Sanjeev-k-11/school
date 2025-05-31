<?php
// School/admin/admin_footer.php
// This file contains the standard footer for admin pages.

// Get current year dynamically
$currentYear = date('Y');

// Define school-specific placeholders (replace with your actual info or fetch from config)
$schoolName = "Your School Name"; // Replace with your school name
$schoolPhone = "+1 123 456 7890"; // Replace with your school phone
$schoolEmail = "info@yourschool.com"; // Replace with your school email
$developerName = "Sanjeev Kumar"; // Replace with developer info
$developerWebsite = "https://github.com/Sanjeev-k-11"; // Optional: Replace with developer website URL
$developerPhone = "9534757076"; // Optional: Replace with developer phone
$developerEmail = "dev606733@gmial.com"; // Optional: Replace with developer email

// School Social Media Links (Replace with your actual links or #)
$socialLinks = [
    'linkedin' => 'https://www.linkedin.com/in/sanjeevkumaryadav/', // LinkedIn URL
    'instagram' => 'https://www.instagram.com/sanjeev_k_11/', // Instagram URL
    'facebook' => '#', // Facebook URL
    'twitter' => '#'   // Twitter/X URL
];
?>

    <!-- Footer -->
    <footer class="app-footer">
        <div class="footer-container">
            <!-- Top Section (Columns) -->
            <div class="footer-columns">
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <ul>
                        <li>
                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-sm">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.181.42l-2.36 3.54a3.752 3.752 0 01-3.75-3.75l3.54-2.36c.365-.279.53-.74.42-1.18L13.5 7.101a2.25 2.25 0 00-1.091-.852H10.5a2.25 2.25 0 00-2.25 2.25v2.25z" />
                              </svg>
                            <span><?php echo htmlspecialchars($schoolPhone); ?></span>
                        </li>
                         <li>
                             <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-sm">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                              </svg>
                             <span><?php echo htmlspecialchars($schoolEmail); ?></span>
                         </li>
                        <?php // Add more contact methods like address if needed ?>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="./admin_dashboard.php">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="./create_student.php">Admissions</a></li>
                        <li><a href="#">Academics</a></li>
                        <li><a href="./create_event.php">Events</a></li>
                         <li><a href="#">Contact Us</a></li>
                        <?php // Add more relevant school links ?>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Portals</h4>
                    <ul>
                        <li><a href="#">Parent Portal</a></li>
                        <li><a href="#">Student Portal</a></li>
                        <li><a href="#">Staff Login</a></li>
                         <li><a href="#">Library Resources</a></li>
                         <li><a href="#">Download Forms</a></li>
                        <?php // Add more relevant school portals/tools ?>
                    </ul>
                </div>
            </div> <!-- End footer-columns -->

            <!-- Bottom Section -->
            <div class="footer-bottom">
                <div class="footer-branding">
                     <!-- Replace with your school logo and name -->
                     <!-- <img src="/path/to/your/logo.png" alt="<?php echo htmlspecialchars($schoolName); ?> Logo" class="footer-logo"> -->
                    <span class="school-name"><?php echo htmlspecialchars($schoolName); ?></span>
                     <span class="school-tagline">Empowering Future Generations</span>
                </div>

                <div class="footer-legal">
                    <a href="#">Privacy Policy</a> | <a href="#">Terms & Conditions</a>
                     <p class="copyright">© <?php echo $currentYear; ?> <?php echo htmlspecialchars($schoolName); ?>. All rights reserved.</p>
                </div>

                 <div class="footer-social">
                      <p>Follow us:</p>
                      <div class="social-icons">
                           <?php if ($socialLinks['linkedin'] != '#'): ?><a href="<?php echo htmlspecialchars($socialLinks['linkedin']); ?>" target="_blank" aria-label="LinkedIn">In</a><?php endif; ?>
                           <?php if ($socialLinks['instagram'] != '#'): ?><a href="<?php echo htmlspecialchars($socialLinks['instagram']); ?>" target="_blank" aria-label="Instagram">Ig</a><?php endif; ?>
                           <?php if ($socialLinks['facebook'] != '#'): ?><a href="<?php echo htmlspecialchars($socialLinks['facebook']); ?>" target="_blank" aria-label="Facebook">Fb</a><?php endif; ?>
                           <?php if ($socialLinks['twitter'] != '#'): ?><a href="<?php echo htmlspecialchars($socialLinks['twitter']); ?>" target="_blank" aria-label="Twitter">X</a><?php endif; ?>
                           <?php // Replace text with SVG icons for a polished look ?>
                      </div>
                 </div>

                <?php if (!empty($developerName)): // Optional: Developer credit ?>
                 <div class="footer-developer">
                      <?php echo htmlspecialchars($developerName); ?>
                      <?php if (!empty($developerWebsite)): ?> | <a href="<?php echo htmlspecialchars($developerWebsite); ?>" target="_blank">Website</a><?php endif; ?>
                      <?php if (!empty($developerPhone) && $developerPhone != 'YOUR_DEV_PHONE'): ?> | <a href="tel:<?php echo htmlspecialchars($developerPhone); ?>">Call</a><?php endif; ?>
                      <?php if (!empty($developerEmail) && $developerEmail != 'YOUR_DEV_EMAIL'): ?> | <a href="mailto:<?php echo htmlspecialchars($developerEmail); ?>">Email</a><?php endif; ?>
                 </div>
             <?php endif; ?>

            </div> <!-- End footer-bottom -->
        </div> <!-- End footer-container -->
    </footer>

    <!-- CSS for the Footer -->
    <style>
        /* Basic reset for list styles */
        .app-footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .app-footer li {
            margin-bottom: 0.5rem; /* Space between list items */
             display: flex; /* Align icon and text */
             align-items: center;
        }
        .app-footer a {
            text-decoration: none;
            color: inherit; /* Inherit color from parent */
            transition: color 0.2s ease-in-out;
        }
        .app-footer a:hover {
            color: #ef4444; /* Use a highlight color on hover */
            text-decoration: underline;
        }

        .app-footer {
            background-color: #1f2937; /* Dark background color (gray-800) */
            color: #d1d5db; /* Light text color (gray-300) */
            padding: 3rem 0; /* Vertical padding */
            font-size: 0.9rem; /* Slightly smaller text */
            line-height: 1.6; /* Improved readability */
        }

        .footer-container {
            max-width: 1280px; /* Max width similar to main content */
            margin: 0 auto; /* Center the container */
            padding: 0 1.5rem; /* Horizontal padding */
        }

        .footer-columns {
            display: grid; /* Use CSS Grid for columns */
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Responsive columns */
            gap: 2rem; /* Space between columns */
            margin-bottom: 2rem; /* Space between columns and bottom section */
             padding-bottom: 2rem; /* Padding before the divider */
             border-bottom: 1px solid #374151; /* Divider line (gray-700) */
        }

        .footer-col h4 {
            color: #f3f4f6; /* Lighter heading color (gray-100) */
            font-size: 1rem; /* Heading font size */
            margin-bottom: 1rem; /* Space below heading */
             font-weight: 600; /* Semibold */
        }

        .footer-col ul li a {
            color: #9ca3af; /* Link color (gray-400) */
            display: inline-block; /* Allows padding/margin if needed */
            margin-left: 0.5rem; /* Space between icon and text */
        }
         .footer-col ul li svg {
              width: 1rem; /* Small icon size */
              height: 1rem;
              color: #9ca3af; /* Icon color */
              flex-shrink: 0; /* Prevent icon from shrinking */
         }

        .icon-sm { /* Utility class for small SVG icons */
             width: 1rem;
             height: 1rem;
             vertical-align: middle; /* Align icon with text */
             margin-right: 0.5rem; /* Space between icon and text */
             color: #9ca3af; /* Default icon color */
        }


        .footer-bottom {
            display: flex; /* Use flexbox for the bottom row */
            flex-wrap: wrap; /* Allow items to wrap on smaller screens */
            justify-content: space-between; /* Space out branding, legal, social */
            align-items: center; /* Vertically center items */
            gap: 1rem; /* Space between items */
             padding-top: 2rem; /* Space above bottom content after divider */
        }

        .footer-branding {
             flex-basis: 250px; /* Give branding some base width */
             flex-grow: 1; /* Allow branding to grow */
             min-width: 150px; /* Ensure it doesn't get too small */
             color: #f3f4f6; /* White text for branding */
             font-size: 1.25rem; /* Larger font for school name */
             font-weight: 700; /* Bold font */
             /* Add styles for logo if you use one */
        }
         .footer-logo {
              height: 40px; /* Adjust logo size */
              margin-right: 10px;
              vertical-align: middle;
         }
         .school-name {
             display: block; /* Stack name and tagline */
             font-size: 1.25rem;
             font-weight: 700;
             margin-bottom: 0.2rem; /* Space between name and tagline */
         }
          .school-tagline {
              display: block;
              font-size: 0.8rem; /* Smaller font for tagline */
              font-weight: 400;
              color: #9ca3af; /* Grayish color */
          }


        .footer-legal {
             flex-grow: 1; /* Allow legal section to grow */
             text-align: center; /* Center legal text */
             min-width: 200px; /* Prevent legal block from collapsing */
        }
        .footer-legal a {
             color: #9ca3af; /* Grayish color for legal links */
             margin: 0 0.5rem; /* Space between links */
        }
         .footer-legal a:hover {
             color: #ef4444;
         }
        .footer-legal .copyright {
             margin-top: 0.5rem; /* Space between links and copyright */
             font-size: 0.85rem; /* Smaller font for copyright */
             color: #6b7280; /* Darker gray (gray-500) */
        }


        .footer-social {
             flex-basis: 200px; /* Give social section some base width */
             flex-grow: 1; /* Allow social section to grow */
             text-align: right; /* Align social links to the right */
             min-width: 150px;
        }
        .footer-social p {
            margin-bottom: 0.5rem; /* Space below "Follow us" */
             color: #f3f4f6; /* Lighter color for "Follow us" */
             font-weight: 600;
        }
        .social-icons a {
            display: inline-block;
            margin-left: 0.8rem; /* Space between social icons */
            font-size: 1.2rem; /* Size of social icons/text */
            color: #9ca3af; /* Icon color */
             /* Add padding/border/background for circular icons if desired */
        }
         .social-icons a:hover {
             color: #ef4444; /* Highlight color on hover */
         }
         /* Example style for circular icons (requires adjustment based on content like text or SVG) */
         /* .social-icons a {
             width: 30px; height: 30px; border-radius: 50%; background-color: #374151;
             display: inline-flex; justify-content: center; align-items: center;
             color: #f3f4f6; font-size: 1rem;
             margin-left: 0.5rem; text-decoration: none;
         }
         .social-icons a:hover {
             background-color: #4b5563; color: #ef4444;
         } */


        .footer-developer {
            width: 100%; /* Take full width below other items */
            text-align: center;
            margin-top: 1.5rem; /* Space above developer info */
            font-size: 0.75rem; /* Smaller font */
            color: #6b7280; /* Darker gray */
             border-top: 1px dashed #374151; /* Dashed line above developer info */
             padding-top: 1.5rem;
        }
        .footer-developer a {
             color: #6b7280; /* Inherit color for developer links */
             text-decoration: underline;
        }
         .footer-developer a:hover {
              color: #d1d5db; /* Lighter hover color */
         }


        /* Responsive adjustments */
        @media (max-width: 768px) {
            .footer-columns {
                grid-template-columns: 1fr; /* Stack columns on smaller screens */
                gap: 1.5rem;
            }
             .footer-bottom {
                 flex-direction: column; /* Stack bottom items */
                 text-align: center; /* Center align text */
             }
             .footer-branding,
             .footer-legal,
             .footer-social {
                 flex-basis: auto; /* Allow items to size based on content */
                 width: 100%; /* Take full width */
                 text-align: center; /* Center text */
             }
             .footer-social .social-icons a {
                 margin: 0 0.5rem; /* Adjust space between stacked social icons */
             }
        }

    </style>

</body>
</html>