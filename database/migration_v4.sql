-- Drop IT v4: chat, payment methods, ratings, POD, favorites, availability, referrals, analytics.
-- Safe to run on top of v3. Uses IF NOT EXISTS / IGNORE where possible.
USE dropit;

-- users: availability, referral, rating summary, theme pref
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS availability    VARCHAR(20) DEFAULT 'offline',
  ADD COLUMN IF NOT EXISTS referral_code   VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS referred_by     INT NULL,
  ADD COLUMN IF NOT EXISTS rating_avg      DECIMAL(3,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS rating_count    INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS theme           VARCHAR(10) DEFAULT 'dark';

-- parcels: payment method + eta + walkin flag + pod
ALTER TABLE parcels
  ADD COLUMN IF NOT EXISTS payment_method  VARCHAR(20) DEFAULT 'wallet',
  ADD COLUMN IF NOT EXISTS payment_ref     VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS eta_minutes     INT NULL,
  ADD COLUMN IF NOT EXISTS is_walkin       TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS pod_photo       VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS pod_signature   MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS pod_receiver    VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS accept_token    VARCHAR(64) NULL;

-- chat messages between customer & driver (and admin)
CREATE TABLE IF NOT EXISTS messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parcel_id  INT NOT NULL,
  sender_id  INT NOT NULL,
  sender_role VARCHAR(20) NOT NULL,
  body       TEXT NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_parcel (parcel_id),
  INDEX idx_created (created_at)
);

-- ratings/reviews after delivery
CREATE TABLE IF NOT EXISTS ratings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parcel_id INT NOT NULL UNIQUE,
  driver_id INT NOT NULL,
  customer_id INT NOT NULL,
  stars TINYINT NOT NULL,
  review TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_driver (driver_id)
);

-- customer favorite addresses
CREATE TABLE IF NOT EXISTS favorite_addresses (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  label   VARCHAR(60) NOT NULL,
  address TEXT NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
);

-- payments (COD + PayMongo GCash sandbox)
CREATE TABLE IF NOT EXISTS payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  parcel_id INT NOT NULL,
  method    VARCHAR(20) NOT NULL,
  amount    DECIMAL(10,2) NOT NULL,
  status    VARCHAR(30) DEFAULT 'pending',
  provider_ref VARCHAR(120) NULL,
  checkout_url TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_parcel (parcel_id)
);

-- driver SOS alerts
CREATE TABLE IF NOT EXISTS sos_alerts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  driver_id INT NOT NULL,
  parcel_id INT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  note TEXT NULL,
  status VARCHAR(20) DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PayMongo settings slots
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('paymongo_secret_key', ''),
  ('paymongo_public_key', ''),
  ('app_secret', '');

-- seed app_secret once (used to sign accept tokens)
UPDATE settings SET setting_value = SUBSTRING(MD5(RAND()), 1, 32)
  WHERE setting_key='app_secret' AND (setting_value IS NULL OR setting_value='');

-- seed referral codes for existing users
UPDATE users SET referral_code = CONCAT('DROP', LPAD(id, 4, '0'))
  WHERE referral_code IS NULL OR referral_code = '';

-- give every existing parcel an accept_token (for 1-click email accept)
UPDATE parcels SET accept_token = SUBSTRING(MD5(CONCAT(id, RAND())), 1, 32)
  WHERE accept_token IS NULL;
