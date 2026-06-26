<?php
namespace App\Models;

use App\Database\Database;
use PDO;

class Escala
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listarPorPeriodo(string $dataInicio, string $dataFim): array
    {
        $sql = "SELECT e.*, f.nome_completo, t.nome as nome_turno, t.hora_inicio, t.hora_fim, t.tipo as tipo_turno
                FROM escalas e
                JOIN funcionarios f ON e.funcionario_id = f.id
                JOIN turnos t ON e.turno_id = t.id
                WHERE e.data BETWEEN :inicio AND :fim
                ORDER BY e.data, t.hora_inicio";

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':inicio', $dataInicio);
        $stmt->bindValue(':fim', $dataFim);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function agendar(int $funcionarioId, int $turnoId, string $data, int $criadoPor): int
    {
        return (int) $this->db->insert('escalas', [
            'funcionario_id' => $funcionarioId,
            'turno_id' => $turnoId,
            'data' => $data,
            'status' => 'agendado',
            'criado_por' => $criadoPor
        ]);
    }

    public function verificarConflito(int $funcionarioId, string $data): bool
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM escalas WHERE funcionario_id = :fid AND data = :data AND status != 'cancelado'");
        $stmt->bindValue(':fid', $funcionarioId);
        $stmt->bindValue(':data', $data);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
}
