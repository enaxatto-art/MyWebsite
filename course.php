<?php
// course.php - Course management page
$page_title = 'Courses';
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
        $course_name = trim($_POST['course_name']);
        $course_code = trim($_POST['course_code']);
        $description = trim($_POST['description']);
        
        $sql = "INSERT INTO courses (course_name, course_code, description) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $course_name, $course_code, $description);
        
        if($stmt->execute()){
            $success_msg = 'Course added successfully!';
        } else {
            $error_msg = 'Error adding course.';
        }
    }
    
    if($action === 'delete'){
        $course_id = (int)$_POST['course_id'];
        $sql = "DELETE FROM courses WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $course_id);
        
        if($stmt->execute()){
            $success_msg = 'Course deleted successfully!';
        } else {
            $error_msg = 'Error deleting course.';
        }
    }
}

// Fetch courses
$result = $conn->query("SELECT * FROM courses ORDER BY course_name");
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
            <h1><i class="fas fa-book"></i> Courses</h1>
            <?php if($is_manager): ?>
            <button onclick="showAddForm()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Course
            </button>
            <?php endif; ?>
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
                    <th>ID</th>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Description</th>
                    <?php if($is_manager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><strong><?= htmlspecialchars($row['course_code']) ?></strong></td>
                    <td><?= htmlspecialchars($row['course_name']) ?></td>
                    <td><?= htmlspecialchars($row['description']) ?></td>
                    <?php if($is_manager): ?>
                    <td>
                        <button onclick="deleteCourse(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if($is_manager): ?>
<!-- Add Course Modal -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Add New Course</h3>
        <form method="post" data-validate>
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label class="form-label">Course Code</label>
                <input type="text" name="course_code" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Course Name</label>
                <input type="text" name="course_name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" rows="3"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="hideAddForm()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="course_id" id="deleteCourseId">
</form>

<script>
function showAddForm() {
    document.getElementById('addModal').style.display = 'block';
}

function hideAddForm() {
    document.getElementById('addModal').style.display = 'none';
}

function deleteCourse(courseId) {
    if(confirm('Are you sure you want to delete this course?')) {
        document.getElementById('deleteCourseId').value = courseId;
        document.getElementById('deleteForm').submit();
    }
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
