# Quick Setup Guide - Xobo Vision

## 🚀 Get Started in 5 Minutes

### Step 1: Database Setup
```sql
1. Open phpMyAdmin
2. Create new database: "xobo-c"
3. Import "database_schema.sql"
```

### Step 2: File Placement
```bash
1. Copy all files to your web server directory
   (e.g., C:\xampp\htdocs\xobo-vision)
2. Ensure proper folder permissions
```

### Step 3: Configuration Check
```php
// Check config/db.php - Update if needed
define('DB_HOST', 'localhost');
define('DB_NAME', 'xobo-c');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Step 4: Access & Test
```
1. Visit: http://localhost/xobo-vision
2. Login as Super Admin:
   Email: admin@xobo.com
   Password: password
```

## 📋 Test Workflow

### 1. Super Admin (admin@xobo.com / password)
- ✅ Login to admin dashboard
- ✅ View company management

### 2. Register Test Company
- ✅ Go to signup page
- ✅ Register a test company
- ✅ As admin, approve the company

### 3. Company Admin
- ✅ Login after approval
- ✅ Add sample products
- ✅ View products list

### 4. Register End User
- ✅ Signup with an approved company
- ✅ Admin approves company (if not already)
- ✅ Login as end user

### 5. Shop & Order
- ✅ Browse products
- ✅ Add to cart
- ✅ Checkout with address
- ✅ View order history

## 🔧 Common Fixes

**Can't connect to database?**
- Start XAMPP/WAMP services
- Check MySQL is running
- Verify database name

**Page not found?**
- Check web server is running
- Verify folder path
- Clear browser cache

**Permission denied?**
- Check user roles in database
- Verify company approval status
- Re-login to refresh session

## 📱 Testing Different Roles

| Role | Email | Password | Access |
|------|-------|----------|--------|
| Super Admin | admin@xobo.com | password | All companies |
| Company Admin | (after signup) | (your password) | Own company only |
| End User | (after signup) | (your password) | Shopping only |

## ✅ Success Indicators

- [x] Database tables created successfully
- [x] Super admin can login
- [x] Company registration works
- [x] Company approval system functions
- [x] Product management operational
- [x] Shopping cart works
- [x] Order placement successful
- [x] Responsive design displays correctly

**🎉 Your multi-tenant e-commerce platform is ready!** 