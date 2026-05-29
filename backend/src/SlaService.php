<?php

declare(strict_types=1);

namespace TicketSystem;

use DateInterval;
use DateTimeImmutable;

final class SlaService
{
    public const PRIORITY_ORDER = ['Urgent', 'High', 'Medium', 'Low'];

    public const SLA_MINUTES = [
        'Urgent' => 60,
        'High' => 240,
        'Medium' => 480,
        'Low' => 1440,
    ];

    public const AT_RISK_MINUTES = 120;

    public static function minutesFor(string $priority, string $category = ''): int
    {
        if ($category === 'Security') {
            return 30;
        }

        return self::SLA_MINUTES[$priority] ?? self::SLA_MINUTES['Medium'];
    }

    public static function dueAt(string $priority, string $category = '', ?DateTimeImmutable $from = null): string
    {
        $from ??= new DateTimeImmutable('now');
        $minutes = self::minutesFor($priority, $category);

        return $from->add(new DateInterval('PT' . $minutes . 'M'))->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $ticket
     * @return array{status: string, label: string, display: string, minutes_remaining: int, over_sla: bool}
     */
    public static function evaluate(array $ticket): array
    {
        if (($ticket['status'] ?? '') === 'Resolved') {
            return [
                'status' => 'resolved',
                'label' => 'Resolved',
                'display' => 'Resolved',
                'minutes_remaining' => 0,
                'over_sla' => false,
            ];
        }

        $due = new DateTimeImmutable((string) $ticket['sla_due_at']);
        $now = new DateTimeImmutable('now');
        $minutesRemaining = (int) floor(($due->getTimestamp() - $now->getTimestamp()) / 60);

        if ($minutesRemaining < 0) {
            return [
                'status' => 'over_sla',
                'label' => 'Over SLA',
                'display' => self::formatRemaining($minutesRemaining),
                'minutes_remaining' => $minutesRemaining,
                'over_sla' => true,
            ];
        }

        if ($minutesRemaining <= self::AT_RISK_MINUTES) {
            return [
                'status' => 'at_risk',
                'label' => 'At Risk',
                'display' => self::formatRemaining($minutesRemaining),
                'minutes_remaining' => $minutesRemaining,
                'over_sla' => false,
            ];
        }

        return [
            'status' => 'on_track',
            'label' => 'On Track',
            'display' => self::formatRemaining($minutesRemaining),
            'minutes_remaining' => $minutesRemaining,
            'over_sla' => false,
        ];
    }

    public static function formatRemaining(int $minutesRemaining): string
    {
        $overdue = $minutesRemaining < 0;
        $minutes = abs($minutesRemaining);

        if ($minutes < 60) {
            return $minutes . 'm ' . ($overdue ? 'overdue' : 'left');
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        $text = $hours . 'h' . ($mins > 0 ? ' ' . $mins . 'm' : '');

        return $text . ' ' . ($overdue ? 'overdue' : 'left');
    }
}
