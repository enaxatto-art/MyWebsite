<?php
// student_marks.php — Modern student marks management page
$page_title = 'Student Marks';
require_once 'includes/header.php';

// Check if user is logged in as admin or student
$is_admin = isset($_SESSION['role']) && in_array($_SESSION['role'],['manager_admin','student_admin']);
$is_student = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'student';
$is_manager = isset($_SESSION['role']) && $_SESSION['role'] === 'manager_admin';

// If not logged in, redirect to appropriate login
if(!$is_admin && !$is_student){
    header('Location: index.php'); exit;
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure required tables/columns exist to avoid runtime insert errors
try {
    // Create marks table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS marks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        assessment_id INT NOT NULL,
        obtained_marks DECIMAL(10,2) NOT NULL DEFAULT 0,
        grade VARCHAR(5) NULL,
        remarks TEXT NULL,
        recorded_by INT NULL,
        recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(student_id), INDEX(assessment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add recorded_by column if missing
    $colRes = $conn->query("SHOW COLUMNS FROM marks LIKE 'recorded_by'");
    if($colRes && $colRes->num_rows === 0){
        $conn->query("ALTER TABLE marks ADD COLUMN recorded_by INT NULL AFTER remarks");
    }
} catch (Throwable $e) {
    // Soft-fail with a clear message in UI
    $error_msg = 'Database setup error: ' . htmlspecialchars($e->getMessage());
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST' && $is_manager){
    // CSRF protection
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'add'){
        $student_id = (int)$_POST['student_id'];
        $assessment_id = (int)$_POST['assessment_id'];
        $obtained_marks = (float)$_POST['obtained_marks'];
        $remarks = trim($_POST['remarks']);
        
        // Get total marks for the assessment
        $sql = "SELECT total_marks FROM assessments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $assessment_id);
        $stmt->execute();
        $assessment = $stmt->get_result()->fetch_assoc();
        
        if($assessment && $obtained_marks <= $assessment['total_marks']){
            $sql = "INSERT INTO marks (student_id, assessment_id, obtained_marks, remarks, recorded_by) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iidsi', $student_id, $assessment_id, $obtained_marks, $remarks, $_SESSION['admin_id']);
            
            if($stmt->execute()){
                $success_msg = 'Marks added successfully!';
            } else {
                $error_msg = 'Error adding marks: ' . htmlspecialchars($conn->error);
            }
        } else {
            if(!$assessment){
                $error_msg = 'Assessment not found or not selected. Please select a course and assessment.';
            } else {
                $error_msg = 'Obtained marks cannot exceed total marks.';
            }
        }
    }
    
    if($action === 'update'){
        $id = (int)$_POST['id'];
        $student_id = (int)$_POST['student_id'];
        $assessment_id = (int)$_POST['assessment_id'];
        $obtained_marks = (float)$_POST['obtained_marks'];
        $remarks = trim($_POST['remarks']);
        
        // Get total marks for the assessment
        $sql = "SELECT total_marks FROM assessments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $assessment_id);
        $stmt->execute();
        $assessment = $stmt->get_result()->fetch_assoc();
        
        if($assessment && $obtained_marks <= $assessment['total_marks']){
            $sql = "UPDATE marks SET student_id = ?, assessment_id = ?, obtained_marks = ?, remarks = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iidsi', $student_id, $assessment_id, $obtained_marks, $remarks, $id);
            
            if($stmt->execute()){
                $success_msg = 'Marks updated successfully!';
            } else {
                $error_msg = 'Error updating marks.';
            }
        } else {
            $error_msg = 'Obtained marks cannot exceed total marks.';
        }
    }
    
    if($action === 'delete'){
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM marks WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if($stmt->execute()){
            $success_msg = 'Marks deleted successfully!';
        } else {
            $error_msg = 'Error deleting marks.';
        }
    }
    
    // Course management actions
    if($action === 'add_course'){
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $description = trim($_POST['description']);
        $credits = (int)$_POST['credits'];
        
        // Check if course code already exists
        $check_sql = "SELECT id FROM courses WHERE course_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $course_code);
        $check_stmt->execute();
        $existing_course = $check_stmt->get_result()->fetch_assoc();
        
        if($existing_course) {
            $error_msg = 'Course code "' . htmlspecialchars($course_code) . '" already exists. Please use a different course code.';
        } else {
            $sql = "INSERT INTO courses (course_name, course_code, description, credits) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssi', $course_name, $course_code, $description, $credits);
            
            if($stmt->execute()){
                $success_msg = 'Course added successfully!';
            } else {
                $error_msg = 'Error adding course.';
            }
        }
    }
    
    if($action === 'update_course'){
        $id = (int)$_POST['id'];
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $description = trim($_POST['description']);
        $credits = (int)$_POST['credits'];
        $status = $_POST['status'];
        
        // Check if course code already exists (excluding current course)
        $check_sql = "SELECT id FROM courses WHERE course_code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('si', $course_code, $id);
        $check_stmt->execute();
        $existing_course = $check_stmt->get_result()->fetch_assoc();
        
        if($existing_course) {
            $error_msg = 'Course code "' . htmlspecialchars($course_code) . '" already exists. Please use a different course code.';
        } else {
            $sql = "UPDATE courses SET course_name = ?, course_code = ?, description = ?, credits = ?, status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssisi', $course_name, $course_code, $description, $credits, $status, $id);
            
            if($stmt->execute()){
                $success_msg = 'Course updated successfully!';
            } else {
                $error_msg = 'Error updating course.';
            }
        }
    }
    
    if($action === 'delete_course'){
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM courses WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if($stmt->execute()){
            $success_msg = 'Course deleted successfully!';
        } else {
            $error_msg = 'Error deleting course.';
        }
    }
}

// Fetch student marks with join
if ($is_student && !$is_admin) {
    $sql = "SELECT m.id, s.full_name AS student_name, s.student_id, c.course_name, a.title AS assessment,
                   a.total_marks, m.obtained_marks, m.grade, m.remarks, m.recorded_at
            FROM marks m
            JOIN students s ON m.student_id = s.id
            JOIN assessments a ON m.assessment_id = a.id
            JOIN courses c ON a.course_id = c.id
            WHERE m.student_id = ?
            ORDER BY m.recorded_at DESC";
    $stmtMarks = $conn->prepare($sql);
    if(!$stmtMarks){ die("Database error: " . $conn->error); }
    $stmtMarks->bind_param('i', $_SESSION['student_id']);
    $stmtMarks->execute();
    $result = $stmtMarks->get_result();
} else {
    $sql = "SELECT m.id, s.full_name AS student_name, s.student_id, c.course_name, a.title AS assessment, 
                   a.total_marks, m.obtained_marks, m.grade, m.remarks, m.recorded_at
            FROM marks m
            JOIN students s ON m.student_id=s.id
            JOIN assessments a ON m.assessment_id=a.id
            JOIN courses c ON a.course_id=c.id
            ORDER BY m.recorded_at DESC";
    $result = $conn->query($sql);
    if (!$result) { die("Database error: " . $conn->error); }
}

if (!$is_student) {
    // Get students and assessments for form
    $students_result = $conn->query("SELECT id, student_id, full_name FROM students ORDER BY full_name");
    if (!$students_result) {
        die("Database error: " . $conn->error);
    }
    // Separate list for directory rendering so we don't advance the above result pointer
    $all_students_list_res = $conn->query("SELECT id, student_id, full_name FROM students WHERE status='Active' ORDER BY full_name");
    $all_students_list = $all_students_list_res ? $all_students_list_res->fetch_all(MYSQLI_ASSOC) : [];

    $assessments_result = $conn->query("SELECT a.id, a.title, a.total_marks, a.course_id, c.course_name 
                                       FROM assessments a 
                                       JOIN courses c ON a.course_id = c.id 
                                       ORDER BY c.course_name, a.title");
    if (!$assessments_result) {
        die("Database error: " . $conn->error);
    }

    // Prepare assessments for JS filtering
    $assessments_js = [];
    $assessments_js_q = $conn->query("SELECT id, title, total_marks, course_id FROM assessments ORDER BY title");
    if ($assessments_js_q) {
        while($row = $assessments_js_q->fetch_assoc()) { $assessments_js[] = $row; }
    }

    // Get courses for course management
    $courses_result = $conn->query("SELECT * FROM courses ORDER BY course_name");
    // Active courses for Add Marks form
    $active_courses_result = $conn->query("SELECT id, course_name FROM courses WHERE status = 'Active' ORDER BY course_name");
    if (!$courses_result) {
        die("Database error: " . $conn->error);
    }

    // Get total assessment count for validation
    $assessment_count_result = $conn->query("SELECT COUNT(*) as count FROM assessments");
    if (!$assessment_count_result) {
        die("Database error: " . $conn->error);
    }
    $assessment_count = $assessment_count_result->fetch_assoc()['count'];
}

if (!$is_student) {
    // Get comprehensive statistics
    $stats_sql = "SELECT 
        COUNT(DISTINCT m.id) as total_marks_records,
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT c.id) as total_courses,
        SUM(m.obtained_marks) as total_marks_obtained,
        AVG(m.obtained_marks) as avg_marks,
        COUNT(DISTINCT CASE WHEN s.status = 'Active' THEN s.id END) as active_students
        FROM students s
        LEFT JOIN marks m ON s.id = m.student_id
        LEFT JOIN assessments a ON m.assessment_id = a.id
        LEFT JOIN courses c ON a.course_id = c.id";
    $stats_result = $conn->query($stats_sql);
    if (!$stats_result) {
        die("Database error: " . $conn->error);
    }
    $stats = $stats_result->fetch_assoc();

    // Get detailed student statistics with their total marks
    $student_stats_sql = "SELECT 
        s.id,
        s.student_id,
        s.full_name,
        s.status,
        COUNT(m.id) as total_assessments,
        COALESCE(SUM(m.obtained_marks), 0) as total_marks_obtained,
        COALESCE(AVG(m.obtained_marks), 0) as avg_marks,
        COALESCE(MAX(m.recorded_at), NULL) as last_assessment_date
        FROM students s
        LEFT JOIN marks m ON s.id = m.student_id
        GROUP BY s.id, s.student_id, s.full_name, s.status
        ORDER BY total_marks_obtained DESC, s.full_name";
    $student_stats_result = $conn->query($student_stats_sql);
    if (!$student_stats_result) {
        die("Database error: " . $conn->error);
    }
}
?>

<div class="container">
    <!-- Header Section with Back Button -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="dashboard.php" class="btn btn-light" style="color: #667eea; background: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 style="margin: 0; color: white;">
                    <i class="fas fa-chart-line"></i> Student Marks Management
                </h1>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <?php if($is_manager): ?>
                <button onclick="showAddCourseForm()" class="btn btn-light" style="color: #667eea; background: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fas fa-plus"></i> Add Course
                </button>
                <button onclick="showAddForm()" class="btn btn-light" style="color: #667eea; background: white; border: none; padding: 0.75rem 1rem; border-radius: 8px; font-weight: 600; transition: all 0.3s ease;">
                    <i class="fas fa-plus"></i> Add Marks
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- All Registered Students Directory -->
    <?php if($is_admin && !empty($all_students_list)): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h3 style="margin-bottom: 0.75rem;"><i class="fas fa-users"></i> All Registered Students</h3>
        <div style="display:flex; gap:0.75rem; margin-bottom:0.75rem;">
            <input type="text" id="marksStudentSearch" class="form-input" placeholder="Search by name or student ID..." style="max-width: 360px;">
        </div>
        <div id="marksStudentsDirectory" style="max-height: 360px; overflow-y: auto; border:1px solid var(--border-color); border-radius:8px;">
            <?php foreach($all_students_list as $s): ?>
            <div class="student-row" data-name="<?= htmlspecialchars(strtolower($s['full_name'])) ?>" data-code="<?= htmlspecialchars(strtolower($s['student_id'])) ?>" style="display:flex; justify-content:space-between; align-items:center; padding:0.75rem 1rem; border-bottom:1px solid var(--border-color);">
                <div>
                    <strong><?= htmlspecialchars($s['full_name']) ?></strong><br>
                    <small style="color: var(--secondary-color);">ID: <?= htmlspecialchars($s['student_id']) ?></small>
                </div>
                <?php if($is_manager): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="prefillAddMarks(<?= (int)$s['id'] ?>)"><i class="fas fa-plus"></i> Add Marks</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Card -->
    <div class="card">
        
        <?php if($is_student): ?>
        <!-- Student View - Show only their own data -->
        <div style="text-align: center; padding: 2rem;">
            <h2 style="color: #1e293b; margin-bottom: 1rem;">
                <i class="fas fa-user-graduate"></i> Welcome, <?= htmlspecialchars($_SESSION['student_name']) ?>!
            </h2>
            <p style="color: #64748b; margin-bottom: 2rem;">Here are your academic records and marks.</p>
            
            <!-- Student's Personal Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <?php
                // Get student's personal statistics
                $student_id = $_SESSION['student_id'];
                $student_stats_sql = "SELECT 
                    COUNT(m.id) as total_assessments,
                    COALESCE(SUM(m.obtained_marks), 0) as total_marks_obtained,
                    COALESCE(AVG(m.obtained_marks), 0) as avg_marks
                    FROM students s
                    LEFT JOIN marks m ON s.id = m.student_id
                    WHERE s.id = ?";
                $student_stmt = $conn->prepare($student_stats_sql);
                $student_stmt->bind_param('i', $student_id);
                $student_stmt->execute();
                $student_stats = $student_stmt->get_result()->fetch_assoc();
                ?>
                <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
                    <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?= number_format($student_stats['total_marks_obtained'], 0) ?>
                    </div>
                    <div style="opacity: 0.9;">Total Marks</div>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 12px;">
                    <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?= number_format($student_stats['avg_marks'], 1) ?>
                    </div>
                    <div style="opacity: 0.9;">Average Score</div>
                </div>
                <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 12px;">
                    <div style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                        <?= $student_stats['total_assessments'] ?>
                    </div>
                    <div style="opacity: 0.9;">Assessments</div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Admin View - Show system overview -->
        <div style="background: #f8fafc; padding: 2rem; border-radius: 16px; margin-bottom: 2rem; border: 1px solid #e2e8f0;">
            <h3 style="margin: 0 0 1.5rem 0; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-pie"></i> System Overview
            </h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; border-left: 4px solid #667eea; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: #667eea; margin-bottom: 0.5rem;">
                        <?= number_format($stats['total_marks_obtained'] ?: 0) ?>
                    </div>
                    <div style="color: #64748b; font-weight: 600;">Total Marks</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; border-left: 4px solid #f093fb; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: #f093fb; margin-bottom: 0.5rem;">
                        <?= $stats['total_students'] ?: 0 ?>
                    </div>
                    <div style="color: #64748b; font-weight: 600;">Students</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; border-left: 4px solid #4facfe; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: #4facfe; margin-bottom: 0.5rem;">
                        <?= $stats['total_courses'] ?: 0 ?>
                    </div>
                    <div style="color: #64748b; font-weight: 600;">Courses</div>
                </div>
                <div style="text-align: center; padding: 1rem; background: white; border-radius: 12px; border-left: 4px solid #43e97b; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <div style="font-size: 2.5rem; font-weight: 700; color: #43e97b; margin-bottom: 0.5rem;">
                        <?= number_format($stats['avg_marks'] ?: 0, 1) ?>
                    </div>
                    <div style="color: #64748b; font-weight: 600;">Avg Score</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($is_admin): ?>
        <!-- Two Column Layout - Admin Only -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <!-- Left Column: Search and Filters -->
            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 1.5rem 0; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-search"></i> Search & Filter
                </h3>
                <form method="GET" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Search Marks</label>
                        <input type="text" name="search" class="form-input" placeholder="Search by student name, course, or assessment..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Filter by Course</label>
                        <select name="course" class="form-input">
                            <option value="">All Courses</option>
                            <?php 
                            $courses_result->data_seek(0); // Reset result pointer
                            while($course = $courses_result->fetch_assoc()): ?>
                            <option value="<?= $course['id'] ?>" <?= ($_GET['course'] ?? '') == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Filter by Student</label>
                        <select name="student" class="form-input">
                            <option value="">All Students</option>
                            <?php 
                            $students_result->data_seek(0); // Reset result pointer
                            while($student = $students_result->fetch_assoc()): ?>
                            <option value="<?= $student['id'] ?>" <?= ($_GET['student'] ?? '') == $student['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['full_name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="student_marks.php" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Right Column: Performance Metrics -->
            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 1.5rem 0; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-chart-bar"></i> Performance Metrics
                </h3>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="background: white; padding: 1.25rem; border-radius: 12px; border-left: 4px solid #10b981; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="font-size: 2rem; font-weight: 700; color: #10b981; margin-bottom: 0.25rem;"><?= $stats['active_students'] ?: 0 ?></div>
                        <div style="color: #64748b; font-weight: 600;">Active Students</div>
                    </div>
                    <div style="background: white; padding: 1.25rem; border-radius: 12px; border-left: 4px solid #3b82f6; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="font-size: 2rem; font-weight: 700; color: #3b82f6; margin-bottom: 0.25rem;"><?= $stats['total_marks_records'] ?: 0 ?></div>
                        <div style="color: #64748b; font-weight: 600;">Assessment Records</div>
                    </div>
                    <div style="background: white; padding: 1.25rem; border-radius: 12px; border-left: 4px solid #f59e0b; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="font-size: 2rem; font-weight: 700; color: #f59e0b; margin-bottom: 0.25rem;">
                            <?= $stats['total_students'] > 0 ? number_format(($stats['total_marks_records'] / $stats['total_students']), 1) : 0 ?>
                        </div>
                        <div style="color: #64748b; font-weight: 600;">Avg Assessments per Student</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($is_admin): ?>
        <!-- All Students with Total Marks Section - Admin Only -->
        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1rem 0; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-graduation-cap"></i> All Students - Total Marks Summary
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <?php 
                $student_stats_result->data_seek(0); // Reset result pointer
                while($student_stat = $student_stats_result->fetch_assoc()): 
                    $status_color = $student_stat['status'] === 'Active' ? '#10b981' : '#f59e0b';
                    $status_bg = $student_stat['status'] === 'Active' ? '#dcfce7' : '#fef3c7';
                ?>
                <div style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; transition: all 0.3s ease; cursor: pointer;" 
                     onclick="filterByStudent(<?= $student_stat['id'] ?>)">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1e293b; font-size: 1.125rem;">
                                <?= htmlspecialchars($student_stat['full_name']) ?>
                            </h4>
                            <p style="margin: 0; color: #64748b; font-size: 0.875rem;">
                                ID: <?= htmlspecialchars($student_stat['student_id']) ?>
                            </p>
                        </div>
                        <span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: <?= $status_bg ?>; color: <?= $status_color ?>;">
                            <?= htmlspecialchars($student_stat['status']) ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div style="text-align: center; padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #667eea;">
                                <?= number_format($student_stat['total_marks_obtained'], 0) ?>
                            </div>
                            <div style="color: #64748b; font-size: 0.75rem;">Total Marks</div>
                        </div>
                        <div style="text-align: center; padding: 0.75rem; background: #f8fafc; border-radius: 8px;">
                            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                                <?= number_format($student_stat['avg_marks'], 1) ?>
                            </div>
                            <div style="color: #64748b; font-size: 0.75rem;">Avg Score</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; color: #64748b;">
                        <span>
                            <i class="fas fa-clipboard-list"></i> 
                            <?= $student_stat['total_assessments'] ?> assessment<?= $student_stat['total_assessments'] !== 1 ? 's' : '' ?>
                        </span>
                        <?php if($student_stat['last_assessment_date']): ?>
                        <span>
                            <i class="fas fa-calendar"></i> 
                            <?= date('M j, Y', strtotime($student_stat['last_assessment_date'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Progress bar for visual representation -->
                    <?php if($student_stat['total_assessments'] > 0): ?>
                    <div style="margin-top: 1rem;">
                        <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div style="width: <?= min(100, ($student_stat['avg_marks'] / 100) * 100) ?>%; height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 3px;"></div>
                        </div>
                        <div style="text-align: center; margin-top: 0.5rem; font-size: 0.75rem; color: #64748b;">
                            Performance: <?= number_format(($student_stat['avg_marks'] / 100) * 100, 1) ?>%
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        
        <?php if($is_student): ?>
        <!-- Student's Personal Marks Table -->
        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem;">
            <h3 style="margin: 0 0 1.5rem 0; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-line"></i> Your Academic Records
            </h3>
            <?php
            // Get student's personal marks
            $student_id = $_SESSION['student_id'];
            $student_marks_sql = "SELECT m.id, c.course_name, a.title AS assessment, 
                                   a.total_marks, m.obtained_marks, m.grade, m.remarks, m.recorded_at
                            FROM marks m
                            JOIN assessments a ON m.assessment_id=a.id
                            JOIN courses c ON a.course_id=c.id
                            WHERE m.student_id = ?
                            ORDER BY m.recorded_at DESC";
            $student_marks_stmt = $conn->prepare($student_marks_sql);
            $student_marks_stmt->bind_param('i', $student_id);
            $student_marks_stmt->execute();
            $student_marks_result = $student_marks_stmt->get_result();
            ?>
            
            <?php if($student_marks_result->num_rows > 0): ?>
            <table class="table data-table">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Assessment</th>
                        <th>Marks</th>
                        <th>Grade</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $student_marks_result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['course_name']) ?></td>
                        <td><?= htmlspecialchars($row['assessment']) ?></td>
                        <td>
                            <strong><?= $row['obtained_marks'] ?></strong> / <?= $row['total_marks'] ?>
                            <div style="width: 100px; height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 4px;">
                                <div style="width: <?= ($row['obtained_marks'] / $row['total_marks']) * 100 ?>%; height: 100%; background: var(--primary-color); border-radius: 2px;"></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= $row['grade'] === 'A' ? 'success' : ($row['grade'] === 'B' ? 'primary' : ($row['grade'] === 'C' ? 'warning' : 'danger')) ?>">
                                <?= $row['grade'] ?: 'N/A' ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($row['recorded_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #64748b;">
                <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <h4 style="margin: 0 0 0.5rem 0;">No Records Found</h4>
                <p style="margin: 0;">You don't have any marks recorded yet. Contact your instructor for more information.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Admin View - All Marks Table -->
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Assessment</th>
                    <th>Marks</th>
                    <th>Grade</th>
                    <th>Date</th>
                    <?php if($is_manager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($row['student_name']) ?></strong><br>
                            <small style="color: var(--secondary-color);"><?= htmlspecialchars($row['student_id']) ?></small>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($row['course_name']) ?></td>
                    <td><?= htmlspecialchars($row['assessment']) ?></td>
                    <td>
                        <strong><?= $row['obtained_marks'] ?></strong> / <?= $row['total_marks'] ?>
                        <div style="width: 100px; height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 4px;">
                            <div style="width: <?= ($row['obtained_marks'] / $row['total_marks']) * 100 ?>%; height: 100%; background: var(--primary-color); border-radius: 2px;"></div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?= $row['grade'] === 'A' ? 'success' : ($row['grade'] === 'B' ? 'primary' : ($row['grade'] === 'C' ? 'warning' : 'danger')) ?>">
                            <?= $row['grade'] ?: 'N/A' ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y', strtotime($row['recorded_at'])) ?></td>
                    <?php if($is_manager): ?>
                    <td>
                        <button onclick="editMarks(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn btn-secondary btn-sm">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteMarks(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if($is_admin): ?>
<!-- Courses Management Section - Admin Only -->
<div class="container" style="margin-top: 2rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2><i class="fas fa-book"></i> Courses Management</h2>
            <?php if($is_manager): ?>
            <button onclick="showAddCourseForm()" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Course
            </button>
            <?php endif; ?>
        </div>
        
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Description</th>
                    <th>Credits</th>
                    <th>Status</th>
                    <th>Assessments</th>
                    <?php if($is_manager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $courses_result->data_seek(0); // Reset result pointer
                while($course = $courses_result->fetch_assoc()): 
                    // Get assessment count for this course
                    $assessment_count_sql = "SELECT COUNT(*) as count FROM assessments WHERE course_id = ?";
                    $assessment_stmt = $conn->prepare($assessment_count_sql);
                    $assessment_stmt->bind_param('i', $course['id']);
                    $assessment_stmt->execute();
                    $assessment_count = $assessment_stmt->get_result()->fetch_assoc()['count'];
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($course['course_code']) ?></strong></td>
                    <td><?= htmlspecialchars($course['course_name']) ?></td>
                    <td><?= htmlspecialchars($course['description']) ?></td>
                    <td><?= $course['credits'] ?></td>
                    <td>
                        <span class="badge badge-<?= $course['status'] === 'Active' ? 'success' : 'secondary' ?>">
                            <?= $course['status'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-primary"><?= $assessment_count ?> assessment<?= $assessment_count !== 1 ? 's' : '' ?></span>
                    </td>
                    <?php if($is_manager): ?>
                    <td>
                        <div style="display: flex; gap: 0.25rem;">
                            <button onclick="editCourse(<?= htmlspecialchars(json_encode($course)) ?>)" class="btn btn-secondary btn-sm" title="Edit Course">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteCourse(<?= $course['id'] ?>, '<?= htmlspecialchars($course['course_name']) ?>')" class="btn btn-danger btn-sm" title="Delete Course">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if($is_manager): ?>
<!-- Add Marks Modal -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Add Student Marks</h3>
        <?php if($assessment_count == 0): ?>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 0.75rem; border-radius: 8px; margin: 0.75rem 0 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <span>To add marks, first add a course and create assessments.</span>
            <div style="display: flex; gap: 0.5rem;">
                <a href="course.php" class="btn btn-primary" style="text-decoration: none;">Add Course</a>
                <a href="assessment.php?show_add=1" class="btn btn-secondary" style="text-decoration: none;">Add Assessment</a>
            </div>
        </div>
        <?php endif; ?>
        
        
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-input" required>
                    <option value="">Select Student</option>
                    <?php $students_result->data_seek(0); while($student = $students_result->fetch_assoc()): ?>
                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</option>
                    <?php endwhile; ?>
                </select>
                <div style="margin-top:0.5rem;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleBrowseStudents()">Browse All Students</button>
                </div>
                <div id="browseStudentsPanel" style="display:none; margin-top:0.75rem; border:1px solid var(--border-color); border-radius:8px;">
                    <div style="padding:0.5rem; border-bottom:1px solid var(--border-color); display:flex; gap:0.5rem; align-items:center;">
                        <input type="text" id="marksBrowseStudentSearch" class="form-input" placeholder="Search students..." style="flex:1;">
                        <button type="button" class="btn btn-light btn-sm" onclick="toggleBrowseStudents()">Close</button>
                    </div>
                    <div id="marksBrowseStudentsList" style="max-height:220px; overflow-y:auto;">
                        <?php foreach($all_students_list as $s): ?>
                        <div class="browse-row" data-name="<?= htmlspecialchars(strtolower($s['full_name'])) ?>" data-code="<?= htmlspecialchars(strtolower($s['student_id'])) ?>" style="padding:0.5rem 0.75rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color);">
                            <div>
                                <strong><?= htmlspecialchars($s['full_name']) ?></strong><br>
                                <small style="color:var(--secondary-color);">ID: <?= htmlspecialchars($s['student_id']) ?></small>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="chooseBrowseStudent(<?= (int)$s['id'] ?>)">Select</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Course</label>
                <select id="addCourseId" class="form-input" required>
                    <option value="">Select Course</option>
                    <?php while($course = $active_courses_result->fetch_assoc()): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                    <a href="course.php" class="btn btn-secondary btn-sm" style="text-decoration: none;">Add Course</a>
                    <button type="button" id="addAssessmentForCourseBtn" class="btn btn-primary btn-sm" disabled>
                        Add Assessment for Course
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Assessment</label>
                <select name="assessment_id" id="addAssessmentId" class="form-input" required disabled>
                    <option value="">Select Assessment</option>
                </select>
                <div id="noAssessMsg" style="display:none; color: var(--secondary-color); font-size: 0.9rem; margin-top: 0.5rem;">
                    No assessments for the selected course.
                </div>
                <div style="margin-top: 0.5rem;">
                    <button type="button" id="addAssessmentForCourseBtn2" class="btn btn-secondary btn-sm" disabled>
                        Add Assessment for Course
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Obtained Marks</label>
                <input type="number" name="obtained_marks" id="addObtainedMarks" class="form-input" step="0.01" min="0" required>
                <small id="maxMarks" style="color: var(--secondary-color);"></small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-input" rows="3" placeholder="Optional remarks"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideAddForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Marks</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Marks Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Edit Student Marks</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            
            <div class="form-group">
                <label class="form-label">Student</label>
                <select name="student_id" id="editStudentId" class="form-input" required>
                    <option value="">Select Student</option>
                    <?php 
                    $students_result->data_seek(0); // Reset result pointer
                    while($student = $students_result->fetch_assoc()): ?>
                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Assessment</label>
                <div style="display:grid; grid-template-columns: 1fr; gap:0.5rem;">
                    <select id="editCourseId" class="form-input" required>
                        <option value="">Select Course</option>
                        <?php 
                        $active_courses_result->data_seek(0);
                        while($course = $active_courses_result->fetch_assoc()): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select name="assessment_id" id="editAssessmentId" class="form-input" required disabled>
                        <option value="">Select Assessment</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Obtained Marks</label>
                <input type="number" name="obtained_marks" id="editObtainedMarks" class="form-input" step="0.01" min="0" required>
                <small id="editMaxMarks" style="color: var(--secondary-color);"></small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" id="editRemarks" class="form-input" rows="3" placeholder="Optional remarks"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideEditForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Marks</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteMarksId">
</form>

<!-- Add Course Modal -->
<div id="addCourseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Add New Course</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add_course">
            
            <div class="form-group">
                <label class="form-label">Course Name</label>
                <input type="text" name="course_name" class="form-input" required placeholder="Enter course name">
            </div>
            
            <div class="form-group">
                <label class="form-label">Course Code</label>
                <input type="text" name="course_code" class="form-input" required placeholder="Enter course code">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" rows="3" placeholder="Enter course description"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Credits</label>
                <input type="number" name="credits" class="form-input" min="1" max="10" required placeholder="Enter credits">
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideAddCourseForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editCourseModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Edit Course</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update_course">
            <input type="hidden" name="id" id="editCourseId">
            
            <div class="form-group">
                <label class="form-label">Course Name</label>
                <input type="text" name="course_name" id="editCourseName" class="form-input" required placeholder="Enter course name">
            </div>
            
            <div class="form-group">
                <label class="form-label">Course Code</label>
                <input type="text" name="course_code" id="editCourseCode" class="form-input" required placeholder="Enter course code">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="editCourseDescription" class="form-input" rows="3" placeholder="Enter course description"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Credits</label>
                <input type="number" name="credits" id="editCourseCredits" class="form-input" min="1" max="10" required placeholder="Enter credits">
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="editCourseStatus" class="form-input" required>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideEditCourseForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Course Form -->
<form id="deleteCourseForm" method="post" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="delete_course">
    <input type="hidden" name="id" id="deleteCourseId">
</form>

<script>
// Data for assessments (server-provided)
const ASSESSMENTS = <?php echo json_encode($assessments_js); ?>;

function showAddForm() {
    document.getElementById('addModal').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addModal').style.display = 'none';
}

// Browse-all-students panel inside Add Marks modal
function toggleBrowseStudents(){
    const panel = document.getElementById('browseStudentsPanel');
    if(!panel) return;
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}

function chooseBrowseStudent(studentId){
    const sel = document.querySelector('#addModal select[name="student_id"]');
    if(sel){ sel.value = String(studentId); }
    toggleBrowseStudents();
}

// Live search within the browse panel
document.addEventListener('input', function(e){
    if(e.target && e.target.id === 'marksBrowseStudentSearch'){
        const q = e.target.value.trim().toLowerCase();
        const list = document.getElementById('marksBrowseStudentsList');
        if(!list) return;
        list.querySelectorAll('.browse-row').forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const code = row.getAttribute('data-code') || '';
            row.style.display = (!q || name.includes(q) || code.includes(q)) ? 'flex' : 'none';
        });
    }
});

// Dependent dropdowns: Course -> Assessment (Add form)
const addCourseSelect = document.getElementById('addCourseId');
const addAssessmentSelect = document.getElementById('addAssessmentId');
const addMarksInput = document.getElementById('addObtainedMarks');
const noAssessMsg = document.getElementById('noAssessMsg');
const addAssessBtn = document.getElementById('addAssessmentForCourseBtn');
const addAssessBtn2 = document.getElementById('addAssessmentForCourseBtn2');
if(addCourseSelect && addAssessmentSelect){
    addCourseSelect.addEventListener('change', function(){
        const courseId = this.value;
        addAssessmentSelect.innerHTML = '<option value="">Select Assessment</option>';
        addAssessmentSelect.disabled = !courseId;
        // Enable both "Add Assessment" buttons when a course is chosen
        if(addAssessBtn){ addAssessBtn.disabled = !courseId; addAssessBtn.dataset.courseId = courseId || ''; }
        if(addAssessBtn2){ addAssessBtn2.disabled = !courseId; addAssessBtn2.dataset.courseId = courseId || ''; }
        // Reset marks constraints
        const maxEl = document.getElementById('maxMarks');
        if(maxEl) maxEl.textContent = '';
        if(addMarksInput) addMarksInput.removeAttribute('max');
        if(!courseId){ if(noAssessMsg) noAssessMsg.style.display = 'none'; return; }
        // Populate assessments for course
        const list = ASSESSMENTS.filter(a => String(a.course_id) === String(courseId));
        list.forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.dataset.total = a.total_marks;
            opt.textContent = `${a.title} (${a.total_marks} marks)`;
            addAssessmentSelect.appendChild(opt);
        });
        // Show/hide "no assessments" message
        if(noAssessMsg){ noAssessMsg.style.display = list.length ? 'none' : 'block'; }
    });
    addAssessmentSelect.addEventListener('change', function(){
        const maxMarks = this.options[this.selectedIndex]?.dataset?.total;
        const maxEl = document.getElementById('maxMarks');
        if(maxMarks){
            if(maxEl) maxEl.textContent = `Maximum marks: ${maxMarks}`;
            if(addMarksInput) addMarksInput.max = maxMarks;
        } else {
            if(maxEl) maxEl.textContent = '';
            if(addMarksInput) addMarksInput.removeAttribute('max');
        }
    });
    // Click handlers for both Add Assessment buttons
    [addAssessBtn, addAssessBtn2].forEach(btn => {
        if(btn){
            btn.addEventListener('click', function(){
                const cid = this.dataset.courseId;
                if(cid){
                    window.location.href = `assessment.php?show_add=1&course_id=${encodeURIComponent(cid)}`;
                }
            });
        }
    });
}

// Dependent dropdowns: Course -> Assessment (Edit form)
const editCourseSelect = document.getElementById('editCourseId');
const editAssessmentSelect = document.getElementById('editAssessmentId');
const editMarksInput = document.getElementById('editObtainedMarks');
if(editCourseSelect && editAssessmentSelect){
    editCourseSelect.addEventListener('change', function(){
        const courseId = this.value;
        editAssessmentSelect.innerHTML = '<option value="">Select Assessment</option>';
        editAssessmentSelect.disabled = !courseId;
        document.getElementById('editMaxMarks').textContent = '';
        if(editMarksInput) editMarksInput.removeAttribute('max');
        if(!courseId) return;
        ASSESSMENTS.filter(a => String(a.course_id) === String(courseId))
            .forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.dataset.total = a.total_marks;
                opt.textContent = `${a.title} (${a.total_marks} marks)`;
                editAssessmentSelect.appendChild(opt);
            });
    });
    editAssessmentSelect.addEventListener('change', function(){
        const maxMarks = this.options[this.selectedIndex]?.dataset?.total;
        const maxEl = document.getElementById('editMaxMarks');
        if(maxMarks){
            maxEl.textContent = `Maximum marks: ${maxMarks}`;
            if(editMarksInput) editMarksInput.max = maxMarks;
        } else {
            maxEl.textContent = '';
            if(editMarksInput) editMarksInput.removeAttribute('max');
        }
    });
}

function editMarks(marksData) {
    document.getElementById('editId').value = marksData.id;
    document.getElementById('editStudentId').value = marksData.student_id;
    // Preselect course and populate assessments
    const selected = ASSESSMENTS.find(a => String(a.id) === String(marksData.assessment_id));
    if(selected){
        editCourseSelect.value = String(selected.course_id);
        editCourseSelect.dispatchEvent(new Event('change'));
        setTimeout(() => {
            editAssessmentSelect.value = String(marksData.assessment_id);
            editAssessmentSelect.dispatchEvent(new Event('change'));
        }, 0);
    }
    document.getElementById('editObtainedMarks').value = marksData.obtained_marks;
    document.getElementById('editRemarks').value = marksData.remarks || '';
    
    // Update max marks display
    const selectedOption = document.querySelector(`#editAssessmentId option[value="${marksData.assessment_id}"]`);
    if(selectedOption) {
        const maxMarks = selectedOption.dataset.total;
        document.getElementById('editMaxMarks').textContent = `Maximum marks: ${maxMarks}`;
        document.getElementById('editObtainedMarks').max = maxMarks;
    }
    
    document.getElementById('editModal').style.display = 'block';
}

function hideEditForm() {
    document.getElementById('editModal').style.display = 'none';
}

function deleteMarks(marksId) {
    if(confirm('Are you sure you want to delete these marks? This action cannot be undone.')) {
        document.getElementById('deleteMarksId').value = marksId;
        document.getElementById('deleteForm').submit();
    }
}

// Course Management Functions
function showAddCourseForm() {
    document.getElementById('addCourseModal').style.display = 'block';
}

function hideAddCourseForm() {
    document.getElementById('addCourseModal').style.display = 'none';
}

function editCourse(courseData) {
    document.getElementById('editCourseId').value = courseData.id;
    document.getElementById('editCourseName').value = courseData.course_name;
    document.getElementById('editCourseCode').value = courseData.course_code;
    document.getElementById('editCourseDescription').value = courseData.description || '';
    document.getElementById('editCourseCredits').value = courseData.credits;
    document.getElementById('editCourseStatus').value = courseData.status;
    
    document.getElementById('editCourseModal').style.display = 'block';
}

function hideEditCourseForm() {
    document.getElementById('editCourseModal').style.display = 'none';
}

function deleteCourse(courseId, courseName) {
    if(confirm(`Are you sure you want to delete the course "${courseName}"? This action cannot be undone and will also delete all related assessments and marks.`)) {
        document.getElementById('deleteCourseId').value = courseId;
        document.getElementById('deleteCourseForm').submit();
    }
}

// Filter by student function
function filterByStudent(studentId) {
    // Set the student filter dropdown
    const studentSelect = document.querySelector('select[name="student"]');
    if(studentSelect) {
        studentSelect.value = studentId;
        // Submit the form to apply the filter
        studentSelect.closest('form').submit();
    }
}

// Add form validation for course codes
document.addEventListener('DOMContentLoaded', function() {
    // Check for duplicate course codes in add form
    const addCourseForm = document.querySelector('#addCourseModal form');
    if(addCourseForm) {
        addCourseForm.addEventListener('submit', function(e) {
            const courseCodeInput = this.querySelector('input[name="course_code"]');
            const courseCode = courseCodeInput.value.trim().toUpperCase();
            
            if(courseCode) {
                // Check if course code already exists
                fetch('check_course_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `course_code=${encodeURIComponent(courseCode)}`
                })
                .then(response => response.json())
                .then(data => {
                    if(data.exists) {
                        e.preventDefault();
                        alert(`Course code "${courseCode}" already exists. Please use a different course code.`);
                        courseCodeInput.focus();
                    }
                })
                .catch(error => {
                    console.error('Error checking course code:', error);
                });
            }
        });
    }
    
    // Check for duplicate course codes in edit form
    const editCourseForm = document.querySelector('#editCourseModal form');
    if(editCourseForm) {
        editCourseForm.addEventListener('submit', function(e) {
            const courseCodeInput = this.querySelector('input[name="course_code"]');
            const courseId = this.querySelector('input[name="id"]').value;
            const courseCode = courseCodeInput.value.trim().toUpperCase();
            
            if(courseCode) {
                // Check if course code already exists (excluding current course)
                fetch('check_course_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `course_code=${encodeURIComponent(courseCode)}&exclude_id=${courseId}`
                })
                .then(response => response.json())
                .then(data => {
                    if(data.exists) {
                        e.preventDefault();
                        alert(`Course code "${courseCode}" already exists. Please use a different course code.`);
                        courseCodeInput.focus();
                    }
                })
                .catch(error => {
                    console.error('Error checking course code:', error);
                });
            }
        });
    }
});
</script>
<?php endif; ?>

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

.badge-primary {
    background: #dbeafe;
    color: #1e40af;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fecaca;
    color: #991b1b;
}
</style>

<?php require_once 'includes/footer.php'; ?>
