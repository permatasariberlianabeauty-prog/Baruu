<?php
/**
 * NOXARA - Database Connection (MySQLi Singleton)
 */

class Database
{
    private static ?Database $instance = null;
    private mysqli $connection;

    private function __construct()
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME,
                DB_PORT
            );
            $this->connection->set_charset(DB_CHARSET);
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        } catch (mysqli_sql_exception $e) {
            if (APP_DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Koneksi database gagal. Silakan coba beberapa saat lagi.');
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        // Reconnect jika koneksi terputus
        if (!$this->connection->ping()) {
            $this->connection->close();
            self::$instance = null;
            self::$instance = new self();
        }
        return $this->connection;
    }

    // Shortcut: execute prepared statement, return result
    public function query(string $sql, string $types = '', mixed ...$params): mysqli_result|bool
    {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result !== false ? $result : true;
    }

    // Fetch single row
    public function fetchOne(string $sql, string $types = '', mixed ...$params): ?array
    {
        $result = $this->query($sql, $types, ...$params);
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_assoc();
            $result->free();
            return $row ?: null;
        }
        return null;
    }

    // Fetch all rows
    public function fetchAll(string $sql, string $types = '', mixed ...$params): array
    {
        $result = $this->query($sql, $types, ...$params);
        if ($result instanceof mysqli_result) {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
            return $rows;
        }
        return [];
    }

    // Execute INSERT/UPDATE/DELETE, return affected rows
    public function execute(string $sql, string $types = '', mixed ...$params): int
    {
        $stmt = $this->connection->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed: ' . $this->connection->error);
        }
        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    // Get last insert ID
    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    // Begin transaction
    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    // Commit transaction
    public function commit(): void
    {
        $this->connection->commit();
    }

    // Rollback transaction
    public function rollback(): void
    {
        $this->connection->rollback();
    }

    // Escape string (for edge cases only - prefer prepared statements)
    public function escape(string $value): string
    {
        return $this->connection->real_escape_string($value);
    }

    // Prevent cloning
    private function __clone() {}
}

// Global shortcut function
function db(): Database
{
    return Database::getInstance();
}
