CREATE TABLE IF NOT EXISTS `expense_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(32) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_payments_expense` (`expense_id`),
  KEY `idx_expense_payments_paid_by` (`paid_by`),
  CONSTRAINT `fk_expense_payments_expense`
    FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_expense_payments_user`
    FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
