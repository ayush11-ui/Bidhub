<?php
$page_title = "View User";
require_once '../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to access the admin area.',
        'type' => 'danger'
    ];
    redirect(SITE_URL);
}

// Get total pending auctions for sidebar badge
$sql = "SELECT COUNT(*) as count FROM auctions WHERE status = 'pending'";
$result = $conn->query($sql);
$total_pending = $result->fetch_assoc()['count'];

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'message' => 'User ID is required.',
        'type' => 'danger'
    ];
    redirect('users.php');
}

$user_id = (int)$_GET['id'];

// Get user details
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'User not found.',
        'type' => 'danger'
    ];
    redirect('users.php');
}

$user = $result->fetch_assoc();

// Get user stats
$stats = [
    'total_auctions' => 0,
    'active_auctions' => 0,
    'successful_auctions' => 0,
    'total_bids' => 0,
    'winning_bids' => 0,
    'total_comments' => 0
];

// Total auctions
$sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_auctions'] = $stmt->get_result()->fetch_assoc()['count'];

// Active auctions
$sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ? AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['active_auctions'] = $stmt->get_result()->fetch_assoc()['count'];

// Successful auctions (ended with bids)
$sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ? AND status = 'ended' AND current_price > starting_price";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['successful_auctions'] = $stmt->get_result()->fetch_assoc()['count'];

// Total bids
$sql = "SELECT COUNT(*) as count FROM bids WHERE bidder_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_bids'] = $stmt->get_result()->fetch_assoc()['count'];

// Winning bids (highest bid on active auctions or winner on ended auctions)
$sql = "SELECT COUNT(*) as count FROM auctions a
        WHERE (a.status = 'active' AND (SELECT MAX(b.bidder_id) FROM bids b WHERE b.auction_id = a.auction_id AND b.bid_amount = a.current_price) = ?)
        OR (a.status = 'ended' AND a.winner_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats['winning_bids'] = $stmt->get_result()->fetch_assoc()['count'];

// Total comments
$sql = "SELECT COUNT(*) as count FROM comments WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_comments'] = $stmt->get_result()->fetch_assoc()['count'];

// Get recent auctions
$auctions = [];
$sql = "SELECT a.auction_id, a.title, a.current_price, a.status, a.end_time,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id ORDER BY is_primary DESC LIMIT 1) as image_path
        FROM auctions a
        WHERE a.seller_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $auctions[] = $row;
}

// Get recent bids
$bids = [];
$sql = "SELECT b.bid_id, b.auction_id, b.bid_amount, b.created_at,
        a.title as auction_title, a.status as auction_status,
        (SELECT MAX(bid_amount) FROM bids WHERE auction_id = b.auction_id) as current_high
        FROM bids b
        JOIN auctions a ON b.auction_id = a.auction_id
        WHERE b.bidder_id = ?
        ORDER BY b.created_at DESC
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bids[] = $row;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Admin Sidebar -->
        <div class="col-lg-3 col-xl-2">
            <div class="list-group mb-4">
                <a href="index.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="auctions.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-gavel me-2"></i> Manage Auctions
                </a>
                <a href="pending-auctions.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-clock me-2"></i> Pending Auctions
                    <?php if ($total_pending > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $total_pending; ?></span>
                    <?php endif; ?>
                </a>
                <a href="categories.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-tags me-2"></i> Manage Categories
                </a>
                <a href="<?php echo SITE_URL; ?>" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-arrow-left me-2"></i> Back to Site
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9 col-xl-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="fas fa-user me-2"></i> User Profile</h1>
                <a href="users.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Users
                </a>
            </div>
            
            <!-- User Profile -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <div class="mb-3">
                                <img src="https://placehold.co/200x200/e9ecef/495057?text=User" class="rounded-circle" alt="User Avatar" width="120" height="120">
                            </div>
                            <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                            
                            <div class="d-grid gap-2 mt-3">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-danger p-2 mb-2">Administrator</span>
                                <?php else: ?>
                                    <span class="badge bg-info p-2 mb-2">Regular User</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer text-muted">
                            <small>
                                <i class="fas fa-calendar me-1"></i> Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">User Statistics</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-gavel"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['total_auctions']; ?></div>
                                            <p class="small mb-0">Total Auctions</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['active_auctions']; ?></div>
                                            <p class="small mb-0">Active Auctions</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-info text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-trophy"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['successful_auctions']; ?></div>
                                            <p class="small mb-0">Successful Sales</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-warning text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-hand-paper"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['total_bids']; ?></div>
                                            <p class="small mb-0">Total Bids</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-danger text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-award"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['winning_bids']; ?></div>
                                            <p class="small mb-0">Winning Bids</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-body text-center p-2">
                                            <div class="mb-2">
                                                <div class="d-inline-flex align-items-center justify-content-center bg-secondary text-white rounded-circle p-2" style="width: 45px; height: 45px;">
                                                    <i class="fas fa-comments"></i>
                                                </div>
                                            </div>
                                            <div class="h2 fw-bold mb-1"><?php echo $stats['total_comments']; ?></div>
                                            <p class="small mb-0">Comments</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Auctions and Bids -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Auctions</h5>
                            <?php if ($stats['total_auctions'] > 0): ?>
                                <a href="auctions.php?seller=<?php echo $user_id; ?>" class="btn btn-sm btn-light">View All</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($auctions)): ?>
                                <p class="text-muted">This user has not created any auctions yet.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($auctions as $auction): ?>
                                        <a href="../auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <img src="<?php echo $auction['image_path'] ? '../' . $auction['image_path'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>" 
                                                         class="rounded" width="60" height="60" style="object-fit: cover;" alt="Auction Image">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($auction['title']); ?></h6>
                                                        <span class="badge bg-<?php 
                                                            echo $auction['status'] === 'active' ? 'success' : 
                                                                ($auction['status'] === 'pending' ? 'warning' : 'secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst($auction['status']); ?>
                                                        </span>
                                                    </div>
                                                    <p class="mb-1">Price: <?php echo formatPrice($auction['current_price']); ?></p>
                                                    <small>
                                                        <i class="fas fa-gavel me-1"></i> <?php echo $auction['bid_count']; ?> bids
                                                    </small>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Bids</h5>
                            <?php if ($stats['total_bids'] > 0): ?>
                                <a href="#" class="btn btn-sm btn-light">View All</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bids)): ?>
                                <p class="text-muted">This user has not placed any bids yet.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($bids as $bid): ?>
                                        <a href="../auction-details.php?id=<?php echo $bid['auction_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($bid['auction_title']); ?></h6>
                                                <small><?php echo date('M j, Y g:i a', strtotime($bid['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1">
                                                Bid: <strong class="<?php echo $bid['bid_amount'] >= $bid['current_high'] ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo formatPrice($bid['bid_amount']); ?>
                                                </strong>
                                                <?php if ($bid['bid_amount'] >= $bid['current_high']): ?>
                                                    <i class="fas fa-trophy text-warning ms-1" title="Highest Bid"></i>
                                                <?php endif; ?>
                                            </p>
                                            <span class="badge bg-<?php 
                                                echo $bid['auction_status'] === 'active' ? 'success' : 
                                                    ($bid['auction_status'] === 'pending' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($bid['auction_status']); ?> Auction
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    border-radius: 0.25rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    background-color: #fff;
    border: 1px solid rgba(0, 0, 0, 0.125);
    height: 100%;
}
.stat-card-body {
    display: flex;
    align-items: center;
    padding: 1rem;
}
.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: 1rem;
}
.stat-card-content h4 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 500;
}
.stat-card-content p {
    margin: 0;
    color: #6c757d;
}
</style>

<?php include '../includes/footer.php'; ?>