USE `ipms_lgu`;

CREATE TABLE `contractors` (
  `id` int(11) NOT NULL,
  `company` varchar(255) NOT NULL,
  `owner` varchar(100) DEFAULT NULL,
  `license` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `experience` int(11) DEFAULT 0,
  `rating` decimal(2,1) DEFAULT 0.0,
  `status` varchar(50) DEFAULT 'Active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contractors`
--

INSERT INTO `contractors` (`id`, `company`, `owner`, `license`, `email`, `phone`, `address`, `specialization`, `experience`, `rating`, `status`, `notes`, `created_at`) VALUES
(1, 'DalawangBesesNayan', 'Badang', '10001', 'titobadang@gmail.com', '09498672916', 'jaan jaan lang', 'Civil Engineering', 5, 5.0, '0', 'Yey', '2026-01-07 13:29:55');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'Employee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Admin', 'User', 'admin@lgu.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Employee', '2026-01-07 12:15:50');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `milestoneId` int(11) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text DEFAULT NULL,
  `date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT 'Anonymous',
  `subject` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `STATUS` varchar(50) DEFAULT 'Pending',
  `date_submitted` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_name`, `subject`, `category`, `location`, `description`, `STATUS`, `date_submitted`) VALUES
(2, 'Rawen Cavite', 'Repair', 'energy', 'wala, secret', 'aaaa', 'Addressed', '2026-01-07 23:34:52');

-- --------------------------------------------------------

--
-- Table structure for table `milestones`
--

CREATE TABLE `milestones` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `allocated` decimal(15,2) DEFAULT 0.00,
  `spent` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `sector` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'Medium',
  `province` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `duration_months` int(11) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT NULL,
  `project_manager` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `code`, `name`, `type`, `sector`, `description`, `priority`, `province`, `barangay`, `location`, `start_date`, `end_date`, `duration_months`, `budget`, `project_manager`, `status`, `created_at`) VALUES
(48, '123', 'wow', 'New', 'Road', 'asdasda', 'High', 'city', '1265', '124', '2025-12-31', '2026-01-31', 2, 100000.00, 'rawen', 'Approved', '2026-01-07 12:27:54'),
(49, '1001', 'Eme Road', 'New', 'Road', 'Ayown', 'High', 'Jaan Jaan lang City', '152', 'Sa kanto', '2026-01-07', '2026-04-16', 6, 5000000.00, 'joe', 'Draft', '2026-01-07 13:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `project_settings`
--

CREATE TABLE `project_settings` (
  `id` int(11) NOT NULL,
  `total_budget` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_settings`
--

INSERT INTO `project_settings` (`id`, `total_budget`) VALUES
(1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not') DEFAULT NULL,
  `civil_status` enum('single','married','widowed','separated') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `id_upload` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `suffix`, `email`, `mobile`, `birthdate`, `gender`, `civil_status`, `address`, `id_type`, `id_number`, `id_upload`, `password`, `created_at`) VALUES
(1, 'Rawen', 'Nalix', 'Cavite', '', 'me@gmail.com', '09496872916', '2026-01-28', 'male', 'single', 'phs 1', '', '', '', '$2y$10$Y/ny3u26xSur1hrkcecd4.R83DJRK4h8dD6SxGrhgP2U4W1x1QRvy', '2026-01-07 12:15:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contractors`
--
ALTER TABLE `contractors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license` (`license`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `milestoneId` (`milestoneId`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `project_settings`
--
ALTER TABLE `project_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contractors`
--
ALTER TABLE `contractors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `milestones`
--
ALTER TABLE `milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`milestoneId`) REFERENCES `milestones` (`id`) ON DELETE CASCADE;
COMMIT;

-- Additional security/ownership migrations
ALTER TABLE `feedback`
  ADD COLUMN IF NOT EXISTS `user_id` int(11) NULL AFTER `id`;

UPDATE `feedback` f
JOIN `users` u
  ON LOWER(TRIM(f.`user_name`)) = LOWER(TRIM(CONCAT_WS(' ', u.`first_name`, u.`last_name`)))
SET f.`user_id` = u.`id`
WHERE f.`user_id` IS NULL;

ALTER TABLE `feedback`
  ADD INDEX `idx_feedback_user_id_date` (`user_id`, `date_submitted`);

ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `users`
  ADD UNIQUE KEY `uniq_users_mobile` (`mobile`),
  ADD UNIQUE KEY `uniq_users_id_pair` (`id_type`, `id_number`);

CREATE TABLE IF NOT EXISTS `user_rate_limiting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `attempt_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_action_time` (`user_id`,`action_type`,`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_notification_state` (
  `user_id` int(11) NOT NULL,
  `last_seen_id` bigint(20) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
