<?php

declare(strict_types=1);

namespace TicketSystem;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require dirname(__DIR__) . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            self::$connection = new PDO($dsn, $config['user'], $config['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $exception) {
            http_response_code(503);
            echo json_encode([
                'error' => 'Database connection failed',
                'message' => 'Start MySQL in XAMPP, then import backend/database/schema.sql and backend/database/seed.sql.',
                'details' => $exception->getMessage(),
            ]);
            exit;
        }

        return self::$connection;
    }
}
