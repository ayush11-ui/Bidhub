<?php
require_once 'config.php';

// Set header to send json response
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to post a comment.'
    ]);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['auction_id']) || empty($_POST['auction_id']) || !isset($_POST['comment']) || empty($_POST['comment'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Auction ID and comment text are required.'
    ]);
    exit;
}

$auction_id = (int)$_POST['auction_id'];
$comment_text = sanitize($_POST['comment']);
$user_id = $_SESSION['user_id'];

// Check if auction exists
$sql = "SELECT auction_id FROM auctions WHERE auction_id = ?";
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

// Insert the comment
try {
    $sql = "INSERT INTO comments (auction_id, user_id, comment) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $auction_id, $user_id, $comment_text);
    
    if ($stmt->execute()) {
        $comment_id = $stmt->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Comment posted successfully!',
            'comment_id' => $comment_id,
            'username' => $_SESSION['username'],
            'comment' => $comment_text,
            'time' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($stmt->error);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error posting comment: ' . $e->getMessage()
    ]);
}
?> 