ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `monthly_salary` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `hourly_rate`;

CREATE TABLE IF NOT EXISTS `staff_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `pay_period` char(7) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(32) NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_payments_period` (`staff_id`, `pay_period`),
  KEY `idx_staff_payments_branch_period` (`branch_id`, `pay_period`),
  KEY `idx_staff_payments_paid_by` (`paid_by`),
  CONSTRAINT `fk_staff_payments_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_payments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_staff_payments_paid_by` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
