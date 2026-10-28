-- Drop IT v2: customer orders + live tracking + notifications
USE dropit;

-- parcels: pickup/dropoff geo + who created
ALTER TABLE parcels
  ADD COLUMN pickup_address    TEXT NULL AFTER sender_address,
  ADD COLUMN pickup_lat        DECIMAL(10,7) NULL AFTER pickup_address,
  ADD COLUMN pickup_lng        DECIMAL(10,7) NULL AFTER pickup_lat,
  ADD COLUMN dropoff_lat       DECIMAL(10,7) NULL AFTER recipient_address,
  ADD COLUMN dropoff_lng       DECIMAL(10,7) NULL AFTER dropoff_lat,
  ADD COLUMN is_customer_order TINYINT(1) DEFAULT 0 AFTER notes;

-- one row per driver, updated continuously
CREATE TABLE IF NOT EXISTS driver_locations (
    driver_id INT PRIMARY KEY,
    lat       DECIMAL(10,7) NOT NULL,
    lng       DECIMAL(10,7) NOT NULL,
    accuracy  DECIMAL(8,2) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_drvloc_user FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- outgoing notification log (email/sms/in-app)
CREATE TABLE IF NOT EXISTS notification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    channel    ENUM('email','sms','in_app') NOT NULL,
    recipient  VARCHAR(200),
    subject    VARCHAR(200),
    body       TEXT,
    status     VARCHAR(30) DEFAULT 'queued',
    response   TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- key/value settings (admin email, SMS key, etc.)
CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
);

-- default settings (only inserted if missing)
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('admin_email',           'admin@dropit.app'),
  ('admin_phone',           '09000000000'),
  ('semaphore_api_key',     ''),
  ('semaphore_sender_name', 'DROPIT'),
  ('smtp_host',             ''),
  ('smtp_port',             '587'),
  ('smtp_user',             ''),
  ('smtp_pass',             ''),
  ('smtp_from',             'no-reply@dropit.app');
