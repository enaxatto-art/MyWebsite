# Student Portal System

A modern, professional student management system built with PHP, MySQL, and modern web technologies.

## Features

### 🎯 Core Features
- **Role-based Access Control**: Manager Admin and Student Admin roles
- **Student Management**: Add, view, and manage student information
- **Course Management**: Create and manage courses and assessments
- **Marks Management**: Record and track student performance
- **Modern UI**: Responsive design with beautiful interface
- **Security**: CSRF protection, input validation, and secure authentication

### 🔐 User Roles
- **Manager Admin**: Full access to all features (add/edit/delete)
- **Student Admin**: View-only access to students, courses, and marks

### 🎨 Modern Design
- Responsive design that works on all devices
- Beautiful gradient backgrounds and modern UI components
- Interactive elements with smooth animations
- Professional color scheme and typography
- Font Awesome icons throughout the interface

## Installation

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   # Place the project in your XAMPP htdocs folder
   # Path: C:\xampp\htdocs\taangi
   ```

2. **Database Setup**
   - Start XAMPP and ensure MySQL is running
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema:
     ```sql
     # Run the SQL file: sql/database_schema.sql
     ```

3. **Configuration**
   - Update database settings in `config/config.php` if needed
   - Default settings work with XAMPP default configuration

4. **Access the Application**
   - Open your browser and go to: `http://localhost/taangi`
   - You'll be redirected to the login page

### Default Login Credentials

**Manager Admin:**
- Username: `manager`
- Password: `password`

**Student Admin:**
- Username: `student_admin`
- Password: `password`

## Project Structure

```
taangi/
├── assets/
│   ├── css/
│   │   └── style.css          # Modern CSS framework
│   ├── js/
│   │   └── main.js           # JavaScript functionality
│   └── images/               # Image assets
├── config/
│   └── config.php            # Application configuration
├── includes/
│   ├── header.php            # Common header
│   └── footer.php            # Common footer
├── sql/
│   └── database_schema.sql   # Database structure and sample data
├── index.php                 # Entry point (redirects to login)
├── login.php                 # Modern login page
├── dashboard.php             # Main dashboard with statistics
├── student.php               # Student management
├── course.php                # Course management
├── student_marks.php         # Marks management
├── logout.php                # Secure logout
└── database.php              # Database connection
```

## Features Overview

### Dashboard
- Welcome message with user information
- Statistics cards showing key metrics
- Recent activity tables
- Quick action buttons
- Role-based content display

### Student Management
- View all students in a modern table
- Add new students (Manager Admin only)
- Search and filter functionality
- Student status indicators
- Responsive design

### Course Management
- View all courses
- Add new courses (Manager Admin only)
- Course information with descriptions
- Delete courses (Manager Admin only)

### Marks Management
- View all student marks
- Add new marks (Manager Admin only)
- Visual progress bars
- Grade indicators with color coding
- Assessment details

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Input Validation**: Server-side and client-side validation
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: HTML escaping for all user inputs
- **Session Security**: Secure session handling
- **Role-based Access**: Proper authorization checks

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Custom CSS with CSS Grid and Flexbox
- **Icons**: Font Awesome 6.4.0
- **Fonts**: Inter (Google Fonts)

## Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## Development

### Adding New Features
1. Create new PHP files in the root directory
2. Include `includes/header.php` and `includes/footer.php`
3. Add CSRF protection to forms
4. Use prepared statements for database queries
5. Follow the existing code structure and naming conventions

### Styling Guidelines
- Use CSS custom properties (variables) defined in `:root`
- Follow the existing color scheme
- Use the provided button and form classes
- Maintain responsive design principles

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check if MySQL is running in XAMPP
   - Verify database credentials in `config/config.php`
   - Ensure the database exists

2. **Login Not Working**
   - Check if the database schema was imported correctly
   - Verify the admin accounts exist in the database
   - Check browser console for JavaScript errors

3. **Styling Issues**
   - Clear browser cache
   - Check if CSS file is loading correctly
   - Verify Font Awesome CDN is accessible

## License

This project is open source and available under the MIT License.

## Support

For support or questions, please check the troubleshooting section or create an issue in the project repository.

---

**Built with ❤️ for modern education management**
