DROP DATABASE IF EXISTS ticket_system;

CREATE DATABASE ticket_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ticket_system;

CREATE TABLE agents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL,
  role VARCHAR(100) NOT NULL,
  skill_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_no VARCHAR(20) NOT NULL UNIQUE,
  requester_name VARCHAR(120) NOT NULL,
  requester_email VARCHAR(160) NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category VARCHAR(80) NOT NULL,
  priority ENUM('Urgent', 'High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
  status ENUM('Open', 'In Progress', 'Escalated', 'Pending User', 'Resolved') NOT NULL DEFAULT 'Open',
  agent_id INT UNSIGNED NULL,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sla_due_at DATETIME NOT NULL,
  auto_escalated_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
  INDEX idx_tickets_priority_status (priority, status),
  INDEX idx_tickets_sla (sla_due_at),
  INDEX idx_tickets_agent (agent_id)
) ENGINE=InnoDB;

CREATE TABLE ticket_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  INDEX idx_events_ticket (ticket_id)
) ENGINE=InnoDB;

CREATE TABLE escalation_rules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_name VARCHAR(160) NOT NULL,
  condition_text VARCHAR(255) NOT NULL,
  action_text VARCHAR(255) NOT NULL,
  owner VARCHAR(120) NOT NULL,
  target_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
