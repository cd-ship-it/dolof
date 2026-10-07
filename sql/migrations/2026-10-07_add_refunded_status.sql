-- Allow paid orders to be marked refunded (frees lunch-box quota; excluded from paid reports).
ALTER TABLE dolos_orders
  MODIFY COLUMN `status` ENUM('pending','paid','expired','cancelled','refunded')
  NOT NULL DEFAULT 'pending';
