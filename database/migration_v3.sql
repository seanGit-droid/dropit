-- Drop IT v3: signup + OTP + distance-based cost + wallet simulation
USE dropit;

-- users: wallet + verification flags
ALTER TABLE users
  ADD COLUMN wallet_balance  DECIMAL(10,2) DEFAULT 0 AFTER is_active,
  ADD COLUMN phone_verified  TINYINT(1) DEFAULT 0 AFTER wallet_balance,
  ADD COLUMN email_verified  TINYINT(1) DEFAULT 0 AFTER phone_verified;

-- parcels: product details + pricing
ALTER TABLE parcels
  ADD COLUMN product_name        VARCHAR(200) NULL AFTER notes,
  ADD COLUMN product_description TEXT NULL AFTER product_name,
  ADD COLUMN vehicle_type        VARCHAR(30) DEFAULT 'Tricycle' AFTER product_description,
  ADD COLUMN distance_km         DECIMAL(8,3) DEFAULT 0 AFTER vehicle_type,
  ADD COLUMN amount              DECIMAL(10,2) DEFAULT 0 AFTER distance_km,
  ADD COLUMN admin_fee           DECIMAL(10,2) DEFAULT 0 AFTER amount,
  ADD COLUMN driver_earnings     DECIMAL(10,2) DEFAULT 0 AFTER admin_fee,
  ADD COLUMN payment_status      VARCHAR(30) DEFAULT 'unpaid' AFTER driver_earnings;

-- OTP codes (phone/email)
CREATE TABLE IF NOT EXISTS otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purpose ENUM('phone','email') NOT NULL,
    identifier VARCHAR(200) NOT NULL,
    code VARCHAR(10) NOT NULL,
    session_id VARCHAR(64),
    expires_at TIMESTAMP,
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id),
    INDEX idx_identifier (identifier, purpose)
);

-- wallet ledger
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    parcel_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type VARCHAR(40),
    description TEXT,
    balance_after DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    CONSTRAINT fk_wtx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- seed starting balances for existing users
UPDATE users SET wallet_balance = 500.00 WHERE role = 'customer' AND wallet_balance = 0;
UPDATE users SET phone_verified = 1, email_verified = 1 WHERE id > 0;
