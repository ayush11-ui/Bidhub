<?php
require_once 'includes/config.php';

// Check if auction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'message' => 'Invalid auction ID.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/auctions.php');
}

$auction_id = (int)$_GET['id'];

// Check if winner_id column exists in auctions table and add it if it doesn't
try {
    $check_column_sql = "SELECT COUNT(*) as column_exists
                        FROM information_schema.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'auctions' 
                        AND COLUMN_NAME = 'winner_id'";
    $result = $conn->query($check_column_sql);
    $column_exists = $result->fetch_assoc()['column_exists'];
    
    if ($column_exists == 0) {
        // Add winner_id column
        $conn->query("ALTER TABLE auctions ADD COLUMN winner_id INT(11) DEFAULT NULL");
        // Add winning_bid column
        $conn->query("ALTER TABLE auctions ADD COLUMN winning_bid DECIMAL(10,2) DEFAULT NULL");
        
        // Add foreign key if possible
        try {
            $conn->query("ALTER TABLE auctions ADD FOREIGN KEY (winner_id) REFERENCES users(user_id) ON DELETE SET NULL");
        } catch (Exception $e) {
            // Log error but continue
            error_log("Foreign key error: " . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log("Column check error: " . $e->getMessage());
}

// Retrieve auction details
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name, u.user_id as seller_id,
        w.username as winner_name, w.profile_picture as winner_profile, w.user_id as winner_user_id
        FROM auctions a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        LEFT JOIN users w ON a.winner_id = w.user_id
        WHERE a.auction_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'Auction not found.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/auctions.php');
}

$auction = $result->fetch_assoc();
$page_title = $auction['title'];

// Retrieve auction images
$images = [];
$sql = "SELECT image_id, image_path, is_primary FROM auction_images WHERE auction_id = ? ORDER BY is_primary DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$images_result = $stmt->get_result();
while ($image = $images_result->fetch_assoc()) {
    $images[] = $image;
}

// If no images, use a placeholder
if (empty($images)) {
    $images[] = [
        'image_id' => 0,
        'image_path' => 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available',
        'is_primary' => 1
    ];
}

// Get bid history
$bids = [];
$sql = "SELECT b.*, u.username
        FROM bids b
        JOIN users u ON b.bidder_id = u.user_id
        WHERE b.auction_id = ?
        ORDER BY b.created_at DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$bids_result = $stmt->get_result();
while ($bid = $bids_result->fetch_assoc()) {
    $bids[] = $bid;
}

// Get comments
$comments = [];
$sql = "SELECT c.*, u.username
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.auction_id = ?
        ORDER BY c.created_at DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$comments_result = $stmt->get_result();
while ($comment = $comments_result->fetch_assoc()) {
    $comments[] = $comment;
}

// Process bid submission
$bid_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_bid'])) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = SITE_URL . '/auction-details.php?id=' . $auction_id;
        redirect(SITE_URL . '/login.php?message=login_required');
    }
    
    // Can't bid on your own auction
    if ($_SESSION['user_id'] == $auction['seller_id']) {
        $bid_error = 'You cannot bid on your own auction.';
    } else {
        $bid_amount = (float)$_POST['bid_amount'];
        $min_bid = $auction['current_price'] + $auction['increment_amount'];
        
        if ($bid_amount < $min_bid) {
            $bid_error = 'Your bid must be at least ' . formatPrice($min_bid);
        } else {
            // Place the bid
            $sql = "INSERT INTO bids (auction_id, bidder_id, bid_amount) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iid", $auction_id, $_SESSION['user_id'], $bid_amount);
            
            if ($stmt->execute()) {
                // Update the auction's current price
                $sql = "UPDATE auctions SET current_price = ? WHERE auction_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("di", $bid_amount, $auction_id);
                $stmt->execute();
                
                // Show success message and refresh page
                $_SESSION['alert'] = [
                    'message' => 'Your bid was placed successfully!',
                    'type' => 'success'
                ];
                redirect(SITE_URL . '/auction-details.php?id=' . $auction_id);
            } else {
                $bid_error = 'There was an error placing your bid. Please try again.';
            }
        }
    }
}

// Process comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = SITE_URL . '/auction-details.php?id=' . $auction_id;
        redirect(SITE_URL . '/login.php?message=login_required');
    }
    
    $comment_text = sanitize($_POST['comment_text']);
    
    if (!empty($comment_text)) {
        $sql = "INSERT INTO comments (auction_id, user_id, comment) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $auction_id, $_SESSION['user_id'], $comment_text);
        
        if ($stmt->execute()) {
            // Show success message and refresh page
            $_SESSION['alert'] = [
                'message' => 'Your comment was posted successfully!',
                'type' => 'success'
            ];
            redirect(SITE_URL . '/auction-details.php?id=' . $auction_id);
        }
    }
}

// Add extra JS for bid validation
$extra_js = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const bidInput = document.getElementById("bid-amount");
    const minBid = ' . ($auction['current_price'] + $auction['increment_amount']) . ';
    const bidForm = document.getElementById("bid-form");
    
    if (bidInput && bidForm) {
        bidForm.addEventListener("submit", function(e) {
            if (parseFloat(bidInput.value) < minBid) {
                e.preventDefault();
                alert("Your bid must be at least " + minBid.toFixed(2));
            }
        });
    }
    
    // Update time ago displays for better formatting
    const updateTimeAgo = function() {
        const timeElements = document.querySelectorAll(".comment-time, .text-muted");
        timeElements.forEach(function(element) {
            const timestamp = element.getAttribute("data-timestamp");
            if (timestamp) {
                element.textContent = getTimeAgo(new Date(timestamp));
            }
        });
    };
    
    // Helper function to format time ago
    function getTimeAgo(dateInput) {
        // Ensure we\'re working with a Date object
        const date = dateInput instanceof Date ? dateInput : new Date(dateInput);
        
        // Check if the date is valid
        if (isNaN(date.getTime())) {
            return "invalid date";
        }
        
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 5) {
            return "just now";
        }
        
        if (diffInSeconds < 60) {
            return `${diffInSeconds} second${diffInSeconds !== 1 ? "s" : ""} ago`;
        }
        
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        if (diffInMinutes < 60) {
            return `${diffInMinutes} minute${diffInMinutes !== 1 ? "s" : ""} ago`;
        }
        
        const diffInHours = Math.floor(diffInMinutes / 60);
        if (diffInHours < 24) {
            return `${diffInHours} hour${diffInHours !== 1 ? "s" : ""} ago`;
        }
        
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 30) {
            return `${diffInDays} day${diffInDays !== 1 ? "s" : ""} ago`;
        }
        
        const diffInMonths = Math.floor(diffInDays / 30);
        if (diffInMonths < 12) {
            return `${diffInMonths} month${diffInMonths !== 1 ? "s" : ""} ago`;
        }
        
        const diffInYears = Math.floor(diffInMonths / 12);
        return `${diffInYears} year${diffInYears !== 1 ? "s" : ""} ago`;
    }
    
    // Update time displays initially and then every minute
    updateTimeAgo();
    setInterval(updateTimeAgo, 60000);
});
</script>';

include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Auction Details -->
        <div class="card mb-4">
            <div class="card-body">
                <h1 class="mb-3"><?php echo $auction['title']; ?></h1>
                
                <!-- Image Carousel -->
                <div id="auctionImageCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                    <div class="carousel-inner">
                        <?php foreach ($images as $index => $image): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $image['image_path']; ?>" class="d-block w-100 carousel-image" alt="Auction Image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($images) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#auctionImageCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#auctionImageCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                        <div class="carousel-indicators">
                            <?php foreach ($images as $index => $image): ?>
                                <button type="button" data-bs-target="#auctionImageCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                                    <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                                    aria-label="Slide <?php echo $index + 1; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Auction Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="mb-3">Auction Details</h5>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Category</span>
                                <span class="badge bg-primary rounded-pill"><?php echo $auction['category_name']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Current Price</span>
                                <span class="fw-bold text-primary" id="current-price"><?php echo formatPrice($auction['current_price']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Starting Price</span>
                                <span><?php echo formatPrice($auction['starting_price']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Minimum Increment</span>
                                <span><?php echo formatPrice($auction['increment_amount']); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3">Auction Status</h5>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Status</span>
                                <span class="badge bg-success"><?php echo ucfirst($auction['status']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Seller</span>
                                <span><?php echo $auction['seller_name']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Time Left</span>
                                <span class="countdown-timer fw-bold" data-end-time="<?php echo $auction['end_time']; ?>">Loading...</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>End Date</span>
                                <span><?php echo date('M d, Y h:i A', strtotime($auction['end_time'])); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Description -->
                <h5 class="mb-3">Description</h5>
                <div class="card mb-4">
                    <div class="card-body">
                        <?php echo nl2br($auction['description']); ?>
                    </div>
                </div>
                
                <!-- Place Bid Form -->
                <?php if ($auction['status'] === 'active' && strtotime($auction['end_time']) > time()): ?>
                    <?php if (!isLoggedIn()): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Place Your Bid</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i> You need to <a href="login.php" class="alert-link">login</a> to place a bid.
                            </div>
                        </div>
                    </div>
                    <?php elseif ($_SESSION['user_id'] == $auction['seller_id']): ?>
                    <!-- Auction Management for Owner -->
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Auction Management</h5>
                        </div>
                        <div class="card-body">
                            <p>This is your auction. You can manage it using the options below:</p>
                            
                            <div class="d-grid gap-2">
                                <?php if ($auction['status'] === 'active'): ?>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#endAuctionModal">
                                        <i class="fas fa-stop-circle me-2"></i> End Auction Early
                                    </button>
                                <?php endif; ?>
                                
                                <a href="user/my-auctions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-list me-2"></i> View All My Auctions
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Auction Stats -->
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Auction Stats</h5>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Bids
                                <span class="badge bg-primary rounded-pill"><?php echo count($bids); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Current Highest Bid
                                <span class="badge bg-success"><?php echo formatPrice($auction['current_price']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Auction Status
                                <span class="badge bg-<?php echo $auction['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($auction['status']); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Place Your Bid</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($bid_error): ?>
                                <div class="alert alert-danger"><?php echo $bid_error; ?></div>
                            <?php endif; ?>
                            <div id="alert-container"></div>
                            <form id="bid-form" action="" method="POST" data-auction-id="<?php echo $auction_id; ?>" data-increment="<?php echo $auction['increment_amount']; ?>">
                                <div class="mb-3">
                                    <label for="bid-amount" class="form-label">Your Bid Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="bid-amount" name="bid_amount" 
                                               step="0.01" min="<?php echo $auction['current_price'] + $auction['increment_amount']; ?>" required>
                                    </div>
                                    <div class="form-text">
                                        Minimum bid: <?php echo formatPrice($auction['current_price'] + $auction['increment_amount']); ?>
                                    </div>
                                </div>
                                <button type="submit" name="place_bid" class="btn btn-primary">Place Bid</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> This auction has ended. No more bids can be placed.
                    </div>
                    
                    <?php if ($auction['status'] === 'ended' && isset($auction['winner_id']) && $auction['winner_id'] != null): ?>
                        <!-- Winner information -->
                        <div class="card mb-4 border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i> Auction Winner</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <?php 
                                        $winner_image = !empty($auction['winner_profile']) ? $auction['winner_profile'] : 'https://placehold.co/200x200/e9ecef/495057?text=User';
                                        ?>
                                        <img src="<?php echo $winner_image; ?>" alt="Winner Profile" class="rounded-circle" width="60" height="60">
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?php echo $auction['winner_name']; ?></h5>
                                        <p class="mb-1">
                                            Winning Bid: <span class="fw-bold text-success"><?php echo formatPrice($auction['winning_bid']); ?></span>
                                        </p>
                                        
                                        <?php if (isLoggedIn() && $_SESSION['user_id'] == $auction['winner_user_id']): ?>
                                            <div class="alert alert-success mt-3 mb-0">
                                                <i class="fas fa-check-circle me-2"></i> Congratulations! You won this auction.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($auction['status'] === 'ended'): ?>
                        <!-- No winner -->
                        <div class="alert alert-secondary">
                            <i class="fas fa-info-circle me-2"></i> This auction ended without any bids.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Bid History -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Bid History</h5>
            </div>
            <div class="card-body">
                <?php if (empty($bids)): ?>
                    <p class="text-muted">No bids have been placed yet.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($bids as $bid): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span><i class="fas fa-user me-2"></i> <?php echo $bid['username']; ?></span>
                                    <span class="fw-bold"><?php echo formatPrice($bid['bid_amount']); ?></span>
                                </div>
                                <small class="text-muted" data-timestamp="<?php echo $bid['created_at']; ?>"><?php echo getTimeAgo($bid['created_at']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Comments -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Comments</h5>
            </div>
            <div class="card-body">
                <?php if (isLoggedIn()): ?>
                    <form action="" method="POST" class="mb-4">
                        <div class="mb-3">
                            <label for="comment-text" class="form-label">Add a Comment</label>
                            <textarea class="form-control" id="comment-text" name="comment_text" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="post_comment" class="btn btn-primary">Post Comment</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-info-circle me-2"></i> You need to <a href="login.php" class="alert-link">login</a> to post comments.
                    </div>
                <?php endif; ?>
                
                <?php if (empty($comments)): ?>
                    <p class="text-muted">No comments yet. Be the first to comment!</p>
                <?php else: ?>
                    <div class="comments-section">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment mb-3">
                                <div class="comment-header">
                                    <span class="comment-author">
                                        <i class="fas fa-user me-1"></i> <?php echo $comment['username']; ?>
                                    </span>
                                    <span class="comment-time" data-timestamp="<?php echo $comment['created_at']; ?>"><?php echo getTimeAgo($comment['created_at']); ?></span>
                                </div>
                                <div class="comment-body mt-2">
                                    <?php echo nl2br($comment['comment']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Related Auctions -->
<section class="mt-5">
    <h2 class="section-title">Similar Auctions</h2>
    <div class="row">
        <?php
        $sql = "SELECT a.auction_id, a.title, a.current_price, a.end_time,
                (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image
                FROM auctions a
                WHERE a.category_id = ? AND a.auction_id != ? AND a.status = 'active' AND a.end_time > NOW()
                ORDER BY a.created_at DESC
                LIMIT 4";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $auction['category_id'], $auction_id);
        $stmt->execute();
        $related = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (count($related) > 0):
            foreach ($related as $rel_auction):
        ?>
            <div class="col-md-3">
                <div class="card auction-card h-100">
                    <img src="<?php echo $rel_auction['primary_image'] ? $rel_auction['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                         class="card-img-top" alt="<?php echo $rel_auction['title']; ?>">
                    <div class="card-body">
                        <h5 class="card-title text-truncate"><?php echo $rel_auction['title']; ?></h5>
                        <p class="auction-price"><?php echo formatPrice($rel_auction['current_price']); ?></p>
                        <p class="auction-time">
                            <i class="far fa-clock me-1"></i>
                            <span class="countdown-timer" data-end-time="<?php echo $rel_auction['end_time']; ?>">
                                Loading...
                            </span>
                        </p>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="auction-details.php?id=<?php echo $rel_auction['auction_id']; ?>" class="btn btn-primary btn-sm w-100">View Auction</a>
                    </div>
                </div>
            </div>
        <?php 
            endforeach;
        else:
        ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No similar auctions found at the moment.
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.carousel-image {
    height: 400px;
    object-fit: contain;
    background-color: #f8f9fa;
}
.comment {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
}
</style>

<?php include 'includes/footer.php'; ?>

<!-- End Auction Modal -->
<?php if (isLoggedIn() && $_SESSION['user_id'] == $auction['seller_id'] && $auction['status'] === 'active'): ?>
<div class="modal fade" id="endAuctionModal" tabindex="-1" aria-labelledby="endAuctionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="endAuctionModalLabel">End Auction Early</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold">Are you sure you want to end this auction early?</p>
                <p>If there are bids on the auction, the highest bidder will be declared the winner. 
                If there are no bids, the auction will be marked as ended without a winner.</p>
                <p class="text-danger">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="user/end-auction.php" method="POST">
                    <input type="hidden" name="auction_id" value="<?php echo $auction_id; ?>">
                    <input type="hidden" name="action" value="end_early">
                    <button type="submit" class="btn btn-danger">End Auction Now</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?> 