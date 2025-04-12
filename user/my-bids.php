<?php
$page_title = "My Bids";
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/my-bids.php';
    redirect(SITE_URL . '/login.php?message=login_required');
}

$user_id = $_SESSION['user_id'];

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get filter values
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_desc';

// Build query based on filter
$where_clause = "WHERE b.bidder_id = ?";
$params = array($user_id);
$types = "i";

if ($status_filter !== 'all') {
    if ($status_filter === 'winning') {
        $where_clause .= " AND b.bid_amount = a.current_price AND a.status = 'active'";
    } elseif ($status_filter === 'outbid') {
        $where_clause .= " AND b.bid_amount < a.current_price AND a.status = 'active'";
    } elseif ($status_filter === 'won') {
        $where_clause .= " AND b.bid_amount = a.current_price AND a.status = 'ended'";
    } elseif ($status_filter === 'lost') {
        $where_clause .= " AND b.bid_amount < a.current_price AND a.status = 'ended'";
    } else {
        // If status is active or ended
        $where_clause .= " AND a.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
}

// Get total bids
$sql = "SELECT COUNT(*) as total 
        FROM bids b 
        JOIN auctions a ON b.auction_id = a.auction_id
        $where_clause";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$total_bids = $result->fetch_assoc()['total'];
$total_pages = ceil($total_bids / $items_per_page);

// Determine sorting
$order_clause = "";
switch ($sort_by) {
    case 'title_asc':
        $order_clause = "ORDER BY a.title ASC";
        break;
    case 'title_desc':
        $order_clause = "ORDER BY a.title DESC";
        break;
    case 'amount_asc':
        $order_clause = "ORDER BY b.bid_amount ASC";
        break;
    case 'amount_desc':
        $order_clause = "ORDER BY b.bid_amount DESC";
        break;
    case 'end_date_asc':
        $order_clause = "ORDER BY a.end_time ASC";
        break;
    case 'end_date_desc':
        $order_clause = "ORDER BY a.end_time DESC";
        break;
    case 'created_asc':
        $order_clause = "ORDER BY b.created_at ASC";
        break;
    case 'created_desc':
    default:
        $order_clause = "ORDER BY b.created_at DESC";
        break;
}

// Get bids with auction details
$sql = "SELECT b.*, a.title, a.description, a.starting_price, a.current_price, a.status as auction_status, 
        a.end_time, c.name as category_name,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) as image_path
        FROM bids b 
        JOIN auctions a ON b.auction_id = a.auction_id
        LEFT JOIN categories c ON a.category_id = c.category_id 
        $where_clause 
        $order_clause 
        LIMIT ? OFFSET ?";

$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$bids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="list-group mb-4">
                <a href="profile.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-user me-2"></i> My Profile
                </a>
                <a href="my-auctions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-gavel me-2"></i> My Auctions
                </a>
                <a href="my-bids.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-hand-paper me-2"></i> My Bids
                </a>
                <a href="create-auction.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plus me-2"></i> Create Auction
                </a>
                <a href="<?php echo SITE_URL; ?>" class="list-group-item list-group-item-action">
                    <i class="fas fa-arrow-left me-2"></i> Back to Site
                </a>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Filter Bids</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="winning" <?php echo $status_filter === 'winning' ? 'selected' : ''; ?>>Currently Winning</option>
                                <option value="outbid" <?php echo $status_filter === 'outbid' ? 'selected' : ''; ?>>Outbid</option>
                                <option value="won" <?php echo $status_filter === 'won' ? 'selected' : ''; ?>>Won</option>
                                <option value="lost" <?php echo $status_filter === 'lost' ? 'selected' : ''; ?>>Lost</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Auctions</option>
                                <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended Auctions</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="created_desc" <?php echo $sort_by === 'created_desc' ? 'selected' : ''; ?>>Newest Bids First</option>
                                <option value="created_asc" <?php echo $sort_by === 'created_asc' ? 'selected' : ''; ?>>Oldest Bids First</option>
                                <option value="amount_desc" <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>Bid Amount (High to Low)</option>
                                <option value="amount_asc" <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>Bid Amount (Low to High)</option>
                                <option value="title_asc" <?php echo $sort_by === 'title_asc' ? 'selected' : ''; ?>>Auction Title (A-Z)</option>
                                <option value="title_desc" <?php echo $sort_by === 'title_desc' ? 'selected' : ''; ?>>Auction Title (Z-A)</option>
                                <option value="end_date_asc" <?php echo $sort_by === 'end_date_asc' ? 'selected' : ''; ?>>End Date (Soonest)</option>
                                <option value="end_date_desc" <?php echo $sort_by === 'end_date_desc' ? 'selected' : ''; ?>>End Date (Latest)</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Bid Statistics -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Bid Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get statistics
                    $stats_sql = "SELECT
                                 COUNT(*) as total_bids,
                                 COUNT(DISTINCT b.auction_id) as total_auctions_bid,
                                 SUM(CASE WHEN b.bid_amount = a.current_price AND a.status = 'active' THEN 1 ELSE 0 END) as winning_bids,
                                 SUM(CASE WHEN b.bid_amount = a.current_price AND a.status = 'ended' THEN 1 ELSE 0 END) as won_auctions
                                 FROM bids b
                                 JOIN auctions a ON b.auction_id = a.auction_id
                                 WHERE b.bidder_id = ?";
                    $stats_stmt = $conn->prepare($stats_sql);
                    $stats_stmt->bind_param("i", $user_id);
                    $stats_stmt->execute();
                    $stats = $stats_stmt->get_result()->fetch_assoc();
                    ?>
                    
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Total Bids
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['total_bids']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Auctions Bid On
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['total_auctions_bid']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Currently Winning
                            <span class="badge bg-success rounded-pill"><?php echo $stats['winning_bids']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Auctions Won
                            <span class="badge bg-success rounded-pill"><?php echo $stats['won_auctions']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My Bids</h1>
                <a href="<?php echo SITE_URL; ?>/auctions-list.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i> Find Auctions to Bid
                </a>
            </div>
            
            <?php if (empty($bids)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't placed any bids yet.
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Showing <?php echo count($bids); ?> of <?php echo $total_bids; ?> bids</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Auction</th>
                                        <th>Your Bid</th>
                                        <th>Current Price</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bids as $bid): ?>
                                        <?php 
                                        // Default image if none is found
                                        $image_path = !empty($bid['image_path']) ? $bid['image_path'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available';
                                        
                                        // Determine bid status
                                        $bid_status = '';
                                        $bid_status_class = '';
                                        
                                        if ($bid['auction_status'] === 'active') {
                                            if ($bid['bid_amount'] == $bid['current_price']) {
                                                $bid_status = 'Winning';
                                                $bid_status_class = 'success';
                                            } else {
                                                $bid_status = 'Outbid';
                                                $bid_status_class = 'warning';
                                            }
                                        } else if ($bid['auction_status'] === 'ended') {
                                            if ($bid['bid_amount'] == $bid['current_price']) {
                                                $bid_status = 'Won';
                                                $bid_status_class = 'success';
                                            } else {
                                                $bid_status = 'Lost';
                                                $bid_status_class = 'danger';
                                            }
                                        } else {
                                            $bid_status = ucfirst($bid['auction_status']);
                                            $bid_status_class = 'secondary';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo SITE_URL . '/' . $image_path; ?>" 
                                                         class="img-thumbnail me-2" 
                                                         alt="<?php echo htmlspecialchars($bid['title']); ?>" 
                                                         style="width: 50px; height: 50px; object-fit: cover;">
                                                    <div>
                                                        <a href="<?php echo SITE_URL . '/auction-details.php?id=' . $bid['auction_id']; ?>" class="fw-bold text-decoration-none">
                                                            <?php echo htmlspecialchars($bid['title']); ?>
                                                        </a>
                                                        <div class="small text-muted">
                                                            <?php echo htmlspecialchars($bid['category_name']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-bold"><?php echo formatPrice($bid['bid_amount']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo formatPrice($bid['current_price']); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $bid_status_class; ?>">
                                                    <?php echo $bid_status; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($bid['auction_status'] === 'active'): ?>
                                                    <span class="d-block" data-countdown="<?php echo $bid['end_time']; ?>"></span>
                                                    <div class="small text-muted">
                                                        <?php echo date('M d, Y', strtotime($bid['end_time'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small">
                                                        <?php echo date('M d, Y', strtotime($bid['end_time'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="small text-muted">
                                                    Bid placed: <?php echo date('M d, Y g:i A', strtotime($bid['created_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL . '/auction-details.php?id=' . $bid['auction_id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                                
                                                <?php if ($bid['auction_status'] === 'active' && $bid['bid_amount'] < $bid['current_price']): ?>
                                                    <a href="<?php echo SITE_URL . '/auction-details.php?id=' . $bid['auction_id']; ?>#bid-form" 
                                                       class="btn btn-sm btn-outline-success mt-1">
                                                        <i class="fas fa-hand-paper me-1"></i> Rebid
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Bids pagination">
                        <ul class="pagination justify-content-center">
                            <?php 
                            // Add current filter params to pagination links
                            $query_params = $_GET;
                            
                            // Previous button
                            if ($page > 1) {
                                $query_params['page'] = $page - 1;
                                echo '<li class="page-item">';
                                echo '<a class="page-link" href="?' . http_build_query($query_params) . '" aria-label="Previous">';
                                echo '<span aria-hidden="true">&laquo;</span>';
                                echo '</a>';
                                echo '</li>';
                            } else {
                                echo '<li class="page-item disabled">';
                                echo '<a class="page-link" href="#" aria-label="Previous">';
                                echo '<span aria-hidden="true">&laquo;</span>';
                                echo '</a>';
                                echo '</li>';
                            }
                            
                            // Page numbers
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $query_params['page'] = $i;
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                echo '<a class="page-link" href="?' . http_build_query($query_params) . '">' . $i . '</a>';
                                echo '</li>';
                            }
                            
                            // Next button
                            if ($page < $total_pages) {
                                $query_params['page'] = $page + 1;
                                echo '<li class="page-item">';
                                echo '<a class="page-link" href="?' . http_build_query($query_params) . '" aria-label="Next">';
                                echo '<span aria-hidden="true">&raquo;</span>';
                                echo '</a>';
                                echo '</li>';
                            } else {
                                echo '<li class="page-item disabled">';
                                echo '<a class="page-link" href="#" aria-label="Next">';
                                echo '<span aria-hidden="true">&raquo;</span>';
                                echo '</a>';
                                echo '</li>';
                            }
                            ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize countdown timers
    document.querySelectorAll('[data-countdown]').forEach(function(element) {
        const endDate = new Date(element.getAttribute('data-countdown')).getTime();
        
        const updateTimer = function() {
            const now = new Date().getTime();
            const distance = endDate - now;
            
            if (distance < 0) {
                element.innerHTML = 'Ended';
                return;
            }
            
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            if (days > 0) {
                element.innerHTML = days + 'd ' + hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                element.innerHTML = hours + 'h ' + minutes + 'm ' + seconds + 's';
            } else {
                element.innerHTML = minutes + 'm ' + seconds + 's';
            }
        };
        
        updateTimer();
        setInterval(updateTimer, 1000);
    });
});
</script>

<?php include '../includes/footer.php'; ?> 