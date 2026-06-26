<?php
namespace App\Models;

use App\Database\Database;
use PDO;

class Turno
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarTodos(): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->query("SELECT * FROM turnos WHERE ativo = 1 ORDER BY nome");
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM turnos WHERE id = :id");
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function criar(array $dados): int
    {
        return (int) $this->db->insert('turnos', [
            'nome' => $dados['nome'],
            'hora_inicio' => $dados['hora_inicio'],
            'hora_fim' => $dados['hora_fim'],
            'tipo' => $dados['tipo'],
            'duracao_horas' => $dados['duracao_horas'] ?? null,
            'ativo' => 1
        ]);
    }
}
