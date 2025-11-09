<?php
// student_students.php — Student profile page (view own profile only)
$page_title = 'My Profile';
require_once 'includes/header.php';

// Check if student is logged in
if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get student's profile information
$profile_sql = "SELECT s.*, se.enrollment_date, se.status as enrollment_status,
                COUNT(DISTINCT se.course_id) as total_courses,
                COUNT(DISTINCT CASE WHEN se.status = 'Active' THEN se.course_id END) as active_courses,
                AVG(se.final_grade) as average_grade
                FROM students s
                LEFT JOIN student_enrollments se ON s.id = se.student_id
                WHERE s.id = ?
                GROUP BY s.id";
                
$stmt = $conn->prepare($profile_sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get recent academic activity
$activity_sql = "SELECT 'Assessment' as type, a.title as title, m.obtained_marks, m.grade, m.recorded_at as date
                 FROM assessments a
                 INNER JOIN courses c ON a.course_id = c.id
                 INNER JOIN student_enrollments se ON c.id = se.course_id
                 LEFT JOIN marks m ON a.id = m.assessment_id AND m.student_id = ?
                 WHERE se.student_id = ? AND m.id IS NOT NULL
                 
                 UNION ALL
                 
                 SELECT 'Enrollment' as type, c.course_name as title, NULL as obtained_marks, NULL as grade, se.enrollment_date as date
                 FROM student_enrollments se
                 INNER JOIN courses c ON se.course_id = c.id
                 WHERE se.student_id = ?
                 
                 ORDER BY date DESC
                 LIMIT 10";
                 
$activity_stmt = $conn->prepare($activity_sql);
$activity_stmt->bind_param('iii', $student_id, $student_id, $student_id);
$activity_stmt->execute();
$activities = $activity_stmt->get_result();
?>

<div class="container">
    <div class="card">
        <h1><i class="fas fa-user"></i> My Profile</h1>
        <p style="color: var(--secondary-color); margin-bottom: 2rem;">
            View and manage your student profile information
        </p>
        
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 2rem;">
            <!-- Profile Information -->
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Personal Information</h3>
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Student ID</label>
                        <p style="margin: 0; font-size: 1.1rem; font-weight: 600;"><?= htmlspecialchars($profile['student_id']) ?></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Full Name</label>
                        <p style="margin: 0;"><?= htmlspecialchars($profile['full_name']) ?></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Email</label>
                        <p style="margin: 0;"><?= htmlspecialchars($profile['email']) ?></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Phone</label>
                        <p style="margin: 0;"><?= htmlspecialchars($profile['phone']) ?></p>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Date of Birth</label>
                        <p style="margin: 0;"><?= $profile['date_of_birth'] ? date('M j, Y', strtotime($profile['date_of_birth'])) : 'Not provided' ?></p>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: var(--secondary-color); display: block; margin-bottom: 0.25rem;">Enrollment Status</label>
                        <span class="badge badge-<?= $profile['enrollment_status'] === 'Active' ? 'success' : 'secondary' ?>">
                            <?= htmlspecialchars($profile['enrollment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Academic Statistics -->
            <div>
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Academic Statistics</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <h3 style="margin: 0; font-size: 2rem;"><?= $profile['total_courses'] ?></h3>
                        <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Total Courses</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <h3 style="margin: 0; font-size: 2rem;"><?= $profile['active_courses'] ?></h3>
                        <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Active Courses</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <h3 style="margin: 0; font-size: 2rem;"><?= $profile['average_grade'] ? number_format($profile['average_grade'], 1) : 'N/A' ?></h3>
                        <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Average Grade</p>
                    </div>
                    <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                        <h3 style="margin: 0; font-size: 2rem;"><?= $profile['enrollment_date'] ? date('Y', strtotime($profile['enrollment_date'])) : 'N/A' ?></h3>
                        <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Enrolled Year</p>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <h4 style="margin-bottom: 1rem; color: var(--primary-color);">Recent Activity</h4>
                <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid #e2e8f0; max-height: 300px; overflow-y: auto;">
                    <?php if($activities->num_rows > 0): ?>
                        <?php while($activity = $activities->fetch_assoc()): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e2e8f0;">
                            <div>
                                <div style="font-weight: 600;"><?= htmlspecialchars($activity['title']) ?></div>
                                <small style="color: var(--secondary-color);">
                                    <?= $activity['type'] ?>
                                    <?php if($activity['obtained_marks'] !== null): ?>
                                        - Score: <?= $activity['obtained_marks'] ?>
                                        <?php if($activity['grade']): ?>
                                            (<?= $activity['grade'] ?>)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <small style="color: var(--secondary-color);">
                                <?= date('M j', strtotime($activity['date'])) ?>
                            </small>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--secondary-color); padding: 2rem 0;">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-secondary {
    background: #f1f5f9;
    color: #64748b;
}
</style>

<?php require_once 'includes/footer.php'; ?>
