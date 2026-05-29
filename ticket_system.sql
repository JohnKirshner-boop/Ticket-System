-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 29, 2026 at 05:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ticket_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `agents`
--

CREATE TABLE `agents` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `role` varchar(100) NOT NULL,
  `skill_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agents`
--

INSERT INTO `agents` (`id`, `name`, `email`, `role`, `skill_level`, `active`, `created_at`) VALUES
(1, 'L. Reyes', 'l.reyes@school.edu', 'Help Desk Manager', 3, 1, '2026-05-29 14:29:30'),
(2, 'R. Mendoza', 'r.mendoza@school.edu', 'Senior Systems Administrator', 3, 1, '2026-05-29 14:29:30'),
(3, 'P. Villanueva', 'p.villanueva@school.edu', 'Network Specialist', 2, 1, '2026-05-29 14:29:30'),
(4, 'K. Flores', 'k.flores@school.edu', 'Software Support Specialist', 2, 1, '2026-05-29 14:29:30'),
(5, 'J. Santos', 'j.santos@school.edu', 'Support Agent', 1, 1, '2026-05-29 14:29:30'),
(6, 'M. Garcia', 'm.garcia@school.edu', 'Field Technician', 1, 1, '2026-05-29 14:29:30');

-- --------------------------------------------------------

--
-- Table structure for table `escalation_rules`
--

CREATE TABLE `escalation_rules` (
  `id` int(10) UNSIGNED NOT NULL,
  `rule_name` varchar(160) NOT NULL,
  `condition_text` varchar(255) NOT NULL,
  `action_text` varchar(255) NOT NULL,
  `owner` varchar(120) NOT NULL,
  `target_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `escalation_rules`
--

INSERT INTO `escalation_rules` (`id`, `rule_name`, `condition_text`, `action_text`, `owner`, `target_minutes`, `active`, `sort_order`) VALUES
(1, 'Urgent Priority Routing', 'Priority is Urgent', 'Assign a senior agent and start at escalation level 2.', 'Help Desk Manager', 60, 1, 1),
(2, 'Over SLA Auto Escalation', 'Ticket is Over SLA', 'Auto-escalate once, keep ticket visible as Over SLA, and notify the queue owner.', 'Queue Supervisor', 0, 1, 2),
(3, 'Security Incident Routing', 'Category is Security', 'Apply a 30-minute SLA and assign a senior systems agent.', 'Security Lead', 30, 1, 3),
(4, 'High Priority At Risk', 'Priority is High and SLA is near deadline', 'Flag ticket as At Risk and prioritize it in the High queue.', 'Service Desk Lead', 240, 1, 4),
(5, 'Maximum Escalation Review', 'Escalation level 3 and unresolved', 'Require manager review before closure.', 'Operations Manager', 0, 1, 5);

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_no` varchar(20) NOT NULL,
  `requester_name` varchar(120) NOT NULL,
  `requester_email` varchar(160) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(80) NOT NULL,
  `priority` enum('Urgent','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `status` enum('Open','In Progress','Escalated','Pending User','Resolved') NOT NULL DEFAULT 'Open',
  `agent_id` int(10) UNSIGNED DEFAULT NULL,
  `escalation_level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `sla_due_at` datetime NOT NULL,
  `auto_escalated_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_no`, `requester_name`, `requester_email`, `title`, `description`, `category`, `priority`, `status`, `agent_id`, `escalation_level`, `sla_due_at`, `auto_escalated_at`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 'HD-1030', 'Alyssa Reyes', 'alyssa.reyes@school.edu', 'Faculty portal login fails after password reset', 'Requester completed password reset but still cannot log into the faculty portal.', 'Account Access', 'High', 'Open', 5, 1, '2026-05-29 23:44:30', NULL, NULL, '2026-05-29 14:29:30', '2026-05-29 14:29:30'),
(2, 'HD-1031', 'Mark Dela Cruz', 'mark.delacruz@school.edu', 'Computer laboratory Wi-Fi drops every 10 minutes', 'Multiple lab computers disconnect from Wi-Fi during class sessions.', 'Network', 'Urgent', 'Escalated', 1, 3, '2026-05-29 23:04:30', '2026-05-29 23:07:35', NULL, '2026-05-29 14:29:30', '2026-05-29 15:07:35'),
(3, 'HD-1032', 'Nina Cortez', 'nina.cortez@school.edu', 'Suspicious email reported by admissions office', 'Admissions staff received a suspicious email with a credential collection link.', 'Security', 'Urgent', 'Escalated', 2, 3, '2026-05-29 22:04:30', '2026-05-29 22:29:30', NULL, '2026-05-29 14:29:30', '2026-05-29 14:29:30'),
(4, 'HD-1033', 'Prof. Lim', 'prof.lim@school.edu', 'Projector not detected in lecture room 204', 'Lectern PC does not detect the room projector through HDMI.', 'Hardware', 'Medium', 'In Progress', 6, 1, '2026-05-30 03:29:30', NULL, NULL, '2026-05-29 14:29:30', '2026-05-29 14:29:30'),
(5, 'HD-1034', 'Carlo Medina', 'carlo.medina@school.edu', 'Unable to install required design software', 'Installer fails during license validation. User needs software before lab activity.', 'Software', 'High', 'Pending User', 4, 1, '2026-05-30 01:29:30', NULL, NULL, '2026-05-29 14:29:30', '2026-05-29 14:29:30'),
(6, 'HD-1035', 'Registrar Desk', 'registrar@school.edu', 'Student ID scanner delayed during enrollment', 'Front desk scanner responds slowly while processing enrollment forms.', 'Hardware', 'Low', 'Resolved', 1, 2, '2026-05-30 16:29:30', NULL, '2026-05-29 22:52:56', '2026-05-29 14:29:30', '2026-05-29 14:52:56');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_events`
--

CREATE TABLE `ticket_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `ticket_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_events`
--

INSERT INTO `ticket_events` (`id`, `ticket_id`, `message`, `created_at`) VALUES
(1, 1, 'Ticket created.', '2026-05-29 14:29:30'),
(2, 1, 'High priority rule applied: monitor for at-risk SLA.', '2026-05-29 14:29:30'),
(3, 2, 'Ticket created.', '2026-05-29 14:29:30'),
(4, 2, 'Urgent priority trigger applied: routed to senior response queue.', '2026-05-29 14:29:30'),
(5, 3, 'Ticket created.', '2026-05-29 14:29:30'),
(6, 3, 'Security trigger applied: 30-minute SLA and senior assignment.', '2026-05-29 14:29:30'),
(7, 3, 'Automatic escalation to level 3: SLA is overdue.', '2026-05-29 14:29:30'),
(8, 3, 'Over SLA: manager review required.', '2026-05-29 14:29:30'),
(9, 4, 'Ticket created.', '2026-05-29 14:29:30'),
(10, 4, 'Field technician assigned.', '2026-05-29 14:29:30'),
(11, 5, 'Ticket created.', '2026-05-29 14:29:30'),
(12, 5, 'Waiting for requester to provide installer log file.', '2026-05-29 14:29:30'),
(13, 6, 'Ticket created.', '2026-05-29 14:29:30'),
(14, 6, 'Added to Low priority queue.', '2026-05-29 14:29:30'),
(15, 6, 'Manual escalation to level 2: Manager requested escalation.', '2026-05-29 14:52:53'),
(16, 6, 'Ticket resolved.', '2026-05-29 14:52:56'),
(17, 2, 'Automatic escalation to level 3: Auto-escalated because SLA is overdue.', '2026-05-29 15:07:35'),
(18, 2, 'Automatic escalation to level 3: Auto-escalated because SLA is overdue.', '2026-05-29 15:07:35'),
(19, 2, 'Automatic escalation to level 3: Auto-escalated because SLA is overdue.', '2026-05-29 15:07:35'),
(20, 2, 'Automatic escalation to level 3: Auto-escalated because SLA is overdue.', '2026-05-29 15:07:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_no` (`ticket_no`),
  ADD KEY `idx_tickets_priority_status` (`priority`,`status`),
  ADD KEY `idx_tickets_sla` (`sla_due_at`),
  ADD KEY `idx_tickets_agent` (`agent_id`);

--
-- Indexes for table `ticket_events`
--
ALTER TABLE `ticket_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_events_ticket` (`ticket_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `escalation_rules`
--
ALTER TABLE `escalation_rules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ticket_events`
--
ALTER TABLE `ticket_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_agent` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_events`
--
ALTER TABLE `ticket_events`
  ADD CONSTRAINT `fk_events_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
