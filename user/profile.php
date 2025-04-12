<?php
$page_title = "My Profile";
require_once '../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/user/profile.php';
    redirect(SITE_URL . '/login.php?message=login_required');
}

$user_id = $_SESSION['user_id'];

// Get user data
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic profile update
    if (isset($_POST['update_profile'])) {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        
        // Validate inputs
        if (empty($first_name)) {
            $errors['first_name'] = 'First name is required';
        }
        
        if (empty($last_name)) {
            $errors['last_name'] = 'Last name is required';
        }
        
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } elseif ($email !== $user['email']) {
            // Check if email is already taken by another user
            $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors['email'] = 'Email address is already taken';
            }
        }
        
        // Update profile if no errors
        if (empty($errors)) {
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
            
            if ($stmt->execute()) {
                // Update user data for display
                $user['first_name'] = $first_name;
                $user['last_name'] = $last_name;
                $user['email'] = $email;
                
                $_SESSION['alert'] = [
                    'message' => 'Profile updated successfully!',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error updating profile: ' . $conn->error,
                    'type' => 'danger'
                ];
            }
        }
    }
    
    // Password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password)) {
            $errors['current_password'] = 'Current password is required';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect';
        }
        
        if (empty($new_password)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($new_password) < 6) {
            $errors['new_password'] = 'New password must be at least 6 characters';
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        // Update password if no errors
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $sql = "UPDATE users SET password = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['alert'] = [
                    'message' => 'Password changed successfully!',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error changing password: ' . $conn->error,
                    'type' => 'danger'
                ];
            }
        }
    }
    
    // Profile picture upload
    if (isset($_POST['upload_picture']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $file = $_FILES['profile_picture'];
        
        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            $errors['profile_picture'] = 'Only JPEG, PNG, and GIF images are allowed';
        } elseif ($file['size'] > $max_size) {
            $errors['profile_picture'] = 'Image size must be less than 2MB';
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../assets/uploads/profile/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $filename = uniqid('profile_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $upload_path = $upload_dir . $filename;
            
            // Upload file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])) {
                    @unlink('../' . $user['profile_picture']);
                }
                
                // Update database
                $profile_picture = 'assets/uploads/profile/' . $filename;
                $sql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $profile_picture, $user_id);
                
                if ($stmt->execute()) {
                    // Update user data for display
                    $user['profile_picture'] = $profile_picture;
                    
                    $_SESSION['alert'] = [
                        'message' => 'Profile picture updated successfully!',
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'message' => 'Error updating profile picture: ' . $conn->error,
                        'type' => 'danger'
                    ];
                }
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error uploading profile picture.',
                    'type' => 'danger'
                ];
            }
        }
    }
}

// Get user statistics
$stats = [
    'auctions_created' => 0,
    'active_auctions' => 0,
    'bids_placed' => 0,
    'won_auctions' => 0
];

// Auctions created
$sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['auctions_created'] = $result->fetch_assoc()['count'];

// Active auctions
$sql = "SELECT COUNT(*) as count FROM auctions WHERE seller_id = ? AND status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['active_auctions'] = $result->fetch_assoc()['count'];

// Bids placed
$sql = "SELECT COUNT(*) as count FROM bids WHERE bidder_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['bids_placed'] = $result->fetch_assoc()['count'];

// Won auctions
$sql = "SELECT COUNT(*) as count FROM auctions a
        JOIN bids b ON a.auction_id = b.auction_id
        WHERE a.status = 'ended' AND b.bidder_id = ? AND b.bid_amount = a.current_price";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['won_auctions'] = $result->fetch_assoc()['count'];

include '../includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <div class="list-group mb-4">
                <a href="profile.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-user me-2"></i> My Profile
                </a>
                <a href="my-auctions.php" class="list-group-item list-group-item-action">
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
            
            <!-- User Stats -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Your Statistics</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Auctions Created
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['auctions_created']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active Auctions
                            <span class="badge bg-success rounded-pill"><?php echo $stats['active_auctions']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Bids Placed
                            <span class="badge bg-info rounded-pill"><?php echo $stats['bids_placed']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Auctions Won
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['won_auctions']; ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <h1 class="mb-4">My Profile</h1>
            
            <div class="row">
                <!-- Profile Overview -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <img src="<?php echo !empty($user['profile_picture']) ? SITE_URL . '/' . $user['profile_picture'] : SITE_URL . '/assets/images/default-profile.jpg'; ?>" 
                                 class="rounded-circle img-thumbnail mb-3" alt="Profile Picture" style="width: 150px; height: 150px; object-fit: cover;">
                            <h4><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h4>
                            <p class="text-muted">@<?php echo $user['username']; ?></p>
                            <p><i class="fas fa-envelope me-2"></i> <?php echo $user['email']; ?></p>
                            <p><i class="fas fa-calendar me-2"></i> Joined <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                            
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#uploadPictureModal">
                                <i class="fas fa-camera me-2"></i> Change Picture
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Edit Form -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Edit Profile</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" disabled>
                                    <div class="form-text">Username cannot be changed.</div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                               id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>">
                                        <?php if (isset($errors['first_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                               id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>">
                                        <?php if (isset($errors['last_name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           id="email" name="email" value="<?php echo $user['email']; ?>">
                                    <?php if (isset($errors['email'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password Form -->
                    <div class="card mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control <?php echo isset($errors['current_password']) ? 'is-invalid' : ''; ?>" 
                                           id="current_password" name="current_password">
                                    <?php if (isset($errors['current_password'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['current_password']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control <?php echo isset($errors['new_password']) ? 'is-invalid' : ''; ?>" 
                                               id="new_password" name="new_password">
                                        <?php if (isset($errors['new_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['new_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                               id="confirm_password" name="confirm_password">
                                        <?php if (isset($errors['confirm_password'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-danger">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Profile Picture Modal -->
<div class="modal fade" id="uploadPictureModal" tabindex="-1" aria-labelledby="uploadPictureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="uploadPictureModalLabel">Upload Profile Picture</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">Select Image</label>
                        <input type="file" class="form-control <?php echo isset($errors['profile_picture']) ? 'is-invalid' : ''; ?>" 
                               id="profile_picture" name="profile_picture" accept="image/jpeg, image/png, image/gif">
                        <div class="form-text">Maximum file size: 2MB. Allowed formats: JPEG, PNG, GIF.</div>
                        <?php if (isset($errors['profile_picture'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['profile_picture']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_picture" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 