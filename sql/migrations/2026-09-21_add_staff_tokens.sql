-- One-time-use ordering links for the staff order form (staff_order.php).
CREATE TABLE dolos_staff_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(48) NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(200) NOT NULL,
  order_id INT UNSIGNED NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_token (token)
);
