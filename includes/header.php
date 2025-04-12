<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <?php if(isset($extra_css)) echo $extra_css; ?>
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                    <i class="fas fa-gavel me-2"></i>BidHub
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/auctions.php">Auctions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/live.php">Live Auctions</a>
                        </li>
                        <?php if(isLoggedIn() && isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/admin/index.php">Admin Dashboard</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <div class="d-flex">
                        <?php if(isLoggedIn()): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user me-2"></i><?php echo $_SESSION['username']; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/profile.php">My Profile</a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/my-auctions.php">My Auctions</a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/my-bids.php">My Bids</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">Logout</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-light me-2">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </a>
                            <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <main><?php if(isset($page_header)): ?>
        <div class="container-fluid page-header py-5 mb-5">
            <div class="container py-5">
                <h1 class="display-3 text-white mb-3 animated slideInDown"><?php echo $page_header; ?></h1>
                <?php if(isset($page_subheader)): ?>
                <p class="text-white pb-3 animated slideInDown"><?php echo $page_subheader; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="container my-5">
            <?php if(isset($_SESSION['alert'])): ?>
                <?php displayAlert($_SESSION['alert']['message'], $_SESSION['alert']['type']); ?>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?> 