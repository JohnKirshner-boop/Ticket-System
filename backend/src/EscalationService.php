<?php

declare(strict_types=1);

namespace TicketSystem;

use PDO;

final class EscalationService
{
    private const MAX_LEVEL = 3;

    public function __construct(private PDO $db)
    {
    }

    public function autoEscalateOverdue(): int
    {
        $stmt = $this->db->query(
            "SELECT id, ticket_no, priority
             FROM tickets
             WHERE status != 'Resolved'
               AND sla_due_at < NOW()
               AND auto_escalated_at IS NULL"
        );

        $count = 0;
        foreach ($stmt->fetchAll() as $ticket) {
            $this->escalate((int) $ticket['id'], 'Auto-escalated because SLA is overdue.', false);
            $count++;
        }

        return $count;
    }

    public function escalate(int $ticketId, string $reason, bool $manual): ?array
    {
        $ticket = $this->fetchTicket($ticketId);
        if ($ticket === null || $ticket['status'] === 'Resolved') {
            return null;
        }

        $currentLevel = (int) $ticket['escalation_level'];
        $newLevel = min($currentLevel + 1, self::MAX_LEVEL);
        $agentId = $this->pickAgent($newLevel, (string) $ticket['priority']);

        $stmt = $this->db->prepare(
            "UPDATE tickets
             SET status = 'Escalated',
                 escalation_level = :level,
                 agent_id = :agent_id,
                 auto_escalated_at = COALESCE(auto_escalated_at, :auto_escalated_at),
                 updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'level' => $newLevel,
            'agent_id' => $agentId,
            'auto_escalated_at' => $manual ? null : date('Y-m-d H:i:s'),
            'id' => $ticketId,
        ]);

        $message = ($manual ? 'Manual escalation' : 'Automatic escalation') .
            ' to level ' . $newLevel . ': ' . $reason;
        $this->log($ticketId, $message);

        return TicketRepository::findByDatabaseId($this->db, $ticketId);
    }

    private function pickAgent(int $level, string $priority): ?int
    {
        $minSkill = match (true) {
            $level >= 3 => 3,
            $level >= 2 || in_array($priority, ['Urgent', 'High'], true) => 2,
            default => 1,
        };

        $stmt = $this->db->prepare(
            "SELECT a.id
             FROM agents a
             LEFT JOIN tickets t ON t.agent_id = a.id AND t.status != 'Resolved'
             WHERE a.active = 1 AND a.skill_level >= :skill
             GROUP BY a.id
             ORDER BY COUNT(t.id) ASC, a.skill_level DESC, a.id ASC
             LIMIT 1"
        );
        $stmt->execute(['skill' => $minSkill]);
        $agentId = $stmt->fetchColumn();

        return $agentId === false ? null : (int) $agentId;
    }

    private function fetchTicket(int $ticketId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tickets WHERE id = :id');
        $stmt->execute(['id' => $ticketId]);
        $ticket = $stmt->fetch();

        return $ticket ?: null;
    }

    private function log(int $ticketId, string $message): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ticket_events (ticket_id, message) VALUES (:ticket_id, :message)'
        );
        $stmt->execute(['ticket_id' => $ticketId, 'message' => $message]);
    }
}
