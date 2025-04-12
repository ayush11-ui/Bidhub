<?php
$page_title = "My Auctions";
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/my-auctions.php';
    redirect(SITE_URL . '/login.php?message=login_required');
}

$user_id = $_SESSION['user_id'];

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Get total auctions
$sql = "SELECT COUNT(*) as total FROM auctions WHERE seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_auctions = $result->fetch_assoc()['total'];
$total_pages = ceil($total_auctions / $items_per_page);

// Get filter values
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'created_desc';

// Build query based on filter
$where_clause = "WHERE seller_id = ?";
$params = array($user_id);
$types = "i";

if ($status_filter !== 'all') {
    $where_clause .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Determine sorting
$order_clause = "";
switch ($sort_by) {
    case 'title_asc':
        $order_clause = "ORDER BY title ASC";
        break;
    case 'title_desc':
        $order_clause = "ORDER BY title DESC";
        break;
    case 'price_asc':
        $order_clause = "ORDER BY starting_price ASC";
        break;
    case 'price_desc':
        $order_clause = "ORDER BY starting_price DESC";
        break;
    case 'end_date_asc':
        $order_clause = "ORDER BY end_time ASC";
        break;
    case 'end_date_desc':
        $order_clause = "ORDER BY end_time DESC";
        break;
    case 'created_asc':
        $order_clause = "ORDER BY created_at ASC";
        break;
    case 'created_desc':
    default:
        $order_clause = "ORDER BY created_at DESC";
        break;
}

// Get auctions
$sql = "SELECT a.*, c.name as category_name, 
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count 
        FROM auctions a 
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
$auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle delete auction
if (isset($_POST['delete_auction']) && isset($_POST['auction_id'])) {
    $auction_id = (int)$_POST['auction_id'];
    
    // Check if auction belongs to user AND is in pending status
    $sql = "SELECT * FROM auctions WHERE auction_id = ? AND seller_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $auction_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $auction = $result->fetch_assoc();
        
        // Get auction images
        $sql = "SELECT image_path FROM auction_images WHERE auction_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $auction_id);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Delete images from filesystem
        foreach ($images as $image) {
            if (!empty($image['image_path']) && file_exists('../' . $image['image_path'])) {
                @unlink('../' . $image['image_path']);
            }
        }
        
        // Delete auction images
        $sql = "DELETE FROM auction_images WHERE auction_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $auction_id);
        $stmt->execute();
        
        // Delete auction
        $sql = "DELETE FROM auctions WHERE auction_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $auction_id);
        
        if ($stmt->execute()) {
            $_SESSION['alert'] = [
                'message' => 'Auction deleted successfully!',
                'type' => 'success'
            ];
        } else {
            $_SESSION['alert'] = [
                'message' => 'Error deleting auction: ' . $conn->error,
                'type' => 'danger'
            ];
        }
    } else {
        $_SESSION['alert'] = [
            'message' => 'Auction not found, not in pending status, or you do not have permission to delete it.',
            'type' => 'danger'
        ];
    }
    
    // Always redirect to refresh the page regardless of outcome
    redirect($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
}

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
                <a href="my-auctions.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-gavel me-2"></i> My Auctions
                </a>
                <a href="my-bids.php" class="list-group-item list-group-item-action">
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
                    <h5 class="mb-0">Filter Auctions</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="created_desc" <?php echo $sort_by === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="created_asc" <?php echo $sort_by === 'created_asc' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="title_asc" <?php echo $sort_by === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                                <option value="title_desc" <?php echo $sort_by === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                                <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
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
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>My Auctions</h1>
                <a href="create-auction.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i> Create New Auction
                </a>
            </div>
            
            <?php if (empty($auctions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> You haven't created any auctions yet.
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Showing <?php echo count($auctions); ?> of <?php echo $total_auctions; ?> auctions</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Image</th>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Bids</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auctions as $auction): ?>
                                        <?php 
                                        // Get primary image
                                        $image_path = !empty($auction['primary_image']) ? $auction['primary_image'] 
                                                                                : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available';
                                        ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo SITE_URL . '/' . $image_path; ?>" 
                                                     class="img-thumbnail" alt="<?php echo htmlspecialchars($auction['title']); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover;">
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL . '/auction-details.php?id=' . $auction['auction_id']; ?>" class="fw-bold text-decoration-none">
                                                    <?php echo htmlspecialchars($auction['title']); ?>
                                                </a>
                                                <div class="small text-muted">
                                                    <?php echo htmlspecialchars($auction['category_name']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_badge = '';
                                                switch ($auction['status']) {
                                                    case 'pending':
                                                        $status_badge = '<span class="badge bg-warning">Pending Approval</span>';
                                                        break;
                                                    case 'active':
                                                        $status_badge = '<span class="badge bg-success">Active</span>';
                                                        break;
                                                    case 'ended':
                                                        $status_badge = '<span class="badge bg-secondary">Ended</span>';
                                                        break;
                                                    case 'rejected':
                                                        $status_badge = '<span class="badge bg-danger">Rejected</span>';
                                                        break;
                                                }
                                                echo $status_badge;
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($auction['current_price'] > $auction['starting_price']): ?>
                                                    <span class="fw-bold"><?php echo formatPrice($auction['current_price']); ?></span>
                                                    <div class="small text-muted">
                                                        started at <?php echo formatPrice($auction['starting_price']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php echo formatPrice($auction['starting_price']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $auction['bid_count']; ?>
                                            </td>
                                            <td>
                                                <?php if ($auction['status'] === 'active'): ?>
                                                    <span class="d-block" data-countdown="<?php echo $auction['end_time']; ?>"></span>
                                                    <div class="small text-muted">
                                                        <?php echo date('M d, Y', strtotime($auction['end_time'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php echo date('M d, Y', strtotime($auction['end_time'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo SITE_URL . '/auction-details.php?id=' . $auction['auction_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="View Auction">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($auction['status'] === 'pending'): ?>
                                                        <a href="edit-auction.php?id=<?php echo $auction['auction_id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary" title="Edit Auction">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                title="Delete Auction" onclick="showDeleteModal(<?php echo $auction['auction_id']; ?>, '<?php echo addslashes(htmlspecialchars($auction['title'])); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Single Delete Modal for all auctions -->
                <div class="modal fade" id="deleteAuctionModal" tabindex="-1" 
                     aria-labelledby="deleteAuctionModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteAuctionModalLabel">Delete Auction</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the auction <strong id="auctionTitlePlaceholder"></strong>?</p>
                                <p class="mb-0 text-danger">This action cannot be undone.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="POST" id="deleteAuctionForm">
                                    <input type="hidden" name="auction_id" id="auctionIdToDelete">
                                    <button type="submit" name="delete_auction" class="btn btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Auctions pagination">
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

// Function to show delete modal
function showDeleteModal(auctionId, auctionTitle) {
    // Set the auction ID in the hidden field
    document.getElementById('auctionIdToDelete').value = auctionId;
    
    // Set the auction title in the modal text
    document.getElementById('auctionTitlePlaceholder').textContent = auctionTitle;
    
    // Show the modal
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteAuctionModal'));
    deleteModal.show();
}
</script>

<?php include '../includes/footer.php'; ?> 