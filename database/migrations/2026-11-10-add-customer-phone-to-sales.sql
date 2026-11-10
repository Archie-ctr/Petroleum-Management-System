ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `customer_phone` varchar(30) DEFAULT NULL AFTER `customer_name`;
