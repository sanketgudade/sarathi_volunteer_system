-- Create database
CREATE DATABASE IF NOT EXISTS sarathi_db;
USE sarathi_db;

-- Volunteers table
CREATE TABLE IF NOT EXISTS volunteers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    skills TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    volunteer_id INT NOT NULL,
    check_in_time DATETIME NOT NULL,
    check_out_time DATETIME,
    location VARCHAR(100),
    notes TEXT,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Tasks table
CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT NOT NULL,
    assigned_by INT,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Insert sample volunteer (password: password123)
INSERT INTO volunteers (name, email, password, phone, status) VALUES 
('John Doe', 'john@example.com', '$2y$10$YourHashedPasswordHere', '1234567890', 'approved');

-- Insert sample tasks
INSERT INTO tasks (title, description, assigned_to, priority, deadline) VALUES
('Field Survey', 'Conduct field survey in area A', 1, 'high', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
('Data Entry', 'Enter survey data into system', 1, 'medium', DATE_ADD(CURDATE(), INTERVAL 5 DAY));