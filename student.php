<?php
// student.php — Modern student management page
$page_title = 'Students';
require_once 'includes/header.php';

if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],['manager_admin','student_admin'])){
    header('Location: login.php'); exit;
}

$is_manager = isset($_SESSION['role']) && in_array($_SESSION['role'], ['manager_admin','student_admin'], true);

// Ensure required enrollment table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS student_enrollments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        course_id INT NOT NULL,
        enrollment_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'Enrolled',
        final_grade VARCHAR(5) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_course (student_id, course_id),
        INDEX(student_id), INDEX(course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error_msg = 'Database setup error: ' . htmlspecialchars($e->getMessage());
}

// Generate next unique student ID based on prefix and existing IDs
function generate_next_student_id(mysqli $conn, string $baseId = 'STU001'): string {
    $baseId = strtoupper(trim($baseId));
    if(!preg_match('/^([A-Z]+)(\d+)$/', $baseId, $m)){
        $m = [0, 'STU', '001'];
    }
    $prefix = $m[1];
    $padLen = strlen($m[2]);

    $max = 0;
    $res = $conn->query("SELECT student_id FROM students WHERE student_id LIKE '".$conn->real_escape_string($prefix)."%'");
    if($res){
        while($r = $res->fetch_assoc()){
            if(preg_match('/^'.preg_quote($prefix, '/').'([0-9]+)$/', strtoupper($r['student_id']), $mm)){
                $n = (int)$mm[1];
                if($n > $max) $max = $n;
                $padLen = max($padLen, strlen($mm[1]));
            }
        }
        $res->close();
    }
    $next = $max + 1;
    return $prefix . str_pad((string)$next, $padLen, '0', STR_PAD_LEFT);
}

// Optional query-based fallbacks to open modals without JS clicks
$open_add = isset($_GET['open_add']) && $is_manager;
$open_upload = isset($_GET['open_upload']) && $is_manager;
$open_edit_id = isset($_GET['edit_id']) && $is_manager ? (int)$_GET['edit_id'] : 0;
$open_enroll = isset($_GET['open_enroll']) && $is_manager;
$open_enroll_student_id = ($open_enroll && isset($_GET['student_id'])) ? (int)$_GET['student_id'] : 0;
$delete_id = isset($_GET['delete_id']) && $is_manager ? (int)$_GET['delete_id'] : 0;

// If edit requested via link, fetch student for prefill
$edit_student = null;
if($open_edit_id){
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    if($stmt){
        $stmt->bind_param('i', $open_edit_id);
        $stmt->execute();
        $edit_student = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// CSV template download
if(isset($_GET['download_template']) && $_GET['download_template'] === 'students_csv' && $is_manager){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_template.csv');
    // Use comma delimiter and ensure proper format
    echo "full_name,email,phone,gender,dob,address,enrollment_date\r\n";
    echo "John Doe,john.doe@example.com,123-456-7890,Male,2000-01-15,123 Main St,2024-09-01\r\n";
    echo "Jane Smith,jane.smith@example.com,123-456-7891,Female,2000-03-22,456 Oak Ave,2024-09-01\r\n";
    echo "Mike Johnson,mike.johnson@example.com,123-456-7892,Male,1999-11-08,789 Pine Rd,2024-09-01\r\n";
    exit;
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST' && $is_manager){
    // CSRF protection
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    if($action === 'add'){
        // Normalize inputs
        $student_id = strtoupper(trim($_POST['student_id']));
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $address = trim($_POST['address']);
        $enrollment_date = $_POST['enrollment_date'];

        // Pre-check for duplicates on student_id or email
        $dup_sql = "SELECT id, student_id, email FROM students WHERE LOWER(student_id) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1";
        if($dup_stmt = $conn->prepare($dup_sql)){
            $dup_stmt->bind_param('ss', $student_id, $email);
            $dup_stmt->execute();
            $dup = $dup_stmt->get_result()->fetch_assoc();
            $dup_stmt->close();
            if($dup){
                if(strtolower($dup['email']) === strtolower($email)){
                    $error_msg = 'Email already exists. Please use a different email.';
                    $open_add = true;
                } else {
                    // Auto-generate next unique student ID and proceed
                    $generated_id = generate_next_student_id($conn, $student_id);
                    $student_id = $generated_id;
                    // Proceed to insert with generated ID
                    $sql = "INSERT INTO students (student_id, full_name, email, phone, gender, dob, address, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ssssssss', $student_id, $full_name, $email, $phone, $gender, $dob, $address, $enrollment_date);
                    if($stmt->execute()){
                        $success_msg = 'Student added. Generated ID: ' . htmlspecialchars($student_id);
                    } else {
                        $error_msg = 'Error adding student.';
                        $open_add = true;
                    }
                }
            } else {
                // Proceed to insert
                $sql = "INSERT INTO students (student_id, full_name, email, phone, gender, dob, address, enrollment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssssss', $student_id, $full_name, $email, $phone, $gender, $dob, $address, $enrollment_date);
                if($stmt->execute()){
                    $success_msg = 'Student added successfully!';
                } else {
                    // Friendly duplicate handling as fallback
                    if($conn->errno === 1062){
                        if(strpos($conn->error, 'student_id') !== false){
                            $error_msg = 'Student ID already exists. Please use a unique ID.';
                        } elseif(strpos($conn->error, 'email') !== false){
                            $error_msg = 'Email already exists. Please use a different email.';
                        } else {
                            $error_msg = 'Duplicate entry detected.';
                        }
                        $open_add = true;
                    } else {
                        $error_msg = 'Error adding student.';
                    }
                }
            }
        } else {
            $error_msg = 'Database error. Please try again.';
        }
    }
    
    if($action === 'update'){
        $id = (int)$_POST['id'];
        $student_id = strtoupper(trim($_POST['student_id']));
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $address = trim($_POST['address']);
        $enrollment_date = $_POST['enrollment_date'];
        $status = $_POST['status'];
        
        $sql = "UPDATE students SET student_id = ?, full_name = ?, email = ?, phone = ?, gender = ?, dob = ?, address = ?, enrollment_date = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssssssssi', $student_id, $full_name, $email, $phone, $gender, $dob, $address, $enrollment_date, $status, $id);
        
        if($stmt->execute()){
            $success_msg = 'Student updated successfully!';
        } else {
            $error_msg = 'Error updating student.';
        }
    }
    
    if($action === 'delete'){
        $id = (int)$_POST['id'];
        $sql = "DELETE FROM students WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        
        if($stmt->execute()){
            $success_msg = 'Student deleted successfully!';
        } else {
            $error_msg = 'Error deleting student.';
        }
    }
    
    if($action === 'enroll_course'){
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
        $enrollment_date = $_POST['enrollment_date'];
        
        // Check if student is already enrolled in this course
        $check_sql = "SELECT id FROM student_enrollments WHERE student_id = ? AND course_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('ii', $student_id, $course_id);
        $check_stmt->execute();
        $existing_enrollment = $check_stmt->get_result()->fetch_assoc();
        
        if($existing_enrollment) {
            $error_msg = 'Student is already enrolled in this course.';
        } else {
            $sql = "INSERT INTO student_enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iis', $student_id, $course_id, $enrollment_date);
            
            if($stmt->execute()){
                $success_msg = 'Student enrolled in course successfully!';
            } else {
                $error_msg = 'Error enrolling student in course.';
            }
        }
    }
    
    if($action === 'unenroll_course'){
        $student_id = (int)$_POST['student_id'];
        $course_id = (int)$_POST['course_id'];
        
        $sql = "DELETE FROM student_enrollments WHERE student_id = ? AND course_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $student_id, $course_id);
        
        if($stmt->execute()){
            $success_msg = 'Student unenrolled from course successfully!';
        } else {
            $error_msg = 'Error unenrolling student from course.';
        }
    }
    
    if($action === 'add_marks'){
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
                $error_msg = 'Error adding marks.';
            }
        } else {
            $error_msg = 'Obtained marks cannot exceed total marks.';
        }
    }
    
    if($action === 'bulk_upload'){
        // Handle CSV upload of multiple students
        if(!isset($_FILES['students_file']) || $_FILES['students_file']['error'] !== UPLOAD_ERR_OK){
            $error_code = $_FILES['students_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            switch($error_code){
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $error_msg = 'File is too large. Maximum file size allowed is ' . ini_get('upload_max_filesize');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error_msg = 'File was only partially uploaded. Please try again.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error_msg = 'No file was selected. Please choose a CSV file.';
                    break;
                default:
                    $error_msg = 'Upload error occurred. Error code: ' . $error_code . '. Please try again.';
            }
        } else {
            $tmpPath = $_FILES['students_file']['tmp_name'];
            $origName = $_FILES['students_file']['name'] ?? '';
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            
            if($ext !== 'csv'){
                $error_msg = 'Invalid file type. Only .csv files are allowed. If you have an Excel file (.xlsx), please open it in Excel and use File → Save As → CSV (Comma delimited).';
            } else {
            $content = file_get_contents($tmpPath);
                if($content === false || strlen($content) === 0){
                    $error_msg = 'Unable to read uploaded file. The file may be empty or corrupted.';
            } else {
                // Normalize encoding and newlines
                    // Remove BOM if present
                    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
                    
                if(function_exists('mb_detect_encoding')){
                        $enc = mb_detect_encoding($content, ['UTF-8','ISO-8859-1','WINDOWS-1252','CP1252'], true);
                        if($enc && $enc !== 'UTF-8'){
                            $content = mb_convert_encoding($content, 'UTF-8', $enc);
                        } elseif(!$enc) {
                            // Try to convert from common encodings
                            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
                        }
                    }
                    
                    // Normalize line endings
                $content = preg_replace("/\r\n?|\n/", "\n", $content);
                    $lines = array_filter(array_map('trim', explode("\n", $content)), function($l){ return $l !== '' && trim($l) !== ''; });
                    
                if(empty($lines)){
                        $error_msg = 'CSV file appears to be empty or contains no valid data.';
                    } elseif(count($lines) < 2){
                        $error_msg = 'CSV file must contain at least a header row and one data row.';
                } else {
                        // Detect delimiter from header - try multiple delimiters
                        $headerLine = $lines[0];
                        // Try different delimiters (comma, semicolon, tab)
                        $delims = [',', ';', "\t"];
                        $bestDelim = ',';
                        $bestCount = 0;
                        
                        foreach($delims as $d){
                            // Test with actual delimiter
                            $testCount = substr_count($headerLine, $d);
                            // For tab, also try str_getcsv to see how many columns we get
                            if($d === "\t" || $testCount > 0){
                                $testHeaders = str_getcsv($headerLine, $d, '"');
                                $testCount = count($testHeaders);
                            }
                            
                            if($testCount > $bestCount){
                                $bestCount = $testCount;
                                $bestDelim = $d;
                            }
                        }
                        
                        // Try all delimiters and pick the one with most columns
                        foreach($delims as $d){
                            $testHeaders = str_getcsv($headerLine, $d, '"');
                            if(count($testHeaders) > $bestCount){
                                $bestCount = count($testHeaders);
                                $bestDelim = $d;
                            }
                        }
                        
                        // Parse header with best delimiter
                        $headers = str_getcsv($headerLine, $bestDelim, '"');
                        $startIdx = 0;
                        $joined = strtolower(implode(',', $headers));
                        
                        // Skip header row if it contains column names
                        if(strpos($joined, 'full') !== false || strpos($joined, 'email') !== false || strpos($joined, 'name') !== false || $bestCount >= 4){
                            $startIdx = 1;
                        }
                        
                        // Debug: Store detected delimiter for error messages
                        $detectedDelimiter = $bestDelim === ',' ? 'comma' : ($bestDelim === ';' ? 'semicolon' : ($bestDelim === "\t" ? 'tab' : 'unknown'));

                    // Determine current sequence
                        $prefix = 'STU';
                        $padLen = 3;
                        $maxnum = 0;
                    $res = $conn->query("SELECT student_id FROM students");
                        if($res){
                            while($r = $res->fetch_assoc()){
                                $sid = $r['student_id'];
                                if(preg_match('/^([A-Za-z]+)(\d+)$/', $sid, $m)){
                                    $num = intval($m[2]);
                                    if($num > $maxnum){
                                        $maxnum = $num;
                                        $prefix = $m[1];
                                        $padLen = strlen($m[2]);
                                    }
                                }
                            }
                        }

                    $insert = $conn->prepare("INSERT INTO students (student_id, full_name, email, phone, gender, dob, address, enrollment_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
                        if(!$insert){
                            $error_msg = 'Database error: ' . $conn->error;
                        } else {
                    $insert->bind_param('ssssssss', $genId, $full_name, $email, $phone, $gender, $dob, $address, $enrollment_date);

                            $successCount = 0;
                            $failCount = 0;
                            $errors = [];
                            
                            // First, let's test parsing the first few rows to confirm delimiter
                            // If header detection didn't work well, try data rows
                            $delims = [',', ';', "\t"];
                            if($startIdx < count($lines)){
                                $testRow = str_getcsv($lines[$startIdx], $bestDelim, '"');
                                if(count($testRow) < 4){
                                    // Try all delimiters on first data row
                                    foreach($delims as $testDelim){
                                        $testRow2 = str_getcsv($lines[$startIdx], $testDelim, '"');
                                        if(count($testRow2) >= 4){
                                            $bestDelim = $testDelim;
                                            $detectedDelimiter = $testDelim === ',' ? 'comma' : ($testDelim === ';' ? 'semicolon' : 'tab');
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            for($i = $startIdx; $i < count($lines); $i++){
                                $row = str_getcsv($lines[$i], $bestDelim, '"');
                                
                                // Handle rows with fewer columns
                                $expectedColumns = ['full_name', 'email', 'phone', 'gender', 'dob', 'address', 'enrollment_date'];
                                $requiredColumns = ['full_name', 'email', 'gender', 'dob'];
                                
                                if(count($row) < 7){
                                    $failCount++;
                                    if(count($errors) < 10){
                                        $rowRaw = substr($lines[$i], 0, 200); // First 200 chars for debugging
                                        
                                        // Determine which columns are missing
                                        $foundColumns = [];
                                        $missingColumns = [];
                                        
                                        if(count($row) >= 1 && !empty(trim($row[0] ?? ''))){
                                            $foundColumns[] = 'Column 1: "' . trim($row[0]) . '" (could be full_name)';
                                        }
                                        if(count($row) >= 2 && !empty(trim($row[1] ?? ''))){
                                            $foundColumns[] = 'Column 2: "' . trim($row[1]) . '"';
                                        }
                                        if(count($row) >= 3 && !empty(trim($row[2] ?? ''))){
                                            $foundColumns[] = 'Column 3: "' . trim($row[2]) . '"';
                                        }
                                        
                                        // Missing columns based on what we found
                                        if(count($row) < 7){
                                            $missingCount = 7 - count($row);
                                            if(count($row) === 2){
                                                $missingColumns = ['email', 'phone', 'gender', 'dob (YYYY-MM-DD)', 'address', 'enrollment_date (YYYY-MM-DD)'];
                                            } elseif(count($row) === 1){
                                                $missingColumns = ['email', 'phone', 'gender', 'dob (YYYY-MM-DD)', 'address', 'enrollment_date (YYYY-MM-DD)'];
                                                // First column might be a number, not the name
                                            } else {
                                                $missingColumns = array_slice($expectedColumns, count($row));
                                            }
                                        }
                                        
                                        $errorMsg = 'Row ' . ($i + 1) . ': Found only ' . count($row) . ' column(s), need 7. ';
                                        $errorMsg .= 'Found: ' . (count($foundColumns) > 0 ? implode(' | ', array_slice($foundColumns, 0, 2)) : 'empty or invalid columns') . '. ';
                                        $errorMsg .= 'Missing columns: ' . implode(', ', array_slice($missingColumns, 0, 4));
                                        if(count($missingColumns) > 4) $errorMsg .= ' (and ' . (count($missingColumns) - 4) . ' more)';
                                        
                                        $errorMsg .= ' Required format: full_name,email,phone,gender,dob,address,enrollment_date';
                                        
                                        $errors[] = $errorMsg;
                                    }
                                    continue;
                                }
                                
                        $full_name = trim($row[0] ?? '');
                        $email = trim($row[1] ?? '');
                        $phone = trim($row[2] ?? '');
                        $gender = trim($row[3] ?? '');
                                
                                // Normalize gender
                                $genderLower = strtolower($gender);
                                if($genderLower === 'm' || $genderLower === 'male'){
                                    $gender = 'Male';
                                } elseif($genderLower === 'f' || $genderLower === 'female'){
                                    $gender = 'Female';
                                } elseif($genderLower !== 'other'){
                                    // Default to Male if not recognized
                                    $gender = in_array($genderLower, ['other', 'o']) ? 'Other' : 'Male';
                                }
                                
                        $dob = trim($row[4] ?? '');
                        $address = trim($row[5] ?? '');
                        $enrollment_date = trim($row[6] ?? '');
                                
                                // Validate date formats
                                if($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)){
                                    // Try to convert common date formats
                                    $dobTimestamp = strtotime($dob);
                                    if($dobTimestamp){
                                        $dob = date('Y-m-d', $dobTimestamp);
                                    } else {
                                        $failCount++;
                                        if(count($errors) < 10){
                                            $errors[] = 'Row ' . ($i + 1) . ': invalid date format for DOB: ' . htmlspecialchars($dob);
                                        }
                                        continue;
                                    }
                                }
                                
                                if($enrollment_date === ''){
                                    $enrollment_date = date('Y-m-d');
                                } elseif(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $enrollment_date)){
                                    $enrollTimestamp = strtotime($enrollment_date);
                                    if($enrollTimestamp){
                                        $enrollment_date = date('Y-m-d', $enrollTimestamp);
                                    } else {
                                        $enrollment_date = date('Y-m-d');
                                    }
                                }
                                
                                // Validate required fields
                                if($full_name === '' || $email === '' || $gender === '' || $dob === ''){
                                    $failCount++;
                                    if(count($errors) < 10){
                                        $missing = [];
                                        if($full_name === '') $missing[] = 'full_name';
                                        if($email === '') $missing[] = 'email';
                                        if($gender === '') $missing[] = 'gender';
                                        if($dob === '') $missing[] = 'dob';
                                        $errors[] = 'Row ' . ($i + 1) . ': missing required fields (' . implode(', ', $missing) . ')';
                                    }
                                    continue;
                                }
                                
                                // Validate email format
                                if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
                                    $failCount++;
                                    if(count($errors) < 10){
                                        $errors[] = 'Row ' . ($i + 1) . ': invalid email format: ' . htmlspecialchars($email);
                                    }
                                    continue;
                                }
                                
                                // Generate student ID
                                $maxnum++;
                                $genId = $prefix . str_pad((string)$maxnum, $padLen, '0', STR_PAD_LEFT);
                                
                                // Execute insert
                        if(!$insert->execute()){
                                    $failCount++;
                                    $errorMsg = $insert->error;
                                    if(count($errors) < 10){
                                        if(strpos($errorMsg, 'Duplicate entry') !== false || strpos($errorMsg, 'email') !== false){
                                            $errors[] = 'Row ' . ($i + 1) . ': email already exists: ' . htmlspecialchars($email);
                                        } elseif(strpos($errorMsg, 'student_id') !== false){
                                            $errors[] = 'Row ' . ($i + 1) . ': student ID conflict';
                                        } else {
                                            $errors[] = 'Row ' . ($i + 1) . ': ' . htmlspecialchars($errorMsg);
                                        }
                                    }
                                } else {
                                    $successCount++;
                                }
                            }
                            
                            $insert->close();
                            
                            if($successCount > 0 || $failCount > 0){
                                $detail = '';
                                if(!empty($errors)){
                                    $detail = ' Errors: ' . implode(' | ', array_slice($errors, 0, 5));
                                    if(count($errors) > 5){
                                        $detail .= ' (and ' . (count($errors) - 5) . ' more)';
                                    }
                                    
                                    // Add helpful tip if delimiter issue
                                    if(strpos($detail, 'insufficient columns') !== false){
                                        $detail .= ' TIP: If you\'re seeing "insufficient columns" errors, your CSV might be using a different delimiter. In Excel, try: File → Save As → Choose "CSV (Comma delimited) (*.csv)" not "CSV UTF-8" or other formats.';
                                    }
                                }
                                if($successCount > 0 && $failCount === 0){
                                    $success_msg = "Successfully uploaded $successCount student(s)!";
                                } else {
                                    $success_msg = "Upload completed: $successCount student(s) added, $failCount failed.$detail";
                                }
                            } else {
                                $error_msg = 'No students were added. Detected delimiter: ' . $detectedDelimiter . '. ';
                                $error_msg .= 'Expected format: full_name, email, phone, gender, dob (YYYY-MM-DD), address, enrollment_date (YYYY-MM-DD). ';
                                $error_msg .= 'TIP: If using Excel, use File → Save As → "CSV (Comma delimited) (*.csv)" format.';
                                if(!empty($errors)){
                                    $error_msg .= ' Errors: ' . implode(' | ', array_slice($errors, 0, 3));
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Fetch students
$result = $conn->query("SELECT * FROM students ORDER BY created_at DESC");

// Fetch student enrollments with course and marks data
$enrollments_sql = "SELECT 
    s.id as student_id,
    s.student_id as student_number,
    s.full_name,
    c.id as course_id,
    c.course_name,
    c.course_code,
    se.enrollment_date,
    se.status as enrollment_status,
    se.final_grade,
    COUNT(m.id) as total_assessments,
    COALESCE(AVG(m.obtained_marks), 0) as average_marks,
    COALESCE(SUM(m.obtained_marks), 0) as total_marks
FROM students s
LEFT JOIN student_enrollments se ON s.id = se.student_id
LEFT JOIN courses c ON se.course_id = c.id
LEFT JOIN assessments a ON c.id = a.course_id
LEFT JOIN marks m ON s.id = m.student_id AND a.id = m.assessment_id
GROUP BY s.id, c.id, se.id
ORDER BY s.full_name, c.course_name";

$enrollments_result = $conn->query($enrollments_sql);
$enrollments_data = [];
while($row = $enrollments_result->fetch_assoc()) {
    $enrollments_data[$row['student_id']][] = $row;
}

// Get courses for enrollment form (show ALL registered courses)
$courses_result = $conn->query("SELECT id, course_name, course_code, status FROM courses ORDER BY course_name");

// Get assessments for marks form
$assessments_result = $conn->query("SELECT a.id, a.title, a.total_marks, c.course_name, c.course_code 
                                   FROM assessments a 
                                   JOIN courses c ON a.course_id = c.id 
                                   ORDER BY c.course_name, a.title");
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
            <h1><i class="fas fa-users"></i> Students</h1>
            <?php if($is_manager): ?>
            <div style="display:flex; gap:0.5rem; flex-wrap: wrap;">
                <a href="?open_add=1" onclick="showAddForm(); return false;" class="btn btn-primary" style="text-decoration:none;">
                    <i class="fas fa-plus"></i> Add Student
                </a>
                <a href="?open_upload=1" onclick="showUploadModal(); return false;" class="btn btn-success" style="text-decoration:none;">
                    <i class="fas fa-file-upload"></i> Upload CSV
                </a>
                <a href="attendance.php" class="btn" style="background: var(--warning-color); color: white; text-decoration: none;">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
                <a href="fee_payments.php" class="btn" style="background: #10b981; color: white; text-decoration: none; border: none;">
                    <i class="fas fa-money-bill-wave"></i> Fee Payments
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if(isset($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
        <?php endif; ?>
        
        <div class="mobile-table-wrapper">
        <table class="table data-table desktop-only">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Gender</th>
                    <th>DOB</th>
                    <th>Status</th>
                    <th>Enrolled</th>
                    <?php if($is_manager): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['student_id']) ?></strong></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= $row['gender'] ?></td>
                    <td><?= date('M j, Y', strtotime($row['dob'])) ?></td>
                    <td>
                        <span class="badge badge-<?= $row['status'] === 'Active' ? 'success' : 'secondary' ?>">
                            <?= $row['status'] ?>
                        </span>
                    </td>
                    <td><?= date('M j, Y', strtotime($row['enrollment_date'])) ?></td>
                    <?php if($is_manager): ?>
                    <td>
                        <a href="?edit_id=<?= (int)$row['id'] ?>" onclick='editStudent(<?= json_encode($row, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>); return false;' class="btn btn-secondary btn-sm" style="text-decoration:none;">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete_id=<?= (int)$row['id'] ?>" onclick="deleteStudent(<?= (int)$row['id'] ?>); return false;" class="btn btn-danger btn-sm" style="text-decoration:none;">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Course Enrollments Section -->
<div class="container" style="margin-top: 2rem;">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2><i class="fas fa-graduation-cap"></i> Student Course Enrollments & Marks</h2>
            <small style="color: var(--secondary-color);">View each student's enrolled courses and their performance</small>
        </div>
        
        <?php foreach($enrollments_data as $student_id => $enrollments): 
            $student_info = null;
            foreach($enrollments as $enrollment) {
                if($enrollment['student_id'] == $student_id) {
                    $student_info = $enrollment;
                    break;
                }
            }
            if(!$student_info) continue;
        ?>
        <div class="student-enrollment-card" style="margin-bottom: 2rem; padding: 1.5rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin: 0; color: var(--primary-color);">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($student_info['full_name']) ?>
                    </h3>
                    <p style="margin: 0.25rem 0 0 0; color: var(--secondary-color); font-size: 0.875rem;">
                        Student ID: <?= htmlspecialchars($student_info['student_number']) ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <span class="badge badge-primary"><?= count($enrollments) ?> Course<?= count($enrollments) !== 1 ? 's' : '' ?></span>
                    <?php if($is_manager): ?>
                    <a href="enroll_course.php?student_id=<?= $student_id ?>" class="btn btn-success btn-sm" style="margin-left: 0.5rem; text-decoration:none;">
                        <i class="fas fa-plus"></i> Enroll Course
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if(empty($enrollments) || !$enrollments[0]['course_id']): ?>
            <div style="text-align: center; padding: 2rem; color: var(--secondary-color);">
                <i class="fas fa-book-open" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>No courses enrolled</p>
            </div>
            <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <?php foreach($enrollments as $enrollment): 
                    if(!$enrollment['course_id']) continue;
                ?>
                <div style="background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                        <div>
                            <h4 style="margin: 0; color: var(--dark-color); font-size: 1rem;">
                                <?= htmlspecialchars($enrollment['course_name']) ?>
                            </h4>
                            <p style="margin: 0.25rem 0 0 0; color: var(--secondary-color); font-size: 0.75rem;">
                                <?= htmlspecialchars($enrollment['course_code']) ?>
                            </p>
                        </div>
                        <span class="badge badge-<?= $enrollment['enrollment_status'] === 'Enrolled' ? 'success' : ($enrollment['enrollment_status'] === 'Completed' ? 'primary' : 'secondary') ?>">
                            <?= $enrollment['enrollment_status'] ?>
                        </span>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div style="text-align: center; padding: 0.5rem; background: #f1f5f9; border-radius: 6px;">
                            <div style="font-size: 1.25rem; font-weight: 600; color: var(--primary-color);">
                                <?= $enrollment['total_assessments'] ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--secondary-color);">Assessments</div>
                        </div>
                        <div style="text-align: center; padding: 0.5rem; background: #f1f5f9; border-radius: 6px;">
                            <div style="font-size: 1.25rem; font-weight: 600; color: var(--success-color);">
                                <?= number_format($enrollment['average_marks'], 1) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--secondary-color);">Avg Marks</div>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; margin-bottom: 0.75rem;">
                        <span style="color: var(--secondary-color);">
                            Enrolled: <?= date('M j, Y', strtotime($enrollment['enrollment_date'])) ?>
                        </span>
                        <?php if($enrollment['final_grade']): ?>
                        <span class="badge badge-primary">Grade: <?= $enrollment['final_grade'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($is_manager): ?>
                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button onclick="addMarks(<?= $enrollment['student_id'] ?>, <?= $enrollment['course_id'] ?>)" 
                                class="btn btn-primary btn-sm" title="Add Marks">
                            <i class="fas fa-plus"></i> Marks
                        </button>
                        <button onclick="unenrollStudent(<?= $enrollment['student_id'] ?>, <?= $enrollment['course_id'] ?>, '<?= htmlspecialchars($enrollment['course_name']) ?>')" 
                                class="btn btn-danger btn-sm" title="Unenroll">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($enrollments_data)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--secondary-color);">
            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>No Students Found</h3>
            <p>Add some students to see their course enrollments and marks.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if($is_manager): ?>
<!-- Add Student Modal -->
<div id="addModal" style="display: <?= $open_add ? 'block' : 'none' ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;" onclick="hideModal('addModal')">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;" onclick="event.stopPropagation()">
        <h3>Add New Student</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="student_id" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" class="form-input">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-input" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Enrollment Date</label>
                    <input type="date" name="enrollment_date" class="form-input" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-input" rows="3"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="student.php" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Add Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Upload Modal -->
<div id="uploadModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;" onclick="hideModal('uploadModal')">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 520px; max-height: 90vh; overflow-y: auto;" onclick="event.stopPropagation()">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="margin: 0;"><i class="fas fa-file-upload"></i> Upload Students (CSV)</h3>
            <button onclick="hideModal('uploadModal')" style="background: none; border: none; font-size: 1.5rem; color: var(--secondary-color); cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <p style="margin: 0 0 0.5rem 0; color: var(--dark-color); font-weight: 600;">
                <i class="fas fa-info-circle"></i> CSV Format Requirements - <span style="color: #dc2626;">REQUIRES 7 COLUMNS</span>:
            </p>
            <p style="margin: 0 0 0.5rem 0; color: var(--secondary-color); font-size: 0.875rem;">
                <strong>ALL columns required (in this exact order):</strong><br>
                <code style="background: white; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.25rem;">
                    full_name,email,phone,gender,dob,address,enrollment_date
                </code>
            </p>
            <p style="margin: 0 0 0.5rem 0; color: var(--secondary-color); font-size: 0.875rem;">
                <strong>Complete example row:</strong><br>
                <code style="background: white; padding: 0.25rem 0.5rem; border-radius: 4px; display: inline-block; margin-top: 0.25rem;">
                    John Doe,john@example.com,123-456-7890,Male,2000-01-15,123 Main St,2024-09-01
                </code>
            </p>
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 0.75rem; border-radius: 6px; margin-top: 0.75rem;">
                <p style="margin: 0; color: #856404; font-size: 0.875rem;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Common Issue:</strong> If your CSV only has 2 columns (like "1,Name"), you're missing 5 required columns. 
                    You MUST include all 7 columns: name, email, phone, gender, date of birth, address, and enrollment date.
                </p>
            </div>
            <p style="margin: 0.75rem 0 0 0; color: var(--secondary-color); font-size: 0.875rem;">
                <strong>Notes:</strong> Gender can be "Male"/"M", "Female"/"F", or "Other". Dates must be YYYY-MM-DD format. Use COMMA (,) as delimiter.
            </p>
        </div>
        <p style="color: var(--secondary-color); margin-bottom: 1rem; font-size: 0.875rem;">
            <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> If your file is in Excel (.xlsx), open it in Excel and choose <strong>File → Save As → CSV (Comma delimited)</strong> format.
            <br><a href="?download_template=students_csv" style="color: var(--primary-color); text-decoration: underline;"><i class="fas fa-download"></i> Download CSV Template</a>
        </p>
        <form method="post" enctype="multipart/form-data" id="csvUploadForm" style="margin-top:1rem;" onsubmit="return validateCsvUpload()">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="bulk_upload">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-file-csv"></i> Select CSV File *</label>
                <input type="file" name="students_file" id="csvFileInput" class="form-input" accept=".csv" required onchange="validateFileExtension(this)">
                <small id="fileHelpText" style="color: var(--secondary-color); display: block; margin-top: 0.5rem;">Only .csv files are allowed</small>
            </div>
            <div id="uploadStatus" style="display: none; margin: 1rem 0; padding: 1rem; border-radius: 8px; background: #f0f9ff; border: 1px solid #bae6fd;">
                <p style="margin: 0; color: var(--dark-color);"><i class="fas fa-spinner fa-spin"></i> Uploading and processing file...</p>
            </div>
            <div style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top: 1.5rem;">
                <a href="dashboard.php" class="btn btn-secondary" style="text-decoration:none;">Back to Dashboard</a>
                <button type="button" class="btn btn-secondary" onclick="hideModal('uploadModal')">Cancel</button>
                <button type="submit" class="btn btn-success" id="uploadSubmitBtn">
                    <i class="fas fa-upload"></i> Upload CSV
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div id="editModal" style="display: <?= ($open_edit_id && $edit_student) ? 'block' : 'none' ?>; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <h3>Edit Student</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="student_id" id="editStudentId" class="form-input" required value="<?= isset($edit_student['student_id']) ? htmlspecialchars($edit_student['student_id']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" id="editFullName" class="form-input" required value="<?= isset($edit_student['full_name']) ? htmlspecialchars($edit_student['full_name']) : '' ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-input" required value="<?= isset($edit_student['email']) ? htmlspecialchars($edit_student['email']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" name="phone" id="editPhone" class="form-input" value="<?= isset($edit_student['phone']) ? htmlspecialchars($edit_student['phone']) : '' ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" id="editGender" class="form-input" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= (isset($edit_student['gender']) && $edit_student['gender']==='Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= (isset($edit_student['gender']) && $edit_student['gender']==='Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= (isset($edit_student['gender']) && $edit_student['gender']==='Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date of Birth</label>
                    <input type="date" name="dob" id="editDob" class="form-input" required value="<?= isset($edit_student['dob']) ? htmlspecialchars($edit_student['dob']) : '' ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Enrollment Date</label>
                    <input type="date" name="enrollment_date" id="editEnrollmentDate" class="form-input" required value="<?= isset($edit_student['enrollment_date']) ? htmlspecialchars($edit_student['enrollment_date']) : '' ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-input" required>
                        <option value="Active" <?= (isset($edit_student['status']) && $edit_student['status']==='Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= (isset($edit_student['status']) && $edit_student['status']==='Inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="Graduated" <?= (isset($edit_student['status']) && $edit_student['status']==='Graduated') ? 'selected' : '' ?>>Graduated</option>
                        <option value="Suspended" <?= (isset($edit_student['status']) && $edit_student['status']==='Suspended') ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" id="editAddress" class="form-input" rows="3"><?= isset($edit_student['address']) ? htmlspecialchars($edit_student['address']) : '' ?></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="student.php" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteStudentId">
</form>

<!-- Delete Confirmation (Server-rendered, no JS required) -->
<?php if($delete_id && $is_manager): ?>
<div id="deleteConfirmModal" style="display: block; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 1.5rem; border-radius: 12px; width: 90%; max-width: 420px;">
        <h3 style="margin-top:0;">Confirm Delete</h3>
        <p>Are you sure you want to delete this student (ID: <?= (int)$delete_id ?>)? This action cannot be undone.</p>
        <form method="post" style="display:flex; gap:0.5rem; justify-content:flex-end; margin-top:1rem;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$delete_id ?>">
            <a href="student.php" class="btn btn-secondary" style="text-decoration:none;">Cancel</a>
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Enroll Course Modal -->
<div id="enrollModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px;">
        <h3>Enroll Student in Course</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="enroll_course">
            <input type="hidden" name="student_id" id="enrollStudentId">
            
            <div class="form-group">
                <label class="form-label">Course</label>
                <select name="course_id" id="enrollCourseSelect" class="form-input" required>
                    <option value="">Select Course</option>
                    <?php 
                    $courses_result->data_seek(0); // Reset result pointer
                    while($course = $courses_result->fetch_assoc()): ?>
                    <option value="<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>">
                        <?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
                <small id="enrollmentHelp" style="color: var(--secondary-color); display: none;">
                    This student is already enrolled in some courses. Only available courses are shown.
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Enrollment Date</label>
                <input type="date" name="enrollment_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="dashboard.php" class="btn btn-secondary" style="text-decoration:none;">Back to Dashboard</a>
                <button type="button" onclick="hideModal('enrollModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Enroll Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Marks Modal -->
<div id="marksModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 500px;">
        <h3>Add Student Marks</h3>
        <form method="post" data-validate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add_marks">
            <input type="hidden" name="student_id" id="marksStudentId">
            <input type="hidden" name="course_id" id="marksCourseId">
            
            <div class="form-group">
                <label class="form-label">Assessment</label>
                <select name="assessment_id" id="marksAssessmentId" class="form-input" required>
                    <option value="">Select Assessment</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Obtained Marks</label>
                <input type="number" name="obtained_marks" id="marksObtainedMarks" class="form-input" step="0.01" min="0" required>
                <small id="marksMaxMarks" style="color: var(--secondary-color);"></small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-input" rows="3" placeholder="Optional remarks"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="dashboard.php" class="btn btn-secondary" style="text-decoration:none;">Back to Dashboard</a>
                <button type="button" onclick="hideModal('marksModal')" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Marks</button>
            </div>
        </form>
    </div>
</div>

<!-- Unenroll Form -->
<form id="unenrollForm" method="post" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <input type="hidden" name="action" value="unenroll_course">
    <input type="hidden" name="student_id" id="unenrollStudentId">
    <input type="hidden" name="course_id" id="unenrollCourseId">
</form>

<script>
// Universal modal hide function
function hideModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) el.style.display = 'none';
    // Clean URL flags that auto-open modals
    try {
        const url = new URL(window.location.href);
        ['open_add','open_upload','edit_id','delete_id'].forEach(k => url.searchParams.delete(k));
        window.history.replaceState({}, document.title, url.toString());
    } catch (e) {}
}

function cancelRegistration() {
    try {
        // Close any open modals and reset their forms
        ['addModal','editModal','uploadModal'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.style.display !== 'none') {
                const form = el.querySelector('form');
                if (form) form.reset();
                hideModal(id);
            }
        });

        // Prefer explicit referrer within same origin, else history back, else dashboard
        const ref = document.referrer;
        if (ref) {
            try {
                const r = new URL(ref);
                if (r.origin === window.location.origin) {
                    window.location.href = ref;
                    return;
                }
            } catch (_) {}
        }

        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'dashboard.php';
        }
    } catch (e) {
        window.location.href = 'dashboard.php';
    }
}

function showAddForm() {
    document.getElementById('addModal').style.display = 'block';
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('open_add','1');
        window.history.replaceState({}, document.title, url.toString());
    } catch (e) {}
}

function showUploadModal(){
    const modal = document.getElementById('uploadModal');
    modal.style.display = 'block';
    // Reset form
    document.getElementById('csvUploadForm').reset();
    document.getElementById('uploadStatus').style.display = 'none';
    document.getElementById('fileHelpText').style.color = 'var(--secondary-color)';
    document.getElementById('fileHelpText').textContent = 'Only .csv files are allowed';
}

function validateFileExtension(input) {
    const file = input.files[0];
    const helpText = document.getElementById('fileHelpText');
    
    if (!file) {
        helpText.textContent = 'Only .csv files are allowed';
        helpText.style.color = 'var(--secondary-color)';
        return;
    }
    
    const fileName = file.name.toLowerCase();
    const extension = fileName.substring(fileName.lastIndexOf('.') + 1);
    
    if (extension !== 'csv') {
        helpText.textContent = 'Error: Please select a .csv file. Excel files (.xlsx) must be converted to CSV first.';
        helpText.style.color = '#dc2626';
        input.value = '';
        return false;
    } else {
        helpText.textContent = `Selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
        helpText.style.color = '#10b981';
        return true;
    }
}

function validateCsvUpload() {
    const fileInput = document.getElementById('csvFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file to upload.');
        return false;
    }
    
    const fileName = file.name.toLowerCase();
    const extension = fileName.substring(fileName.lastIndexOf('.') + 1);
    
    if (extension !== 'csv') {
        alert('Error: Only .csv files are allowed. If you have an Excel file (.xlsx), please:\n1. Open it in Excel\n2. Go to File → Save As\n3. Choose "CSV (Comma delimited)" format\n4. Save and upload that file.');
        return false;
    }
    
    // Show upload status
    document.getElementById('uploadStatus').style.display = 'block';
    document.getElementById('uploadSubmitBtn').disabled = true;
    document.getElementById('uploadSubmitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    return true;
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = ['addModal', 'uploadModal', 'editModal', 'enrollModal', 'marksModal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal && modal.style.display === 'block') {
                hideModal(modalId);
            }
        });
    }
});

function editStudent(student) {
    document.getElementById('editId').value = student.id;
    document.getElementById('editStudentId').value = student.student_id;
    document.getElementById('editFullName').value = student.full_name;
    document.getElementById('editEmail').value = student.email;
    document.getElementById('editPhone').value = student.phone || '';
    document.getElementById('editGender').value = student.gender;
    document.getElementById('editDob').value = student.dob;
    document.getElementById('editEnrollmentDate').value = student.enrollment_date;
    document.getElementById('editStatus').value = student.status;
    document.getElementById('editAddress').value = student.address || '';
    
    document.getElementById('editModal').style.display = 'block';
}

function deleteStudent(studentId) {
    if(confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
        document.getElementById('deleteStudentId').value = studentId;
        document.getElementById('deleteForm').submit();
    }
}

function enrollStudent(studentId) {
    document.getElementById('enrollStudentId').value = studentId;
    
    // Get enrolled courses for this student
    const enrolledCourses = [];
    <?php 
    // Get enrolled courses for each student
    $enrolled_courses_sql = "SELECT student_id, course_id FROM student_enrollments";
    $enrolled_result = $conn->query($enrolled_courses_sql);
    $enrolled_data = [];
    while($row = $enrolled_result->fetch_assoc()) {
        $enrolled_data[$row['student_id']][] = $row['course_id'];
    }
    ?>
    
    const enrolledCoursesData = <?= json_encode($enrolled_data) ?>;
    const studentEnrolledCourses = enrolledCoursesData[studentId] || [];
    
    // Filter course options
    const courseSelect = document.getElementById('enrollCourseSelect');
    const allOptions = courseSelect.querySelectorAll('option[data-course-id]');
    let availableCourses = 0;
    
    allOptions.forEach(option => {
        const courseId = parseInt(option.dataset.courseId);
        if(studentEnrolledCourses.includes(courseId)) {
            option.style.display = 'none';
            option.disabled = true;
        } else {
            option.style.display = 'block';
            option.disabled = false;
            availableCourses++;
        }
    });
    
    // Show help text if some courses are hidden
    const helpText = document.getElementById('enrollmentHelp');
    if(studentEnrolledCourses.length > 0) {
        helpText.style.display = 'block';
    } else {
        helpText.style.display = 'none';
    }
    
    // Reset selection
    courseSelect.value = '';
    
    document.getElementById('enrollModal').style.display = 'block';
}

function addMarks(studentId, courseId) {
    document.getElementById('marksStudentId').value = studentId;
    document.getElementById('marksCourseId').value = courseId;
    
    // Filter assessments for this course
    const assessmentSelect = document.getElementById('marksAssessmentId');
    assessmentSelect.innerHTML = '<option value="">Select Assessment</option>';
    
    <?php 
    $assessments_result->data_seek(0); // Reset result pointer
    while($assessment = $assessments_result->fetch_assoc()): ?>
    if(<?= $assessment['course_id'] ?> == courseId) {
        const option = document.createElement('option');
        option.value = '<?= $assessment['id'] ?>';
        option.textContent = '<?= htmlspecialchars($assessment['title']) ?> (<?= $assessment['total_marks'] ?> marks)';
        option.dataset.total = '<?= $assessment['total_marks'] ?>';
        assessmentSelect.appendChild(option);
    }
    <?php endwhile; ?>
    
    document.getElementById('marksModal').style.display = 'block';
}

function unenrollStudent(studentId, courseId, courseName) {
    if(confirm(`Are you sure you want to unenroll this student from "${courseName}"? This will also delete all associated marks.`)) {
        document.getElementById('unenrollStudentId').value = studentId;
        document.getElementById('unenrollCourseId').value = courseId;
        document.getElementById('unenrollForm').submit();
    }
}

// Update max marks when assessment is selected in marks modal
document.addEventListener('DOMContentLoaded', function() {
    const marksAssessmentSelect = document.getElementById('marksAssessmentId');
    if(marksAssessmentSelect) {
        marksAssessmentSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const maxMarks = selectedOption.dataset.total;
            const maxMarksElement = document.getElementById('marksMaxMarks');
            const marksInput = document.getElementById('marksObtainedMarks');
            
            if(maxMarks) {
                maxMarksElement.textContent = `Maximum marks: ${maxMarks}`;
                marksInput.max = maxMarks;
            } else {
                maxMarksElement.textContent = '';
                marksInput.removeAttribute('max');
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

.badge-secondary {
    background: #f1f5f9;
    color: #475569;
}

.badge-primary {
    background: #dbeafe;
    color: #1e40af;
}

.student-enrollment-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.student-enrollment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
    .student-enrollment-card {
        margin-bottom: 1rem;
        padding: 1rem;
    }
    
    .student-enrollment-card h3 {
        font-size: 1.1rem;
    }
    
    .student-enrollment-card h4 {
        font-size: 0.9rem;
    }
}
</style>

<?php if(($open_add || $open_upload || $open_edit_id || $delete_id || $open_enroll) && $is_manager): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    <?php if($open_add): ?>
    if (typeof showAddForm === 'function') { showAddForm(); }
    <?php endif; ?>

    <?php if($open_upload): ?>
    if (typeof showUploadModal === 'function') { showUploadModal(); }
    <?php endif; ?>

    <?php if($open_edit_id && $edit_student): ?>
    if (typeof editStudent === 'function') {
        editStudent(<?= json_encode($edit_student, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);
    }
    <?php endif; ?>

    <?php if($delete_id): ?>
    if (typeof deleteStudent === 'function') { deleteStudent(<?= (int)$delete_id ?>); }
    <?php endif; ?>

    <?php if($open_enroll && $open_enroll_student_id): ?>
    if (typeof enrollStudent === 'function') { enrollStudent(<?= (int)$open_enroll_student_id ?>); }
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>