<?php
// assessment.php — Assessment management page
$page_title = 'Assessments';
require_once 'includes/header.php';

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['manager_admin','student_admin'])){
    header('Location: login.php'); exit;
}

$is_manager = $_SESSION['role'] === 'manager_admin';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST' && $is_manager){
    // CSRF protection
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'add'){
        $course_id = (int)$_POST['course_id'];
        $title = trim($_POST['title']);
        $type = $_POST['type'];
        $total_marks = (int)$_POST['total_marks'];
        $weight = (float)$_POST['weight'];
        $due_date = $_POST['due_date'] ?: null;
        
        $sql = "INSERT INTO assessments (course_id, title, type, total_marks, weight, due_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issids', $course_id, $title, $type, $total_marks, $weight, $due_date);
        
        if($stmt->execute()){
            $success_msg = 'Assessment added successfully!';
        } else {
            $error_msg = 'Error adding assessment.';
        }
    }
    
    if($action === 'update'){
        $id = (int)$_POST['id'];
        $course_id = (int)$_POST['course_id'];
        $title = trim($_POST['title']);
        $type = $_POST['type'];
        $total_marks = (int)$_POST['total_marks'];
        $weight = (float)$_POST['weight'];
        $due_date = $_POST['due_date'] ?: null;
        
        $sql = "UPDATE assessments SET course_id = ?, title = ?, type = ?, total_marks = ?, weight = ?, due_date = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issidsi', $course_id, $title, $type, $total_marks, $weight, $due_date, $id);
        
        if($stmt->execute()){
            $success_msg = 'Assessment updated successfully!';
        } else {
            $error_msg = 'Error updating assessment.';
        }
    }
    
    if($action === 'delete'){
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM assessments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if($stmt->execute()){
            $success_msg = 'Assessment deleted successfully!';
        } else {
            $error_msg = 'Error deleting assessment.';
        }
    }
}

// Handle search and filtering
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$type_filter = $_GET['type'] ?? '';
$prefill_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$show_add_modal = isset($_GET['show_add']) && $_GET['show_add'] == '1';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if(!empty($search)) {
    $where_conditions[] = "(a.title LIKE ? OR c.course_name LIKE ? OR c.course_code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if(!empty($course_filter)) {
    $where_conditions[] = "a.course_id = ?";
    $params[] = (int)$course_filter;
    $param_types .= 'i';
}

if(!empty($type_filter)) {
    $where_conditions[] = "a.type = ?";
    $params[] = $type_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Fetch assessments with course information
$sql = "SELECT a.*, c.course_name, c.course_code, 
               COUNT(m.id) as marks_count,
               COALESCE(AVG(m.obtained_marks), 0) as avg_marks
        FROM assessments a 
        JOIN courses c ON a.course_id = c.id 
        LEFT JOIN marks m ON a.id = m.assessment_id
        $where_clause
        GROUP BY a.id
        ORDER BY c.course_name, a.title";

if(!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Get courses for form and filter
$courses_result = $conn->query("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
if (!$courses_result) {
    die("Database error: " . $conn->error);
}

// Check if there are any courses
$course_count = $courses_result->num_rows;

// Get assessment statistics
$stats_sql = "SELECT 
    COUNT(*) as total_assessments,
    COUNT(DISTINCT course_id) as courses_with_assessments,
    AVG(total_marks) as avg_total_marks,
    SUM(weight) as total_weight
    FROM assessments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="container">
    <!-- Back to Dashboard Button -->
    <div style="margin-bottom: 1.5rem;">
        <a href="dashboard.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1><i class="fas fa-clipboard-list"></i> Assessments</h1>
            <?php if($is_manager): ?>
            <div style="display:flex; gap:0.5rem;">
                <a href="course.php" class="btn btn-secondary">
                    <i class="fas fa-book"></i> Add Course
                </a>
                <button onclick="showAddForm()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Assessment
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['total_assessments'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Total Assessments</p>
            </div>
            <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= $stats['courses_with_assessments'] ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Courses Covered</p>
            </div>
            <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= number_format((float)($stats['avg_total_marks'] ?? 0), 0) ?></h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Avg Total Marks</p>
            </div>
            <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; padding: 1.5rem; border-radius: 12px; text-align: center;">
                <h3 style="margin: 0; font-size: 2rem;"><?= number_format((float)($stats['total_weight'] ?? 0), 1) ?>%</h3>
                <p style="margin: 0.5rem 0 0 0; opacity: 0.9;">Total Weight</p>
            </div>
        </div>
        
        <!-- Search and Filter Section -->
        <div style="background: #f8fafc; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
            <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                <div class="form-group">
                    <label class="form-label">Search Assessments</label>
                    <input type="text" name="search" class="form-input" placeholder="Search by title, course name, or code..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Filter by Course</label>
                    <select name="course" class="form-input">
                        <option value="">All Courses</option>
                        <?php 
                        $courses_result->data_seek(0); // Reset result pointer
                        while($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Filter by Type</label>
                    <select name="type" class="form-input">
                        <option value="">All Types</option>
                        <option value="Quiz" <?= $type_filter === 'Quiz' ? 'selected' : '' ?>>Quiz</option>
                        <option value="Assignment" <?= $type_filter === 'Assignment' ? 'selected' : '' ?>>Assignment</option>
                        <option value="Midterm" <?= $type_filter === 'Midterm' ? 'selected' : '' ?>>Midterm</option>
                        <option value="Final" <?= $type_filter === 'Final' ? 'selected' : '' ?>>Final</option>
                        <option value="Project" <?= $type_filter === 'Project' ? 'selected' : '' ?>>Project</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="assessment.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <?php if(isset($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        
        <table class="table data-table">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Total Marks</th>
                    <th>Weight</th>
                    <th>Due Date</th>
                    <th>Marks Count</th>
                    <th>Avg Score</th>
                    <?php if($is_manager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($row['course_name']) ?></strong><br>
                            <small style="color: var(--secondary-color);"><?= htmlspecialchars($row['course_code']) ?></small>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['type'] === 'Final' ? 'danger' : ($row['type'] === 'Midterm' ? 'warning' : 'primary') ?>">
                            <?= $row['type'] ?>
                        </span>
                    </td>
                    <td><?= $row['total_marks'] ?></td>
                    <td><?= $row['weight'] ?>%</td>
                    <td>
                        <?php if($row['due_date']): ?>
                            <?= date('M j, Y', strtotime($row['due_date'])) ?>
                            <?php if(strtotime($row['due_date']) < time()): ?>
                                <br><small style="color: #dc2626;">Overdue</small>
                            <?php elseif(strtotime($row['due_date']) <= strtotime('+7 days')): ?>
                                <br><small style="color: #f59e0b;">Due Soon</small>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $row['marks_count'] > 0 ? 'success' : 'secondary' ?>">
                            <?= $row['marks_count'] ?> student<?= $row['marks_count'] !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td>
                        <?php if($row['marks_count'] > 0): ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: var(--primary-color);">
                                    <?= number_format($row['avg_marks'], 1) ?>
                                </span>
                                <div style="width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px;">
                                    <div style="width: <?= ($row['avg_marks'] / $row['total_marks']) * 100 ?>%; height: 100%; background: var(--primary-color); border-radius: 2px;"></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--secondary-color);">No marks</span>
                        <?php endif; ?>
                    </td>
                    <?php if($is_manager): ?>
                    <td>
                        <div style="display: flex; gap: 0.25rem;">
                            <button onclick="editAssessment(<?= htmlspecialchars(json_encode($row)) ?>)" class="btn btn-secondary btn-sm" title="Edit Assessment">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="viewMarks(<?= $row['id'] ?>, '<?= htmlspecialchars($row['title']) ?>')" class="btn btn-info btn-sm" title="View Marks">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="deleteAssessment(<?= $row['id'] ?>)" class="btn btn-danger btn-sm" title="Delete Assessment">
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

<?php if($is_manager): ?>
<!-- Add Assessment Modal -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Add New Assessment</h3>
        
        <?php if($course_count == 0): ?>
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 0.75rem; border-radius: 8px; margin: 0.75rem 0 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <span>No courses available. Please add a course first.</span>
            <div style="display: flex; gap: 0.5rem;">
                <a href="course.php" class="btn btn-primary" style="text-decoration: none;">Add Course</a>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Course</span>
                    <a href="course.php" class="btn btn-secondary btn-sm" style="text-decoration:none;">Add Course</a>
                </label>
                <select name="course_id" class="form-input" required <?= $course_count == 0 ? 'disabled' : '' ?>>
                    <option value="">Select Course</option>
                    <?php 
                    $courses_result->data_seek(0); // Reset result pointer
                    while($course = $courses_result->fetch_assoc()): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Assessment Title</label>
                <input type="text" name="title" class="form-input" required placeholder="e.g., Midterm Exam, Final Project">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-input" required>
                        <option value="">Select Type</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Midterm">Midterm</option>
                        <option value="Final">Final</option>
                        <option value="Project">Project</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Marks</label>
                    <input type="number" name="total_marks" class="form-input" min="1" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Weight (%)</label>
                    <input type="number" name="weight" class="form-input" min="0" max="100" step="0.01" placeholder="e.g., 25.00">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-input">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideAddForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary" <?= $course_count == 0 ? 'disabled' : '' ?>>Add Assessment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Edit Assessment</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            
            <div class="form-group">
                <label class="form-label">Course</label>
                <select name="course_id" id="editCourseId" class="form-input" required>
                    <option value="">Select Course</option>
                    <?php 
                    $courses_result->data_seek(0); // Reset result pointer
                    while($course = $courses_result->fetch_assoc()): ?>
                    <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Assessment Title</label>
                <input type="text" name="title" id="editTitle" class="form-input" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" id="editType" class="form-input" required>
                        <option value="">Select Type</option>
                        <option value="Quiz">Quiz</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Midterm">Midterm</option>
                        <option value="Final">Final</option>
                        <option value="Project">Project</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Marks</label>
                    <input type="number" name="total_marks" id="editTotalMarks" class="form-input" min="1" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Weight (%)</label>
                    <input type="number" name="weight" id="editWeight" class="form-input" min="0" max="100" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" id="editDueDate" class="form-input">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideEditForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Assessment</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteAssessmentId">
</form>

<!-- View Marks Modal -->
<div id="viewMarksModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto;">
        <h3 id="viewMarksTitle">Assessment Marks</h3>
        <div id="viewMarksContent">
            <!-- Marks will be loaded here -->
        </div>
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
            <button type="button" onclick="hideViewMarksForm()" class="btn btn-secondary">Close</button>
        </div>
    </div>
</div>

<script>
function showAddForm() {
    document.getElementById('addModal').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addModal').style.display = 'none';
}

function editAssessment(assessment) {
    document.getElementById('editId').value = assessment.id;
    document.getElementById('editCourseId').value = assessment.course_id;
    document.getElementById('editTitle').value = assessment.title;
    document.getElementById('editType').value = assessment.type;
    document.getElementById('editTotalMarks').value = assessment.total_marks;
    document.getElementById('editWeight').value = assessment.weight;
    document.getElementById('editDueDate').value = assessment.due_date || '';
    
    document.getElementById('editModal').style.display = 'block';
}

function hideEditForm() {
    document.getElementById('editModal').style.display = 'none';
}

function deleteAssessment(assessmentId) {
    if(confirm('Are you sure you want to delete this assessment? This will also delete all associated marks.')) {
        document.getElementById('deleteAssessmentId').value = assessmentId;
        document.getElementById('deleteForm').submit();
    }
}

function viewMarks(assessmentId, assessmentTitle) {
    document.getElementById('viewMarksTitle').textContent = `Marks for: ${assessmentTitle}`;
    document.getElementById('viewMarksContent').innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading marks...</div>';
    document.getElementById('viewMarksModal').style.display = 'block';
    
    // Load marks via AJAX
    fetch(`get_assessment_marks.php?id=${assessmentId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('viewMarksContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('viewMarksContent').innerHTML = '<div style="text-align: center; padding: 2rem; color: #dc2626;">Error loading marks. Please try again.</div>';
        });
}

function hideViewMarksForm() {
    document.getElementById('viewMarksModal').style.display = 'none';
}

// Auto-open Add Assessment with preselected course when requested
<?php if($show_add_modal && $prefill_course_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    showAddForm();
    const courseSelect = document.querySelector('#addModal select[name="course_id"]');
    if (courseSelect) {
        courseSelect.value = String(<?= $prefill_course_id ?>);
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

<style>
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
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
