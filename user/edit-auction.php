<?php
$page_title = "Edit Auction";
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/my-auctions.php';
    redirect(SITE_URL . '/login.php?message=login_required');
}

// Check if auction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'message' => 'Invalid auction ID.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/user/my-auctions.php');
}

$auction_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Get auction details and verify ownership
$sql = "SELECT a.*, c.name as category_name 
        FROM auctions a 
        JOIN categories c ON a.category_id = c.category_id 
        WHERE a.auction_id = ? AND a.seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $auction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'Auction not found or you do not have permission to edit it.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/user/my-auctions.php');
}

$auction = $result->fetch_assoc();

// Check if auction is in pending status
if ($auction['status'] !== 'pending') {
    $_SESSION['alert'] = [
        'message' => 'Only auctions in pending review status can be edited.',
        'type' => 'danger'
    ];
    redirect(SITE_URL . '/user/my-auctions.php');
}

// Get all categories
$categories = [];
$sql = "SELECT category_id, name FROM categories ORDER BY name";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get auction images
$images = [];
$sql = "SELECT image_id, image_path, is_primary FROM auction_images WHERE auction_id = ? ORDER BY is_primary DESC, image_id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$images_result = $stmt->get_result();
while ($image = $images_result->fetch_assoc()) {
    $images[] = $image;
}

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_auction'])) {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $starting_price = (float)$_POST['starting_price'];
    $reserve_price = !empty($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : null;
    $increment_amount = (float)$_POST['increment_amount'];
    $duration_days = (int)$_POST['duration'];
    
    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a valid category";
    }
    
    if ($starting_price <= 0) {
        $errors[] = "Starting price must be greater than zero";
    }
    
    if ($reserve_price !== null && $reserve_price <= $starting_price) {
        $errors[] = "Reserve price must be greater than starting price";
    }
    
    if ($increment_amount <= 0) {
        $errors[] = "Bid increment amount must be greater than zero";
    }
    
    if ($duration_days <= 0 || $duration_days > 30) {
        $errors[] = "Duration must be between 1 and 30 days";
    }
    
    // If no errors, update auction
    if (empty($errors)) {
        // Calculate end time from start time + duration
        $start_time = date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s', strtotime("+$duration_days days"));
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update auction data
            $sql = "UPDATE auctions SET 
                    title = ?, 
                    description = ?, 
                    category_id = ?, 
                    starting_price = ?, 
                    current_price = ?, 
                    reserve_price = ?, 
                    increment_amount = ?, 
                    start_time = ?, 
                    end_time = ? 
                    WHERE auction_id = ? AND seller_id = ? AND status = 'pending'";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiidddssii", 
                $title, 
                $description, 
                $category_id, 
                $starting_price, 
                $starting_price, // current_price = starting_price
                $reserve_price, 
                $increment_amount, 
                $start_time, 
                $end_time,
                $auction_id,
                $user_id
            );
            
            $stmt->execute();
            
            // Handle image uploads
            $upload_dir = "../uploads/auctions/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Handle image deletion requests
            if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $image_id) {
                    $sql = "SELECT image_path FROM auction_images WHERE image_id = ? AND auction_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $image_id, $auction_id);
                    $stmt->execute();
                    $image_result = $stmt->get_result();
                    
                    if ($image_result->num_rows > 0) {
                        $image_path = $image_result->fetch_assoc()['image_path'];
                        
                        // Delete file from filesystem
                        if (file_exists('../' . $image_path)) {
                            @unlink('../' . $image_path);
                        }
                        
                        // Delete record from database
                        $sql = "DELETE FROM auction_images WHERE image_id = ? AND auction_id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $image_id, $auction_id);
                        $stmt->execute();
                    }
                }
            }
            
            // Upload new images
            $uploaded_files = [];
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                foreach ($_FILES['images']['name'] as $key => $name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        if ($_FILES['images']['size'][$key] > $max_size) {
                            $errors[] = "File $name exceeds the maximum size limit of 5MB";
                            continue;
                        }
                        
                        if (!in_array($_FILES['images']['type'][$key], $allowed_types)) {
                            $errors[] = "File $name has an invalid file type. Only JPG, PNG and GIF are allowed";
                            continue;
                        }
                        
                        $file_extension = pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                        $new_filename = uniqid() . '.' . $file_extension;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $destination)) {
                            $uploaded_files[] = [
                                'path' => 'uploads/auctions/' . $new_filename,
                                'is_primary' => (count($images) === 0 && $key === 0) // First image is primary if no existing images
                            ];
                        } else {
                            $errors[] = "Failed to upload file $name";
                        }
                    }
                }
            }
            
            // Insert new image records
            if (!empty($uploaded_files)) {
                $img_sql = "INSERT INTO auction_images (auction_id, image_path, is_primary) VALUES (?, ?, ?)";
                $img_stmt = $conn->prepare($img_sql);
                
                foreach ($uploaded_files as $file) {
                    $img_stmt->bind_param("isi", $auction_id, $file['path'], $file['is_primary']);
                    $img_stmt->execute();
                }
            }
            
            // Reset the auction to pending status if it's still pending
            $sql = "UPDATE auctions SET status = 'pending' WHERE auction_id = ? AND seller_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $auction_id, $user_id);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success = true;
            
            // Set success message
            $_SESSION['alert'] = [
                'message' => 'Your auction has been updated successfully and will be reviewed again.',
                'type' => 'success'
            ];
            
            // Redirect to my auctions page
            redirect(SITE_URL . '/user/my-auctions.php');
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
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
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Auction</h1>
                <a href="my-auctions.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to My Auctions
                </a>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> <strong>Note:</strong> You can only edit auctions that are still pending review.
                Once approved, auction details cannot be changed.
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> Please fix the following issues:
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Auction Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($auction['title']); ?>">
                            <div class="form-text">Keep it concise and descriptive.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($auction['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($auction['description']); ?></textarea>
                            <div class="form-text">
                                Provide a detailed description of your item, including condition, features, and any other relevant information.
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="starting_price" class="form-label">Starting Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="starting_price" name="starting_price" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($auction['starting_price']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="reserve_price" class="form-label">Reserve Price <small>(Optional)</small></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="reserve_price" name="reserve_price" step="0.01" min="0" value="<?php echo htmlspecialchars($auction['reserve_price'] ?: ''); ?>">
                                </div>
                                <div class="form-text">
                                    Minimum price at which you are willing to sell. If not met, you're not obligated to sell.
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="increment_amount" class="form-label">Bid Increment <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="increment_amount" name="increment_amount" step="0.01" min="0.01" required value="<?php echo htmlspecialchars($auction['increment_amount']); ?>">
                                </div>
                                <div class="form-text">
                                    Minimum amount by which each new bid must exceed the current highest bid.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="duration" class="form-label">Auction Duration <span class="text-danger">*</span></label>
                            <select class="form-select" id="duration" name="duration" required>
                                <?php
                                $duration_options = [1, 3, 5, 7, 10, 14, 30];
                                $current_duration = round((strtotime($auction['end_time']) - strtotime($auction['start_time'])) / 86400); // days
                                
                                foreach ($duration_options as $days) {
                                    $selected = ($days == $current_duration) ? 'selected' : '';
                                    echo "<option value=\"$days\" $selected>$days " . ($days == 1 ? 'day' : 'days') . "</option>";
                                }
                                ?>
                            </select>
                            <div class="form-text">
                                Length of time your auction will run once approved.
                            </div>
                        </div>
                        
                        <!-- Current Images -->
                        <?php if (!empty($images)): ?>
                        <div class="mb-4">
                            <label class="form-label">Current Images</label>
                            <div class="row">
                                <?php foreach ($images as $image): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <img src="<?php echo SITE_URL . '/' . $image['image_path']; ?>" class="card-img-top" alt="Auction Image" style="height: 150px; object-fit: contain;">
                                        <div class="card-body p-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="delete_images[]" value="<?php echo $image['image_id']; ?>" id="delete_image_<?php echo $image['image_id']; ?>">
                                                <label class="form-check-label" for="delete_image_<?php echo $image['image_id']; ?>">
                                                    Delete this image
                                                </label>
                                            </div>
                                            <?php if ($image['is_primary']): ?>
                                            <div class="mt-1 text-center">
                                                <span class="badge bg-primary">Primary Image</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="images" class="form-label">Upload New Images</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/jpeg, image/png, image/gif">
                            <div class="form-text">
                                You can upload additional images (JPG, PNG or GIF). If no images exist yet, the first new image will be the main image. Max 5MB per image.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="update_auction" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Update Auction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 