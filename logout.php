<?php
require_once 'includes/config.php';

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page with success message
$_SESSION['alert'] = [
    'message' => 'You have been successfully logged out.',
    'type' => 'success'
];

// Redirect to homepage
redirect(SITE_URL);
?> 