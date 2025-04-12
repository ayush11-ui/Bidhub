<?php
$page_title = "Create Auction";
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/create-auction.php';
    redirect(SITE_URL . '/login.php?message=login_required');
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

$errors = [];
$success = false;
$auction_id = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_auction'])) {
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
    
    // If no errors, insert auction
    if (empty($errors)) {
        // Calculate start and end times
        $start_time = date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s', strtotime("+$duration_days days"));
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert auction with 'pending' status
            $sql = "INSERT INTO auctions (title, description, seller_id, category_id, 
                    starting_price, current_price, reserve_price, increment_amount, 
                    start_time, end_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiidddiss", 
                $title, 
                $description, 
                $_SESSION['user_id'], 
                $category_id, 
                $starting_price, 
                $starting_price, // current_price starts at starting_price
                $reserve_price, 
                $increment_amount, 
                $start_time, 
                $end_time
            );
            
            $stmt->execute();
            $auction_id = $conn->insert_id;
            
            // Handle image uploads
            $upload_dir = "../uploads/auctions/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
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
                                'is_primary' => ($key === 0) // First image is primary
                            ];
                        } else {
                            $errors[] = "Failed to upload file $name";
                        }
                    }
                }
            }
            
            // Insert image records
            if (!empty($uploaded_files)) {
                $img_sql = "INSERT INTO auction_images (auction_id, image_path, is_primary) VALUES (?, ?, ?)";
                $img_stmt = $conn->prepare($img_sql);
                
                foreach ($uploaded_files as $file) {
                    $img_stmt->bind_param("isi", $auction_id, $file['path'], $file['is_primary']);
                    $img_stmt->execute();
                }
            }
            
            // Notify admin about new auction
            $admin_notification_sql = "INSERT INTO notifications (user_id, message, link, is_read)
                                      SELECT user_id, CONCAT('New auction submitted: ', ?), CONCAT('/admin/review-auction.php?id=', ?), 0
                                      FROM users WHERE role = 'admin'";
            $admin_notification_stmt = $conn->prepare($admin_notification_sql);
            $admin_notification_stmt->bind_param("si", $title, $auction_id);
            $admin_notification_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            $success = true;
            
            // Set success message
            $_SESSION['alert'] = [
                'message' => 'Your auction has been submitted for review. You will be notified when it is approved.',
                'type' => 'success'
            ];
            
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
                <a href="my-auctions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-gavel me-2"></i> My Auctions
                </a>
                <a href="my-bids.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-hand-paper me-2"></i> My Bids
                </a>
                <a href="create-auction.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-plus me-2"></i> Create Auction
                </a>
                <a href="<?php echo SITE_URL; ?>" class="list-group-item list-group-item-action">
                    <i class="fas fa-arrow-left me-2"></i> Back to Site
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <h1 class="mb-4">Create New Auction</h1>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> <strong>Note:</strong> To maintain fairness and transparency, 
                auctions cannot be edited after creation. Please carefully review all details before submitting.
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> Your auction has been submitted for review and will be live once approved by an admin.
                    <div class="mt-3">
                        <a href="my-auctions.php" class="btn btn-primary me-2">
                            <i class="fas fa-list me-2"></i> View My Auctions
                        </a>
                        <a href="create-auction.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus me-2"></i> Create Another Auction
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Admin Approval Notice -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle me-2"></i> <strong>Note:</strong> All auctions must be reviewed and approved by an admin before they go live. Your auction will appear as "Pending" until it's approved.
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
                                <input type="text" class="form-control" id="title" name="title" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                <div class="form-text">Keep it concise and descriptive.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">
                                    Provide a detailed description of your item, including condition, features, and any other relevant information.
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="starting_price" class="form-label">Starting Price <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="starting_price" name="starting_price" step="0.01" min="0.01" required value="<?php echo isset($_POST['starting_price']) ? htmlspecialchars($_POST['starting_price']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="reserve_price" class="form-label">Reserve Price <small>(Optional)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="reserve_price" name="reserve_price" step="0.01" min="0" value="<?php echo isset($_POST['reserve_price']) ? htmlspecialchars($_POST['reserve_price']) : ''; ?>">
                                    </div>
                                    <div class="form-text">
                                        Minimum price at which you are willing to sell. If not met, you're not obligated to sell.
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="increment_amount" class="form-label">Bid Increment <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="increment_amount" name="increment_amount" step="0.01" min="0.01" required value="<?php echo isset($_POST['increment_amount']) ? htmlspecialchars($_POST['increment_amount']) : '1.00'; ?>">
                                    </div>
                                    <div class="form-text">
                                        Minimum amount by which each new bid must exceed the current highest bid.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="duration" class="form-label">Auction Duration <span class="text-danger">*</span></label>
                                <select class="form-select" id="duration" name="duration" required>
                                    <option value="1" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 1) ? 'selected' : ''; ?>>1 day</option>
                                    <option value="3" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 3) ? 'selected' : ''; ?>>3 days</option>
                                    <option value="5" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 5) ? 'selected' : ''; ?>>5 days</option>
                                    <option value="7" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 7) || !isset($_POST['duration']) ? 'selected' : ''; ?>>7 days</option>
                                    <option value="10" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 10) ? 'selected' : ''; ?>>10 days</option>
                                    <option value="14" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 14) ? 'selected' : ''; ?>>14 days</option>
                                    <option value="30" <?php echo (isset($_POST['duration']) && $_POST['duration'] == 30) ? 'selected' : ''; ?>>30 days</option>
                                </select>
                                <div class="form-text">
                                    Length of time your auction will run once approved.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="images" class="form-label">Images <small>(Recommended)</small></label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/jpeg, image/png, image/gif">
                                <div class="form-text">
                                    You can upload up to 5 images (JPG, PNG or GIF). First image will be the main image. Max 5MB per image.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="create_auction" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i> Submit Auction for Review
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 