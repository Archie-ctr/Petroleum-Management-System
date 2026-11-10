CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(50) NOT NULL DEFAULT 'system_alert',
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('high','medium','low') NOT NULL DEFAULT 'medium',
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_status_created` (`status`,`created_at`),
  KEY `notifications_type` (`notification_type`),
  KEY `notifications_priority` (`priority`),
  KEY `notifications_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `help_document_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `help_feedback_document_user` (`document_id`,`user_id`),
  KEY `help_feedback_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `help_document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topic` varchar(200) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `status` enum('pending','reviewed','completed','declined') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `help_requests_status_created` (`status`,`created_at`),
  KEY `help_requests_user` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
