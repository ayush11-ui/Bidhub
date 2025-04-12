<?php
$page_title = "Manage Categories";
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

// Process category creation
if (isset($_POST['add_category'])) {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    
    if (empty($name)) {
        $_SESSION['alert'] = [
            'message' => 'Category name is required.',
            'type' => 'danger'
        ];
    } else {
        // Check if category with same name already exists
        $check_sql = "SELECT COUNT(*) as count FROM categories WHERE name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($exists) {
            $_SESSION['alert'] = [
                'message' => 'A category with this name already exists.',
                'type' => 'danger'
            ];
        } else {
            // Insert new category
            $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                $_SESSION['alert'] = [
                    'message' => 'Category added successfully.',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error adding category: ' . $conn->error,
                    'type' => 'danger'
                ];
            }
        }
    }
}

// Process category update
if (isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['category_id'];
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    
    if (empty($name)) {
        $_SESSION['alert'] = [
            'message' => 'Category name is required.',
            'type' => 'danger'
        ];
    } else {
        // Check if category with same name already exists (excluding this category)
        $check_sql = "SELECT COUNT(*) as count FROM categories WHERE name = ? AND category_id != ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("si", $name, $category_id);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc()['count'] > 0;
        
        if ($exists) {
            $_SESSION['alert'] = [
                'message' => 'A category with this name already exists.',
                'type' => 'danger'
            ];
        } else {
            // Update category
            $sql = "UPDATE categories SET name = ?, description = ? WHERE category_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $description, $category_id);
            
            if ($stmt->execute()) {
                $_SESSION['alert'] = [
                    'message' => 'Category updated successfully.',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error updating category: ' . $conn->error,
                    'type' => 'danger'
                ];
            }
        }
    }
}

// Process category deletion
if (isset($_POST['delete_category'])) {
    $category_id = (int)$_POST['category_id'];
    
    // Check if any auctions use this category
    $check_sql = "SELECT COUNT(*) as count FROM auctions WHERE category_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $auctions_count = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($auctions_count > 0) {
        $_SESSION['alert'] = [
            'message' => 'Cannot delete category with existing auctions. Please reassign or delete these auctions first.',
            'type' => 'danger'
        ];
    } else {
        // Delete category
        $sql = "DELETE FROM categories WHERE category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        
        if ($stmt->execute()) {
            $_SESSION['alert'] = [
                'message' => 'Category deleted successfully.',
                'type' => 'success'
            ];
        } else {
            $_SESSION['alert'] = [
                'message' => 'Error deleting category: ' . $conn->error,
                'type' => 'danger'
            ];
        }
    }
}

// Get all categories with auction counts
$sql = "SELECT c.*, COUNT(a.auction_id) as auction_count 
        FROM categories c 
        LEFT JOIN auctions a ON c.category_id = a.category_id 
        GROUP BY c.category_id 
        ORDER BY c.name ASC";
$categories = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

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
                <a href="categories.php" class="list-group-item list-group-item-action active d-flex align-items-center">
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
                <h1><i class="fas fa-tags me-2"></i> Manage Categories</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i> Add New Category
                </button>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Categories</h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary"><?php echo count($categories); ?> Total</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Auctions</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i> No categories found.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></td>
                                            <td>
                                                <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<span class="text-muted fst-italic">No description</span>'; ?>
                                            </td>
                                            <td>
                                                <?php if ($category['auction_count'] > 0): ?>
                                                    <a href="auctions.php?category=<?php echo $category['category_id']; ?>" class="badge bg-info text-decoration-none">
                                                        <?php echo $category['auction_count']; ?> auctions
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0 auctions</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo addslashes($category['description']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            <?php echo $category['auction_count'] > 0 ? 'disabled' : ''; ?>
                                                            onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['name']); ?>', <?php echo $category['auction_count']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i> Categories with existing auctions cannot be deleted.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3"></textarea>
                        <div class="form-text">Optional description for this category.</div>
                    </div>
                    <input type="hidden" name="add_category" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        <div class="form-text">Optional description for this category.</div>
                    </div>
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <input type="hidden" name="edit_category" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete this category? This action cannot be undone.</p>
                    <p>Category: <strong id="deleteCategoryName"></strong></p>
                    <div id="deleteCategoryWarning" class="alert alert-warning d-none">
                        <i class="fas fa-exclamation-triangle me-2"></i> This category cannot be deleted because it contains auctions.
                    </div>
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <input type="hidden" name="delete_category" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteButton">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(categoryId, name, description) {
    document.getElementById('edit_category_id').value = categoryId;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    
    var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    editModal.show();
}

function confirmDelete(categoryId, name, auctionCount) {
    document.getElementById('delete_category_id').value = categoryId;
    document.getElementById('deleteCategoryName').textContent = name;
    
    // Handle warning and disable button if category has auctions
    const warningElement = document.getElementById('deleteCategoryWarning');
    const deleteButton = document.getElementById('confirmDeleteButton');
    
    if (auctionCount > 0) {
        warningElement.classList.remove('d-none');
        deleteButton.disabled = true;
    } else {
        warningElement.classList.add('d-none');
        deleteButton.disabled = false;
    }
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
    deleteModal.show();
}
</script>

<?php include '../includes/footer.php'; ?> 