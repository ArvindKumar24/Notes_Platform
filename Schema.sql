



CREATE DATABASE IF NOT EXISTS Notes_Website;
USE Notes_Website;




CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student','teacher','admin') DEFAULT 'student',
    course VARCHAR(100),
    profile_picture VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    reset_token VARCHAR(255),
    reset_token_expires DATETIME,
    
    PRIMARY KEY (id)
);




CREATE TABLE categories (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    
    PRIMARY KEY (id)
);




CREATE TABLE notes (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    course VARCHAR(100),
    title VARCHAR(200) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    type ENUM('note','question_paper','assessment') NOT NULL,
    downloads_count INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);




CREATE TABLE downloads (
    id INT NOT NULL AUTO_INCREMENT,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);




CREATE TABLE downloads_log (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    note_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);




CREATE TABLE student_submissions (
    id INT NOT NULL AUTO_INCREMENT,
    assessment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    teacher_remarks TEXT,
    reviewed_at DATETIME,
    
    PRIMARY KEY (id),
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES notes(id) ON DELETE CASCADE
);




CREATE INDEX idx_notes_user ON notes(user_id);
CREATE INDEX idx_notes_category ON notes(category_id);
CREATE INDEX idx_downloads_note ON downloads(note_id);
CREATE INDEX idx_downloads_user ON downloads(user_id);
CREATE INDEX idx_submissions_student ON student_submissions(student_id);


INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'notesshare@edu.in', 'NotesShare', 'admin');

