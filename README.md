# Xobo Vision - Multi-Tenant E-Commerce & Delivery Platform

A complete multi-tenant e-commerce platform built with pure PHP, MySQL, and vanilla CSS/JS. This system allows multiple companies to manage their products and receive orders from their registered users.

## Features

### 🔐 User Roles & Authentication
- **Super Admin**: Manages company approvals and system oversight
- **Company Admin**: Manages products and views orders for their company
- **End Users**: Browse products and place orders within their company

### 🏢 Multi-Tenant Architecture
- Each company operates independently
- Isolated product catalogs and user bases
- Company approval workflow

### 🛍️ E-Commerce Functionality
- Product catalog management
- Shopping cart with session storage
- Order placement and tracking
- Weight and pricing calculations

### 🎨 Modern UI/UX
- Responsive design for all devices
- Clean, professional interface
- Intuitive navigation based on user roles

## Technology Stack

- **Backend**: PHP 8+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Security**: PDO prepared statements, password hashing, CSRF protection

## Installation & Setup

### Prerequisites
- XAMPP, WAMP, or LAMP stack
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)

### Step 1: Database Setup
1. Open phpMyAdmin or your MySQL client
2. Create a new database named `xobo-c`
3. Import the `database_schema.sql` file to create all tables

### Step 2: Configuration
1. Clone/download the project to your web server directory
2. Update database credentials in `config/db.php` if needed:


### Step 3: Access the Application
1. Navigate to `http://localhost/xobo-vision` in your browser
2. The system will redirect to the login page

## Default Login Credentials

### Super Admin
- **Email**: admin@xobo.com
- **Password**: password

*Note: Change this password immediately in production!*

## User Flows

### 1. Company Registration
1. Visit the signup page
2. Fill in company details and admin email
3. Wait for super admin approval
4. Once approved, login and start managing products

### 2. Super Admin Workflow
1. Login with admin credentials
2. Review pending company registrations
3. Approve or reject companies
4. Monitor system activity

### 3. Company Admin Workflow
1. Login after company approval
2. Add and manage products
3. View customer orders
4. Monitor sales activity

### 4. End User Workflow
1. Login (only after company is approved)
2. Browse company's product catalog
3. Add items to cart
4. Place orders with delivery address
5. Track order history

## File Structure

```
xobo-vision/
├── config/
│   └── db.php                 # Database configuration
├── auth/
│   ├── login.php             # User authentication
│   ├── logout.php            # Session termination
│   └── register_user.php     # End user registration (by invitation)
├── admin/
│   └── dashboard.php         # Super admin panel
├── company/
│   ├── products.php          # Product management
│   └── orders.php            # Order viewing
├── shop/
│   ├── shop.php              # Product catalog
│   ├── cart.php              # Shopping cart
│   └── orders.php            # Order history
├── includes/
│   ├── header.php            # Common header
│   ├── footer.php            # Common footer
│   └── functions.php         # Utility functions
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       └── shop.js           # JavaScript functionality
├── database_schema.sql       # Database structure
├── index.php                 # Main entry point
└── README.md                 # This file
```

## Security Features

- **SQL Injection Protection**: PDO prepared statements
- **Password Security**: PHP password hashing
- **XSS Prevention**: Input sanitization and output escaping
- **Access Control**: Role-based permissions
- **Session Management**: Secure session handling

## Database Schema

### Companies
- Company registration and approval status
- Multi-tenant isolation

### Users
- Role-based user management
- Company association

### Products
- Company-specific product catalogs
- SKU, weight, and pricing management

### Orders & Order Items
- Complete order tracking
- Detailed order item records

## Customization

### Adding New Features
1. Create new PHP files in appropriate directories
2. Follow the existing naming conventions
3. Include proper authentication checks
4. Update navigation in `includes/header.php`

### Styling Changes
1. Modify `assets/css/style.css`
2. Follow the existing CSS structure
3. Maintain responsive design principles

### Database Changes
1. Create migration scripts
2. Update the schema file
3. Modify relevant PHP files

## Deployment

### Production Checklist
- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Enable HTTPS
- [ ] Set proper file permissions
- [ ] Configure error logging
- [ ] Set up backups

### Server Requirements
- PHP 8.0+
- MySQL 5.7+
- Apache/Nginx
- SSL certificate (recommended)

## Troubleshooting

### Common Issues

**Database Connection Failed**
- Check database credentials in `config/db.php`
- Ensure MySQL service is running
- Verify database name exists

**Access Denied Errors**
- Check user role assignments
- Verify company approval status
- Clear browser cache and cookies

**Styling Issues**
- Check CSS file path in header
- Verify web server permissions
- Clear browser cache

## Support & Development

### Getting Help
1. Check this README first
2. Review error logs
3. Verify database structure

### Contributing
1. Follow PSR coding standards
2. Test all functionality
3. Document changes
4. Ensure security best practices

## License

This project is developed for educational and business purposes. Please ensure compliance with your local regulations and licensing requirements.

---

**Built with ❤️ using Pure PHP, MySQL & Vanilla JS**
