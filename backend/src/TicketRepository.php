<?php

declare(strict_types=1);

namespace TicketSystem;

use PDO;

final class TicketRepository
{
    public function __construct(private PDO $db)
    {
    }

    public static function findByDatabaseId(PDO $db, int $id): ?array
    {
        return (new self($db))->findByDatabaseIdInternal($id);
    }

    public function tickets(array $filters = []): array
    {
        $sql = "SELECT t.*, a.name AS agent_name, a.role AS agent_role
                FROM tickets t
                LEFT JOIN agents a ON a.id = t.agent_id
                WHERE 1=1";
        $params = [];

        if (($filters['status'] ?? 'all') !== 'all') {
            $sql .= ' AND t.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['priority'] ?? 'all') !== 'all') {
            $sql .= ' AND t.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (
                t.ticket_no LIKE :q OR
                t.title LIKE :q OR
                t.requester_name LIKE :q OR
                t.category LIKE :q OR
                a.name LIKE :q
            )";
            $params['q'] = '%' . $filters['q'] . '%';
        }

        $sql .= " ORDER BY FIELD(t.priority, 'Urgent', 'High', 'Medium', 'Low'), t.created_at ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->enrichMany($stmt->fetchAll(), true);
    }

    public function findByCode(string $ticketNo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, a.name AS agent_name, a.role AS agent_role
             FROM tickets t
             LEFT JOIN agents a ON a.id = t.agent_id
             WHERE t.ticket_no = :ticket_no"
        );
        $stmt->execute(['ticket_no' => $ticketNo]);
        $ticket = $stmt->fetch();

        return $ticket ? $this->enrichOne($ticket, true) : null;
    }

    public function findByDatabaseIdInternal(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.*, a.name AS agent_name, a.role AS agent_role
             FROM tickets t
             LEFT JOIN agents a ON a.id = t.agent_id
             WHERE t.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $ticket = $stmt->fetch();

        return $ticket ? $this->enrichOne($ticket, true) : null;
    }

    public function create(array $input): array
    {
        $priority = $this->normalizePriority((string) ($input['priority'] ?? 'Medium'));
        $category = trim((string) ($input['category'] ?? 'Software')) ?: 'Software';
        $agentId = $this->resolveAgent($input['agent_id'] ?? null, $priority, $category);
        $ticketNo = $this->nextTicketNumber();
        $escalationLevel = ($priority === 'Urgent' || $category === 'Security') ? 2 : 1;
        $status = $escalationLevel > 1 ? 'Escalated' : 'Open';

        $stmt = $this->db->prepare(
            "INSERT INTO tickets (
                ticket_no, requester_name, requester_email, title, description, category,
                priority, status, agent_id, escalation_level, sla_due_at
             ) VALUES (
                :ticket_no, :requester_name, :requester_email, :title, :description, :category,
                :priority, :status, :agent_id, :escalation_level, :sla_due_at
             )"
        );
        $stmt->execute([
            'ticket_no' => $ticketNo,
            'requester_name' => trim((string) $input['requester_name']),
            'requester_email' => trim((string) ($input['requester_email'] ?? '')),
            'title' => trim((string) $input['title']),
            'description' => trim((string) ($input['description'] ?? '')),
            'category' => $category,
            'priority' => $priority,
            'status' => $status,
            'agent_id' => $agentId,
            'escalation_level' => $escalationLevel,
            'sla_due_at' => SlaService::dueAt($priority, $category),
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->log($id, 'Ticket created.');

        if ($priority === 'Urgent') {
            $this->log($id, 'Urgent priority trigger applied: routed to senior response queue.');
        }
        if ($category === 'Security') {
            $this->log($id, 'Security trigger applied: 30-minute SLA and senior assignment.');
        }

        return $this->findByDatabaseIdInternal($id) ?? [];
    }

    public function assign(int $id, int $agentId): ?array
    {
        if (!$this->agentExists($agentId)) {
            return null;
        }

        $stmt = $this->db->prepare(
            'UPDATE tickets SET agent_id = :agent_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['agent_id' => $agentId, 'id' => $id]);
        $this->log($id, 'Assigned to ' . ($this->agentName($agentId) ?? 'selected agent') . '.');

        return $this->findByDatabaseIdInternal($id);
    }

    public function reassign(int $id): ?array
    {
        $ticket = $this->rawTicket($id);
        if ($ticket === null) {
            return null;
        }

        $agentId = $this->pickAgent((string) $ticket['priority'], (string) $ticket['category'], (int) ($ticket['agent_id'] ?? 0));
        if ($agentId === null) {
            return $this->findByDatabaseIdInternal($id);
        }

        $stmt = $this->db->prepare(
            'UPDATE tickets SET agent_id = :agent_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['agent_id' => $agentId, 'id' => $id]);
        $this->log($id, 'Reassigned to next available qualified agent.');

        return $this->findByDatabaseIdInternal($id);
    }

    public function resolve(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "UPDATE tickets
             SET status = 'Resolved', resolved_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
        $this->log($id, 'Ticket resolved.');

        return $this->findByDatabaseIdInternal($id);
    }

    public function dashboard(): array
    {
        $active = $this->scalar("SELECT COUNT(*) FROM tickets WHERE status != 'Resolved'");
        $atRisk = $this->scalar(
            "SELECT COUNT(*) FROM tickets
             WHERE status != 'Resolved'
               AND sla_due_at >= NOW()
               AND sla_due_at <= DATE_ADD(NOW(), INTERVAL " . SlaService::AT_RISK_MINUTES . " MINUTE)"
        );
        $overSla = $this->scalar(
            "SELECT COUNT(*) FROM tickets WHERE status != 'Resolved' AND sla_due_at < NOW()"
        );
        $resolvedToday = $this->scalar(
            "SELECT COUNT(*) FROM tickets WHERE status = 'Resolved' AND DATE(resolved_at) = CURDATE()"
        );
        $escalated = $this->scalar(
            "SELECT COUNT(*) FROM tickets WHERE status != 'Resolved' AND status = 'Escalated'"
        );

        $watch = $this->db->query(
            "SELECT t.*, a.name AS agent_name, a.role AS agent_role
             FROM tickets t
             LEFT JOIN agents a ON a.id = t.agent_id
             WHERE t.status != 'Resolved'
               AND (t.sla_due_at <= DATE_ADD(NOW(), INTERVAL " . SlaService::AT_RISK_MINUTES . " MINUTE)
                    OR t.priority = 'Urgent')
             ORDER BY t.sla_due_at ASC
             LIMIT 6"
        )->fetchAll();

        return [
            'open_tickets' => $active,
            'at_risk_sla' => $atRisk,
            'over_sla' => $overSla,
            'resolved_today' => $resolvedToday,
            'escalation_rate' => $active > 0 ? (int) round(($escalated / $active) * 100) : 0,
            'priority_snapshot' => $this->prioritySnapshot(),
            'watchlist' => $this->enrichMany($watch, false),
            'recent_activity' => $this->recentActivity(8),
        ];
    }

    public function priorityQueues(): array
    {
        $queues = [];
        foreach (SlaService::PRIORITY_ORDER as $priority) {
            $stmt = $this->db->prepare(
                "SELECT t.*, a.name AS agent_name, a.role AS agent_role
                 FROM tickets t
                 LEFT JOIN agents a ON a.id = t.agent_id
                 WHERE t.priority = :priority AND t.status != 'Resolved'
                 ORDER BY t.created_at ASC"
            );
            $stmt->execute(['priority' => $priority]);
            $tickets = $this->enrichMany($stmt->fetchAll(), false);
            $queues[$priority] = [
                'priority' => $priority,
                'count' => count($tickets),
                'tickets' => $tickets,
            ];
        }

        return $queues;
    }

    public function slaMonitor(): array
    {
        $tickets = $this->tickets();
        $over = [];
        $risk = [];
        foreach ($tickets as $ticket) {
            if (($ticket['sla']['status'] ?? '') === 'over_sla') {
                $over[] = $ticket;
            } elseif (($ticket['sla']['status'] ?? '') === 'at_risk') {
                $risk[] = $ticket;
            }
        }

        return ['over_sla' => $over, 'at_risk' => $risk];
    }

    public function agents(): array
    {
        $stmt = $this->db->query(
            "SELECT a.id, a.name, a.email, a.role, a.skill_level,
                    COUNT(t.id) AS active_tickets
             FROM agents a
             LEFT JOIN tickets t ON t.agent_id = a.id AND t.status != 'Resolved'
             WHERE a.active = 1
             GROUP BY a.id
             ORDER BY a.skill_level DESC, a.name ASC"
        );

        return $stmt->fetchAll();
    }

    public function rules(): array
    {
        return $this->db->query(
            "SELECT rule_name, condition_text, action_text, owner, target_minutes
             FROM escalation_rules
             WHERE active = 1
             ORDER BY sort_order ASC"
        )->fetchAll();
    }

    public function dbIdFromIdentifier(string $identifier): ?int
    {
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        $stmt = $this->db->prepare('SELECT id FROM tickets WHERE ticket_no = :ticket_no');
        $stmt->execute(['ticket_no' => $identifier]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function enrichMany(array $rows, bool $withEvents): array
    {
        $positions = $this->queuePositions();

        return array_map(fn (array $row): array => $this->enrichOne($row, $withEvents, $positions), $rows);
    }

    private function enrichOne(array $row, bool $withEvents, ?array $positions = null): array
    {
        $positions ??= $this->queuePositions();
        $sla = SlaService::evaluate($row);
        $ticketNo = (string) $row['ticket_no'];
        $queueKey = $row['priority'] . ':' . $ticketNo;

        return [
            'id' => $ticketNo,
            'db_id' => (int) $row['id'],
            'requester_name' => $row['requester_name'],
            'requester_email' => $row['requester_email'],
            'title' => $row['title'],
            'description' => $row['description'],
            'category' => $row['category'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'agent_id' => $row['agent_id'] !== null ? (int) $row['agent_id'] : null,
            'agent_name' => $row['agent_name'] ?? 'Unassigned',
            'agent_role' => $row['agent_role'] ?? '',
            'queue_position' => $positions[$queueKey] ?? null,
            'escalation_level' => (int) $row['escalation_level'],
            'sla_due_at' => $row['sla_due_at'],
            'sla' => $sla,
            'auto_escalated_at' => $row['auto_escalated_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'events' => $withEvents ? $this->events((int) $row['id']) : [],
            'matching_rules' => $this->matchingRules($row, $sla),
        ];
    }

    private function queuePositions(): array
    {
        $positions = [];
        foreach (SlaService::PRIORITY_ORDER as $priority) {
            $stmt = $this->db->prepare(
                "SELECT ticket_no
                 FROM tickets
                 WHERE priority = :priority AND status != 'Resolved'
                 ORDER BY created_at ASC"
            );
            $stmt->execute(['priority' => $priority]);
            $position = 1;
            foreach ($stmt->fetchAll() as $row) {
                $positions[$priority . ':' . $row['ticket_no']] = $position++;
            }
        }

        return $positions;
    }

    private function events(int $ticketId): array
    {
        $stmt = $this->db->prepare(
            'SELECT message, created_at FROM ticket_events WHERE ticket_id = :ticket_id ORDER BY created_at DESC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);

        return $stmt->fetchAll();
    }

    private function matchingRules(array $ticket, array $sla): array
    {
        $rules = [];
        foreach ($this->rules() as $rule) {
            $condition = $rule['condition_text'];
            if (
                str_contains($condition, $ticket['priority']) ||
                str_contains($condition, $ticket['category']) ||
                ($sla['over_sla'] && str_contains($condition, 'Over SLA')) ||
                ((int) $ticket['escalation_level'] >= 3 && str_contains($condition, 'level 3'))
            ) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    private function prioritySnapshot(): array
    {
        $stmt = $this->db->query(
            "SELECT priority, COUNT(*) AS total
             FROM tickets
             WHERE status != 'Resolved'
             GROUP BY priority"
        );
        $totals = [];
        foreach ($stmt->fetchAll() as $row) {
            $totals[$row['priority']] = (int) $row['total'];
        }

        return array_map(
            fn (string $priority): array => ['priority' => $priority, 'count' => $totals[$priority] ?? 0],
            SlaService::PRIORITY_ORDER
        );
    }

    private function recentActivity(int $limit): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.message, e.created_at, t.ticket_no
             FROM ticket_events e
             INNER JOIN tickets t ON t.id = e.ticket_id
             ORDER BY e.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn (array $row): string => $row['ticket_no'] . ': ' . $row['message'],
            $stmt->fetchAll()
        );
    }

    private function rawTicket(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $ticket = $stmt->fetch();

        return $ticket ?: null;
    }

    private function resolveAgent(mixed $requestedId, string $priority, string $category): ?int
    {
        $requested = (int) ($requestedId ?? 0);
        if ($requested > 0 && $this->agentExists($requested)) {
            return $requested;
        }

        return $this->pickAgent($priority, $category);
    }

    private function pickAgent(string $priority, string $category, int $exclude = 0): ?int
    {
        $minSkill = ($priority === 'Urgent' || $priority === 'High' || $category === 'Security') ? 2 : 1;
        $sql = "SELECT a.id
                FROM agents a
                LEFT JOIN tickets t ON t.agent_id = a.id AND t.status != 'Resolved'
                WHERE a.active = 1 AND a.skill_level >= :skill";
        $params = ['skill' => $minSkill];

        if ($exclude > 0) {
            $sql .= ' AND a.id != :exclude';
            $params['exclude'] = $exclude;
        }

        $sql .= ' GROUP BY a.id ORDER BY COUNT(t.id) ASC, a.skill_level DESC, a.id ASC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function agentExists(int $agentId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM agents WHERE id = :id AND active = 1');
        $stmt->execute(['id' => $agentId]);

        return (bool) $stmt->fetchColumn();
    }

    private function agentName(int $agentId): ?string
    {
        $stmt = $this->db->prepare('SELECT name FROM agents WHERE id = :id');
        $stmt->execute(['id' => $agentId]);
        $name = $stmt->fetchColumn();

        return $name === false ? null : (string) $name;
    }

    private function nextTicketNumber(): string
    {
        $max = $this->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(ticket_no, 4) AS UNSIGNED)), 1029)
             FROM tickets
             WHERE ticket_no LIKE 'HD-%'"
        );

        return 'HD-' . ($max + 1);
    }

    private function normalizePriority(string $priority): string
    {
        return in_array($priority, SlaService::PRIORITY_ORDER, true) ? $priority : 'Medium';
    }

    private function scalar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }

    private function log(int $ticketId, string $message): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ticket_events (ticket_id, message) VALUES (:ticket_id, :message)'
        );
        $stmt->execute(['ticket_id' => $ticketId, 'message' => $message]);
    }
}
