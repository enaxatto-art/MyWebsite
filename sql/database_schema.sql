-- Student Portal Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS student_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_portal;

-- Admins table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('manager_admin', 'student_admin') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    address TEXT,
    enrollment_date DATE NOT NULL,
    status ENUM('Active', 'Inactive', 'Graduated', 'Suspended') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses table
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    credits INT DEFAULT 3,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Assessments table
CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    type ENUM('Quiz', 'Assignment', 'Midterm', 'Final', 'Project') NOT NULL,
    total_marks INT NOT NULL,
    weight DECIMAL(5,2) DEFAULT 0.00,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Marks table
CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    assessment_id INT NOT NULL,
    obtained_marks DECIMAL(5,2) NOT NULL,
    grade VARCHAR(2),
    remarks TEXT,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES admins(id),
    UNIQUE KEY unique_student_assessment (student_id, assessment_id)
);

-- Student enrollments table
CREATE TABLE student_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('Enrolled', 'Completed', 'Dropped', 'Failed') DEFAULT 'Enrolled',
    final_grade VARCHAR(2),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_course (student_id, course_id)
);

-- Attendance table (per-day status per student per course)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
    remarks VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_student_course_date (student_id, course_id, attendance_date)
);

-- Login attempts table (for security)
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE
);

-- Insert default admin accounts
INSERT INTO admins (username, password, role, full_name, email) VALUES
('manager', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager_admin', 'Manager Admin', 'manager@example.com'),
('student_admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student_admin', 'Student Admin', 'studentadmin@example.com');

-- Insert sample courses
INSERT INTO courses (course_code, course_name, description, credits) VALUES
('CS101', 'Introduction to Computer Science', 'Basic concepts of computer science and programming', 3),
('MATH101', 'Calculus I', 'Differential and integral calculus', 4),
('ENG101', 'English Composition', 'Academic writing and communication skills', 3),
('PHYS101', 'Physics I', 'Mechanics and thermodynamics', 4),
('CHEM101', 'Chemistry I', 'General chemistry principles', 4);

-- Insert sample students
INSERT INTO students (student_id, full_name, email, phone, gender, dob, address, enrollment_date) VALUES
('STU001', 'John Doe', 'john.doe@example.com', '123-456-7890', 'Male', '2000-01-15', '123 Main St, City, State', '2023-09-01'),
('STU002', 'Jane Smith', 'jane.smith@example.com', '123-456-7891', 'Female', '2000-03-22', '456 Oak Ave, City, State', '2023-09-01'),
('STU003', 'Mike Johnson', 'mike.johnson@example.com', '123-456-7892', 'Male', '1999-11-08', '789 Pine Rd, City, State', '2023-09-01'),
('STU004', 'Sarah Wilson', 'sarah.wilson@example.com', '123-456-7893', 'Female', '2000-07-14', '321 Elm St, City, State', '2023-09-01'),
('STU005', 'David Brown', 'david.brown@example.com', '123-456-7894', 'Male', '1999-12-30', '654 Maple Dr, City, State', '2023-09-01');

-- Insert sample assessments
INSERT INTO assessments (course_id, title, type, total_marks, weight, due_date) VALUES
(1, 'Programming Basics Quiz', 'Quiz', 20, 10.00, '2023-10-15'),
(1, 'First Programming Assignment', 'Assignment', 50, 20.00, '2023-10-30'),
(1, 'Midterm Exam', 'Midterm', 100, 30.00, '2023-11-15'),
(1, 'Final Project', 'Project', 100, 40.00, '2023-12-15'),
(2, 'Calculus Quiz 1', 'Quiz', 25, 15.00, '2023-10-20'),
(2, 'Calculus Midterm', 'Midterm', 100, 35.00, '2023-11-20'),
(2, 'Calculus Final', 'Final', 100, 50.00, '2023-12-20');

-- Insert sample marks
INSERT INTO marks (student_id, assessment_id, obtained_marks, grade, recorded_by) VALUES
(1, 1, 18.00, 'A', 1),
(1, 2, 45.00, 'A', 1),
(1, 3, 85.00, 'B', 1),
(2, 1, 16.00, 'B', 1),
(2, 2, 42.00, 'B', 1),
(2, 3, 78.00, 'C', 1),
(3, 1, 20.00, 'A', 1),
(3, 2, 48.00, 'A', 1),
(3, 3, 92.00, 'A', 1);

-- Insert sample enrollments
INSERT INTO student_enrollments (student_id, course_id, enrollment_date) VALUES
(1, 1, '2023-09-01'),
(1, 2, '2023-09-01'),
(2, 1, '2023-09-01'),
(2, 3, '2023-09-01'),
(3, 1, '2023-09-01'),
(3, 2, '2023-09-01'),
(4, 1, '2023-09-01'),
(4, 4, '2023-09-01'),
(5, 2, '2023-09-01'),
(5, 5, '2023-09-01');

-- Sample attendance rows
INSERT INTO attendance (student_id, course_id, attendance_date, status, remarks) VALUES
    (1, 1, '2023-10-01', 'Present', NULL),
    (1, 1, '2023-10-02', 'Late', 'Arrived 10 mins late'),
    (1, 2, '2023-10-01', 'Absent', 'Sick'),
    (2, 1, '2023-10-01', 'Present', NULL),
    (3, 1, '2023-10-01', 'Excused', 'Official leave');
