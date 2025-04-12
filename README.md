# Bidhub - Online Auction Platform

Bidhub is a full-featured auction platform that allows users to create, bid on, and manage auctions.

## Features

- User registration and authentication
- Create and manage auction listings with images
- Real-time bidding system
- Admin dashboard for site management
- User profiles and auction history
- Category-based auction browsing
- Responsive design for mobile and desktop

## Installation

Follow these steps to set up Bidhub on your local environment:

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server
- XAMPP, WAMP, MAMP, or similar local development environment

### Setup Instructions

1. **Place the project in your web server directory**:
   - Copy the Bidhub folder to your `htdocs` directory (usually located in XAMPP, WAMP, or MAMP installation path)

   OR

   - Create a symbolic link if your project is located elsewhere:
     
     **Windows (Run as Administrator)**:
     ```
     mklink /D C:\xampp\htdocs\Bidhub C:\path\to\your\Bidhub
     ```
     
     **Linux/Mac**:
     ```
     ln -s /path/to/your/Bidhub /path/to/htdocs/Bidhub
     ```

2. **Start your web server**: 
   - Start Apache and MySQL services through your local server management panel (XAMPP, WAMP, MAMP, etc.)

3. **Set up the database**:
   - Open your browser and navigate to: `http://localhost/Bidhub/db_setup.php`
   - Follow the on-screen instructions to set up the database
   - This will create all necessary tables and basic data

4. **Access the application**:
   - Open your browser and navigate to: `http://localhost/Bidhub/`
   - The application should now be running

## Initial Login

After setup, you can log in with these default credentials:

- **Admin Account**:
  - Username: admin
  - Password: admin123

- **Test User Account**:
  - Username: user
  - Password: user123

## Configuration

You can modify the site settings in `includes/config.php`:

- Database connection settings
- Site URL and name
- File upload settings
- Email configuration

## Directory Structure

- `admin/` - Admin dashboard files
- `assets/` - CSS, JavaScript, and image files
- `includes/` - Core PHP functions and configuration
- `user/` - User dashboard files
- `uploads/` - Directory for uploaded auction images

## Support

For support, please create an issue in the repository or contact the developer. 