<?php
// student_fee_payments.php — Student fee payment view
$page_title = 'My Fee Payments';
require_once 'includes/header.php';

if(!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student'){
    header('Location: student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get fee payments for this student
$sql = "SELECT fp.*, a.full_name as recorded_by_name
        FROM fee_payments fp
        LEFT JOIN admins a ON fp.recorded_by = a.id
        WHERE fp.student_id = ?
        ORDER BY fp.payment_date DESC, fp.created_at DESC";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN status = 'Pending' THEN amount ELSE 0 END) as total_pending
    FROM fee_payments WHERE student_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Get student info
$student_sql = "SELECT * FROM students WHERE id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
?>

<style>
.student-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.student-header h1 {
    margin: 0;
    font-size: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.info-card h3 {
    color: #667eea;
    font-size: 0.875rem;
    text-transform: uppercase;
    margin: 0 0 0.5rem 0;
}

.info-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.badge {
    padding: 0.375rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-success {
    background: #dcfce7;
    color: #166534;
}

.badge-warning {
    background: #fef3c7;
    color: #92400e;
}

.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="container">
    <div class="student-header">
        <h1><i class="fas fa-money-bill-wave"></i> My Fee Payments</h1>
        <p>Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
    </div>

    <!-- Statistics -->
    <div class="info-grid">
        <div class="info-card">
            <h3>Total Payments</h3>
            <div class="value"><?= $stats['total_payments'] ?? 0 ?></div>
        </div>
        <div class="info-card">
            <h3>Total Paid</h3>
            <div class="value" style="color: #10b981;">$<?= number_format($stats['total_paid'] ?? 0, 2) ?></div>
        </div>
        <div class="info-card">
            <h3>Pending Amount</h3>
            <div class="value" style="color: #f59e0b;">$<?= number_format($stats['total_pending'] ?? 0, 2) ?></div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Payment History</h3>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Payment Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($payments->num_rows > 0): ?>
                        <?php while($payment = $payments->fetch_assoc()): ?>
                        <tr>
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
                            <td><?= htmlspecialchars($payment['description'] ?: 'N/A') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: var(--secondary-color);">
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
        <a href="student_dashboard.php" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

