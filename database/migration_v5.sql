-- Drop IT v5: admin price adjustment + customer price-review flow
USE dropit;

ALTER TABLE parcels
  ADD COLUMN IF NOT EXISTS pending_amount       DECIMAL(10,2) NULL,
  ADD COLUMN IF NOT EXISTS price_adjust_reason  TEXT NULL,
  ADD COLUMN IF NOT EXISTS price_review_status  VARCHAR(20) NULL,  -- NULL | 'waiting' | 'accepted' | 'declined'
  ADD COLUMN IF NOT EXISTS pending_driver_id    INT NULL;           -- driver held during price review
