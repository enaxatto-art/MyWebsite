<?php
// student_dashboard.php — Student Dashboard (view own information, grade, marks level, and admin notifications)
$page_title = 'My Information';
require_once 'includes/header.php';
requireStudent(); // Use the authentication check function

// Get student information
$student_id = $_SESSION['student_id'];

// Get student full name and ID
$sql = "SELECT full_name, student_id FROM students WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Get student's overall grade and marks statistics
$grades_sql = "SELECT 
    COALESCE(AVG(CASE 
        WHEN m.grade = 'A' THEN 4.0
        WHEN m.grade = 'B' THEN 3.0
        WHEN m.grade = 'C' THEN 2.0
        WHEN m.grade = 'D' THEN 1.0
        WHEN m.grade = 'F' THEN 0.0
        ELSE NULL
    END), 0) as gpa,
    COUNT(m.id) as total_assessments,
    COALESCE(SUM(m.obtained_marks), 0) as total_marks_obtained,
    COALESCE(SUM(a.total_marks), 0) as total_marks_possible,
    COALESCE(AVG((m.obtained_marks / a.total_marks) * 100), 0) as average_percentage,
    GROUP_CONCAT(DISTINCT se.final_grade SEPARATOR ', ') as course_grades
    FROM students s
    LEFT JOIN marks m ON s.id = m.student_id
    LEFT JOIN assessments a ON m.assessment_id = a.id
    LEFT JOIN student_enrollments se ON s.id = se.student_id
    WHERE s.id = ?
    GROUP BY s.id";
    
$stmt2 = $conn->prepare($grades_sql);
$stmt2->bind_param('i', $student_id);
$stmt2->execute();
$grade_info = $stmt2->get_result()->fetch_assoc();

// Calculate overall grade level
$gpa = floatval($grade_info['gpa']);
$average_percentage = floatval($grade_info['average_percentage']);

// Determine grade level based on GPA and percentage
$grade_level = '';
$grade_color = '#64748b';

if ($gpa >= 3.5 || $average_percentage >= 90) {
    $grade_level = 'Excellent';
    $grade_color = '#10b981'; // Green
} elseif ($gpa >= 3.0 || $average_percentage >= 80) {
    $grade_level = 'Very Good';
    $grade_color = '#3b82f6'; // Blue
} elseif ($gpa >= 2.5 || $average_percentage >= 70) {
    $grade_level = 'Good';
    $grade_color = '#f59e0b'; // Orange
} elseif ($gpa >= 2.0 || $average_percentage >= 60) {
    $grade_level = 'Satisfactory';
    $grade_color = '#f97316'; // Dark Orange
} elseif ($gpa > 0 || $average_percentage > 0) {
    $grade_level = 'Needs Improvement';
    $grade_color = '#ef4444'; // Red
} else {
    $grade_level = 'No Data';
    $grade_color = '#64748b'; // Gray
}

// Get detailed marks breakdown by course
$marks_breakdown_sql = "SELECT 
    c.course_code,
    c.course_name,
    se.final_grade as course_final_grade,
    COUNT(m.id) as assessment_count,
    COALESCE(SUM(m.obtained_marks), 0) as course_total_marks,
    COALESCE(SUM(a.total_marks), 0) as course_total_possible,
    COALESCE(AVG((m.obtained_marks / a.total_marks) * 100), 0) as course_average
    FROM student_enrollments se
    INNER JOIN courses c ON se.course_id = c.id
    LEFT JOIN assessments a ON a.course_id = c.id
    LEFT JOIN marks m ON m.student_id = se.student_id AND m.assessment_id = a.id
    WHERE se.student_id = ?
    GROUP BY c.id, c.course_code, c.course_name, se.final_grade
    ORDER BY c.course_name";
    
$stmt3 = $conn->prepare($marks_breakdown_sql);
$stmt3->bind_param('i', $student_id);
$stmt3->execute();
$marks_breakdown = $stmt3->get_result();

// Get notifications for this student
$notifications_sql = "SELECT n.*, a.full_name as created_by_name,
                     (SELECT COUNT(*) FROM notification_reads nr WHERE nr.notification_id = n.id AND nr.student_id = ?) as is_read
                     FROM notifications n
                     LEFT JOIN admins a ON n.created_by = a.id
                     WHERE (
                         (n.target_audience IN ('All', 'Students') AND (n.expires_at IS NULL OR n.expires_at > NOW()))
                         OR (n.target_audience = 'Specific' AND n.target_student_id = ? AND (n.expires_at IS NULL OR n.expires_at > NOW()))
                     )
                     ORDER BY n.created_at DESC
                     LIMIT 50";
                     
$stmt4 = $conn->prepare($notifications_sql);
$stmt4->bind_param('ii', $student_id, $student_id);
$stmt4->execute();
$notifications = $stmt4->get_result();

// Mark notification as read when viewed
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $notification_id = intval($_GET['read']);
    $check_sql = "SELECT id FROM notification_reads WHERE notification_id = ? AND student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $notification_id, $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows == 0) {
        $insert_sql = "INSERT INTO notification_reads (notification_id, student_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param('ii', $notification_id, $student_id);
        $insert_stmt->execute();
    }
}

?>

<style>
.student-header {
    background: linear-gradient(135deg, #10b981 0%, #000000 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    text-align: center;
}

.student-header h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
}

.student-header p {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 4px solid #667eea;
}

.stat-card.primary { border-left-color: #667eea; }
.stat-card.success { border-left-color: #10b981; }
.stat-card.warning { border-left-color: #f59e0b; }
.stat-card.info { border-left-color: #3b82f6; }

.stat-label {
    font-size: 0.875rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0.5rem 0;
}

.grade-level-card {
    background: linear-gradient(135deg, <?= $grade_color ?> 0%, <?= $grade_color ?>dd 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.grade-level-title {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.grade-level-value {
    font-size: 3rem;
    font-weight: 700;
    margin: 0.5rem 0;
}

.grade-level-details {
    display: flex;
    justify-content: space-around;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.3);
}

.grade-detail {
    text-align: center;
}

.grade-detail-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.grade-detail-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.course-grade-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.course-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.course-grade-badge {
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-weight: 700;
    font-size: 1rem;
}

.course-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.course-stat {
    text-align: center;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 8px;
}

.course-stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 0.25rem;
}

.course-stat-label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
}

.notification-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left: 4px solid #667eea;
    transition: all 0.3s ease;
}

.notification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.notification-card.unread {
    border-left-color: #f59e0b;
    background: #fffbeb;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
}

.notification-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.notification-date {
    font-size: 0.875rem;
    color: #64748b;
}

.notification-message {
    color: #475569;
    line-height: 1.6;
    margin-bottom: 0.75rem;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
    color: #64748b;
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-info { background: #dbeafe; color: #1e40af; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-reminder { background: #fce7f3; color: #9f1239; }

.section-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin: 2rem 0 1rem 0;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
}

.no-data {
    text-align: center;
    padding: 3rem;
    color: #64748b;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.no-data i {
    font-size: 4rem;
    opacity: 0.3;
    margin-bottom: 1rem;
}

.action-buttons {
    text-align: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.action-buttons .btn {
    margin: 0 0.5rem;
    padding: 0.75rem 2rem;
}
</style>

<div class="container">
    <!-- Student Name and ID Header -->
    <div class="student-header">
        <h1><i class="fas fa-user-circle"></i> <?= htmlspecialchars($student['full_name']) ?></h1>
        <p>Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
    </div>

    <!-- Grade Level Card -->
    <div class="grade-level-card">
        <div class="grade-level-title">Academic Performance Level</div>
        <div class="grade-level-value"><?= htmlspecialchars($grade_level) ?></div>
        <div class="grade-level-details">
            <div class="grade-detail">
                <div class="grade-detail-value"><?= number_format($gpa, 2) ?></div>
                <div class="grade-detail-label">GPA</div>
            </div>
            <div class="grade-detail">
                <div class="grade-detail-value"><?= number_format($average_percentage, 1) ?>%</div>
                <div class="grade-detail-label">Average</div>
            </div>
            <div class="grade-detail">
                <div class="grade-detail-value"><?= $grade_info['total_assessments'] ?></div>
                <div class="grade-detail-label">Assessments</div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-label">Total Marks</div>
            <div class="stat-value"><?= number_format($grade_info['total_marks_obtained'], 0) ?></div>
            <div style="color: #64748b; font-size: 0.875rem;">
                of <?= number_format($grade_info['total_marks_possible'], 0) ?> possible
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-label">Average Percentage</div>
            <div class="stat-value" style="color: #10b981;"><?= number_format($average_percentage, 1) ?>%</div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-label">GPA</div>
            <div class="stat-value" style="color: #3b82f6;"><?= number_format($gpa, 2) ?></div>
            <div style="color: #64748b; font-size: 0.875rem;">on 4.0 scale</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-label">Total Assessments</div>
            <div class="stat-value" style="color: #f59e0b;"><?= $grade_info['total_assessments'] ?></div>
        </div>
    </div>

    <!-- Course Grades and Marks Breakdown -->
    <?php if($marks_breakdown->num_rows > 0): ?>
    <h2 class="section-title">
        <i class="fas fa-book"></i> Course Grades & Marks
    </h2>
    
    <div>
        <?php while($course = $marks_breakdown->fetch_assoc()): 
            $course_percentage = $course['course_total_possible'] > 0 
                ? ($course['course_total_marks'] / $course['course_total_possible']) * 100 
                : 0;
            
            // Determine badge color for course grade
            $badge_class = 'badge-info';
            if ($course['course_final_grade']) {
                if ($course['course_final_grade'] === 'A') $badge_class = 'badge-success';
                elseif ($course['course_final_grade'] === 'B') $badge_class = 'badge-info';
                elseif ($course['course_final_grade'] === 'C') $badge_class = 'badge-warning';
                else $badge_class = 'badge-danger';
            }
        ?>
        <div class="course-grade-card">
            <div class="course-header">
                <div>
                    <h3 class="course-name">
                        <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                    </h3>
                </div>
                <?php if($course['course_final_grade']): ?>
                <span class="course-grade-badge <?= $badge_class ?>">
                    Grade: <?= htmlspecialchars($course['course_final_grade']) ?>
                </span>
                <?php else: ?>
                <span class="course-grade-badge badge-info">
                    No Grade Yet
                </span>
                <?php endif; ?>
            </div>
            
            <div class="course-stats">
                <div class="course-stat">
                    <div class="course-stat-value"><?= number_format($course_percentage, 1) ?>%</div>
                    <div class="course-stat-label">Average</div>
                </div>
                <div class="course-stat">
                    <div class="course-stat-value"><?= number_format($course['course_total_marks'], 0) ?></div>
                    <div class="course-stat-label">Marks Obtained</div>
                </div>
                <div class="course-stat">
                    <div class="course-stat-value"><?= number_format($course['course_total_possible'], 0) ?></div>
                    <div class="course-stat-label">Total Possible</div>
                </div>
                <div class="course-stat">
                    <div class="course-stat-value"><?= $course['assessment_count'] ?></div>
                    <div class="course-stat-label">Assessments</div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <!-- Notifications Section -->
    <h2 class="section-title">
        <i class="fas fa-bell"></i> Notifications from Administration
    </h2>
    
    <?php if($notifications->num_rows > 0): ?>
        <div>
            <?php while($notification = $notifications->fetch_assoc()): 
                $is_unread = !$notification['is_read'];
                $badge_class = 'badge-' . strtolower($notification['type']);
                if ($badge_class === 'badge-important') $badge_class = 'badge-danger';
                if ($badge_class === 'badge-info') $badge_class = 'badge-info';
            ?>
            <div class="notification-card <?= $is_unread ? 'unread' : '' ?>" id="notif-<?= $notification['id'] ?>">
                <div class="notification-header">
                    <div>
                        <h3 class="notification-title"><?= htmlspecialchars($notification['title']) ?></h3>
                        <div class="notification-date">
                            <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                            <?php if($notification['created_by_name']): ?>
                                <span style="margin-left: 1rem;">by <?= htmlspecialchars($notification['created_by_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($notification['type']) ?></span>
                </div>
                <div class="notification-message">
                    <?= nl2br(htmlspecialchars($notification['message'])) ?>
                </div>
                <div class="notification-meta">
                    <div>
                        <?php if($notification['expires_at']): ?>
                            <small><i class="fas fa-calendar-times"></i> Expires: <?= date('M j, Y', strtotime($notification['expires_at'])) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if($is_unread): ?>
                        <small style="color: #f59e0b; font-weight: 600;">
                            <i class="fas fa-circle"></i> New
                        </small>
                    <?php endif; ?>
                </div>
                <?php if($is_unread): ?>
                <script>
                // Mark as read when viewed
                (function() {
                    setTimeout(function() {
                        fetch('student_dashboard.php?read=<?= $notification['id'] ?>', {method: 'GET'});
                    }, 1000);
                })();
                </script>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-data">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications from administration at this time.</p>
        </div>
    <?php endif; ?>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-home"></i> Back to Home
        </a>
        <a href="logout.php?type=student" class="btn btn-secondary">
            <i class="fas fa-sign-out-alt"></i> exit
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
