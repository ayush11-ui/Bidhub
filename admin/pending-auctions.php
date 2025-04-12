<?php
$page_title = "Pending Auctions";
require_once '../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to access the admin area.',
        'type' => 'danger'
    ];
    redirect(SITE_URL);
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 15;
$offset = ($page - 1) * $items_per_page;

// Get filter values
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

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
        $order_clause = "ORDER BY a.starting_price ASC";
        break;
    case 'price_desc':
        $order_clause = "ORDER BY a.starting_price DESC";
        break;
    case 'newest':
    default:
        $order_clause = "ORDER BY a.created_at DESC";
        break;
}

// Count total pending auctions
$sql = "SELECT COUNT(*) as total FROM auctions WHERE status = 'pending'";
$result = $conn->query($sql);
$total_pending = $result->fetch_assoc()['total'];
$total_pages = ceil($total_pending / $items_per_page);

// Get pending auctions
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name, u.email as seller_email,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id ORDER BY is_primary DESC, image_id ASC LIMIT 1) as image_path
        FROM auctions a 
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.status = 'pending'
        $order_clause
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offset, $items_per_page);
$stmt->execute();
$pending_auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
                <a href="pending-auctions.php" class="list-group-item list-group-item-action active d-flex align-items-center">
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
                <h1><i class="fas fa-hourglass-half me-2"></i> Pending Auctions</h1>
                
                <!-- Sorting dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sort me-2"></i> Sort By
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                        <li><a class="dropdown-item <?php echo $sort_by === 'newest' ? 'active' : ''; ?>" href="?sort=newest">Newest First</a></li>
                        <li><a class="dropdown-item <?php echo $sort_by === 'oldest' ? 'active' : ''; ?>" href="?sort=oldest">Oldest First</a></li>
                        <li><a class="dropdown-item <?php echo $sort_by === 'title_asc' ? 'active' : ''; ?>" href="?sort=title_asc">Title (A-Z)</a></li>
                        <li><a class="dropdown-item <?php echo $sort_by === 'title_desc' ? 'active' : ''; ?>" href="?sort=title_desc">Title (Z-A)</a></li>
                        <li><a class="dropdown-item <?php echo $sort_by === 'price_asc' ? 'active' : ''; ?>" href="?sort=price_asc">Price (Low to High)</a></li>
                        <li><a class="dropdown-item <?php echo $sort_by === 'price_desc' ? 'active' : ''; ?>" href="?sort=price_desc">Price (High to Low)</a></li>
                    </ul>
                </div>
            </div>
            
            <?php if (empty($pending_auctions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> There are no pending auctions at the moment.
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="row align-items-center">
                            <div class="col">
                                <span>Showing <?php echo count($pending_auctions); ?> of <?php echo $total_pending; ?> pending auctions</span>
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
                                        <th>Submitted</th>
                                        <th width="140">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_auctions as $auction): ?>
                                        <tr>
                                            <td>
                                                <div class="me-3">
                                                    <img src="<?php echo !empty($auction['image_path']) ? $auction['image_path'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>" 
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
                                            <td><?php echo formatPrice($auction['starting_price']); ?></td>
                                            <td><?php echo date('M d, Y g:i A', strtotime($auction['created_at'])); ?></td>
                                            <td>
                                                <a href="review-auction.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye me-1"></i> Review
                                                </a>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="approveAuction(<?php echo $auction['auction_id']; ?>, '<?php echo addslashes($auction['title']); ?>')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="rejectAuction(<?php echo $auction['auction_id']; ?>, '<?php echo addslashes($auction['title']); ?>')">
                                                        <i class="fas fa-times"></i>
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
                    <nav aria-label="Pending auctions pagination">
                        <ul class="pagination justify-content-center">
                            <?php 
                            // Previous button
                            if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort_by; ?>" aria-label="Previous">
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
                                    <a class="page-link" href="?page=<?php echo $i; ?>&sort=<?php echo $sort_by; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php 
                            // Next button
                            if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort_by; ?>" aria-label="Next">
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

<!-- Quick Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalLabel">Approve Auction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="approveForm" method="POST" action="process-auction.php">
                <div class="modal-body">
                    <p>Are you sure you want to approve this auction?</p>
                    <p>Title: <strong id="approveAuctionTitle"></strong></p>
                    <p>Once approved, the auction will be visible to all users and bidding can begin.</p>
                    <input type="hidden" name="auction_id" id="approveAuctionId">
                    <input type="hidden" name="action" value="approve">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Auction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">Reject Auction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectForm" method="POST" action="process-auction.php">
                <div class="modal-body">
                    <p>Are you sure you want to reject this auction?</p>
                    <p>Title: <strong id="rejectAuctionTitle"></strong></p>
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason for rejection:</label>
                        <textarea class="form-control" id="rejectReason" name="reason" rows="3" required></textarea>
                        <div class="form-text">This will be sent to the seller.</div>
                    </div>
                    <input type="hidden" name="auction_id" id="rejectAuctionId">
                    <input type="hidden" name="action" value="reject">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Auction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveAuction(auctionId, title) {
    document.getElementById('approveAuctionId').value = auctionId;
    document.getElementById('approveAuctionTitle').textContent = title;
    var approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
    approveModal.show();
}

function rejectAuction(auctionId, title) {
    document.getElementById('rejectAuctionId').value = auctionId;
    document.getElementById('rejectAuctionTitle').textContent = title;
    var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
    rejectModal.show();
}
</script>

<?php include '../includes/footer.php'; ?> 