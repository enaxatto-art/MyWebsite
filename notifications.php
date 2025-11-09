<?php
// notifications.php — Notification management (Admin)
$page_title = 'Notifications';
require_once 'includes/header.php';

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['manager_admin','student_admin'])){
    header('Location: login.php');
    exit;
}

$is_manager = $_SESSION['role'] === 'manager_admin';
$msg = '';
$msg_type = 'success';

// Ensure required notification tables exist to avoid fatal errors
function _notif_table_exists($conn, $name){
    $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($name)."'");
    return $res && $res->num_rows > 0;
}

if(!_notif_table_exists($conn, 'notifications')){
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
    if(!$conn->query($sql)){
        $msg = 'Database setup required: could not create notifications table. Please run setup_tables.php.';
        $msg_type = 'danger';
    }
}

if(!_notif_table_exists($conn, 'notification_reads')){
    $sql = "CREATE TABLE notification_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        student_id INT NOT NULL,
        read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        UNIQUE KEY unique_notification_student (notification_id, student_id)
    )";
    $conn->query($sql); // if it fails, continue; list view will still render
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'create' && $is_manager){
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'Info';
        $target_audience = $_POST['target_audience'] ?? 'All';
        $target_student_id = !empty($_POST['target_student_id']) ? intval($_POST['target_student_id']) : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' ' . ($_POST['expires_time'] ?? '23:59:59') : null;
        
        if(empty($title) || empty($message)){
            $msg = 'Title and message are required.';
            $msg_type = 'danger';
        } else {
            $sql = "INSERT INTO notifications (title, message, type, target_audience, target_student_id, expires_at, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssisi', $title, $message, $type, $target_audience, $target_student_id, $expires_at, $_SESSION['admin_id']);
            
            if($stmt->execute()){
                $msg = 'Notification created successfully!';
            } else {
                $msg = 'Error creating notification.';
                $msg_type = 'danger';
            }
            $stmt->close();
        }
    }
    
    if($action === 'update' && $is_manager){
        $id = intval($_POST['notification_id']);
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'Info';
        $target_audience = $_POST['target_audience'] ?? 'All';
        $target_student_id = !empty($_POST['target_student_id']) ? intval($_POST['target_student_id']) : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' ' . ($_POST['expires_time'] ?? '23:59:59') : null;
        
        $sql = "UPDATE notifications SET title = ?, message = ?, type = ?, target_audience = ?, target_student_id = ?, expires_at = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssisi', $title, $message, $type, $target_audience, $target_student_id, $expires_at, $id);
        
        if($stmt->execute()){
            $msg = 'Notification updated successfully!';
        } else {
            $msg = 'Error updating notification.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
    
    if($action === 'delete' && $is_manager){
        $id = intval($_POST['notification_id']);
        $sql = "DELETE FROM notifications WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if($stmt->execute()){
            $msg = 'Notification deleted successfully!';
        } else {
            $msg = 'Error deleting notification.';
            $msg_type = 'danger';
        }
        $stmt->close();
    }
}

// Get notifications
$sql = "SELECT n.*, a.full_name as created_by_name, s.full_name as target_student_name, s.student_id as target_student_code,
               COUNT(nr.id) as read_count
        FROM notifications n
        LEFT JOIN admins a ON n.created_by = a.id
        LEFT JOIN students s ON n.target_student_id = s.id
        LEFT JOIN notification_reads nr ON n.id = nr.notification_id
        GROUP BY n.id
        ORDER BY n.created_at DESC";
        
$notifications = $conn->query($sql);

// Get students for target selection
$students_result = $conn->query("SELECT id, student_id, full_name FROM students WHERE status = 'Active' ORDER BY full_name");
$students = $students_result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_notifications,
    SUM(CASE WHEN expires_at IS NULL OR expires_at > NOW() THEN 1 ELSE 0 END) as active_notifications,
    SUM(CASE WHEN target_audience = 'Students' THEN 1 ELSE 0 END) as student_notifications
    FROM notifications";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="container">
    <!-- Header -->
    <div class="card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #000000 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; color: white; font-size: 1.75rem;">
                    <i class="fas fa-bell"></i> Notification System
                </h1>
                <p style="color: rgba(255,255,255,0.9); margin: 0.5rem 0 0 0; font-size: 1rem;">
                    Create and manage notifications for students
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
            <h3><i class="fas fa-list"></i> Total Notifications</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?= $stats['total_notifications'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-check-circle"></i> Active</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?= $stats['active_notifications'] ?? 0 ?></p>
        </div>
        <div class="dashboard-card">
            <h3><i class="fas fa-user-graduate"></i> For Students</h3>
            <p style="font-size: 2rem; font-weight: 700; color: var(--info-color);"><?= $stats['student_notifications'] ?? 0 ?></p>
        </div>
    </div>

    <!-- Create Notification Button -->
    <?php if($is_manager): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <button onclick="showCreateModal()" class="btn btn-success">
            <i class="fas fa-plus"></i> Create Notification
        </button>
    </div>
    <?php endif; ?>

    <!-- Notifications List -->
    <div class="card">
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list"></i> Notifications</h3>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Message</th>
                        <th>Read By</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <?php if($is_manager): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if($notifications->num_rows > 0): ?>
                        <?php while($notification = $notifications->fetch_assoc()): 
                            $is_expired = $notification['expires_at'] && strtotime($notification['expires_at']) < time();
                        ?>
                        <tr style="<?= $is_expired ? 'opacity: 0.6;' : '' ?>">
                            <td><strong><?= htmlspecialchars($notification['title']) ?></strong></td>
                            <td>
                                <span class="badge badge-<?= $notification['type'] === 'Important' ? 'danger' : ($notification['type'] === 'Warning' ? 'warning' : ($notification['type'] === 'Success' ? 'success' : 'info')) ?>">
                                    <?= htmlspecialchars($notification['type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if($notification['target_audience'] === 'Specific'): ?>
                                    <?= htmlspecialchars($notification['target_student_name'] ?? 'N/A') ?> 
                                    <br><small>(<?= htmlspecialchars($notification['target_student_code'] ?? '') ?>)</small>
                                <?php else: ?>
                                    <?= htmlspecialchars($notification['target_audience']) ?>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 300px;">
                                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($notification['message']) ?>">
                                    <?= htmlspecialchars(substr($notification['message'], 0, 100)) ?><?= strlen($notification['message']) > 100 ? '...' : '' ?>
                                </div>
                            </td>
                            <td><?= $notification['read_count'] ?? 0 ?></td>
                            <td>
                                <?php if($notification['expires_at']): ?>
                                    <?= date('M j, Y', strtotime($notification['expires_at'])) ?>
                                    <?php if($is_expired): ?>
                                        <br><small style="color: var(--danger-color);">Expired</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small style="color: var(--secondary-color);">Never</small>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($notification['created_at'])) ?></td>
                            <?php if($is_manager): ?>
                            <td>
                                <button onclick="showEditModal(<?= htmlspecialchars(json_encode($notification)) ?>)" class="btn btn-sm btn-primary" style="margin-right: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteNotification(<?= $notification['id'] ?>)" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $is_manager ? '8' : '7' ?>" style="text-align: center; padding: 2rem; color: var(--secondary-color);">
                                No notifications found.
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

<!-- Create/Edit Notification Modal -->
<?php if($is_manager): ?>
<div id="notificationModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-bell"></i> Create Notification</h3>
            <button onclick="closeModal()" class="close-btn">&times;</button>
        </div>
        <form method="post" id="notificationForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" id="notificationAction" value="create">
            <input type="hidden" name="notification_id" id="notificationId">
            
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" id="notificationTitle" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Message *</label>
                <textarea name="message" id="notificationMessage" class="form-input" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Type *</label>
                <select name="type" id="notificationType" class="form-input" required>
                    <option value="Info">Info</option>
                    <option value="Warning">Warning</option>
                    <option value="Success">Success</option>
                    <option value="Important">Important</option>
                    <option value="Reminder">Reminder</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Target Audience *</label>
                <select name="target_audience" id="notificationTarget" class="form-input" required onchange="toggleStudentSelect()">
                    <option value="All">All</option>
                    <option value="Students">Students</option>
                    <option value="Admins">Admins</option>
                    <option value="Specific">Specific Student</option>
                </select>
            </div>
            
            <div class="form-group" id="studentSelectGroup" style="display: none;">
                <label class="form-label">Select Student</label>
                <select name="target_student_id" id="notificationStudentId" class="form-input">
                    <option value="">Select Student</option>
                    <?php foreach($students as $student): ?>
                    <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" id="hasExpiry" onchange="toggleExpiry()"> Set Expiration Date
                </label>
            </div>
            
            <div class="form-group" id="expiryGroup" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label class="form-label">Expiration Date</label>
                        <input type="date" name="expires_at" id="notificationExpiresAt" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Expiration Time</label>
                        <input type="time" name="expires_time" id="notificationExpiresTime" class="form-input" value="23:59:59" step="1">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 0.5rem; margin-top: 0.75rem; align-items: end;">
                    <div>
                        <label class="form-label">Choose System Sound</label>
                        <select id="presetTone" class="form-input">
                            <option value="">None</option>
                            <option value="beep">Beep</option>
                            <option value="chime">Chime</option>
                            <option value="ding">Ding</option>
                            <option value="alarm">Alarm</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Or Upload Custom Sound</label>
                        <input type="file" id="notificationSound" accept="audio/*" class="form-input">
                    </div>
                    <button type="button" id="testSoundBtn" class="btn btn-secondary" disabled>Test Sound</button>
                    <button type="button" id="clearSoundBtn" class="btn btn-danger" disabled>Clear</button>
                </div>
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

<script>
function _playPresetTone(name){
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.connect(g); g.connect(ctx.destination);
        let pattern = [];
        if(name === 'beep') pattern = [{f:880,d:0.2},{f:0,d:0.1},{f:880,d:0.2}];
        else if(name === 'chime') pattern = [{f:523.25,d:0.18},{f:659.25,d:0.18},{f:783.99,d:0.25}];
        else if(name === 'ding') pattern = [{f:987.77,d:0.25},{f:0,d:0.05},{f:659.25,d:0.2}];
        else if(name === 'alarm') pattern = [{f:700,d:0.25},{f:900,d:0.25},{f:700,d:0.25},{f:900,d:0.25}];
        else pattern = [{f:880,d:0.4}];
        let t = ctx.currentTime;
        g.gain.setValueAtTime(0.0001, t);
        pattern.forEach(step => {
            if(step.f>0){ o.frequency.setValueAtTime(step.f, t); g.gain.linearRampToValueAtTime(0.6, t+0.01); }
            else { g.gain.setValueAtTime(0.0001, t); }
            t += step.d;
        });
        o.start();
        o.stop(t);
    } catch(e) {}
}

function _scheduleNotificationSound(expiresAt, dataUrl, preset){
    if(_alarmTimeoutId) { clearTimeout(_alarmTimeoutId); _alarmTimeoutId = null; }
    const now = new Date();
    const diff = expiresAt - now;
    if(isNaN(diff) || diff <= 0) return;
    _alarmTimeoutId = setTimeout(() => {
        try {
            if(dataUrl){
                const audio = new Audio(dataUrl);
                audio.play().catch(()=>{});
            } else if(preset){
                _playPresetTone(preset);
            }
        } finally {
            localStorage.removeItem('pending_notification_alarm');
            _alarmTimeoutId = null;
        }
    }, diff);
}

function _saveAlarmToStorage(expiresAt, dataUrl, preset){
    try {
        localStorage.setItem('pending_notification_alarm', JSON.stringify({ expiresAt: expiresAt.toISOString(), dataUrl, preset }));
    } catch(e) {}
}

const soundInput = document.getElementById('notificationSound');
const testBtn = document.getElementById('testSoundBtn');
const clearBtn = document.getElementById('clearSoundBtn');
const presetSel = document.getElementById('presetTone');
if(soundInput){
    soundInput.addEventListener('change', (e) => {
        const file = e.target.files && e.target.files[0];
        if(!file) { _selectedSoundDataUrl = null; if(!_selectedPreset){ testBtn.disabled = true; clearBtn.disabled = true; } return; }
        const reader = new FileReader();
        reader.onload = () => {
            _selectedSoundDataUrl = reader.result;
            testBtn.disabled = false; clearBtn.disabled = false;
        };
        reader.readAsDataURL(file);
    });
}
if(presetSel){
    presetSel.addEventListener('change', (e) => {
        _selectedPreset = e.target.value || '';
        if(_selectedPreset){ testBtn.disabled = false; clearBtn.disabled = false; }
        else if(!_selectedSoundDataUrl){ testBtn.disabled = true; clearBtn.disabled = true; }
    });
}
if(testBtn){
    testBtn.addEventListener('click', () => {
        if(_selectedSoundDataUrl){
            const audio = new Audio(_selectedSoundDataUrl);
            audio.play().catch(()=>{});
        } else if(_selectedPreset){
            _playPresetTone(_selectedPreset);
        }
    });
}
if(clearBtn){
    clearBtn.addEventListener('click', () => {
        const input = document.getElementById('notificationSound');
        if(input){ input.value = ''; }
        _selectedSoundDataUrl = null;
        _selectedPreset = '';
        if(presetSel) presetSel.value = '';
        testBtn.disabled = true; clearBtn.disabled = true;
    });
}

// On form submit, persist alarm to localStorage so it survives the page reload
const form = document.getElementById('notificationForm');
if(form){
    form.addEventListener('submit', () => {
        const hasExpiry = document.getElementById('hasExpiry')?.checked;
        const when = _combineExpiryDateTime();
        if(hasExpiry && when){
            _saveAlarmToStorage(when, _selectedSoundDataUrl || null, _selectedPreset || '');
        }
    });
}

function showCreateModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-bell"></i> Create Notification';
    document.getElementById('notificationForm').reset();
    document.getElementById('notificationAction').value = 'create';
    document.getElementById('notificationId').value = '';
    document.getElementById('studentSelectGroup').style.display = 'none';
    document.getElementById('expiryGroup').style.display = 'none';
    document.getElementById('hasExpiry').checked = false;
    document.getElementById('notificationModal').style.display = 'block';
}

function showEditModal(notification) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Notification';
    document.getElementById('notificationAction').value = 'update';
    document.getElementById('notificationId').value = notification.id;
    document.getElementById('notificationTitle').value = notification.title;
    document.getElementById('notificationMessage').value = notification.message;
    document.getElementById('notificationType').value = notification.type;
    document.getElementById('notificationTarget').value = notification.target_audience;
    
    if(notification.target_student_id) {
        document.getElementById('notificationStudentId').value = notification.target_student_id;
    }
    
    toggleStudentSelect();
    
    if(notification.expires_at) {
        const expiresDate = new Date(notification.expires_at);
        document.getElementById('notificationExpiresAt').value = expiresDate.toISOString().split('T')[0];
        document.getElementById('notificationExpiresTime').value = expiresDate.toTimeString().split(' ')[0].substring(0, 5);
        document.getElementById('hasExpiry').checked = true;
        document.getElementById('expiryGroup').style.display = 'block';
    } else {
        document.getElementById('hasExpiry').checked = false;
        document.getElementById('expiryGroup').style.display = 'none';
    }
    
    document.getElementById('notificationModal').style.display = 'block';
}

function toggleStudentSelect() {
    const target = document.getElementById('notificationTarget').value;
    const studentSelectGroup = document.getElementById('studentSelectGroup');
    if(target === 'Specific') {
        studentSelectGroup.style.display = 'block';
        document.getElementById('notificationStudentId').required = true;
    } else {
        studentSelectGroup.style.display = 'none';
        document.getElementById('notificationStudentId').required = false;
        document.getElementById('notificationStudentId').value = '';
    }
}

function toggleExpiry() {
    const hasExpiry = document.getElementById('hasExpiry').checked;
    const expiryGroup = document.getElementById('expiryGroup');
    if(hasExpiry) {
        expiryGroup.style.display = 'block';
    } else {
        expiryGroup.style.display = 'none';
        document.getElementById('notificationExpiresAt').value = '';
    }
}

function closeModal() {
    document.getElementById('notificationModal').style.display = 'none';
}

function deleteNotification(id) {
    if(confirm('Are you sure you want to delete this notification?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="notification_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('notificationModal');
    if(event.target == modal) {
        closeModal();
    }
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>

