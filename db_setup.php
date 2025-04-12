<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bidhub_db';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $db_name";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($db_name);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create categories table
$sql = "CREATE TABLE IF NOT EXISTS categories (
    category_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Categories table created successfully<br>";
} else {
    echo "Error creating categories table: " . $conn->error . "<br>";
}

// Create auctions table
$sql = "CREATE TABLE IF NOT EXISTS auctions (
    auction_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    seller_id INT(11) NOT NULL,
    category_id INT(11) NOT NULL,
    starting_price DECIMAL(10,2) NOT NULL,
    current_price DECIMAL(10,2) NOT NULL,
    reserve_price DECIMAL(10,2) DEFAULT NULL,
    increment_amount DECIMAL(10,2) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    featured BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'active', 'ended', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Auctions table created successfully<br>";
} else {
    echo "Error creating auctions table: " . $conn->error . "<br>";
}

// Create auction_images table
$sql = "CREATE TABLE IF NOT EXISTS auction_images (
    image_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    auction_id INT(11) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Auction images table created successfully<br>";
} else {
    echo "Error creating auction images table: " . $conn->error . "<br>";
}

// Create bids table
$sql = "CREATE TABLE IF NOT EXISTS bids (
    bid_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    auction_id INT(11) NOT NULL,
    bidder_id INT(11) NOT NULL,
    bid_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Bids table created successfully<br>";
} else {
    echo "Error creating bids table: " . $conn->error . "<br>";
}

// Create comments table
$sql = "CREATE TABLE IF NOT EXISTS comments (
    comment_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    auction_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(auction_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Comments table created successfully<br>";
} else {
    echo "Error creating comments table: " . $conn->error . "<br>";
}

// Create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Notifications table created successfully<br>";
} else {
    echo "Error creating notifications table: " . $conn->error . "<br>";
}

// Insert default admin user
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (username, password, email, first_name, last_name, role)
        VALUES ('admin', '$admin_password', 'admin@bidhub.com', 'Admin', 'User', 'admin')
        ON DUPLICATE KEY UPDATE username = 'admin'";

if ($conn->query($sql) === TRUE) {
    echo "Default admin user created successfully<br>";
} else {
    echo "Error creating default admin user: " . $conn->error . "<br>";
}

// Insert sample categories
$categories = [
    ['name' => 'Electronics', 'description' => 'Electronic items like smartphones, laptops, etc.'],
    ['name' => 'Collectibles', 'description' => 'Rare collectible items'],
    ['name' => 'Art', 'description' => 'Paintings, sculptures and other art pieces'],
    ['name' => 'Jewelry', 'description' => 'Precious jewelry and watches'],
    ['name' => 'Vehicles', 'description' => 'Cars, motorcycles and other vehicles']
];

foreach ($categories as $category) {
    $name = $conn->real_escape_string($category['name']);
    $description = $conn->real_escape_string($category['description']);
    
    $sql = "INSERT INTO categories (name, description) 
            VALUES ('$name', '$description')
            ON DUPLICATE KEY UPDATE name = '$name'";
    
    if ($conn->query($sql) === TRUE) {
        echo "Category '{$name}' created successfully<br>";
    } else {
        echo "Error creating category '{$name}': " . $conn->error . "<br>";
    }
}

echo "<br>Database setup completed successfully!";

$conn->close();
?> 