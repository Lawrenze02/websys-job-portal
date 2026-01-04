<?php
require_once 'config/database.php';

$sql = "CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bio TEXT,
    address VARCHAR(255),
    website VARCHAR(255),
    github VARCHAR(255),
    linkedin VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
);";

if ($conn->query($sql) === TRUE) {
    echo "Table profiles created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
