<?php
$page_title = "Create Auction";
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

// Get all categories
$category_sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
$categories = $conn->query($category_sql)->fetch_all(MYSQLI_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $starting_price = (float)$_POST['starting_price'];
    $reserve_price = !empty($_POST['reserve_price']) ? (float)$_POST['reserve_price'] : null;
    $increment_amount = (float)$_POST['increment_amount'];
    $buy_now_price = !empty($_POST['buy_now_price']) ? (float)$_POST['buy_now_price'] : null;
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $status = sanitize($_POST['status']);
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Please select a valid category';
    }
    
    if ($starting_price <= 0) {
        $errors[] = 'Starting price must be greater than zero';
    }
    
    if ($increment_amount <= 0) {
        $errors[] = 'Increment amount must be greater than zero';
    }
    
    if ($reserve_price !== null && $reserve_price <= $starting_price) {
        $errors[] = 'Reserve price must be greater than starting price';
    }
    
    if ($buy_now_price !== null && $buy_now_price <= $starting_price) {
        $errors[] = 'Buy now price must be greater than starting price';
    }
    
    if (empty($start_time)) {
        $errors[] = 'Start time is required';
    }
    
    if (empty($end_time)) {
        $errors[] = 'End time is required';
    }
    
    if (!empty($start_time) && !empty($end_time)) {
        $start_timestamp = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        
        if ($start_timestamp >= $end_timestamp) {
            $errors[] = 'End time must be after start time';
        }
    }
    
    // If no errors, proceed with auction creation
    if (empty($errors)) {
        $sql = "INSERT INTO auctions (title, description, category_id, seller_id, starting_price, current_price, 
                reserve_price, increment_amount, buy_now_price, start_time, end_time, status, featured, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $conn->prepare($sql);
        $admin_id = $_SESSION['user_id'];
        $stmt->bind_param("ssiiddddsssi", $title, $description, $category_id, $admin_id, $starting_price, 
                    $starting_price, $reserve_price, $increment_amount, $buy_now_price, $start_time, $end_time, $status, $featured);
        
        if ($stmt->execute()) {
            $auction_id = $stmt->insert_id;
            
            // Handle image uploads
            $upload_dir = '../uploads/auctions/' . $auction_id . '/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Process uploaded images
            if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $imageCount = count($_FILES['images']['name']);
                
                for ($i = 0; $i < $imageCount; $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['images']['tmp_name'][$i];
                        $name = basename($_FILES['images']['name'][$i]);
                        $extension = pathinfo($name, PATHINFO_EXTENSION);
                        
                        // Generate unique filename
                        $unique_filename = uniqid() . '.' . $extension;
                        $upload_path = $upload_dir . $unique_filename;
                        
                        if (move_uploaded_file($tmp_name, $upload_path)) {
                            // Set the first image as primary
                            $is_primary = ($i === 0) ? 1 : 0;
                            
                            // Save image info to database
                            $img_sql = "INSERT INTO auction_images (auction_id, image_path, is_primary) VALUES (?, ?, ?)";
                            $img_stmt = $conn->prepare($img_sql);
                            $rel_path = 'uploads/auctions/' . $auction_id . '/' . $unique_filename;
                            $img_stmt->bind_param("isi", $auction_id, $rel_path, $is_primary);
                            $img_stmt->execute();
                        }
                    }
                }
            }
            
            $_SESSION['alert'] = [
                'message' => 'Auction created successfully.',
                'type' => 'success'
            ];
            
            redirect('auctions.php');
        } else {
            $errors[] = 'Error creating auction: ' . $stmt->error;
        }
    }
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
                <h1><i class="fas fa-plus-circle me-2"></i> Create New Auction</h1>
                <a href="auctions.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Auctions
                </a>
            </div>
            
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Auction Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Basic Information</h5>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Auction Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>" <?php echo isset($category_id) && $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pricing Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Pricing Information</h5>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="starting_price" class="form-label">Starting Price ($) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="starting_price" name="starting_price" step="0.01" min="0.01" value="<?php echo isset($starting_price) ? htmlspecialchars($starting_price) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="reserve_price" class="form-label">Reserve Price ($)</label>
                                    <input type="number" class="form-control" id="reserve_price" name="reserve_price" step="0.01" min="0.01" value="<?php echo isset($reserve_price) ? htmlspecialchars($reserve_price) : ''; ?>">
                                    <div class="form-text">Minimum price for sale. Optional.</div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="increment_amount" class="form-label">Bid Increment ($) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="increment_amount" name="increment_amount" step="0.01" min="0.01" value="<?php echo isset($increment_amount) ? htmlspecialchars($increment_amount) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="buy_now_price" class="form-label">Buy Now Price ($)</label>
                                    <input type="number" class="form-control" id="buy_now_price" name="buy_now_price" step="0.01" min="0.01" value="<?php echo isset($buy_now_price) ? htmlspecialchars($buy_now_price) : ''; ?>">
                                    <div class="form-text">Instant purchase price. Optional.</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timing and Status -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Timing and Status</h5>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?php echo isset($start_time) ? date('Y-m-d\TH:i', strtotime($start_time)) : date('Y-m-d\TH:i'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="end_time" name="end_time" value="<?php echo isset($end_time) ? date('Y-m-d\TH:i', strtotime($end_time)) : date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active" <?php echo isset($status) && $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="pending" <?php echo isset($status) && $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="ended" <?php echo isset($status) && $status === 'ended' ? 'selected' : ''; ?>>Ended</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?php echo isset($featured) && $featured ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">
                                        <i class="fas fa-star text-warning me-1"></i> Mark as Featured Auction
                                    </label>
                                    <div class="form-text">Featured auctions appear prominently on the homepage</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Images -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2 mb-3">Images</h5>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="images" class="form-label">Upload Images</label>
                                    <input class="form-control" type="file" id="images" name="images[]" multiple accept="image/*">
                                    <div class="form-text">You can upload multiple images. The first image will be used as the main image.</div>
                                </div>
                                
                                <div id="imagePreview" class="row mt-3"></div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i> Create Auction
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    const imageInput = document.getElementById('images');
    const imagePreview = document.getElementById('imagePreview');
    
    imageInput.addEventListener('change', function() {
        imagePreview.innerHTML = '';
        
        if (this.files) {
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                
                if (file.type.match('image.*')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const col = document.createElement('div');
                        col.className = 'col-sm-6 col-md-4 col-lg-3 mb-3';
                        
                        const card = document.createElement('div');
                        card.className = 'card h-100';
                        
                        const img = document.createElement('img');
                        img.className = 'card-img-top';
                        img.src = e.target.result;
                        img.style.height = '180px';
                        img.style.objectFit = 'cover';
                        
                        const cardBody = document.createElement('div');
                        cardBody.className = 'card-body p-2';
                        
                        const fileName = document.createElement('p');
                        fileName.className = 'card-text small text-muted mb-0';
                        fileName.textContent = file.name;
                        
                        cardBody.appendChild(fileName);
                        card.appendChild(img);
                        card.appendChild(cardBody);
                        col.appendChild(card);
                        imagePreview.appendChild(col);
                    };
                    
                    reader.readAsDataURL(file);
                }
            }
        }
    });
    
    // Set min values for reserve and buy now prices based on starting price
    const startingPriceInput = document.getElementById('starting_price');
    const reservePriceInput = document.getElementById('reserve_price');
    const buyNowPriceInput = document.getElementById('buy_now_price');
    
    startingPriceInput.addEventListener('input', function() {
        const startingPrice = parseFloat(this.value);
        if (startingPrice > 0) {
            reservePriceInput.min = startingPrice;
            buyNowPriceInput.min = startingPrice;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?> 