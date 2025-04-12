<?php
require_once 'config.php';

// Set header to send json response
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to place a bid.'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['auction_id']) || empty($_POST['auction_id']) || !isset($_POST['bid_amount']) || empty($_POST['bid_amount'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction ID and bid amount are required.'
    ]);
    exit;
}

$auction_id = (int)$_POST['auction_id'];
$bid_amount = (float)$_POST['bid_amount'];
$user_id = $_SESSION['user_id'];

// Get auction details
$sql = "SELECT a.*, u.user_id as seller_id
        FROM auctions a
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.auction_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction not found.'
    ]);
    exit;
}

$auction = $result->fetch_assoc();

// Check if auction is active
if ($auction['status'] !== 'active') {
    echo json_encode([
        'success' => false,
        'message' => 'This auction is not active.'
    ]);
    exit;
}

// Check if auction has ended
if (strtotime($auction['end_time']) <= time()) {
    echo json_encode([
        'success' => false,
        'message' => 'This auction has ended.'
    ]);
    exit;
}

// Check if user is trying to bid on their own auction
if ($user_id == $auction['seller_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'You cannot bid on your own auction.'
    ]);
    exit;
}

// Check if bid amount is higher than current price + increment
$min_bid = $auction['current_price'] + $auction['increment_amount'];
if ($bid_amount < $min_bid) {
    echo json_encode([
        'success' => false,
        'message' => 'Your bid must be at least ' . formatPrice($min_bid)
    ]);
    exit;
}

// Place the bid
$conn->begin_transaction();

try {
    // Insert bid
    $sql = "INSERT INTO bids (auction_id, bidder_id, bid_amount) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iid", $auction_id, $user_id, $bid_amount);
    $stmt->execute();
    
    // Update auction current price
    $sql = "UPDATE auctions SET current_price = ? WHERE auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $bid_amount, $auction_id);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Bid placed successfully!',
        'new_price' => formatPrice($bid_amount),
        'raw_price' => $bid_amount
    ]);
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Error placing bid: ' . $e->getMessage()
    ]);
}
?> 