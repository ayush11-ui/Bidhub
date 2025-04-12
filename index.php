<?php 
$page_title = "Home"; 
include 'includes/header.php';

// Fetch featured auctions
$featured_auctions = [];
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM auctions a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.featured = 1 AND a.status = 'active' AND a.end_time > NOW()
        ORDER BY a.created_at DESC
        LIMIT 4";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $featured_auctions[] = $row;
    }
}

// Fetch live auctions count
$live_count = 0;
$sql = "SELECT COUNT(*) as count FROM auctions WHERE status = 'active' AND end_time > NOW()";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $live_count = $row['count'];
}

// Fetch categories with auction counts
$categories = [];
$sql = "SELECT c.category_id, c.name, COUNT(a.auction_id) as auction_count
        FROM categories c
        LEFT JOIN auctions a ON c.category_id = a.category_id
        GROUP BY c.category_id
        ORDER BY auction_count DESC
        LIMIT 6";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// For logged-in users, fetch their bids and auctions stats
$user_stats = [];
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Get winning bids count
    $sql = "SELECT COUNT(*) as winning_bids 
            FROM bids b
            JOIN auctions a ON b.auction_id = a.auction_id
            WHERE b.bidder_id = ? AND b.bid_amount = a.current_price AND a.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_stats['winning_bids'] = $result->fetch_assoc()['winning_bids'];
    
    // Get active auctions count
    $sql = "SELECT COUNT(*) as active_auctions FROM auctions WHERE seller_id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_stats['active_auctions'] = $result->fetch_assoc()['active_auctions'];
    
    // Get ending soon (24 hours) auctions with bids
    $sql = "SELECT DISTINCT a.auction_id, a.title, a.current_price, a.end_time,
            (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id ORDER BY is_primary DESC LIMIT 1) as image_path
            FROM auctions a
            JOIN bids b ON a.auction_id = b.auction_id
            WHERE b.bidder_id = ? AND a.status = 'active' AND a.end_time <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.end_time ASC
            LIMIT 3";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_stats['ending_soon'] = [];
    while ($row = $result->fetch_assoc()) {
        $user_stats['ending_soon'][] = $row;
    }
}
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container text-center">
        <h1 class="animated slideInDown">Welcome to BidHub</h1>
        <p class="animated slideInDown">Your Premier Online Auction Platform</p>
        <div class="mt-4">
            <a href="auctions.php" class="btn btn-primary btn-lg me-2">Browse Auctions</a>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-outline-light btn-lg">Join Now</a>
            <?php else: ?>
                <a href="user/create-auction.php" class="btn btn-outline-light btn-lg">Sell an Item</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (isLoggedIn()): ?>
<!-- User Dashboard Section -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title">Your Auction Dashboard</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 dashboard-card">
                    <div class="card-body">
                        <div class="dashboard-card-icon bg-success">
                            <i class="fas fa-hand-paper"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $user_stats['winning_bids']; ?></h3>
                            <p>Auctions You're Winning</p>
                            <a href="user/my-bids.php?status=winning" class="btn btn-sm btn-outline-success mt-2">View All</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 dashboard-card">
                    <div class="card-body">
                        <div class="dashboard-card-icon bg-primary">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $user_stats['active_auctions']; ?></h3>
                            <p>Your Active Auctions</p>
                            <a href="user/my-auctions.php?status=active" class="btn btn-sm btn-outline-primary mt-2">Manage Auctions</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 dashboard-card">
                    <div class="card-body">
                        <div class="dashboard-card-icon bg-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo count($user_stats['ending_soon']); ?></h3>
                            <p>Auctions Ending Soon</p>
                            <a href="live.php" class="btn btn-sm btn-outline-warning mt-2">View Live Auctions</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($user_stats['ending_soon'])): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Your Auctions Ending Soon</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Auction</th>
                                <th>Current Bid</th>
                                <th>Ends In</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats['ending_soon'] as $auction): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $auction['image_path'] ? $auction['image_path'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>" 
                                            class="img-thumbnail me-2" alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                            style="width: 50px; height: 50px; object-fit: cover;">
                                        <div><?php echo htmlspecialchars($auction['title']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo formatPrice($auction['current_price']); ?></td>
                                <td>
                                    <span data-countdown="<?php echo $auction['end_time']; ?>">Loading...</span>
                                </td>
                                <td>
                                    <a href="auction.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="py-5 <?php echo isLoggedIn() ? 'bg-light' : ''; ?>">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 text-center py-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-gavel fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Bid on Unique Items</h4>
                        <p class="card-text">Find unique treasures and collectibles from trusted sellers.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center py-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-tags fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Sell Your Items</h4>
                        <p class="card-text">Reach thousands of potential buyers and get the best price for your items.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center py-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-shield-alt fa-3x text-primary"></i>
                        </div>
                        <h4 class="card-title">Secure Transactions</h4>
                        <p class="card-text">Our platform ensures safe and secure transactions for all users.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Auctions Section -->
<section class="py-5 <?php echo !isLoggedIn() ? 'bg-light' : ''; ?>">
    <div class="container">
        <h2 class="section-title text-center">Featured Auctions</h2>
        
        <?php if (count($featured_auctions) > 0): ?>
            <div class="row g-4">
                <?php foreach ($featured_auctions as $auction): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card auction-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $auction['primary_image'] ? $auction['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                                     class="card-img-top" alt="<?php echo $auction['title']; ?>" style="height: 180px; object-fit: cover;">
                                <span class="auction-status active">Active</span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $auction['title']; ?></h5>
                                <p class="card-text text-truncate"><?php echo $auction['description']; ?></p>
                                <p class="auction-price"><?php echo formatPrice($auction['current_price']); ?></p>
                                <p class="auction-time">
                                    <i class="far fa-clock me-1"></i>
                                    <span class="countdown-timer" data-end-time="<?php echo $auction['end_time']; ?>">
                                        Loading...
                                    </span>
                                </p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-primary w-100">
                                    <?php if (isLoggedIn()): ?>
                                        Place Bid
                                    <?php else: ?>
                                        View Details
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="auctions.php" class="btn btn-outline-primary">View All Auctions</a>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No featured auctions available at the moment.</div>
        <?php endif; ?>
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-center">Popular Categories</h2>
        <div class="row g-4">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-4 col-lg-2">
                    <a href="auctions.php?category=<?php echo $category['category_id']; ?>" class="text-decoration-none">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $category['name']; ?></h5>
                                <p class="card-text"><?php echo $category['auction_count']; ?> total auctions</p>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Live Auctions CTA -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="mb-4">Live Auctions Happening Now!</h2>
        <p class="lead mb-4">Join <?php echo $live_count; ?> active auctions and start bidding on your favorite items.</p>
        <a href="live.php" class="btn btn-light btn-lg">View Live Auctions</a>
    </div>
</section>

<?php if (!isLoggedIn()): ?>
<!-- How It Works Section - Only visible to non-logged in users -->
<section class="py-5">
    <div class="container">
        <h2 class="section-title text-center">How It Works</h2>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px">
                                <span class="h4 m-0">1</span>
                            </div>
                        </div>
                        <h4 class="card-title">Register</h4>
                        <p class="card-text">Create an account to start bidding or selling on BidHub.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px">
                                <span class="h4 m-0">2</span>
                            </div>
                        </div>
                        <h4 class="card-title">Browse</h4>
                        <p class="card-text">Find items you love from our wide selection of auctions.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px">
                                <span class="h4 m-0">3</span>
                            </div>
                        </div>
                        <h4 class="card-title">Bid</h4>
                        <p class="card-text">Place bids on items you want to win.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px">
                                <span class="h4 m-0">4</span>
                            </div>
                        </div>
                        <h4 class="card-title">Win</h4>
                        <p class="card-text">Complete your purchase and enjoy your new item!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Join Now CTA for non-logged-in users -->
<section class="py-5 bg-light">
    <div class="container text-center">
        <h2 class="mb-4">Ready to Start Bidding?</h2>
        <p class="lead mb-4">Create an account to place bids, track auctions, and sell your own items.</p>
        <div>
            <a href="register.php" class="btn btn-primary btn-lg me-2">Register Now</a>
            <a href="login.php" class="btn btn-outline-primary btn-lg">Login</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 