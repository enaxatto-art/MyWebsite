# Default Admin Passwords

## Overview
This document contains information about default admin account credentials for the Student Portal system.

## Default Admin Accounts

### Manager Admin
- **Username:** `manager`
- **Password:** `password`
- **Role:** Manager Admin
- **Email:** manager@example.com
- **Full Name:** Manager Admin

### Student Admin
- **Username:** `student_admin`
- **Password:** `password`
- **Role:** Student Admin
- **Email:** studentadmin@example.com
- **Full Name:** Student Admin

## Password Hash
The default password `password` is hashed using PHP's `password_hash()` function with bcrypt algorithm.

The hash stored in the database is:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

## Security Recommendations

⚠️ **IMPORTANT:** Change these default passwords immediately after installation!

1. Log in as Manager Admin
2. Go to Profile Settings
3. Update the password to a strong, unique password
4. Repeat for Student Admin account

## Password Requirements
- Minimum 8 characters recommended
- Use a combination of uppercase, lowercase, numbers, and special characters
- Do not reuse passwords from other systems

## Login URLs
- **Admin Login:** `/login.php`
- **Student Login:** `/student_login.php`

## Notes
- Passwords are verified using PHP's `password_verify()` function
- Passwords are stored as bcrypt hashes in the database
- Never commit actual passwords to version control

