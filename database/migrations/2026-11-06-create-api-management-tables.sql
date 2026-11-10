CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_id` char(16) NOT NULL,
  `api_name` varchar(100) NOT NULL,
  `api_purpose` text NOT NULL,
  `api_key_hash` varchar(255) NOT NULL,
  `secret_key_hash` varchar(255) NOT NULL,
  `permissions` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `status` enum('active','inactive','revoked') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  `last_rotated` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_keys_key_id` (`key_id`),
  KEY `api_keys_status` (`status`),
  KEY `api_keys_created_by` (`created_by`),
  KEY `api_keys_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_key_id` int(11) DEFAULT NULL,
  `endpoint` varchar(500) NOT NULL,
  `http_method` varchar(10) NOT NULL,
  `response_code` smallint(5) unsigned NOT NULL,
  `response_time` int(10) unsigned NOT NULL DEFAULT 0,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `api_logs_api_key_id` (`api_key_id`),
  KEY `api_logs_timestamp` (`timestamp`),
  KEY `api_logs_response_code` (`response_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
