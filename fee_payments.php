<?php
// fee_payments.php — Fee payment management (Admin)
$page_title = 'Fee Payments';
require_once 'includes/header.php';
requireAdmin(); // Use the authentication check function

$is_manager = $_SESSION['role'] === 'manager_admin';
$msg = '';
$msg_type = 'success';

// Ensure required table exists to avoid fatal errors
function _tableExists($conn, $tableName) {
    $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($tableName) . "'");
    return $res && $res->num_rows > 0;
}

if (!_tableExists($conn, 'fee_payments')) {
    $createSql = "CREATE TABLE fee_payments (
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
    if (!$conn->query($createSql)) {
        $msg = 'Database setup required: could not create fee_payments table. Please run setup_tables.php.';
        $msg_type = 'danger';
    }
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'add' && $is_manager){
        $student_id = intval($_POST['student_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $academic_year = trim($_POST['academic_year'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Paid';
        $recorded_by = $_SESSION['admin_id'];
        
        $sql = "INSERT INTO fee_payments (student_id, amount, payment_date, payment_method, payment_reference, semester, academic_year, description, status, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('idsssssssi', $student_id, $amount, $payment_date, $payment_method, $payment_reference, $semester, $academic_year, $description, $status, $recorded_by);
        
        if($stmt->execute()){
            $msg = 'Fee payment recorded successfully!';
        } else {
            $msg = 'Error recording payment.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
    
    if($action === 'update' && $is_manager){
        $payment_id = intval($_POST['payment_id']);
        $amount = floatval($_POST['amount']);
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $academic_year = trim($_POST['academic_year'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Paid';
        
        $sql = "UPDATE fee_payments SET amount = ?, payment_date = ?, payment_method = ?, payment_reference = ?, semester = ?, academic_year = ?, description = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('dsssssssi', $amount, $payment_date, $payment_method, $payment_reference, $semester, $academic_year, $description, $status, $payment_id);
        
        if($stmt->execute()){
            $msg = 'Payment updated successfully!';
        } else {
            $msg = 'Error updating payment.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
    
    if($action === 'delete' && $is_manager){
        $payment_id = intval($_POST['payment_id']);
        $sql = "DELETE FROM fee_payments WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $payment_id);
        
        if($stmt->execute()){
            $msg = 'Payment deleted successfully!';
        } else {
            $msg = 'Error deleting payment.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

// Get filter parameters
$filter_student = $_GET['student_id'] ?? '';
$filter_semester = $_GET['semester'] ?? '';
$filter_year = $_GET['academic_year'] ?? '';

// Build query
$where = [];
$params = [];
$types = '';

if($filter_student){
    $where[] = "fp.student_id = ?";
    $params[] = intval($filter_student);
    $types .= 'i';
}

if($filter_semester){
    $where[] = "fp.semester = ?";
    $params[] = $filter_semester;
    $types .= 's';
}

if($filter_year){
    $where[] = "fp.academic_year = ?";
    $params[] = $filter_year;
    $types .= 's';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get fee payments
$sql = "SELECT fp.*, s.full_name, s.student_id as student_code, a.full_name as recorded_by_name
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        JOIN admins a ON fp.recorded_by = a.id
        $where_clause
        ORDER BY fp.payment_date DESC, fp.created_at DESC";
        
$stmt = $conn->prepare($sql);
if($params){
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result();

// Get students for filter
$students_result = $conn->query("SELECT id, student_id, full_name FROM students WHERE status = 'Active' ORDER BY full_name");
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Get unique semesters and years
$semesters_result = $conn->query("SELECT DISTINCT semester FROM fee_payments WHERE semester IS NOT NULL AND semester != '' ORDER BY semester DESC");
$semesters = $semesters_result->fetch_all(MYSQLI_ASSOC);
$years_result = $conn->query("SELECT DISTINCT academic_year FROM fee_payments WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
$years = $years_result->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as total_pending,
    AVG(amount) as avg_amount
    FROM fee_payments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="container">
    <!-- Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; color: white; font-size: 1.75rem;">
                    <i class="fas fa-money-bill-wave"></i> Fee Payment Tracker
                </h1>
                <p style="color: rgba(255,255,255,0.9); margin: 0.5rem 0 0 0; font-size: 1rem;">
                    Manage and track student fee payments
                </p>
            </div>
        </div>
    </div>

    <?php if($msg): ?>
    <div class="alert alert-<?= $msg_type ?>" style="margin: 1rem 0;">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="dashboard-card">
            <h3><i class="fas fa-list"></i> Total Payments</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?= $stats['total_payments'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-check-circle"></i> Total Paid</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--success-color);">$<?= number_format($stats['total_paid'] ?? 0, 2) ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-clock"></i> Pending</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">$<?= number_format($stats['total_pending'] ?? 0, 2) ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> Average Amount</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--info-color);">$<?= number_format($stats['avg_amount'] ?? 0, 2) ?></p>
        </div>
    </div>

    <!-- Filters and Add Button -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <form method="get" style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1;">
                <select name="student_id" class="form-input" style="width: auto; min-width: 200px;">
                    <option value="">All Students</option>
                    <?php foreach($students as $student): ?>
                    <option value="<?= $student['id'] ?>" <?= $filter_student == $student['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="semester" class="form-input" style="width: auto; min-width: 150px;">
                    <option value="">All Semesters</option>
                    <?php foreach($semesters as $sem): ?>
                    <option value="<?= htmlspecialchars($sem['semester']) ?>" <?= $filter_semester == $sem['semester'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sem['semester']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select name="academic_year" class="form-input" style="width: auto; min-width: 150px;">
                    <option value="">All Years</option>
                    <?php foreach($years as $year): ?>
                    <option value="<?= htmlspecialchars($year['academic_year']) ?>" <?= $filter_year == $year['academic_year'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($year['academic_year']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="fee_payments.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </form>
            <?php if($is_manager): ?>
            <button onclick="showAddModal()" class="btn btn-success">
                <i class="fas fa-plus"></i> Add Payment
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Payment Records</h3>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Recorded By</th>
                        <?php if($is_manager): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($payments->num_rows > 0): ?>
                        <?php while($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($payment['full_name']) ?></strong><br>
                                <small style="color: var(--secondary-color);"><?= htmlspecialchars($payment['student_code']) ?></small>
                            </td>
                            <td><strong>$<?= number_format($payment['amount'], 2) ?></strong></td>
                            <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['payment_reference'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($payment['semester'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($payment['academic_year'] ?: 'N/A') ?></td>
                            <td>
                                <span class="badge badge-<?= $payment['status'] === 'Paid' ? 'success' : ($payment['status'] === 'Pending' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($payment['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($payment['recorded_by_name']) ?></td>
                            <?php if($is_manager): ?>
                            <td>
                                <button onclick="showEditModal(<?= htmlspecialchars(json_encode($payment)) ?>)" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deletePayment(<?= $payment['id'] ?>)" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_manager ? '10' : '9' ?>" style="text-align: center; padding: 2rem; color: var(--secondary-color);">
                                No payment records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Back Button -->
    <div style="text-align: center; margin-top: 2rem;">
        <a href="dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- Add/Edit Payment Modal -->
<div id="paymentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-money-bill-wave"></i> Add Payment</h3>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        <form method="post" id="paymentForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="paymentAction" value="add">
            <input type="hidden" name="payment_id" id="paymentId">
            
            <div class="form-group">
                <label class="form-label">Student *</label>
                <select name="student_id" id="paymentStudentId" class="form-input" required>
                    <option value="">Select Student</option>
                    <?php foreach($students as $student): ?>
                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Amount *</label>
                <input type="number" name="amount" id="paymentAmount" class="form-input" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Date *</label>
                <input type="date" name="payment_date" id="paymentDate" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Method *</label>
                <select name="payment_method" id="paymentMethod" class="form-input" required>
                    <option value="Cash">Cash</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Payment Reference</label>
                <input type="text" name="payment_reference" id="paymentReference" class="form-input" placeholder="Transaction ID, Receipt No., etc.">
            </div>
            
            <div class="form-group">
                <label class="form-label">Semester</label>
                <input type="text" name="semester" id="paymentSemester" class="form-input" placeholder="e.g., Fall 2024">
            </div>
            
            <div class="form-group">
                <label class="form-label">Academic Year</label>
                <input type="text" name="academic_year" id="paymentYear" class="form-input" placeholder="e.g., 2024-2025">
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="paymentDescription" class="form-input" rows="3" placeholder="Additional notes..."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Status *</label>
                <select name="status" id="paymentStatus" class="form-input" required>
                    <option value="Paid">Paid</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fas fa-save"></i> Save
                </button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    overflow-y: auto;
}

.modal-content {
    background: white;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--secondary-color);
    cursor: pointer;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
</style>

<script>
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-money-bill-wave"></i> Add Payment';
    document.getElementById('paymentForm').reset();
    document.getElementById('paymentAction').value = 'add';
    document.getElementById('paymentId').value = '';
    document.getElementById('paymentDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('paymentModal').style.display = 'block';
}

function showEditModal(payment) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Payment';
    document.getElementById('paymentAction').value = 'update';
    document.getElementById('paymentId').value = payment.id;
    document.getElementById('paymentStudentId').value = payment.student_id;
    document.getElementById('paymentAmount').value = payment.amount;
    document.getElementById('paymentDate').value = payment.payment_date;
    document.getElementById('paymentMethod').value = payment.payment_method;
    document.getElementById('paymentReference').value = payment.payment_reference || '';
    document.getElementById('paymentSemester').value = payment.semester || '';
    document.getElementById('paymentYear').value = payment.academic_year || '';
    document.getElementById('paymentDescription').value = payment.description || '';
    document.getElementById('paymentStatus').value = payment.status;
    document.getElementById('paymentModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

function deletePayment(id) {
    if(confirm('Are you sure you want to delete this payment record?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="payment_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('paymentModal');
    if(event.target == modal) {
        closeModal();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

