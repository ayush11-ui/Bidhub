        </div>
    </main>
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">About BidHub</h5>
                    <p>BidHub is your premier online auction platform where you can buy and sell unique items. Join our community today!</p>
                    <div class="social-links mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>" class="text-decoration-none text-white-50">Home</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/auctions.php" class="text-decoration-none text-white-50">All Auctions</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/live.php" class="text-decoration-none text-white-50">Live Auctions</a></li>
                        <li class="mb-2"><a href="<?php echo SITE_URL; ?>/register.php" class="text-decoration-none text-white-50">Register</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/login.php" class="text-decoration-none text-white-50">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Contact Us</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 Auction St, Bidville</li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> (123) 456-7890</li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> info@bidhub.com</li>
                    </ul>
                    <form class="mt-3">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Subscribe to newsletter">
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> BidHub. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/script.js"></script>
    <?php if(isset($extra_js)) echo $extra_js; ?>
</body>
</html> 