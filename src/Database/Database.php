<?php

namespace App\Database;

use PDO;
use PDOException;

/**
 * Database - Singleton para conexão com MySQL
 * Farmácia Gingongo RG
 *
 * Configuração:
 *  - Banco: farmacia_valodia_rg
 *  - Charset: UTF-8
 *  - Error Mode: Exceptions
 */
class Database
{
    private static $instance = null;
    private $conn = null;
    private $driver = null;

    // Configurações
    private $host = 'localhost';
    private $db_name = 'farmacia_valodia_rg';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';

    /**
     * Construtor privado (Singleton)
     * Tenta MySQL primeiro; em caso de falha, tenta SQLite como fallback.
     */
    private function __construct()
    {
        $connected = false;

        // Tentar MySQL
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            $this->driver = 'mysql';
            $connected = true;
        } catch (PDOException $e) {
            error_log("[Database] MySQL indisponível: " . $e->getMessage());
        }

        // Fallback para SQLite se MySQL falhou
        if (!$connected) {
            $this->initSQLite();
        }
    }

    private function initSQLite()
    {
        try {
            $db_path = __DIR__ . '/../../database/farmacia_valodia_rg.db';
            $dir = dirname($db_path);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $is_new = !file_exists($db_path);
            $this->conn = new PDO("sqlite:" . $db_path);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec('PRAGMA foreign_keys = ON');
            $this->driver = 'sqlite';

            if ($is_new) {
                $schema = __DIR__ . '/../../database/schema_sqlite.sql';
                if (file_exists($schema)) {
                    $this->conn->exec(file_get_contents($schema));
                }
            }
        } catch (PDOException $e) {
            error_log("[Database] Falha crítica no SQLite: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public function isMysql()
    {
        return $this->driver === 'mysql';
    }

    public function isConnected()
    {
        try {
            return $this->conn !== null && $this->conn->query('SELECT 1')->fetchColumn() == 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function select($table, $conditions = [], $single = false)
    {
        $sql = "SELECT * FROM {$table}";
        $params = [];
        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $key => $value) {
                $clauses[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $single ? $stmt->fetch() : $stmt->fetchAll();
    }

    public function insert($table, $data)
    {
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = ':' . implode(', :', $keys);
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->conn->prepare($sql);
        foreach ($data as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        return $this->conn->lastInsertId();
    }

    public function update($table, $data, $conditions)
    {
        $set_clauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $set_clauses[] = "{$key} = :set_{$key}";
            $params[":set_{$key}"] = $value;
        }
        $where_clauses = [];
        foreach ($conditions as $key => $value) {
            $where_clauses[] = "{$key} = :where_{$key}";
            $params[":where_{$key}"] = $value;
        }
        $sql = "UPDATE {$table} SET " . implode(', ', $set_clauses);
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete($table, $conditions)
    {
        if (empty($conditions)) {
            throw new \Exception("Delete sem condições não é permitido por segurança.");
        }
        $where_clauses = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            $where_clauses[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $where_clauses);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    private function __clone() {}
    public function __wakeup() { throw new \Exception("Não é possível desserializar."); }
}
