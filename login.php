<?php
$page_title = "Login";
require_once 'includes/config.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL);
}

$errors = [];
$username = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Check for empty fields
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Add success message
                $_SESSION['alert'] = [
                    'message' => 'Login successful! Welcome back, ' . $user['username'] . '.',
                    'type' => 'success'
                ];
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect(SITE_URL . '/admin/index.php');
                } else {
                    // Redirect to the intended page if set, otherwise to homepage
                    $redirect_to = $_SESSION['redirect_after_login'] ?? SITE_URL;
                    unset($_SESSION['redirect_after_login']);
                    redirect($redirect_to);
                }
            } else {
                $errors['login'] = 'Invalid username or password';
            }
        } else {
            $errors['login'] = 'Invalid username or password';
        }
        
        $stmt->close();
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Login to Your Account</h2>
                
                <?php if (isset($errors['login'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['login']; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                id="username" name="username" value="<?php echo $username; ?>">
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                id="password" name="password">
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                </div>
            </div>
        </div>
        
        <?php if (isset($_GET['message']) && $_GET['message'] === 'login_required'): ?>
            <div class="alert alert-warning mt-3">
                <i class="fas fa-exclamation-circle me-2"></i> You need to be logged in to access that page.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 