<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bidhub_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Site settings
define('SITE_NAME', 'BidHub');
define('SITE_URL', 'http://localhost/bidhub');

// Set timezone
date_default_timezone_set('UTC');

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function sanitize($input) {
    global $conn;
    return $conn->real_escape_string(htmlspecialchars(trim($input)));
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 5) {
        return "just now";
    }
    
    if ($diff < 60) {
        return $diff . " second" . ($diff != 1 ? "s" : "") . " ago";
    }
    
    $diff = floor($diff / 60);
    if ($diff < 60) {
        return $diff . " minute" . ($diff != 1 ? "s" : "") . " ago";
    }
    
    $diff = floor($diff / 60);
    if ($diff < 24) {
        return $diff . " hour" . ($diff != 1 ? "s" : "") . " ago";
    }
    
    $diff = floor($diff / 24);
    return $diff . " day" . ($diff != 1 ? "s" : "") . " ago";
}

function displayAlert($message, $type = 'info') {
    echo "<div class='alert alert-$type' role='alert'>$message</div>";
}
?> 