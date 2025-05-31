<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */

date_default_timezone_set('Asia/Kolkata');


define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // <--- Change this
define('DB_PASSWORD', ''); // <--- Change this
define('DB_NAME', 'login_db');     // <--- Change this

/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

// Set charset
if (!$link->set_charset("utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", $link->error);
    // You might want to handle this error more gracefully in production
}

?>