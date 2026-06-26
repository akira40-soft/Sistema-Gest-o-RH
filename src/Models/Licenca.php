<?php
namespace App\Models;

use App\Database\Database;
use PDO;

class Licenca
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function solicitar(array $dados, ?array $arquivo = null): int
    {
        $caminhoComprovativo = null;

        if ($arquivo && $arquivo['error'] === UPLOAD_ERR_OK) {
            $docModel = new Documento();
            $upload = $docModel->processarUpload($arquivo, $dados['funcionario_id'], 'comprovativo_licenca');
            $caminhoComprovativo = $upload['caminho'];
        }

        $inicio = new \DateTime($dados['data_inicio']);
        $fim = new \DateTime($dados['data_fim']);
        $dias = $inicio->diff($fim)->days + 1;

        return (int) $this->db->insert('licencas', [
            'funcionario_id' => $dados['funcionario_id'],
            'tipo' => $dados['tipo'],
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => $dados['data_fim'],
            'dias_uteis' => $dias,
            'motivo' => $dados['motivo'],
            'documento_comprovativo' => $caminhoComprovativo,
            'status' => 'pendente'
        ]);
    }

    public function listarPorFuncionario(int $funcionarioId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT * FROM licencas WHERE funcionario_id = :fid ORDER BY created_at DESC");
        $stmt->execute([':fid' => $funcionarioId]);
        return $stmt->fetchAll();
    }

    public function listarPendentes(): array
    {
        $sql = "SELECT l.*, f.nome_completo, d.nome as departamento
                FROM licencas l
                JOIN funcionarios f ON l.funcionario_id = f.id
                JOIN departamentos d ON f.departamento_id = d.id
                WHERE l.status = 'pendente'
                ORDER BY l.data_inicio";
        return $this->db->getConnection()->query($sql)->fetchAll();
    }

    public function atualizarStatus(int $id, string $status, int $aprovadoPor, string $observacoes = ''): bool
    {
        return $this->db->update('licencas', [
            'status' => $status,
            'aprovado_por' => $aprovadoPor,
            'data_aprovacao' => date('Y-m-d H:i:s'),
            'observacoes' => $observacoes
        ], ['id' => $id]);
    }
}
