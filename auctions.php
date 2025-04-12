<?php
$page_title = "All Auctions";
$page_header = "Auctions";
$page_subheader = "Browse all available auctions";
include 'includes/header.php';

// Get all categories for filter
$categories = [];
$sql = "SELECT category_id, name FROM categories ORDER BY name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Set up filtering
$where_conditions = [];
$params = [];
$param_types = "";

// Status filter (default to 'all' if not specified)
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'ended', 'pending']) ? $_GET['status'] : 'all';
if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Category filter
if (isset($_GET['category']) && $_GET['category'] > 0) {
    $where_conditions[] = "a.category_id = ?";
    $params[] = $_GET['category'];
    $param_types .= "i";
}

// Price filter
if (isset($_GET['min_price']) && $_GET['min_price'] > 0) {
    $where_conditions[] = "a.current_price >= ?";
    $params[] = $_GET['min_price'];
    $param_types .= "d";
}

if (isset($_GET['max_price']) && $_GET['max_price'] > 0) {
    $where_conditions[] = "a.current_price <= ?";
    $params[] = $_GET['max_price'];
    $param_types .= "d";
}

// Search query
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ss";
}

// Add a default condition if no conditions provided
if (empty($where_conditions)) {
    $where_conditions[] = "1=1"; // This is always true, effectively no condition
}

// Sort order
$sort_options = [
    'newest' => 'a.created_at DESC',
    'ending_soon' => 'a.end_time ASC',
    'price_low' => 'a.current_price ASC',
    'price_high' => 'a.current_price DESC'
];

$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_options) ? $_GET['sort'] : 'newest';
$order_by = $sort_options[$sort];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Count total auctions for pagination
$count_sql = "SELECT COUNT(*) as total FROM auctions a WHERE " . implode(' AND ', $where_conditions);
$stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$total_results = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $items_per_page);

// Get auctions
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name,
        (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image,
        (SELECT COUNT(*) FROM bids WHERE auction_id = a.auction_id) as bid_count
        FROM auctions a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY $order_by
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
$param_types .= "ii";
$params[] = $offset;
$params[] = $items_per_page;
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$auctions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="row">
    <!-- Sidebar Filters -->
    <div class="col-lg-3">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Filter Auctions</h5>
            </div>
            <div class="card-body">
                <form action="" method="GET" id="filter-form">
                    <!-- If sort is set, preserve it -->
                    <?php if (isset($_GET['sort'])): ?>
                        <input type="hidden" name="sort" value="<?php echo $_GET['sort']; ?>">
                    <?php endif; ?>
                    
                    <!-- Search -->
                    <div class="mb-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                               placeholder="Search auctions...">
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <!-- Category -->
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="0">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="mb-3">
                        <label class="form-label">Price Range</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" class="form-control" name="min_price" placeholder="Min"
                                       value="<?php echo isset($_GET['min_price']) ? htmlspecialchars($_GET['min_price']) : ''; ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" name="max_price" placeholder="Max"
                                       value="<?php echo isset($_GET['max_price']) ? htmlspecialchars($_GET['max_price']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="auctions.php" class="btn btn-outline-secondary mt-2">Clear Filters</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Featured Auctions Sidebar -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Featured Auctions</h5>
            </div>
            <div class="card-body p-0">
                <?php
                $featured_sql = "SELECT a.auction_id, a.title, a.current_price, a.status,
                                (SELECT image_path FROM auction_images WHERE auction_id = a.auction_id AND is_primary = 1 LIMIT 1) as primary_image
                                FROM auctions a 
                                WHERE a.featured = 1 AND a.status = 'active' AND a.end_time > NOW()
                                ORDER BY a.created_at DESC
                                LIMIT 3";
                $featured_result = $conn->query($featured_sql);
                if ($featured_result && $featured_result->num_rows > 0):
                    while ($featured = $featured_result->fetch_assoc()):
                ?>
                    <div class="p-3 border-bottom">
                        <div class="row g-0">
                            <div class="col-4">
                                <img src="<?php echo $featured['primary_image'] ? $featured['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                                     class="img-fluid rounded" alt="<?php echo $featured['title']; ?>">
                            </div>
                            <div class="col-8 ps-3">
                                <h6 class="mb-1 text-truncate"><?php echo $featured['title']; ?></h6>
                                <p class="mb-1 text-primary fw-bold"><?php echo formatPrice($featured['current_price']); ?></p>
                                <a href="auction-details.php?id=<?php echo $featured['auction_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile;
                else:
                ?>
                    <div class="p-3">
                        <p class="mb-0">No featured auctions available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="col-lg-9">
        <!-- Sort and Display Options -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="mb-0">Showing <?php echo count($auctions); ?> of <?php echo $total_results; ?> auctions</p>
            </div>
            <div class="d-flex align-items-center">
                <label for="sort" class="me-2">Sort by:</label>
                <select class="form-select form-select-sm" id="sort-select" style="width: auto;">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="ending_soon" <?php echo $sort === 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($auctions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No auctions found matching your criteria. Try adjusting your filters.
            </div>
        <?php else: ?>
            <!-- Auctions Grid -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($auctions as $auction): ?>
                    <div class="col">
                        <div class="card h-100 auction-card">
                            <div class="position-relative">
                                <img src="<?php echo $auction['primary_image'] ? $auction['primary_image'] : 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available'; ?>"
                                     class="card-img-top" alt="<?php echo $auction['title']; ?>">
                                <?php 
                                $status_class = 'active';
                                $status_text = 'Active';
                                if ($auction['status'] === 'ended') {
                                    $status_class = 'danger';
                                    $status_text = 'Ended';
                                } elseif ($auction['status'] === 'pending') {
                                    $status_class = 'warning';
                                    $status_text = 'Coming Soon';
                                }
                                ?>
                                <span class="auction-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $auction['title']; ?></h5>
                                <p class="card-text text-truncate"><?php echo $auction['description']; ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary"><?php echo $auction['category_name']; ?></span>
                                    <small class="text-muted"><?php echo $auction['bid_count']; ?> bids</small>
                                </div>
                                <p class="auction-price"><?php echo formatPrice($auction['current_price']); ?></p>
                                <p class="auction-time">
                                    <i class="far fa-clock me-1"></i>
                                    <?php if ($auction['status'] === 'active'): ?>
                                        <span class="countdown-timer" data-end-time="<?php echo $auction['end_time']; ?>">
                                            Loading...
                                        </span>
                                    <?php elseif ($auction['status'] === 'ended'): ?>
                                        <span class="text-danger">Auction Ended</span>
                                        <small class="d-block text-muted">Ended: <?php echo date('M d, Y', strtotime($auction['end_time'])); ?></small>
                                    <?php elseif ($auction['status'] === 'pending'): ?>
                                        <span class="text-warning">Starts: <?php echo date('M d, Y', strtotime($auction['start_time'])); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="auction-details.php?id=<?php echo $auction['auction_id']; ?>" class="btn btn-primary w-100">
                                    <?php if ($auction['status'] === 'active' && isLoggedIn() && $_SESSION['user_id'] != $auction['seller_id']): ?>
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
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
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
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        if ($end_page - $start_page < 4 && $start_page > 1) {
                            $start_page = max(1, $end_page - 4);
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle sort select change
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort', this.value);
            window.location.href = currentUrl.toString();
        });
    }
});
</script>

<!-- Custom CSS for auction status badges -->
<style>
.auction-status {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    border-radius: 4px;
    color: white;
    font-weight: bold;
    font-size: 0.8rem;
}

.auction-status.active {
    background-color: #28a745;
}

.auction-status.danger {
    background-color: #dc3545;
}

.auction-status.warning {
    background-color: #ffc107;
    color: #212529;
}
</style>

<?php include 'includes/footer.php'; ?> 