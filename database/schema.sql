-- Drop IT database schema (v2 — customer orders + live tracking + notifications)
CREATE DATABASE IF NOT EXISTS dropit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dropit;

DROP TABLE IF EXISTS notification_log;
DROP TABLE IF EXISTS driver_locations;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS parcel_history;
DROP TABLE IF EXISTS parcels;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','customer','driver') NOT NULL DEFAULT 'customer',
    full_name VARCHAR(150),
    phone VARCHAR(30),
    avatar VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE parcels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracking_number VARCHAR(40) UNIQUE NOT NULL,
    customer_id INT NULL,
    driver_id INT NULL,
    sender_name VARCHAR(150),
    sender_address TEXT,
    pickup_address TEXT NULL,
    pickup_lat DECIMAL(10,7) NULL,
    pickup_lng DECIMAL(10,7) NULL,
    recipient_name VARCHAR(150),
    recipient_phone VARCHAR(30),
    recipient_address TEXT,
    dropoff_lat DECIMAL(10,7) NULL,
    dropoff_lng DECIMAL(10,7) NULL,
    weight DECIMAL(8,2) DEFAULT 0,
    notes TEXT,
    is_customer_order TINYINT(1) DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_parcel_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_parcel_driver FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE parcel_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parcel_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_parcel FOREIGN KEY (parcel_id) REFERENCES parcels(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    parcel_id INT NULL,
    title VARCHAR(200),
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject VARCHAR(200),
    message TEXT,
    status VARCHAR(30) DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(255),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE driver_locations (
    driver_id INT PRIMARY KEY,
    lat       DECIMAL(10,7) NOT NULL,
    lng       DECIMAL(10,7) NOT NULL,
    accuracy  DECIMAL(8,2) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drvloc_user FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE notification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel    ENUM('email','sms','in_app') NOT NULL,
    recipient  VARCHAR(200),
    subject    VARCHAR(200),
    body       TEXT,
    status     VARCHAR(30) DEFAULT 'queued',
    response   TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

INSERT INTO settings (setting_key, setting_value) VALUES
  ('admin_email',           ''),
  ('admin_phone',           '09000000000'),
  ('smtp_host',             'smtp-relay.brevo.com'),
  ('smtp_port',             '587'),
  ('smtp_user',             ''),
  ('smtp_pass',             ''),
  ('smtp_from',             'Drop IT <no-reply@dropit.app>');
