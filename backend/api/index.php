<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/SlaService.php';
require_once dirname(__DIR__) . '/src/TicketRepository.php';
require_once dirname(__DIR__) . '/src/EscalationService.php';

use TicketSystem\Database;
use TicketSystem\EscalationService;
use TicketSystem\TicketRepository;

function jsonResponse(mixed $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function requestBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST ?: [];
}

try {
    $db = Database::connection();
    $escalation = new EscalationService($db);
    $escalation->autoEscalateOverdue();
    $repo = new TicketRepository($db);

    $method = $_SERVER['REQUEST_METHOD'];
    $route = trim((string) ($_GET['route'] ?? ''), '/');
    $segments = $route === '' ? [] : explode('/', $route);

    if ($segments === [] || $segments[0] === 'health') {
        jsonResponse(['ok' => true, 'service' => 'ticket-system-api']);
    }

    if ($segments[0] === 'dashboard' && $method === 'GET') {
        jsonResponse($repo->dashboard());
    }

    if ($segments[0] === 'agents' && $method === 'GET') {
        jsonResponse(['agents' => $repo->agents()]);
    }

    if ($segments[0] === 'queues' && $method === 'GET') {
        jsonResponse(['queues' => $repo->priorityQueues()]);
    }

    if ($segments[0] === 'rules' && $method === 'GET') {
        jsonResponse(['rules' => $repo->rules()]);
    }

    if ($segments[0] === 'sla' && $method === 'GET') {
        jsonResponse($repo->slaMonitor());
    }

    if ($segments[0] === 'tickets') {
        if ($method === 'GET' && count($segments) === 1) {
            jsonResponse([
                'tickets' => $repo->tickets([
                    'q' => $_GET['q'] ?? '',
                    'status' => $_GET['status'] ?? 'all',
                    'priority' => $_GET['priority'] ?? 'all',
                ]),
            ]);
        }

        if ($method === 'POST' && count($segments) === 1) {
            $body = requestBody();
            if (trim((string) ($body['requester_name'] ?? '')) === '' || trim((string) ($body['title'] ?? '')) === '') {
                jsonResponse(['error' => 'requester_name and title are required'], 422);
            }

            jsonResponse(['ticket' => $repo->create($body)], 201);
        }

        if (count($segments) >= 2) {
            $dbId = $repo->dbIdFromIdentifier($segments[1]);
            if ($dbId === null) {
                jsonResponse(['error' => 'Ticket not found'], 404);
            }

            if ($method === 'GET' && count($segments) === 2) {
                jsonResponse(['ticket' => TicketRepository::findByDatabaseId($db, $dbId)]);
            }

            if ($method === 'POST' && ($segments[2] ?? '') === 'assign') {
                $body = requestBody();
                $agentId = (int) ($body['agent_id'] ?? 0);
                if ($agentId <= 0) {
                    jsonResponse(['error' => 'agent_id is required'], 422);
                }

                $ticket = $repo->assign($dbId, $agentId);
                if ($ticket === null) {
                    jsonResponse(['error' => 'Agent could not be assigned'], 400);
                }
                jsonResponse(['ticket' => $ticket]);
            }

            if ($method === 'POST' && ($segments[2] ?? '') === 'reassign') {
                jsonResponse(['ticket' => $repo->reassign($dbId)]);
            }

            if ($method === 'POST' && ($segments[2] ?? '') === 'escalate') {
                jsonResponse([
                    'ticket' => $escalation->escalate($dbId, 'Manager requested escalation.', true),
                ]);
            }

            if ($method === 'POST' && ($segments[2] ?? '') === 'resolve') {
                jsonResponse(['ticket' => $repo->resolve($dbId)]);
            }
        }
    }

    jsonResponse(['error' => 'Route not found'], 404);
} catch (Throwable $exception) {
    jsonResponse([
        'error' => 'Server error',
        'message' => $exception->getMessage(),
    ], 500);
}
