-- IMPORTANT: This is a CLEAN INSTALL / DEMO RESET script.
-- It drops and recreates the application's tables below, so importing it into
-- an existing installation will permanently erase existing application data.
-- Export a backup first. For an existing live database, apply a reviewed migration
-- instead of re-importing this file.
CREATE DATABASE IF NOT EXISTS ebarangay_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ebarangay_management;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS public_contacts;
DROP TABLE IF EXISTS facility_reservations;
DROP TABLE IF EXISTS facilities;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS record_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS service_records;
DROP TABLE IF EXISTS household_members;
DROP TABLE IF EXISTS households;
DROP TABLE IF EXISTS residents;
DROP TABLE IF EXISTS puroks;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE puroks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    leader_name VARCHAR(160),
    assigned_area VARCHAR(180),
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE residents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resident_no VARCHAR(40) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    suffix VARCHAR(30),
    gender VARCHAR(30),
    birth_date DATE,
    civil_status VARCHAR(60),
    contact_no VARCHAR(60),
    email VARCHAR(150),
    address TEXT NOT NULL,
    purok_id INT NULL,
    occupation VARCHAR(150),
    education VARCHAR(150),
    voter_status VARCHAR(60),
    residency_status VARCHAR(80) DEFAULT 'Resident',
    is_pwd TINYINT(1) DEFAULT 0,
    is_senior TINYINT(1) DEFAULT 0,
    is_solo_parent TINYINT(1) DEFAULT 0,
    is_4ps TINYINT(1) DEFAULT 0,
    is_ofw TINYINT(1) DEFAULT 0,
    is_other TINYINT(1) DEFAULT 0,
    is_youth TINYINT(1) DEFAULT 0,
    is_head_of_family TINYINT(1) DEFAULT 0,
    pwd_id_number VARCHAR(80),
    solo_parent_id_number VARCHAR(80),
    special_classification VARCHAR(150),
    status VARCHAR(80) NOT NULL DEFAULT 'Pending Verification',
    status_notes TEXT,
    status_changed_at DATETIME NULL,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_residents_name (last_name, first_name),
    KEY idx_residents_status (status),
    KEY idx_residents_purok (purok_id),
    FOREIGN KEY (purok_id) REFERENCES puroks(id) ON DELETE SET NULL
);

CREATE TABLE households (
    id INT AUTO_INCREMENT PRIMARY KEY,
    household_no VARCHAR(40) NOT NULL UNIQUE,
    household_head_id INT NULL,
    address TEXT NOT NULL,
    purok_id INT NULL,
    classification VARCHAR(120),
    income_bracket VARCHAR(120),
    housing_type VARCHAR(120),
    utilities TEXT,
    status VARCHAR(80) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (household_head_id) REFERENCES residents(id) ON DELETE SET NULL,
    FOREIGN KEY (purok_id) REFERENCES puroks(id) ON DELETE SET NULL
);

CREATE TABLE public_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    contact_type VARCHAR(60) NOT NULL DEFAULT 'Office',
    value VARCHAR(255) NOT NULL,
    availability VARCHAR(150),
    status VARCHAR(30) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL
);

INSERT INTO public_contacts (label, contact_type, value, availability, status, created_at) VALUES
('Barangay Hall', 'Phone', '0917-000-0000', 'Mon-Fri, 8:00 AM - 5:00 PM', 'Active', NOW()),
('Tanod Desk', 'Phone', '0917-111-1111', '24/7', 'Active', NOW()),
('Barangay Email', 'Email', 'info@barangay.test', 'Replies within office hours', 'Active', NOW());

CREATE TABLE household_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    household_id INT NOT NULL,
    resident_id INT NOT NULL,
    relationship VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_household_resident (household_id, resident_id),
    FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE CASCADE
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    resident_id INT NULL,
    full_name VARCHAR(160) NOT NULL,
    username VARCHAR(50) NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    position VARCHAR(80) NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL
);

CREATE TABLE service_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_slug VARCHAR(80) NOT NULL,
    reference_no VARCHAR(60) NOT NULL UNIQUE,
    title VARCHAR(180) NOT NULL,
    resident_id INT NULL,
    requester_name VARCHAR(160),
    category VARCHAR(120),
    description TEXT,
    respondent_name VARCHAR(160) NULL,
    location VARCHAR(255) NULL,
    amount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(40) NULL,
    payment_reference VARCHAR(100) NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    status VARCHAR(80) NOT NULL DEFAULT 'Pending',
    priority VARCHAR(40) DEFAULT 'Normal',
    due_date DATE NULL,
    assigned_to INT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    deleted_by INT NULL,
    deleted_status VARCHAR(80) NULL,
    KEY idx_records_module_status (module_slug, status),
    KEY idx_records_due_date (due_date),
    KEY idx_records_resident (resident_id),
    KEY idx_records_updated (updated_at),
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE record_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    status VARCHAR(80) NOT NULL,
    notes TEXT,
    changed_by INT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (record_id) REFERENCES service_records(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(60) NOT NULL UNIQUE,
    transaction_no VARCHAR(60) NOT NULL UNIQUE,
    record_id INT NULL,
    resident_id INT NULL,
    payer_name VARCHAR(160) NOT NULL,
    purpose VARCHAR(180) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(80) NOT NULL,
    reference_number VARCHAR(100) NULL,
    payment_status VARCHAR(60) NOT NULL DEFAULT 'Paid',
    received_by INT NULL,
    paid_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    deleted_by INT NULL,
    FOREIGN KEY (record_id) REFERENCES service_records(id) ON DELETE SET NULL,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    channel VARCHAR(40) NOT NULL DEFAULT 'In-App',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(120) NOT NULL,
    entity VARCHAR(120) NOT NULL,
    entity_id INT NULL,
    details TEXT,
    ip_address VARCHAR(80),
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    author_name VARCHAR(160) NOT NULL,
    author_role VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_posts_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    location VARCHAR(180) NOT NULL,
    organizer VARCHAR(160),
    status VARCHAR(40) NOT NULL DEFAULT 'Scheduled',
    created_by INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_events_date (event_date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    capacity INT NOT NULL DEFAULT 0,
    fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL
);

CREATE TABLE facility_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(60) NOT NULL UNIQUE,
    facility_id INT NOT NULL,
    resident_id INT NULL,
    user_id INT NULL,
    applicant_name VARCHAR(160) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    reservation_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_res_slot (facility_id, reservation_date, status),
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO roles (name, slug) VALUES
('Resident', 'resident'),
('Barangay Secretary', 'secretary'),
('Barangay Treasurer', 'treasurer'),
('Punong Barangay', 'punong_barangay'),
('Barangay Staff', 'staff'),
('System Administrator', 'admin');

INSERT INTO puroks (name, leader_name, assigned_area, notes, created_at, updated_at) VALUES
('Purok 1', 'Maria Santos', 'North sector', 'Residential and small stores', NOW(), NOW()),
('Purok 2', 'Jose Reyes', 'Central sector', 'Barangay hall and plaza area', NOW(), NOW()),
('Purok 3', 'Elena Cruz', 'South sector', 'Riverside households', NOW(), NOW());

INSERT INTO residents
(resident_no, first_name, middle_name, last_name, gender, birth_date, civil_status, contact_no, email, address, purok_id, occupation, education, voter_status, residency_status, is_pwd, is_senior, is_solo_parent, is_4ps, status, notes, created_at, updated_at)
VALUES
('RES-20261006-0001', 'Juan', 'Dela', 'Cruz', 'Male', '1985-04-12', 'Married', '09170000001', 'juan@example.com', 'Blk 1 Lot 2, Purok 1', 1, 'Driver', 'High School', 'Registered Voter', 'Resident', 0, 0, 0, 0, 'Active', 'Seed resident for demonstrations.', NOW(), NOW()),
('RES-20261006-0002', 'Ana', 'Santos', 'Reyes', 'Female', '1958-08-21', 'Widowed', '09170000002', 'ana@example.com', 'Purok 2 Main Road', 2, 'Retired', 'College', 'Registered Voter', 'Resident', 0, 1, 0, 0, 'Active', 'Senior citizen classification sample.', NOW(), NOW()),
('RES-20261006-0003', 'Carlo', 'Lim', 'Garcia', 'Male', '2002-01-30', 'Single', '09170000003', 'carlo@example.com', 'Riverside, Purok 3', 3, 'Student', 'College', 'Not Registered', 'Resident', 0, 0, 0, 1, 'Pending Verification', 'Pending verification sample.', NOW(), NOW());

INSERT INTO households
(household_no, household_head_id, address, purok_id, classification, income_bracket, housing_type, utilities, status, created_at, updated_at)
VALUES
('HH-20261006-0001', 1, 'Blk 1 Lot 2, Purok 1', 1, 'Nuclear Family', 'PHP 15,001 - PHP 30,000', 'Owned', 'Electricity, water', 'Active', NOW(), NOW()),
('HH-20261006-0002', 2, 'Purok 2 Main Road', 2, 'Single Senior Household', 'Below PHP 10,000', 'Owned', 'Electricity, water', 'Active', NOW(), NOW());

INSERT INTO household_members (household_id, resident_id, relationship, created_at) VALUES
(1, 1, 'Household Head', NOW()),
(2, 2, 'Household Head', NOW());

-- Demo password for all seeded users is: password
INSERT INTO users (role_id, resident_id, full_name, username, email, position, password, status, created_at, updated_at) VALUES
(6, NULL, 'System Administrator', 'admin', 'admin@barangay.test', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW()),
(2, NULL, 'Barangay Secretary', 'secretary', 'secretary@barangay.test', 'Barangay Secretary', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW()),
(3, NULL, 'Barangay Treasurer', 'treasurer', 'treasurer@barangay.test', 'Barangay Treasurer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW()),
(4, NULL, 'Punong Barangay', 'captain', 'captain@barangay.test', 'Barangay Captain', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW()),
(5, NULL, 'Barangay Staff', 'staff', 'staff@barangay.test', 'Barangay Kagawad', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW()),
(1, 1, 'Juan Dela Cruz', 'juan', 'juan@example.com', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Active', NOW(), NOW());

INSERT INTO service_records
(module_slug, reference_no, title, resident_id, requester_name, category, description, amount, status, priority, due_date, created_by, updated_by, created_at, updated_at)
VALUES
('documents', 'DOC-20261006-A10001', 'Barangay Clearance Request', 1, 'Juan Dela Cruz', 'Barangay Clearance', 'Clearance for local employment requirement.', 50.00, 'For Approval', 'Normal', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 2, 2, NOW(), NOW()),
('services', 'SRV-20261006-A10002', 'Educational Assistance Inquiry', 3, 'Carlo Lim Garcia', 'Community Assistance', 'Request for educational assistance eligibility review.', 0.00, 'Under Review', 'Normal', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 2, 2, NOW(), NOW()),
('complaints', 'CB-20261006-A10003', 'Noise Complaint', 2, 'Ana Santos Reyes', 'Blotter', 'Reported repeated late-night noise near the plaza.', 0.00, 'Pending', 'High', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 5, 5, NOW(), NOW()),
('announcements', 'ANN-20261006-A10004', 'Community Clean-Up Drive', NULL, 'Barangay Secretary', 'Public Notice', 'Clean-up drive for all puroks this weekend.', 0.00, 'Ready for Release', 'Normal', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 2, 2, NOW(), NOW());

INSERT INTO record_history (record_id, status, notes, changed_by, created_at)
SELECT id, status, 'Initial seeded status.', created_by, NOW() FROM service_records;

INSERT INTO payments
(receipt_no, transaction_no, record_id, resident_id, payer_name, purpose, amount, payment_method, reference_number, payment_status, received_by, paid_at, created_at)
VALUES
('OR-20261006-0001', 'TXN-20261006-0001', 1, 1, 'Juan Dela Cruz', 'Barangay Clearance Fee', 50.00, 'Cash', NULL, 'Paid', 3, NOW(), NOW());

INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at)
VALUES
(1, 'Database seeded', 'System', NULL, 'Initial Programming 3 sample data loaded.', 'local', NOW());


INSERT INTO facilities (name, description, capacity, fee, status, created_at) VALUES
('Barangay Covered Court', 'Basketball, volleyball and community gatherings.', 200, 300.00, 'Active', NOW()),
('Barangay Hall Function Room', 'Meetings, seminars and small events.', 60, 200.00, 'Active', NOW());

INSERT INTO facility_reservations
(reference_no, facility_id, resident_id, user_id, applicant_name, purpose, reservation_date, start_time, end_time, status, created_at, updated_at)
VALUES
('FAC-20261006-A10006', 1, 1, 6, 'Juan Dela Cruz', 'Evening sports activity', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '18:00:00', '21:00:00', 'Pending', NOW(), NOW());

INSERT INTO events (title, description, event_date, event_time, location, organizer, status, created_by, created_at, updated_at) VALUES
('Barangay Assembly', 'Quarterly barangay assembly with attendance tracking.', DATE_ADD(CURDATE(), INTERVAL 14 DAY), '09:00:00', 'Barangay Covered Court', 'Barangay Secretary', 'Scheduled', 2, NOW(), NOW());

INSERT INTO posts (user_id, author_name, author_role, content, image_path, created_at) VALUES
(2, 'Barangay Secretary', 'Barangay Secretary', 'Welcome to the new E-Barangay portal. Request documents, follow your applications and read barangay news here.', NULL, NOW());
