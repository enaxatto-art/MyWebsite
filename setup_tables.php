<?php
// setup_tables.php — Auto-create missing tables for new features
session_start();
require_once __DIR__ . '/database.php';

// Check if user is admin
if(!isset($_SESSION['role'])){
    die('Access denied. Please login as admin first.');
}

$messages = [];
$errors = [];

// Function to check if table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create fee_payments table
if(!tableExists($conn, 'fee_payments')){
    $sql = "CREATE TABLE fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method ENUM('Cash', 'Bank Transfer', 'Credit Card', 'Mobile Money', 'Other') DEFAULT 'Cash',
        payment_reference VARCHAR(100),
        semester VARCHAR(50),
        academic_year VARCHAR(20),
        description TEXT,
        status ENUM('Paid', 'Pending', 'Cancelled') DEFAULT 'Paid',
        recorded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES admins(id),
        INDEX idx_student_id (student_id),
        INDEX idx_payment_date (payment_date)
    )";
    if($conn->query($sql)){
        $messages[] = "✓ Created fee_payments table";
    } else {
        $errors[] = "✗ Error creating fee_payments: " . $conn->error;
    }
} else {
    $messages[] = "✓ fee_payments table already exists";
}

// Create fee_structure table
if(!tableExists($conn, 'fee_structure')){
    $sql = "CREATE TABLE fee_structure (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semester VARCHAR(50) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_semester_year (semester, academic_year)
    )";
    if($conn->query($sql)){
        $messages[] = "✓ Created fee_structure table";
    } else {
        $errors[] = "✗ Error creating fee_structure: " . $conn->error;
    }
} else {
    $messages[] = "✓ fee_structure table already exists";
}

// Create attendance table
if(!tableExists($conn, 'attendance')){
    $sql = "CREATE TABLE attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        status ENUM('Present', 'Absent', 'Late', 'Excused') DEFAULT 'Present',
        remarks TEXT,
        recorded_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (recorded_by) REFERENCES admins(id),
        UNIQUE KEY unique_student_course_date (student_id, course_id, attendance_date),
        INDEX idx_student_id (student_id),
        INDEX idx_course_id (course_id),
        INDEX idx_attendance_date (attendance_date)
    )";
    if($conn->query($sql)){
        $messages[] = "✓ Created attendance table";
    } else {
        $errors[] = "✗ Error creating attendance: " . $conn->error;
    }
} else {
    $messages[] = "✓ attendance table already exists";
}

// Create notifications table
if(!tableExists($conn, 'notifications')){
    $sql = "CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('Info', 'Warning', 'Success', 'Important', 'Reminder') DEFAULT 'Info',
        target_audience ENUM('All', 'Students', 'Admins', 'Specific') DEFAULT 'All',
        target_student_id INT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_by INT NOT NULL,
        expires_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (target_student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES admins(id),
        INDEX idx_target_audience (target_audience),
        INDEX idx_target_student (target_student_id),
        INDEX idx_created_at (created_at)
    )";
    if($conn->query($sql)){
        $messages[] = "✓ Created notifications table";
    } else {
        $errors[] = "✗ Error creating notifications: " . $conn->error;
    }
} else {
    $messages[] = "✓ notifications table already exists";
}

// Create notification_reads table
if(!tableExists($conn, 'notification_reads')){
    $sql = "CREATE TABLE notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        student_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        UNIQUE KEY unique_notification_student (notification_id, student_id)
    )";
    if($conn->query($sql)){
        $messages[] = "✓ Created notification_reads table";
    } else {
        $errors[] = "✗ Error creating notification_reads: " . $conn->error;
    }
} else {
    $messages[] = "✓ notification_reads table already exists";
}

// Add columns to admins table if they don't exist
$admin_columns = $conn->query("SHOW COLUMNS FROM admins LIKE 'phone'");
if($admin_columns->num_rows == 0){
    $sql = "ALTER TABLE admins ADD COLUMN phone VARCHAR(20) NULL AFTER email";
    if($conn->query($sql)){
        $messages[] = "✓ Added phone column to admins table";
    }
}
$admin_columns = $conn->query("SHOW COLUMNS FROM admins LIKE 'location'");
if($admin_columns->num_rows == 0){
    $sql = "ALTER TABLE admins ADD COLUMN location VARCHAR(100) NULL AFTER phone";
    if($conn->query($sql)){
        $messages[] = "✓ Added location column to admins table";
    }
}
$admin_columns = $conn->query("SHOW COLUMNS FROM admins LIKE 'gmail'");
if($admin_columns->num_rows == 0){
    $sql = "ALTER TABLE admins ADD COLUMN gmail VARCHAR(100) NULL AFTER location";
    if($conn->query($sql)){
        $messages[] = "✓ Added gmail column to admins table";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Database Tables</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .setup-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }
        .message {
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 8px;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 1.5rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="setup-card">
        <h1><i class="fas fa-database"></i> Database Setup</h1>
        
        <?php if(!empty($messages)): ?>
            <?php foreach($messages as $msg): ?>
                <div class="message"><?= htmlspecialchars($msg) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if(!empty($errors)): ?>
            <?php foreach($errors as $error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if(empty($errors)): ?>
            <div class="message">
                <strong>✓ Setup Complete!</strong> All required tables have been created successfully.
            </div>
            <a href="dashboard.php" class="btn">
                <i class="fas fa-arrow-left"></i> Go to Dashboard
            </a>
        <?php endif; ?>
    </div>
</body>
</html>

