<?php
// mail_config.php

// PHPMailer configuration
// !! IMPORTANT: Replace with your actual SMTP settings !!
define('SMTP_HOST', 'smtp.gmail.com'); // e.g., smtp.gmail.com
define('SMTP_USERNAME', 'sy781405@gmail.com'); // Your full email address
define('SMTP_PASSWORD', 'ecgd vdvt jlre vebh'); // Your email password or app password
define('SMTP_PORT', 465); // e.g., 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' or 'ssl'

// Email details
define('MAIL_FROM_EMAIL', 'sy781405@gmail.com'); // Sender email address
define('MAIL_FROM_NAME', 'vishal school'); // Sender name

// OTP settings
define('OTP_LENGTH', 6); // Length of the numeric OTP
define('OTP_EXPIRY_MINUTES', 10); // OTP valid for 15 minutes
?>