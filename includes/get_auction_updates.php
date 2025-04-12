<?php
require_once 'config.php';

// Set header to send json response
header('Content-Type: application/json');

// Check if auction ID is provided
if (!isset($_GET['auction_id']) || empty($_GET['auction_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction ID is required.'
    ]);
    exit;
}

$auction_id = (int)$_GET['auction_id'];

// Get current auction price
$sql = "SELECT auction_id, current_price, status, end_time FROM auctions WHERE auction_id = ?";
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

// Get latest bids
$bids = [];
$sql = "SELECT b.bid_id, b.bid_amount, b.created_at, u.username
        FROM bids b
        JOIN users u ON b.bidder_id = u.user_id
        WHERE b.auction_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$bids_result = $stmt->get_result();
while ($bid = $bids_result->fetch_assoc()) {
    $bids[] = [
        'bid_id' => $bid['bid_id'],
        'username' => $bid['username'],
        'amount' => formatPrice($bid['bid_amount']),
        'created_at' => $bid['created_at'],
        'time_ago' => getTimeAgo($bid['created_at'])
    ];
}

// Get latest comments
$comments = [];
$sql = "SELECT c.comment_id, c.comment, c.created_at, u.username
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.auction_id = ?
        ORDER BY c.created_at DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$comments_result = $stmt->get_result();
while ($comment = $comments_result->fetch_assoc()) {
    $comments[] = [
        'comment_id' => $comment['comment_id'],
        'username' => $comment['username'],
        'text' => $comment['comment'],
        'created_at' => $comment['created_at'],
        'time_ago' => getTimeAgo($comment['created_at'])
    ];
}

// Return the data
echo json_encode([
    'success' => true,
    'auction_id' => $auction_id,
    'current_price' => formatPrice($auction['current_price']),
    'raw_price' => $auction['current_price'],
    'status' => $auction['status'],
    'end_time' => $auction['end_time'],
    'is_active' => ($auction['status'] === 'active' && strtotime($auction['end_time']) > time()),
    'latest_bids' => $bids,
    'latest_comments' => $comments
]);
?> 