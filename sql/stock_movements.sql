-- ============================================================
-- DermAve IMS - stock movement log (Stock In / Stock Out)
-- Run once in phpMyAdmin on database dermave_ims.
-- Every change to an item's quantity made through stock_move()
-- adds one row here, so the numbers can always be explained.
-- ============================================================

USE dermave_ims;

CREATE TABLE IF NOT EXISTS stock_movements (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id          INT UNSIGNED NOT NULL,
  movement_type    ENUM('in','out') NOT NULL,
  quantity         INT UNSIGNED NOT NULL,            -- always a positive number
  balance_after    INT NOT NULL,                     -- the item's quantity after this movement
  reason           VARCHAR(150) NOT NULL,            -- e.g. Restock, Damaged, Expired, Used in treatment
  user_id          INT UNSIGNED DEFAULT NULL,        -- who did it (users.id)
  treatment_log_id INT UNSIGNED DEFAULT NULL,        -- filled in later, for treatment deductions
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_item (item_id),
  KEY idx_created (created_at),
  KEY idx_treatment_log (treatment_log_id),
  CONSTRAINT fk_movement_item FOREIGN KEY (item_id) REFERENCES directory_items (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
