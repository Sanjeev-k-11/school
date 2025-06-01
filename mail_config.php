<?php
// mail_config.php

// PHPMailer configuration
// !! IMPORTANT: Replace with your actual SMTP settings !!
define('SMTP_HOST', ''); // e.g., smtp.gmail.com
define('SMTP_USERNAME', ''); // Your full email address
define('SMTP_PASSWORD', ' '); // Your email password or app password
define('SMTP_PORT', 465); // e.g., 587 for TLS, 465 for SSL
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' or 'ssl'

// Email details
define('MAIL_FROM_EMAIL', ''); // Sender email address
define('MAIL_FROM_NAME', ''); // Sender name

// OTP settings
define('OTP_LENGTH', 6); // Length of the numeric OTP
define('OTP_EXPIRY_MINUTES', 10); // OTP valid for 15 minutes
?>
