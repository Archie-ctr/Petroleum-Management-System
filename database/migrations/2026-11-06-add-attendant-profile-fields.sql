ALTER TABLE `users`
  MODIFY COLUMN `status` enum('active','inactive','suspended') DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS `phone` varchar(30) DEFAULT NULL AFTER `email`,
  ADD COLUMN IF NOT EXISTS `employee_id` varchar(50) DEFAULT NULL AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `branch_id`,
  ADD COLUMN IF NOT EXISTS `shift_time` varchar(50) DEFAULT NULL AFTER `hourly_rate`,
  ADD COLUMN IF NOT EXISTS `pin_code` varchar(20) DEFAULT NULL AFTER `shift_time`,
  ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL AFTER `deleted_at`,
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_by`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_users_employee_id` (`employee_id`);
