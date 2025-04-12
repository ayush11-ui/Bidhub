<?php
$page_title = "Live Auctions";
$page_header = "Live Auctions";
$page_subheader = "Watch and bid on auctions happening now";

// Add additional CSS for live auctions page
$extra_css = '<style>
.live-auction-header {
    position: relative;
    padding-left: 25px;
}
.live-indicator {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    background-color: #f44336;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(244, 67, 54, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
    }
}
.auction-timer {
    font-size: 1.2rem;
    font-weight: 700;
}
.bid-history {
    max-height: 300px;
    overflow-y: auto;
}
.comment-section {
    max-height: 400px;
    overflow-y: auto;
}
</style>';

// Additional JS for real-time updates
$extra_js = '<script>
function updateAuctionData() {
    const liveAuctionContainers = document.querySelectorAll(".live-auction-container");
    
    liveAuctionContainers.forEach(container => {
        const auctionId = container.getAttribute("data-auction-id");
        const url = "includes/get_auction_updates.php?auction_id=" + auctionId;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update current price
                    const priceElement = container.querySelector(".current-price");
                    if (priceElement) {
                        priceElement.textContent = data.current_price;
                    }
                    
                    // Update bid history if available
                    if (data.latest_bids && data.latest_bids.length > 0) {
                        const bidsList = container.querySelector(".bids-list");
                        if (bidsList) {
                            // Only update if there are new bids
                            const lastBidId = bidsList.getAttribute("data-last-bid-id");
                            if (!lastBidId || data.latest_bids[0].bid_id != lastBidId) {
                                bidsList.innerHTML = "";
                                data.latest_bids.forEach(bid => {
                                    const bidEl = document.createElement("div");
                                    bidEl.className = "alert alert-light mb-2";
                                    bidEl.innerHTML = `<strong>${bid.username}</strong> placed a bid of 
                                    ${bid.amount} <span class="text-muted" data-timestamp="${bid.created_at}">${new Date(bid.created_at).toLocaleString()}</span>`;
                                    bidsList.appendChild(bidEl);
                                });
                                if (data.latest_bids.length > 0) {
                                    bidsList.setAttribute("data-last-bid-id", data.latest_bids[0].bid_id);
                                }
                                
                                // Initialize time ago for new bids
                                initializeTimestamps();
                            }
                        }
                    }
                }
            })
            .catch(error => console.error("Error fetching auction updates:", error));
    });
}

// Update every 5 seconds
setInterval(updateAuctionData, 5000);

// Initialize timestamps to show time ago format
function initializeTimestamps() {
    // Update all timestamps with data-timestamp attribute
    document.querySelectorAll("[data-timestamp]").forEach(el => {
        const timestamp = el.getAttribute("data-timestamp");
        el.textContent = getTimeAgo(new Date(timestamp));
    });
}

// Run initializeTimestamps when page loads
document.addEventListener("DOMContentLoaded", function() {
    initializeTimestamps();
    
    // Update timestamps every minute
    setInterval(initializeTimestamps, 60000);
});

// Helper function to format time ago
function getTimeAgo(dateInput) {
    // Ensure we are working with a Date object
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
    return `${diffInDays} day${diffInDays !== 1 ? "s" : ""} ago`;
}
</script>';

include 'includes/header.php';

// Get live auctions ending soon
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count
        FROM auctions a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.status = 'active' AND a.end_time > NOW()
        ORDER BY a.end_time ASC
        LIMIT 6";

$result = $conn->query($sql);
$live_auctions = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $live_auctions[] = $row;
    }
}

// Get total active auctions
$total_sql = "SELECT COUNT(*) as total FROM auctions WHERE status = 'active' AND end_time > NOW()";
$total_result = $conn->query($total_sql);
$total_active = $total_result->fetch_assoc()['total'];
?>

<!-- Live Auction Banner -->
<div class="bg-primary text-white py-4 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="live-auction-header mb-2">
                    <span class="live-indicator"></span>
                    Live Auctions
                </h2>
                <p class="mb-0">Currently <strong><?php echo $total_active; ?></strong> auctions are active. Be quick to place your bids!</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="auctions.php?status=active" class="btn btn-outline-light">View All Auctions</a>
            </div>
        </div>
    </div>
</div>

<!-- Live Auctions -->
<div class="container">
    <?php if (empty($live_auctions)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i> There are no live auctions at the moment. Please check back later.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($live_auctions as $index => $auction): ?>
                <?php
                // Get the latest bids for this auction
                $sql = "SELECT b.bid_id, b.bid_amount, b.created_at, u.username
                        FROM bids b
                        JOIN users u ON b.bidder_id = u.user_id
                        WHERE b.auction_id = ?
                        ORDER BY b.created_at DESC
                        LIMIT 5";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $auction['auction_id']);
                $stmt->execute();
                $bids_result = $stmt->get_result();
                $bids = [];
                while ($bid = $bids_result->fetch_assoc()) {
                    // Add time_ago field to match the AJAX response format
                    $bid['time_ago'] = getTimeAgo($bid['created_at']);
                    $bids[] = $bid;
                }
                
                // Check if this is one of the first two auctions (featured)
                $is_featured = $index < 2;
                ?>
                
                <div class="col-lg-<?php echo $is_featured ? '6' : '4'; ?> mb-4">
                    <div class="card live-auction-container" data-auction-id="<?php echo $auction['auction_id']; ?>">
                        <div class="card-header bg-primary text-white">
                            <h5 class="live-auction-header mb-0">
                                <span class="live-indicator"></span>
                                <?php echo $auction['title']; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="<?php echo $is_featured ? 'col-md-6' : 'col-12 mb-3'; ?>">
                                    <img src="<?php echo $auction['primary_image'] ? $auction['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                                         class="img-fluid rounded" alt="<?php echo $auction['title']; ?>" style="width: 100%; height: <?php echo $is_featured ? '250px' : '200px'; ?>; object-fit: cover;">
                                </div>
                                <div class="<?php echo $is_featured ? 'col-md-6' : 'col-12'; ?>">
                                    <div class="mb-3">
                                        <p class="mb-1">Current Price:</p>
                                        <h3 class="text-primary current-price"><?php echo formatPrice($auction['current_price']); ?></h3>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="mb-1">Time Left:</p>
                                        <div class="auction-timer countdown-timer" data-end-time="<?php echo $auction['end_time']; ?>">
                                            Loading...
                                        </div>
                                    </div>
                                    
                                    <p><strong>Category:</strong> <span class="badge bg-secondary"><?php echo $auction['category_name']; ?></span></p>
                                    
                                    <?php if ($is_featured): ?>
                                    <p class="text-truncate"><strong>Seller:</strong> <?php echo $auction['seller_name']; ?></p>
                                    <p class="mb-3"><strong>Bids:</strong> <?php echo $auction['bid_count']; ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid">
                                        <a href="auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-primary">
                                            <?php if (isLoggedIn() && $_SESSION['user_id'] == $auction['seller_id']): ?>
                                                View Your Auction
                                            <?php else: ?>
                                                View Auction
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($is_featured && !empty($bids)): ?>
                            <div class="mt-4">
                                <h5>Recent Bids</h5>
                                <div class="bid-history bids-list" data-last-bid-id="<?php echo !empty($bids) ? $bids[0]['bid_id'] : ''; ?>">
                                    <?php foreach ($bids as $bid): ?>
                                    <div class="alert alert-light mb-2">
                                        <strong><?php echo $bid['username']; ?></strong> placed a bid of 
                                        <?php echo formatPrice($bid['bid_amount']); ?> 
                                        <span class="text-muted" data-timestamp="<?php echo $bid['created_at']; ?>"><?php echo date('M d, Y h:i A', strtotime($bid['created_at'])); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-gavel me-1"></i> <?php echo $auction['bid_count']; ?> bids</span>
                                <small class="text-muted">Ends on <?php echo date('M d, Y h:i A', strtotime($auction['end_time'])); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_active > count($live_auctions)): ?>
        <div class="text-center mt-3 mb-5">
            <a href="auctions.php?status=active" class="btn btn-outline-primary">View All <?php echo $total_active; ?> Active Auctions</a>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!isLoggedIn()): ?>
<!-- How to Participate Section -->
<section class="bg-light py-5 mt-5">
    <div class="container">
        <h2 class="section-title text-center mb-4">How to Participate in Live Auctions</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="fas fa-user-plus fa-3x text-primary"></i>
                        </div>
                        <h4>1. Create an Account</h4>
                        <p>Sign up for a free account to start participating in our live auctions.</p>
                        <?php if (!isLoggedIn()): ?>
                            <a href="register.php" class="btn btn-sm btn-outline-primary">Register Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="fas fa-search fa-3x text-primary"></i>
                        </div>
                        <h4>2. Browse Auctions</h4>
                        <p>Find the items you're interested in among our diverse range of active auctions.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <i class="fas fa-gavel fa-3x text-primary"></i>
                        </div>
                        <h4>3. Place Your Bids</h4>
                        <p>Bid on items in real-time and compete with other bidders to win your desired items.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Coming Soon Auctions -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title mb-4">Upcoming Auctions</h2>
        
        <?php
        // Get auctions that will start within the next 24 hours
        $sql = "SELECT a.auction_id, a.title, a.starting_price, a.start_time, c.name as category_name,
                (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image
                FROM auctions a
                JOIN categories c ON a.category_id = c.category_id
                WHERE a.status = 'pending' AND a.start_time > NOW() AND a.start_time < DATE_ADD(NOW(), INTERVAL 24 HOUR)
                ORDER BY a.start_time ASC
                LIMIT 3";
        $result = $conn->query($sql);
        $upcoming_auctions = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $upcoming_auctions[] = $row;
            }
        }
        
        if (!empty($upcoming_auctions)):
        ?>
            <div class="row">
                <?php foreach ($upcoming_auctions as $auction): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">Starting Soon</h5>
                            </div>
                            <img src="<?php echo $auction['primary_image'] ? $auction['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                                 class="card-img-top" alt="<?php echo $auction['title']; ?>" style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $auction['title']; ?></h5>
                                <p class="card-text">
                                    <strong>Starting Price:</strong> <?php echo formatPrice($auction['starting_price']); ?><br>
                                    <strong>Category:</strong> <?php echo $auction['category_name']; ?><br>
                                    <strong>Starts:</strong> <?php echo date('M d, Y h:i A', strtotime($auction['start_time'])); ?>
                                </p>
                            </div>
                            <div class="card-footer text-center">
                                <div id="countdown-<?php echo $auction['auction_id']; ?>" class="fw-bold text-warning">
                                    Starting in: <span class="countdown-timer" data-end-time="<?php echo $auction['start_time']; ?>">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No upcoming auctions scheduled for the next 24 hours. Check back later!
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?> 