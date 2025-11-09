<?php
// dashboard.php — Modern dashboard with role-based content
$page_title = 'Dashboard';
require_once 'includes/header.php';
requireAdmin(); // Use the authentication check function

$role = $_SESSION['role'];
$is_manager = $role === 'manager_admin';


// Get statistics
$stats = [];

// Total students
$result = $conn->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'");
$stats['students'] = $result->fetch_assoc()['count'];

// Total courses
$result = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'Active'");
$stats['courses'] = $result->fetch_assoc()['count'];

// Total assessments
$result = $conn->query("SELECT COUNT(*) as count FROM assessments");
$stats['assessments'] = $result->fetch_assoc()['count'];

// Recent students
$result = $conn->query("SELECT * FROM students ORDER BY created_at DESC LIMIT 5");
$recent_students = $result->fetch_all(MYSQLI_ASSOC);

// Recent marks
$sql = "SELECT m.obtained_marks, s.full_name as student_name, a.title as assessment_title, m.recorded_at
        FROM marks m
        JOIN students s ON m.student_id = s.id
        JOIN assessments a ON m.assessment_id = a.id
        ORDER BY m.recorded_at DESC LIMIT 5";
$result = $conn->query($sql);
$recent_marks = $result->fetch_all(MYSQLI_ASSOC);
?>

<div class="container">
    <!-- Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; color: white; font-size: 1.75rem;">
                    <i class="fas fa-tachometer-alt"></i> 
                    <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                </h1>
                <p style="color: rgba(255,255,255,0.9); margin: 0.5rem 0 0 0; font-size: 1rem;">
                    <?= $is_manager ? 'Manager Admin Dashboard' : 'Student Admin Dashboard' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card">
        <h3 style="margin-bottom: 1.5rem; color: var(--primary-color);">
            <i class="fas fa-bars"></i> Navigation Menu
        </h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="dashboard.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-tachometer-alt" style="color: var(--primary-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Dashboard</div>
                    <small style="color: var(--secondary-color);">Overview & Stats</small>
                </div>
            </a>
            
            <a href="student.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-users" style="color: var(--success-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Students</div>
                    <small style="color: var(--secondary-color);">Manage Students</small>
                </div>
            </a>
            
            <a href="course.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-book" style="color: var(--warning-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Courses</div>
                    <small style="color: var(--secondary-color);">Manage Courses</small>
                </div>
            </a>
            
            <a href="assessment.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-clipboard-list" style="color: var(--info-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Assessments</div>
                    <small style="color: var(--secondary-color);">Manage Assessments</small>
                </div>
            </a>
            
            <a href="student_marks.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-chart-line" style="color: var(--danger-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Marks</div>
                    <small style="color: var(--secondary-color);">View & Manage Marks</small>
                </div>
            </a>
            
            <a href="profile.php" class="nav-card" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: var(--text-color); transition: all 0.3s ease;">
                <i class="fas fa-user" style="color: var(--primary-color); font-size: 1.25rem;"></i>
                <div>
                    <div style="font-weight: 600;">Manager Admin</div>
                    <small style="color: var(--secondary-color);">Profile Settings</small>
                </div>
            </a>
        </div>
    
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-users" style="color: var(--primary-color);"></i> Active Students</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?= $stats['students'] ?></p>
            <p>Total enrolled students</p>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-book" style="color: var(--success-color);"></i> Active Courses</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?= $stats['courses'] ?></p>
            <p>Available courses</p>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line" style="color: var(--warning-color);"></i> Assessments</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?= $stats['assessments'] ?></p>
            <p>Total assessments</p>
        </div>
    </div>

    <!-- Recent Activity -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 2rem;">
        <!-- Recent Students -->
        <div class="card">
            <h3><i class="fas fa-user-plus"></i> Recent Students</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Student ID</th>
                        <th>Enrolled</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_students as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['full_name']) ?></td>
                        <td><?= htmlspecialchars($student['student_id']) ?></td>
                        <td><?= date('M j, Y', strtotime($student['enrollment_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="student.php" class="btn btn-primary">View All Students</a>
            </div>
        </div>

        <!-- Recent Marks -->
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Recent Marks</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Assessment</th>
                        <th>Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent_marks as $mark): ?>
                    <tr>
                        <td><?= htmlspecialchars($mark['student_name']) ?></td>
                        <td><?= htmlspecialchars($mark['assessment_title']) ?></td>
                        <td><strong><?= $mark['obtained_marks'] ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="student_marks.php" class="btn btn-primary">View All Marks</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-top: 2rem;">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
            <a href="student.php" class="btn btn-primary">
                <i class="fas fa-users"></i> Manage Students
            </a>
            <a href="course.php" class="btn btn-success">
                <i class="fas fa-book"></i> Manage Courses
            </a>
            <a href="student_marks.php" class="btn btn-warning">
                <i class="fas fa-chart-line"></i> View Marks
            </a>
            <?php if($is_manager): ?>
            <a href="course.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Add Course
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back to Home and Logout Buttons -->
    <div style="text-align: center; margin-top: 2rem;">
        <a href="index.php" class="btn btn-primary" style="padding: 0.75rem 2rem; font-size: 1rem; margin-right: 1rem;">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        <a href="logout.php" class="btn btn-danger" style="padding: 0.75rem 2rem; font-size: 1rem;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    
        </div>
    </div>
    
</div>

<style>
.nav-card:hover {
    background: #e2e8f0 !important;
    border-color: var(--primary-color) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>



<?php require_once 'includes/footer.php'; ?>
