<?php

namespace App\Database;

use PDO;
use PDOException;

/**
 * Classe Database - Versão SQLite
 * 
 * Esta versão usa SQLite ao invés de MySQL.
 * SQLite é um banco de dados em arquivo - NÃO precisa de servidor rodando!
 * 
 * Vantagens:
 * - Não precisa instalar MySQL
 * - Zero configuração
 * - Perfeito para desenvolvimento e testes
 * 
 * Para voltar ao MySQL:
 * 1. Renomeie este arquivo para Database_SQLite.php
 * 2. Renomeie Database_MySQL.php para Database.php
 */
class Database
{
    private static $instance = null;
    private $conn;

    // Caminho do arquivo SQLite
    private $db_path;

    /**
     * Construtor privado para implementar Singleton
     * @throws PDOException se a conexão falhar
     */
    private function __construct()
    {
        try {
            // Definir caminho do banco SQLite
            $this->db_path = __DIR__ . '/../../database/farmacia_valodia_rg.db';

            // Criar diretório se não existir
            $dir = dirname($this->db_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $dsn = "sqlite:" . $this->db_path;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];

            $this->conn = new PDO($dsn, null, null, $options);

            // Habilitar Foreign Keys no SQLite (por padrão estão desabilitadas)
            $this->conn->exec('PRAGMA foreign_keys = ON');

            // Log de sucesso
            error_log("✅ Conexão SQLite estabelecida: " . $this->db_path);

        }
        catch (PDOException $e) {
            error_log("❌ Erro de Conexão SQLite: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retorna a instância única da classe (Singleton)
     * @return Database
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Retorna a conexão PDO
     * @return PDO
     */
    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Testa se a conexão está ativa
     * @return bool
     */
    public function isConnected()
    {
        try {
            return $this->conn !== null && $this->conn->query('SELECT 1')->fetchColumn() === 1;
        }
        catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Retorna o caminho do arquivo do banco
     * @return string
     */
    public function getDatabasePath()
    {
        return $this->db_path;
    }

    /**
     * Previne clonagem da instância
     */
    private function __clone()
    {
    }

    /**
     * Previne desserialização da instância
     */
    public function __wakeup()
    {
        throw new \Exception("Não é possível desserializar um Singleton.");
    }
}
