<?php

declare(strict_types=1);

if (!isset($_GET['route']) || $_GET['route'] === '') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/api/([^?]+)#', $uri, $matches)) {
        $_GET['route'] = $matches[1];
    }
}

require dirname(__DIR__) . '/backend/api/index.php';
