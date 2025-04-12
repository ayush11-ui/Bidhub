<?php
$page_title = "Admin Dashboard";
require_once '../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to access the admin area.',
        'type' => 'danger'
    ];
    redirect(SITE_URL);
}

// Get statistics
$stats = [
    'users' => 0,
    'auctions' => 0,
    'active_auctions' => 0,
    'pending_auctions' => 0,
    'ended_auctions' => 0,
    'bids' => 0,
    'comments' => 0,
    'revenue' => 0
];

// Users count
$sql = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['users'] = $result->fetch_assoc()['count'];
}

// Auctions count
$sql = "SELECT COUNT(*) as count FROM auctions";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['auctions'] = $result->fetch_assoc()['count'];
}

// Active auctions count
$sql = "SELECT COUNT(*) as count FROM auctions WHERE status = 'active'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['active_auctions'] = $result->fetch_assoc()['count'];
}

// Pending auctions count
$sql = "SELECT COUNT(*) as count FROM auctions WHERE status = 'pending'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['pending_auctions'] = $result->fetch_assoc()['count'];
}

// Ended auctions count
$sql = "SELECT COUNT(*) as count FROM auctions WHERE status = 'ended'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['ended_auctions'] = $result->fetch_assoc()['count'];
}

// Bids count
$sql = "SELECT COUNT(*) as count FROM bids";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['bids'] = $result->fetch_assoc()['count'];
}

// Comments count
$sql = "SELECT COUNT(*) as count FROM comments";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $stats['comments'] = $result->fetch_assoc()['count'];
}

// Recent pending auctions
$pending_auctions = [];
$sql = "SELECT a.auction_id, a.title, a.created_at, u.username as seller_name
        FROM auctions a
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.status = 'pending'
        ORDER BY a.created_at DESC
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_auctions[] = $row;
    }
}

// Recent active auctions
$active_auctions = [];
$sql = "SELECT a.auction_id, a.title, a.current_price, a.end_time,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count
        FROM auctions a
        WHERE a.status = 'active'
        ORDER BY a.end_time ASC
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $active_auctions[] = $row;
    }
}

// Recent users
$recent_users = [];
$sql = "SELECT user_id, username, email, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Admin Sidebar -->
        <div class="col-lg-3 col-xl-2">
            <div class="list-group mb-4">
                <a href="index.php" class="list-group-item list-group-item-action active d-flex align-items-center">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="auctions.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-gavel me-2"></i> Manage Auctions
                </a>
                <a href="pending-auctions.php" class="list-group-item list-group-item-action d-flex align-items-center">
                    <i class="fas fa-clock me-2"></i> Pending Auctions
                    <?php if ($stats['pending_auctions'] > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $stats['pending_auctions']; ?></span>
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
            <h1 class="mb-4">Admin Dashboard</h1>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $stats['users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $stats['auctions']; ?></h3>
                            <p>Total Auctions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $stats['active_auctions']; ?></h3>
                            <p>Active Auctions</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="dashboard-card">
                        <div class="dashboard-card-icon">
                            <i class="fas fa-comment-dollar"></i>
                        </div>
                        <div class="dashboard-card-content">
                            <h3><?php echo $stats['bids']; ?></h3>
                            <p>Total Bids</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="create-auction.php" class="btn btn-outline-primary btn-lg mb-3 w-100">
                                <i class="fas fa-plus me-2"></i> Create Auction
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="pending-auctions.php" class="btn btn-outline-warning btn-lg mb-3 w-100">
                                <i class="fas fa-clock me-2"></i> Review Pending
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Auctions -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">Pending Auctions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pending_auctions)): ?>
                                <p class="text-muted">No pending auctions at the moment.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($pending_auctions as $auction): ?>
                                        <a href="review-auction.php?id=<?php echo $auction['auction_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo $auction['title']; ?></h6>
                                                <small><?php echo getTimeAgo($auction['created_at']); ?></small>
                                            </div>
                                            <small>by <?php echo $auction['seller_name']; ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($pending_auctions) < $stats['pending_auctions']): ?>
                                    <div class="text-center mt-3">
                                        <a href="pending-auctions.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Active Auctions -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Active Auctions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($active_auctions)): ?>
                                <p class="text-muted">No active auctions at the moment.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($active_auctions as $auction): ?>
                                        <a href="../auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo $auction['title']; ?></h6>
                                                <span class="badge bg-primary"><?php echo formatPrice($auction['current_price']); ?></span>
                                            </div>
                                            <small>
                                                <i class="fas fa-gavel me-1"></i> <?php echo $auction['bid_count']; ?> bids
                                                <span class="ms-3">
                                                    <i class="far fa-clock me-1"></i>
                                                    <span class="countdown-timer" data-end-time="<?php echo $auction['end_time']; ?>">
                                                        Loading...
                                                    </span>
                                                </span>
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($active_auctions) < $stats['active_auctions']): ?>
                                    <div class="text-center mt-3">
                                        <a href="auctions.php?status=active" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Users -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Users</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_users)): ?>
                        <p class="text-muted">No users found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y g:i a', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <a href="view-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="users.php" class="btn btn-outline-primary">View All Users</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 