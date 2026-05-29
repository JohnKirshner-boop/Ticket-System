USE ticket_system;

INSERT INTO agents (name, email, role, skill_level) VALUES
  ('L. Reyes', 'l.reyes@school.edu', 'Help Desk Manager', 3),
  ('R. Mendoza', 'r.mendoza@school.edu', 'Senior Systems Administrator', 3),
  ('P. Villanueva', 'p.villanueva@school.edu', 'Network Specialist', 2),
  ('K. Flores', 'k.flores@school.edu', 'Software Support Specialist', 2),
  ('J. Santos', 'j.santos@school.edu', 'Support Agent', 1),
  ('M. Garcia', 'm.garcia@school.edu', 'Field Technician', 1);

INSERT INTO escalation_rules (rule_name, condition_text, action_text, owner, target_minutes, sort_order) VALUES
  ('Urgent Priority Routing', 'Priority is Urgent', 'Assign a senior agent and start at escalation level 2.', 'Help Desk Manager', 60, 1),
  ('Over SLA Auto Escalation', 'Ticket is Over SLA', 'Auto-escalate once, keep ticket visible as Over SLA, and notify the queue owner.', 'Queue Supervisor', 0, 2),
  ('Security Incident Routing', 'Category is Security', 'Apply a 30-minute SLA and assign a senior systems agent.', 'Security Lead', 30, 3),
  ('High Priority At Risk', 'Priority is High and SLA is near deadline', 'Flag ticket as At Risk and prioritize it in the High queue.', 'Service Desk Lead', 240, 4),
  ('Maximum Escalation Review', 'Escalation level 3 and unresolved', 'Require manager review before closure.', 'Operations Manager', 0, 5);

INSERT INTO tickets (
  ticket_no, requester_name, requester_email, title, description, category,
  priority, status, agent_id, escalation_level, sla_due_at, auto_escalated_at
) VALUES
  (
    'HD-1030',
    'Alyssa Reyes',
    'alyssa.reyes@school.edu',
    'Faculty portal login fails after password reset',
    'Requester completed password reset but still cannot log into the faculty portal.',
    'Account Access',
    'High',
    'Open',
    5,
    1,
    DATE_ADD(NOW(), INTERVAL 75 MINUTE),
    NULL
  ),
  (
    'HD-1031',
    'Mark Dela Cruz',
    'mark.delacruz@school.edu',
    'Computer laboratory Wi-Fi drops every 10 minutes',
    'Multiple lab computers disconnect from Wi-Fi during class sessions.',
    'Network',
    'Urgent',
    'Escalated',
    3,
    2,
    DATE_ADD(NOW(), INTERVAL 35 MINUTE),
    NULL
  ),
  (
    'HD-1032',
    'Nina Cortez',
    'nina.cortez@school.edu',
    'Suspicious email reported by admissions office',
    'Admissions staff received a suspicious email with a credential collection link.',
    'Security',
    'Urgent',
    'Escalated',
    2,
    3,
    DATE_SUB(NOW(), INTERVAL 25 MINUTE),
    NOW()
  ),
  (
    'HD-1033',
    'Prof. Lim',
    'prof.lim@school.edu',
    'Projector not detected in lecture room 204',
    'Lectern PC does not detect the room projector through HDMI.',
    'Hardware',
    'Medium',
    'In Progress',
    6,
    1,
    DATE_ADD(NOW(), INTERVAL 5 HOUR),
    NULL
  ),
  (
    'HD-1034',
    'Carlo Medina',
    'carlo.medina@school.edu',
    'Unable to install required design software',
    'Installer fails during license validation. User needs software before lab activity.',
    'Software',
    'High',
    'Pending User',
    4,
    1,
    DATE_ADD(NOW(), INTERVAL 3 HOUR),
    NULL
  ),
  (
    'HD-1035',
    'Registrar Desk',
    'registrar@school.edu',
    'Student ID scanner delayed during enrollment',
    'Front desk scanner responds slowly while processing enrollment forms.',
    'Hardware',
    'Low',
    'Open',
    6,
    1,
    DATE_ADD(NOW(), INTERVAL 18 HOUR),
    NULL
  );

INSERT INTO ticket_events (ticket_id, message) VALUES
  (1, 'Ticket created.'),
  (1, 'High priority rule applied: monitor for at-risk SLA.'),
  (2, 'Ticket created.'),
  (2, 'Urgent priority trigger applied: routed to senior response queue.'),
  (3, 'Ticket created.'),
  (3, 'Security trigger applied: 30-minute SLA and senior assignment.'),
  (3, 'Automatic escalation to level 3: SLA is overdue.'),
  (3, 'Over SLA: manager review required.'),
  (4, 'Ticket created.'),
  (4, 'Field technician assigned.'),
  (5, 'Ticket created.'),
  (5, 'Waiting for requester to provide installer log file.'),
  (6, 'Ticket created.'),
  (6, 'Added to Low priority queue.');
