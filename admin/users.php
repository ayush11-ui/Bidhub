<?php
$page_title = "Manage Users";
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

// Set default filters and pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15; // Users per page
$offset = ($page - 1) * $limit;

// Process filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'created_at';
$order = isset($_GET['order']) ? trim($_GET['order']) : 'DESC';

// Validate sort field to prevent SQL injection
$allowed_sort_fields = ['user_id', 'username', 'email', 'role', 'created_at'];
if (!in_array($sort, $allowed_sort_fields)) {
    $sort = 'created_at';
}

// Validate order
if ($order !== 'ASC' && $order !== 'DESC') {
    $order = 'DESC';
}

// Build WHERE clause for filters
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($role)) {
    $where_clauses[] = "role = ?";
    $params[] = $role;
    $types .= 's';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Count total users with filters
$count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
$total_users = 0;

if (empty($params)) {
    $result = $conn->query($count_sql);
    $total_users = $result->fetch_assoc()['total'];
} else {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_users = $result->fetch_assoc()['total'];
}

// Calculate total pages
$total_pages = ceil($total_users / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Get users with pagination and filters
$users = [];
$sql = "SELECT * FROM users $where_sql ORDER BY $sort $order LIMIT ?, ?";

if (empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $offset, $limit);
} else {
    $params[] = $offset;
    $params[] = $limit;
    $types .= 'ii';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Get user stats
    $user_id = $row['user_id'];
    
    // Total auctions
    $auction_sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ?";
    $auction_stmt = $conn->prepare($auction_sql);
    $auction_stmt->bind_param("i", $user_id);
    $auction_stmt->execute();
    $row['auctions_count'] = $auction_stmt->get_result()->fetch_assoc()['count'];
    
    // Total bids
    $bids_sql = "SELECT COUNT(*) as count FROM bids WHERE bidder_id = ?";
    $bids_stmt = $conn->prepare($bids_sql);
    $bids_stmt->bind_param("i", $user_id);
    $bids_stmt->execute();
    $row['bids_count'] = $bids_stmt->get_result()->fetch_assoc()['count'];
    
    $users[] = $row;
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
                <h1><i class="fas fa-users me-2"></i> Manage Users</h1>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Username or email" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="sort" class="form-label">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Registration Date</option>
                                <option value="username" <?php echo $sort === 'username' ? 'selected' : ''; ?>>Username</option>
                                <option value="email" <?php echo $sort === 'email' ? 'selected' : ''; ?>>Email</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="order" class="form-label">Order</label>
                            <select class="form-select" id="order" name="order">
                                <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users List -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Users (<?php echo $total_users; ?>)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Registration Date</th>
                                    <th scope="col">Auctions</th>
                                    <th scope="col">Bids</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No users found matching your criteria</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td><?php echo $user['auctions_count']; ?></td>
                                            <td><?php echo $user['bids_count']; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="view-user.php?id=<?php echo $user['user_id']; ?>" class="btn btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($user['role'] !== 'admin' || $_SESSION['user_id'] != $user['user_id']): ?>
                                                        <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                                onclick="confirmDelete(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="User pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo "?page=" . ($page - 1) . "&search=" . urlencode($search) . "&role=" . urlencode($role) . "&sort=" . urlencode($sort) . "&order=" . urlencode($order); ?>">
                                        Previous
                                    </a>
                                </li>
                                
                                <?php
                                // Calculate range of page numbers to display
                                $range = 2; // Number of pages to show before and after current page
                                $start_page = max(1, $page - $range);
                                $end_page = min($total_pages, $page + $range);
                                
                                // Always show first page
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&role=' . urlencode($role) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Display page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&role=' . urlencode($role) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order) . '">' . $i . '</a>
                                    </li>';
                                }
                                
                                // Always show last page
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&role=' . urlencode($role) . '&sort=' . urlencode($sort) . '&order=' . urlencode($order) . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo "?page=" . ($page + 1) . "&search=" . urlencode($search) . "&role=" . urlencode($role) . "&sort=" . urlencode($sort) . "&order=" . urlencode($order); ?>">
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete user <span id="deleteUserName" class="fw-bold"></span>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" action="delete-user.php" method="post">
                    <input type="hidden" id="deleteUserId" name="user_id">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = username;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>

<?php include '../includes/footer.php'; ?>