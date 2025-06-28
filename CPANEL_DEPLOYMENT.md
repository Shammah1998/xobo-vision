# XOBO MART - cPanel Deployment Guide

## Prerequisites
- cPanel hosting account with PHP 7.4+ support
- MySQL/MariaDB database
- File Manager access or FTP access

## Step 1: Database Setup

1. **Create Database**
   - Log into cPanel
   - Go to "MySQL Databases"
   - Create a new database (e.g., `yourusername_xobo_c`)
   - Note down the database name, username, and password

2. **Import Database Schema**
   - Go to phpMyAdmin in cPanel
   - Select your newly created database
   - Import the `database_schema.sql` file

## Step 2: Upload Files

### Option A: Using File Manager
1. Go to "File Manager" in cPanel
2. Navigate to `public_html` (or your desired directory)
3. Upload all project files to the directory

### Option B: Using FTP
1. Use an FTP client (FileZilla, WinSCP, etc.)
2. Connect to your hosting server
3. Upload all files to `public_html` (or your desired directory)

## Step 3: Configure Database Connection

1. **Edit `config/config.php`**
   ```php
   // Update these values with your cPanel database credentials
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourusername_xobo_c'); // Your actual database name
   define('DB_USER', 'yourusername_dbuser'); // Your database username
   define('DB_PASS', 'your_database_password'); // Your database password
   ```

2. **Update Base URL** (if needed)
   ```php
   // If your project is in a subdirectory, update this:
   if ($isLocal) {
       $baseUrl = '/xobo-c';
   } else {
       // For cPanel, this should usually be empty or your subdirectory
       $baseUrl = ''; // or '/your-subdirectory' if in subdirectory
   }
   ```

## Step 4: Configure .htaccess

1. **If your project is in the root directory** (`public_html`):
   - The current `.htaccess` file should work as-is

2. **If your project is in a subdirectory** (e.g., `public_html/xobo-c`):
   - Edit `.htaccess` and uncomment/modify this line:
   ```apache
   RewriteBase /xobo-c/
   ```

## Step 5: Set File Permissions

Set the following directory permissions:
- `assets/images/` - 755 (for image uploads)
- `config/` - 644 (for configuration files)
- All other directories - 755
- All PHP files - 644

## Step 6: Test the Application

1. Visit your website URL
2. The first user to register will become a super admin
3. Test the login and admin functionality

## Troubleshooting

### 404 Errors
- Check if `.htaccess` is properly configured
- Ensure `mod_rewrite` is enabled on your hosting
- Verify file paths in `config/config.php`

### Database Connection Errors
- Verify database credentials in `config/config.php`
- Check if MySQL service is running
- Ensure database user has proper permissions

### Permission Errors
- Check file and directory permissions
- Ensure `assets/images/` is writable for image uploads

### White Screen/Blank Page
- Check PHP error logs in cPanel
- Enable error reporting temporarily by editing `config/config.php`:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```

## Security Notes

1. **Change Default Credentials**: Update database credentials
2. **Secure Configuration**: Ensure `config/` directory is not publicly accessible
3. **HTTPS**: Enable SSL certificate for production use
4. **Backup**: Regularly backup your database and files

## Performance Optimization

1. **Enable Caching**: The `.htaccess` file includes caching rules for static assets
2. **Compression**: Gzip compression is enabled for better performance
3. **CDN**: Consider using a CDN for static assets in production

## Support

If you encounter issues:
1. Check cPanel error logs
2. Verify all configuration settings
3. Test with a simple PHP file to ensure basic functionality works 