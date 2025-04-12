<?php
$page_title = "Review Auction";
require_once '../includes/config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['alert'] = [
        'message' => 'You do not have permission to access the admin area.',
        'type' => 'danger'
    ];
    redirect(SITE_URL);
}

// Check if auction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['alert'] = [
        'message' => 'Invalid auction ID.',
        'type' => 'danger'
    ];
    redirect('pending-auctions.php');
}

$auction_id = (int)$_GET['id'];

// Handle auction approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && !empty($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'approve' || $action === 'reject') {
            $status = ($action === 'approve') ? 'active' : 'rejected';
            $reason = isset($_POST['reason']) ? sanitize($_POST['reason']) : '';
            
            $sql = "UPDATE auctions SET status = ? WHERE auction_id = ? AND status = 'pending'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $status, $auction_id);
            
            if ($stmt->execute()) {
                $_SESSION['alert'] = [
                    'message' => 'Auction ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully.',
                    'type' => 'success'
                ];
                redirect('pending-auctions.php');
            } else {
                $_SESSION['alert'] = [
                    'message' => 'Error updating auction status.',
                    'type' => 'danger'
                ];
            }
        }
    }
}

// Get auction details
$sql = "SELECT a.*, c.name as category_name, u.username as seller_name, u.email as seller_email
        FROM auctions a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.seller_id = u.user_id
        WHERE a.auction_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['alert'] = [
        'message' => 'Auction not found.',
        'type' => 'danger'
    ];
    redirect('pending-auctions.php');
}

$auction = $result->fetch_assoc();

// Get auction images
$images = [];
$sql = "SELECT image_id, image_path, is_primary FROM auction_images WHERE auction_id = ? ORDER BY is_primary DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $auction_id);
$stmt->execute();
$images_result = $stmt->get_result();
while ($image = $images_result->fetch_assoc()) {
    $images[] = $image;
}

// If no images, use a placeholder
if (empty($images)) {
    $images[] = [
        'image_id' => 0,
        'image_path' => 'https://placehold.co/600x400/e9ecef/495057?text=No+Image+Available',
        'is_primary' => 1
    ];
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
                <a href="pending-auctions.php" class="list-group-item list-group-item-action active d-flex align-items-center">
                    <i class="fas fa-clock me-2"></i> Pending Auctions
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
                <h1>Review Auction</h1>
                <a href="pending-auctions.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Pending Auctions
                </a>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Pending Approval: <?php echo $auction['title']; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Image Carousel -->
                            <div id="auctionImageCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
                                <div class="carousel-inner">
                                    <?php foreach ($images as $index => $image): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo $image['image_path'] && $image['image_id'] > 0 ? '../' . $image['image_path'] : $image['image_path']; ?>" class="d-block w-100 carousel-image" alt="Auction Image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($images) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#auctionImageCarousel" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Previous</span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#auctionImageCarousel" data-bs-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="visually-hidden">Next</span>
                                    </button>
                                    <div class="carousel-indicators">
                                        <?php foreach ($images as $index => $image): ?>
                                            <button type="button" data-bs-target="#auctionImageCarousel" data-bs-slide-to="<?php echo $index; ?>" 
                                                <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                                                aria-label="Slide <?php echo $index + 1; ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h3 class="mb-3"><?php echo $auction['title']; ?></h3>
                            
                            <div class="row mb-4">
                                <div class="col-6">
                                    <p class="mb-1"><strong>Category:</strong></p>
                                    <p><?php echo $auction['category_name']; ?></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1"><strong>Starting Price:</strong></p>
                                    <p class="text-primary fw-bold"><?php echo formatPrice($auction['starting_price']); ?></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1"><strong>Increment Amount:</strong></p>
                                    <p><?php echo formatPrice($auction['increment_amount']); ?></p>
                                </div>
                                <?php if ($auction['reserve_price']): ?>
                                <div class="col-6">
                                    <p class="mb-1"><strong>Reserve Price:</strong></p>
                                    <p><?php echo formatPrice($auction['reserve_price']); ?></p>
                                </div>
                                <?php endif; ?>
                                <div class="col-6">
                                    <p class="mb-1"><strong>Start Time:</strong></p>
                                    <p><?php echo date('M d, Y h:i A', strtotime($auction['start_time'])); ?></p>
                                </div>
                                <div class="col-6">
                                    <p class="mb-1"><strong>End Time:</strong></p>
                                    <p><?php echo date('M d, Y h:i A', strtotime($auction['end_time'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <p class="mb-1"><strong>Seller:</strong></p>
                                <p>
                                    <?php echo $auction['seller_name']; ?> 
                                    <span class="text-muted">(<?php echo $auction['seller_email']; ?>)</span>
                                </p>
                            </div>
                            
                            <div class="mb-4">
                                <p class="mb-1"><strong>Submission Date:</strong></p>
                                <p><?php echo date('M d, Y h:i A', strtotime($auction['created_at'])); ?></p>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="fas fa-check me-2"></i> Approve Auction
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times me-2"></i> Reject Auction
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h4>Description</h4>
                        <div class="card">
                            <div class="card-body bg-light">
                                <?php echo nl2br($auction['description']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Auction Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="approveModalLabel">Approve Auction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to approve this auction?</p>
                    <p>Title: <strong><?php echo $auction['title']; ?></strong></p>
                    <p>Once approved, the auction will be visible to all users and bidding can begin.</p>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="approve">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Auction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Auction Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectModalLabel">Reject Auction</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to reject this auction?</p>
                    <p>Title: <strong><?php echo $auction['title']; ?></strong></p>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Rejection Reason (Optional)</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="reject">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Auction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.carousel-image {
    height: 400px;
    object-fit: contain;
    background-color: #f8f9fa;
}
</style>

<?php include '../includes/footer.php'; ?> 