<?php
// get_assessment_marks.php — AJAX endpoint to get marks for a specific assessment
require_once 'database.php';

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div style="text-align: center; padding: 2rem; color: #dc2626;">Invalid assessment ID.</div>';
    exit;
}

$assessment_id = (int)$_GET['id'];

// Get assessment details
$sql = "SELECT a.*, c.course_name, c.course_code 
        FROM assessments a 
        JOIN courses c ON a.course_id = c.id 
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if(!$assessment) {
    echo '<div style="text-align: center; padding: 2rem; color: #dc2626;">Assessment not found.</div>';
    exit;
}

// Get marks for this assessment
$sql = "SELECT m.*, s.full_name, s.student_id 
        FROM marks m 
        JOIN students s ON m.student_id = s.id 
        WHERE m.assessment_id = ? 
        ORDER BY s.full_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$marks = $stmt->get_result();

echo '<div style="margin-bottom: 1rem;">';
echo '<h4 style="margin: 0 0 0.5rem 0; color: #1e293b;">' . htmlspecialchars($assessment['course_name']) . ' (' . htmlspecialchars($assessment['course_code']) . ')</h4>';
echo '<p style="margin: 0; color: #64748b;">' . htmlspecialchars($assessment['title']) . ' - ' . htmlspecialchars($assessment['type']) . ' (' . $assessment['total_marks'] . ' marks)</p>';
echo '</div>';

if($marks->num_rows > 0) {
    echo '<div style="overflow-x: auto;">';
    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">';
    echo '<thead>';
    echo '<tr style="background: #f8fafc;">';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Student</th>';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Student ID</th>';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Marks Obtained</th>';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Grade</th>';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Date</th>';
    echo '<th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Remarks</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    while($mark = $marks->fetch_assoc()) {
        echo '<tr>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($mark['full_name']) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($mark['student_id']) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">';
        echo '<strong>' . number_format($mark['obtained_marks'], 2) . '</strong> / ' . $assessment['total_marks'];
        echo '<div style="width: 100px; height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: 4px;">';
        echo '<div style="width: ' . (($mark['obtained_marks'] / $assessment['total_marks']) * 100) . '%; height: 100%; background: #667eea; border-radius: 2px;"></div>';
        echo '</div>';
        echo '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">';
        echo '<span style="padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background: #dcfce7; color: #166534;">';
        echo htmlspecialchars($mark['grade'] ?: 'N/A');
        echo '</span>';
        echo '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . date('M j, Y', strtotime($mark['recorded_at'])) . '</td>';
        echo '<td style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($mark['remarks'] ?: '') . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    
    // Show statistics
    $marks->data_seek(0);
    $total_students = $marks->num_rows;
    $total_marks = 0;
    $passed = 0;
    
    while($mark = $marks->fetch_assoc()) {
        $total_marks += $mark['obtained_marks'];
        if($mark['obtained_marks'] >= ($assessment['total_marks'] * 0.5)) { // 50% pass rate
            $passed++;
        }
    }
    
    $average = $total_students > 0 ? $total_marks / $total_students : 0;
    $pass_rate = $total_students > 0 ? ($passed / $total_students) * 100 : 0;
    
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">';
    echo '<div style="text-align: center;">';
    echo '<div style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">' . $total_students . '</div>';
    echo '<div style="font-size: 0.875rem; color: #64748b;">Total Students</div>';
    echo '</div>';
    echo '<div style="text-align: center;">';
    echo '<div style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">' . number_format($average, 1) . '</div>';
    echo '<div style="font-size: 0.875rem; color: #64748b;">Average Score</div>';
    echo '</div>';
    echo '<div style="text-align: center;">';
    echo '<div style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">' . number_format($pass_rate, 1) . '%</div>';
    echo '<div style="font-size: 0.875rem; color: #64748b;">Pass Rate</div>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div style="text-align: center; padding: 3rem; color: #64748b;">';
    echo '<i class="fas fa-clipboard-list" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem;"></i>';
    echo '<p>No marks recorded for this assessment yet.</p>';
    echo '</div>';
}
?>
