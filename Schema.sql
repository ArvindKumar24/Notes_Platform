-- Create database
CREATE DATABASE IF NOT EXISTS `Notes_website`
CHARACTER SET utf8mb4
COLLATE utf8mb4_0900_ai_ci;

USE `Notes_website`;

-- =========================
-- USERS TABLE
-- =========================
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('student','teacher','admin') DEFAULT 'student',
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_token_expires` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- CATEGORIES TABLE
-- =========================
CREATE TABLE `categories` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- NOTES TABLE
-- =========================
CREATE TABLE `notes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `file_path` VARCHAR(255) NOT NULL,
    `type` ENUM('note','question_paper','assessment') NOT NULL,
    `downloads_count` INT DEFAULT '0',
    `uploaded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- DOWNLOADS TABLE
-- =========================
CREATE TABLE `downloads` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `downloaded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- DOWNLOADS LOG TABLE
-- =========================
CREATE TABLE `downloads_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `downloaded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `note_id` (`note_id`),
    CONSTRAINT `downloads_log_ibfk_1`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `downloads_log_ibfk_2`
        FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- ANNOUNCEMENTS TABLE
-- =========================
CREATE TABLE `announcements` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    CONSTRAINT `announcements_ibfk_1`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_0900_ai_ci;
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'notesshare@edu.in', 'NotesShare', 'admin');
-- Add password reset token columns to users table
