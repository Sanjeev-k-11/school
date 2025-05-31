<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header("location: login.php"); // Redirect to login if not logged in or not admin
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md text-center">
        <h1 class="text-2xl font-bold mb-4">Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>This is the Admin Dashboard.</p>
        <p class="mt-4"><a href="logout.php" class="text-blue-600 hover:underline">Logout</a></p>
         <!-- TODO: Add Admin functionalities like creating staff/student accounts -->
    </div>
</body>
</html>