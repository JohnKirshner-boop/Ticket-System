# Help Desk Ticket System

Clean rebuild of the Ticket-System project for XAMPP.

## Included Features

- PHP/MySQL backend API
- Ticket details page with requester, description, category, agent, priority, queue number, SLA, escalation level, and activity log
- SLA monitoring with `On Track`, `At Risk`, and `Over SLA`
- Automatic escalation for overdue tickets
- Agent names shown in ticket list, queue, details, and agent roster
- Queue numbers calculated per priority queue
- Priority-based routing and escalation rules
- Priority-only workflow

## Setup

1. Start Apache and MySQL in XAMPP.
2. In phpMyAdmin, run:
   - `backend/database/schema.sql`
   - `backend/database/seed.sql`
3. Open:
   - `http://localhost/Ticket-System/`

## API Routes

Base URL: `http://localhost/Ticket-System/api/`

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `health` | API health check |
| GET | `tickets` | List tickets |
| GET | `tickets/{ticket_no}` | Ticket details |
| POST | `tickets` | Create ticket |
| POST | `tickets/{ticket_no}/assign` | Assign agent |
| POST | `tickets/{ticket_no}/reassign` | Reassign to next qualified agent |
| POST | `tickets/{ticket_no}/escalate` | Manual escalation |
| POST | `tickets/{ticket_no}/resolve` | Resolve ticket |
| GET | `dashboard` | Dashboard metrics |
| GET | `queues` | Priority queues with queue numbers |
| GET | `sla` | Over SLA and At Risk tickets |
| GET | `agents` | Agent roster |
| GET | `rules` | Escalation rules |
