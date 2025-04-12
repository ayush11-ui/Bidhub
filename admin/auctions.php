<?php
$page_title = "Manage Auctions";
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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 15;
$offset = ($page - 1) * $items_per_page;

// Get filter values
$status = isset($_GET['status']) ? sanitize($_GET['status']) : 'all';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Build WHERE clause
$where_clauses = [];
$params = [];
$param_types = '';

if ($status !== 'all') {
    $where_clauses[] = "a.status = ?";
    $params[] = $status;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

if ($category > 0) {
    $where_clauses[] = "a.category_id = ?";
    $params[] = $category;
    $param_types .= 'i';
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Determine sorting
$order_clause = "";
switch ($sort_by) {
    case 'oldest':
        $order_clause = "ORDER BY a.created_at ASC";
        break;
    case 'title_asc':
        $order_clause = "ORDER BY a.title ASC";
        break;
    case 'title_desc':
        $order_clause = "ORDER BY a.title DESC";
        break;
    case 'price_asc':
        $order_clause = "ORDER BY a.current_price ASC";
        break;
    case 'price_desc':
        $order_clause = "ORDER BY a.current_price DESC";
        break;
    case 'end_soon':
        $order_clause = "ORDER BY a.end_time ASC";
        break;
    case 'newest':
    default:
        $order_clause = "ORDER BY a.created_at DESC";
        break;
}

// Count total auctions
$count_sql = "SELECT COUNT(*) as total FROM auctions a $where_sql";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_auctions = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $result = $conn->query($count_sql);
    $total_auctions = $result->fetch_assoc()['total'];
}

$total_pages = ceil($total_auctions / $items_per_page);

// Get auctions
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name, u.email as seller_email,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) as image_path
        FROM auctions a 
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        $where_sql
        $order_clause
        LIMIT ?, ?";

// Get all categories for filter dropdown
$category_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
$categories = $conn->query($category_sql)->fetch_all(MYSQLI_ASSOC);

// Add limit parameters
$all_params = $params;
$all_params[] = $offset;
$all_params[] = $items_per_page;
$param_types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$all_params);
$stmt->execute();
$auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle auction deletion if requested
if (isset($_POST['delete_auction']) && !empty($_POST['auction_id'])) {
    $auction_id = (int)$_POST['auction_id'];
    
    // Get image paths first
    $img_sql = "SELECT image_path FROM auction_images WHERE auction_id = ?";
    $stmt = $conn->prepare($img_sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Delete auction images from database
    $img_del_sql = "DELETE FROM auction_images WHERE auction_id = ?";
    $stmt = $conn->prepare($img_del_sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    
    // Delete bids associated with the auction
    $bids_del_sql = "DELETE FROM bids WHERE auction_id = ?";
    $stmt = $conn->prepare($bids_del_sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    
    // Delete comments associated with the auction
    $comments_del_sql = "DELETE FROM comments WHERE auction_id = ?";
    $stmt = $conn->prepare($comments_del_sql);
    $stmt->bind_param("i", $auction_id);
    $stmt->execute();
    
    // Delete auction
    $del_sql = "DELETE FROM auctions WHERE auction_id = ?";
    $stmt = $conn->prepare($del_sql);
    $stmt->bind_param("i", $auction_id);
    if ($stmt->execute()) {
        // Delete image files
        foreach ($images as $image) {
            if (!empty($image['image_path']) && file_exists('../' . $image['image_path'])) {
                unlink('../' . $image['image_path']);
            }
        }
        
        $_SESSION['alert'] = [
            'message' => 'Auction deleted successfully.',
            'type' => 'success'
        ];
    } else {
        $_SESSION['alert'] = [
            'message' => 'Error deleting auction.',
            'type' => 'danger'
        ];
    }
    
    // Redirect to remove the POST data
    redirect("auctions.php?status=$status&sort=$sort_by&page=$page" . 
             (!empty($search) ? "&search=$search" : "") . 
             ($category > 0 ? "&category=$category" : ""));
}

// Handle featured status toggle
if (isset($_POST['toggle_featured']) && !empty($_POST['auction_id'])) {
    $auction_id = (int)$_POST['auction_id'];
    $featured = (int)$_POST['featured_status'];
    
    $sql = "UPDATE auctions SET featured = ? WHERE auction_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $featured, $auction_id);
    
    if ($stmt->execute()) {
        $_SESSION['alert'] = [
            'message' => $featured ? 'Auction added to featured listings.' : 'Auction removed from featured listings.',
            'type' => 'success'
        ];
    } else {
        $_SESSION['alert'] = [
            'message' => 'Error updating featured status.',
            'type' => 'danger'
        ];
    }
    
    // Redirect to remove the POST data
    redirect("auctions.php?status=$status&sort=$sort_by&page=$page" . 
             (!empty($search) ? "&search=$search" : "") . 
             ($category > 0 ? "&category=$category" : ""));
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
                <a href="auctions.php" class="list-group-item list-group-item-action active d-flex align-items-center">
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
                <h1><i class="fas fa-gavel me-2"></i> Manage Auctions</h1>
                <a href="create-auction.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Create Auction
                </a>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form action="auctions.php" method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="ended" <?php echo $status === 'ended' ? 'selected' : ''; ?>>Ended</option>
                                <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select name="category" id="category" class="form-select">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select name="sort" id="sort" class="form-select">
                                <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                <option value="title_asc" <?php echo $sort_by === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                                <option value="title_desc" <?php echo $sort_by === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                                <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                                <option value="end_soon" <?php echo $sort_by === 'end_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (empty($auctions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No auctions found matching your criteria.
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col">
                                <span>Showing <?php echo count($auctions); ?> of <?php echo $total_auctions; ?> auctions</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="80">Image</th>
                                        <th>Title</th>
                                        <th>Seller</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>End Time</th>
                                        <th>Bids</th>
                                        <th>Featured</th>
                                        <th width="140">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($auctions as $auction): ?>
                                        <tr>
                                            <td>
                                                <div class="me-3">
                                                    <img src="<?php echo !empty($auction['image_path']) ? '../' . $auction['image_path'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>" 
                                                         class="rounded" alt="Auction Image" style="width: 80px; height: 80px; object-fit: cover;">
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($auction['title']); ?>
                                                <div class="small text-muted"><?php echo substr(htmlspecialchars($auction['description']), 0, 50) . '...'; ?></div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($auction['seller_name']); ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($auction['seller_email']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($auction['category_name']); ?></td>
                                            <td><?php echo formatPrice($auction['current_price']); ?></td>
                                            <td>
                                                <?php if ($auction['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($auction['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($auction['status'] === 'ended'): ?>
                                                    <span class="badge bg-secondary">Ended</span>
                                                <?php elseif ($auction['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($auction['status'] === 'active'): ?>
                                                    <span class="countdown-timer" data-end-time="<?php echo $auction['end_time']; ?>">
                                                        <?php echo date('M d, Y', strtotime($auction['end_time'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <?php echo date('M d, Y', strtotime($auction['end_time'])); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $auction['bid_count']; ?></td>
                                            <td class="text-center">
                                                <?php if ($auction['featured']): ?>
                                                    <span class="badge bg-info"><i class="fas fa-star"></i> Featured</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="../auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($auction['status'] === 'pending'): ?>
                                                        <a href="review-auction.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm <?php echo $auction['featured'] ? 'btn-info' : 'btn-outline-info'; ?>" 
                                                            onclick="toggleFeatured(<?php echo $auction['auction_id']; ?>, <?php echo $auction['featured'] ? 0 : 1; ?>)" 
                                                            title="<?php echo $auction['featured'] ? 'Remove from featured' : 'Add to featured'; ?>">
                                                        <i class="fas <?php echo $auction['featured'] ? 'fa-star' : 'fa-star'; ?>"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $auction['auction_id']; ?>, '<?php echo addslashes($auction['title']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
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
                    <nav aria-label="Auctions pagination">
                        <ul class="pagination justify-content-center">
                            <?php 
                            // Previous button
                            if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            // Page numbers
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php 
                            // Next button
                            if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <a class="page-link" href="#" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Auction Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">Delete Auction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete this auction? This action cannot be undone.</p>
                    <p class="text-danger fw-bold">Warning: Deleting this auction will also remove all associated bids and comments.</p>
                    <p>Title: <strong id="deleteAuctionTitle"></strong></p>
                    <input type="hidden" name="auction_id" id="deleteAuctionId">
                    <input type="hidden" name="delete_auction" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Auction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Featured Modal -->
<div class="modal fade" id="featuredModal" tabindex="-1" aria-labelledby="featuredModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="featuredModalLabel">Change Featured Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p id="featuredMessage"></p>
                    <input type="hidden" name="auction_id" id="featuredAuctionId">
                    <input type="hidden" name="featured_status" id="featuredStatus">
                    <input type="hidden" name="toggle_featured" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(auctionId, title) {
    document.getElementById('deleteAuctionId').value = auctionId;
    document.getElementById('deleteAuctionTitle').textContent = title;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

function toggleFeatured(auctionId, status) {
    document.getElementById('featuredAuctionId').value = auctionId;
    document.getElementById('featuredStatus').value = status;
    
    if(status === 1) {
        document.getElementById('featuredMessage').textContent = "Are you sure you want to add this auction to the featured listings?";
        document.getElementById('featuredModalLabel').textContent = "Add to Featured";
    } else {
        document.getElementById('featuredMessage').textContent = "Are you sure you want to remove this auction from the featured listings?";
        document.getElementById('featuredModalLabel').textContent = "Remove from Featured";
    }
    
    var featuredModal = new bootstrap.Modal(document.getElementById('featuredModal'));
    featuredModal.show();
}

// Initialize countdown timers
document.addEventListener('DOMContentLoaded', function() {
    const countdownTimers = document.querySelectorAll('.countdown-timer');
    
    countdownTimers.forEach(function(timerElement) {
        const endTime = new Date(timerElement.getAttribute('data-end-time')).getTime();
        
        // Update the timer every second
        const countdownInterval = setInterval(function() {
            // Get current time
            const now = new Date().getTime();
            
            // Calculate time remaining
            const timeRemaining = endTime - now;
            
            // If the auction has ended, display "Ended"
            if (timeRemaining <= 0) {
                clearInterval(countdownInterval);
                timerElement.innerHTML = '<span class="text-danger">Ended</span>';
                return;
            }
            
            // Calculate days, hours, minutes, seconds
            const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
            
            // Display the countdown
            if (days > 0) {
                timerElement.innerHTML = `${days}d ${hours}h ${minutes}m`;
            } else {
                timerElement.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
                if (hours === 0 && minutes < 30) {
                    timerElement.classList.add('text-danger', 'fw-bold');
                }
            }
        }, 1000);
    });
});
</script>

<?php include '../includes/footer.php'; ?> 