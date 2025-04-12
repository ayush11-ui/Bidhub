<?php
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/my-auctions.php';
    redirect(SITE_URL . '/login.php?message=login_required');
}

// Check if auction ID and action are provided
if (!isset($_POST['auction_id']) || empty($_POST['auction_id']) || !isset($_POST['action']) || $_POST['action'] !== 'end_early') {
    $_SESSION['alert'] = [
        'message' => 'Invalid request.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/user/my-auctions.php');
}

$auction_id = (int)$_POST['auction_id'];
$user_id = $_SESSION['user_id'];

// Get auction details and verify ownership
$sql = "SELECT * FROM auctions WHERE auction_id = ? AND seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $auction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to end this auction.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/user/my-auctions.php');
}

$auction = $result->fetch_assoc();

// Check if auction is active
if ($auction['status'] !== 'active') {
    $_SESSION['alert'] = [
        'message' => 'This auction is not active and cannot be ended.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/auction-details.php?id=' . $auction_id);
}

// Start transaction
$conn->begin_transaction();

try {
    // First check if winner_id and winning_bid columns exist, add them if they don't
    $check_columns_sql = "SELECT 
        SUM(CASE WHEN COLUMN_NAME = 'winner_id' THEN 1 ELSE 0 END) AS winner_id_exists,
        SUM(CASE WHEN COLUMN_NAME = 'winning_bid' THEN 1 ELSE 0 END) AS winning_bid_exists
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auctions'";
    
    $columns_result = $conn->query($check_columns_sql);
    $columns_data = $columns_result->fetch_assoc();
    
    // Add winner_id column if it doesn't exist
    if ($columns_data['winner_id_exists'] == 0) {
        $conn->query("ALTER TABLE auctions ADD COLUMN winner_id INT(11) DEFAULT NULL");
    }
    
    // Add winning_bid column if it doesn't exist
    if ($columns_data['winning_bid_exists'] == 0) {
        $conn->query("ALTER TABLE auctions ADD COLUMN winning_bid DECIMAL(10,2) DEFAULT NULL");
    }
    
    // Check if foreign key exists and add if it doesn't
    try {
        // Check if the foreign key already exists
        $check_fk_sql = "SELECT COUNT(*) as cnt
                         FROM information_schema.TABLE_CONSTRAINTS 
                         WHERE CONSTRAINT_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'auctions' 
                         AND CONSTRAINT_NAME LIKE '%winner_id%'";
        $result = $conn->query($check_fk_sql);
        $row = $result->fetch_assoc();
        
        if ($row['cnt'] == 0) {
            // Add the foreign key if it doesn't exist
            $conn->query("ALTER TABLE auctions ADD FOREIGN KEY (winner_id) REFERENCES users(user_id) ON DELETE SET NULL");
        }
    } catch (Exception $e) {
        error_log("Foreign key check/add error: " . $e->getMessage());
    }
    
    // Update auction status to ended
    $sql = "UPDATE auctions SET status = 'ended', end_time = NOW() WHERE auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    
    // If there are bids, notify the winner
    $sql = "SELECT b.bidder_id, b.bid_amount, u.username, u.email
            FROM bids b
            JOIN users u ON b.bidder_id = u.user_id
            WHERE b.auction_id = ?
            ORDER BY b.bid_amount DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    $highest_bid = $stmt->get_result()->fetch_assoc();
    
    if ($highest_bid) {
        // Declare a winner
        $winner_id = $highest_bid['bidder_id'];
        $winning_bid = $highest_bid['bid_amount'];
        
        // Update the auction with the winner information
        $sql = "UPDATE auctions SET winner_id = ?, winning_bid = ? WHERE auction_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idi", $winner_id, $winning_bid, $auction_id);
        $stmt->execute();
        
        // Add notification for the winner
        $notification_message = "Congratulations! You've won the auction for '{$auction['title']}' with a bid of " . formatPrice($winning_bid);
        $notification_link = SITE_URL . "/auction-details.php?id=" . $auction_id;
        
        $sql = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $winner_id, $notification_message, $notification_link);
        $stmt->execute();
        
        // Add notification for the seller
        $seller_notification = "Your auction '{$auction['title']}' has ended. The item was sold for " . formatPrice($winning_bid);
        
        $sql = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $user_id, $seller_notification, $notification_link);
        $stmt->execute();
    }
    
    $conn->commit();
    
    $_SESSION['alert'] = [
        'message' => 'Auction has been ended successfully.',
        'type' => 'success'
    ];
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['alert'] = [
        'message' => 'Error ending auction: ' . $e->getMessage(),
        'type' => 'danger'
    ];
}

redirect(SITE_URL . '/auction-details.php?id=' . $auction_id);
?> 