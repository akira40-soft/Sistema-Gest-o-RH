<?php

namespace App\Database;

use PDO;
use PDOException;

/**
 * Classe Database - Padrão Singleton para conexão com MySQL
 * Esta classe garante que apenas uma conexão com o banco de dados seja aberta durante a execução.
 * 
 * Configuração:
 * - Banco: farmacia_valodia_rg
 * - Charset: UTF-8
 * - Error Mode: Exceptions
 */
class Database
{
    private static $instance = null;
    private $conn;

    // Configurações do banco de dados
    private $host = 'localhost';
    private $db_name = 'farmacia_valodia_rg'; // Nome atualizado
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';

    /**
     * Construtor privado para implementar Singleton
     * @throws PDOException se a conexão falhar
     */
    private function __construct()
    {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false // Desabilitar conexões persistentes por padrão
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            // Log de sucesso (apenas em desenvolvimento)
            if ($_ENV['APP_ENV'] ?? 'development' === 'development') {
                error_log("✅ Conexão com banco de dados estabelecida com sucesso!");
            }

        }
        catch (PDOException $e) {
            // Log detalhado do erro
            error_log("❌ Erro de Conexão com Banco de Dados: " . $e->getMessage());
            error_log("Stack Trace: " . $e->getTraceAsString());

            // Em produção, não expor detalhes do erro
            if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
                throw new PDOException("Erro ao conectar ao banco de dados. Contate o administrador do sistema.");
            }

            throw $e; // Em desenvolvimento, mostra o erro completo
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
