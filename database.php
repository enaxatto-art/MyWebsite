<?php
// database.php - Database connection with modern configuration
require_once __DIR__ . '/config/config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Set timezone for database connection
    $conn->query("SET time_zone = '+00:00'");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}
?>
 