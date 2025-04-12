<?php
require_once '../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to access the admin area.',
        'type' => 'danger'
    ];
    redirect(SITE_URL);
    exit;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['alert'] = [
        'message' => 'Invalid request method.',
        'type' => 'danger'
    ];
    redirect('pending-auctions.php');
    exit;
}

// Validate required parameters
if (!isset($_POST['auction_id']) || !isset($_POST['action'])) {
    $_SESSION['alert'] = [
        'message' => 'Missing required parameters.',
        'type' => 'danger'
    ];
    redirect('pending-auctions.php');
    exit;
}

$auction_id = (int)$_POST['auction_id'];
$action = sanitize($_POST['action']);

// Get auction details to ensure it exists and is pending
$sql = "SELECT a.*, u.user_id as seller_id, u.username, u.email 
        FROM auctions a 
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.auction_id = ? AND a.status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'Auction not found or not in pending status.',
        'type' => 'danger'
    ];
    redirect('pending-auctions.php');
    exit;
}

$auction = $result->fetch_assoc();

if ($action === 'approve') {
    // Update auction status to active
    $sql = "UPDATE auctions SET status = 'active' WHERE auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $auction_id);
    
    if ($stmt->execute()) {
        // Send notification to the seller
        $message = "Your auction '{$auction['title']}' has been approved and is now live.";
        $link = "/user/my-auctions.php";
        
        $notification_sql = "INSERT INTO notifications (user_id, message, link, is_read) VALUES (?, ?, ?, 0)";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("iss", $auction['seller_id'], $message, $link);
        $notification_stmt->execute();
        
        // Send email to the seller
        $to = $auction['email'];
        $subject = "Your Auction Has Been Approved";
        
        $email_message = "
        <html>
        <head>
            <title>Auction Approved</title>
        </head>
        <body>
            <h2>Congratulations! Your auction has been approved.</h2>
            <p>Dear {$auction['username']},</p>
            <p>We're pleased to inform you that your auction <strong>{$auction['title']}</strong> has been approved by our team and is now live on our platform.</p>
            <p>Your auction will run until " . date('F j, Y, g:i a', strtotime($auction['end_time'])) . ".</p>
            <p>You can view and manage your auction by clicking the button below:</p>
            <p><a href='" . SITE_URL . "/auction.php?id={$auction_id}' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>View Your Auction</a></p>
            <p>Thank you for using our platform!</p>
            <p>Best regards,<br>The " . SITE_NAME . " Team</p>
        </body>
        </html>
        ";
        
        // Set mail headers
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        
        // Send email (disabled in dev/commented until mail server is configured)
        // mail($to, $subject, $email_message, $headers);
        
        $_SESSION['alert'] = [
            'message' => "Auction '{$auction['title']}' has been approved successfully.",
            'type' => 'success'
        ];
    } else {
        $_SESSION['alert'] = [
            'message' => 'Failed to approve auction. Please try again.',
            'type' => 'danger'
        ];
    }
} elseif ($action === 'reject') {
    // Check for rejection reason
    if (!isset($_POST['reason']) || empty($_POST['reason'])) {
        $_SESSION['alert'] = [
            'message' => 'A reason for rejection is required.',
            'type' => 'danger'
        ];
        redirect('review-auction.php?id=' . $auction_id);
        exit;
    }
    
    $reason = sanitize($_POST['reason']);
    
    // Update auction status to rejected
    $sql = "UPDATE auctions SET status = 'rejected' WHERE auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $auction_id);
    
    if ($stmt->execute()) {
        // Send notification to the seller
        $message = "Your auction '{$auction['title']}' has been rejected. Reason: {$reason}";
        $link = "/user/my-auctions.php?status=rejected";
        
        $notification_sql = "INSERT INTO notifications (user_id, message, link, is_read) VALUES (?, ?, ?, 0)";
        $notification_stmt = $conn->prepare($notification_sql);
        $notification_stmt->bind_param("iss", $auction['seller_id'], $message, $link);
        $notification_stmt->execute();
        
        // Send email to the seller
        $to = $auction['email'];
        $subject = "Your Auction Was Not Approved";
        
        $email_message = "
        <html>
        <head>
            <title>Auction Rejected</title>
        </head>
        <body>
            <h2>Your auction was not approved</h2>
            <p>Dear {$auction['username']},</p>
            <p>We regret to inform you that your auction <strong>{$auction['title']}</strong> has been reviewed and was not approved for the following reason:</p>
            <p style='padding: 10px; background-color: #f8f9fa; border-left: 4px solid #dc3545;'>{$reason}</p>
            <p>You can edit and resubmit your auction by visiting your My Auctions page:</p>
            <p><a href='" . SITE_URL . "/user/my-auctions.php?status=rejected' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>My Auctions</a></p>
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
            <p>Best regards,<br>The " . SITE_NAME . " Team</p>
        </body>
        </html>
        ";
        
        // Set mail headers
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        
        // Send email (disabled in dev/commented until mail server is configured)
        // mail($to, $subject, $email_message, $headers);
        
        $_SESSION['alert'] = [
            'message' => "Auction '{$auction['title']}' has been rejected.",
            'type' => 'success'
        ];
    } else {
        $_SESSION['alert'] = [
            'message' => 'Failed to reject auction. Please try again.',
            'type' => 'danger'
        ];
    }
} else {
    $_SESSION['alert'] = [
        'message' => 'Invalid action.',
        'type' => 'danger'
    ];
}

redirect('pending-auctions.php'); 