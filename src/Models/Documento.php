<?php
namespace App\Models;

use App\Database\Database;
use Exception;

class Documento
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function salvar(array $dados): int
    {
        return (int) $this->db->insert('documentos_funcionarios', [
            'funcionario_id' => $dados['funcionario_id'],
            'tipo_documento' => $dados['tipo_documento'],
            'nome_original' => $dados['nome_original'],
            'nome_arquivo' => $dados['nome_arquivo'],
            'caminho_arquivo' => $dados['caminho_arquivo'],
            'tamanho_kb' => $dados['tamanho_kb'],
            'mime_type' => $dados['mime_type'],
            'uploaded_por' => $dados['uploaded_por'],
            'data_validade' => $dados['data_validade'] ?? null
        ]);
    }

    public function listarPorFuncionario(int $funcionarioId): array
    {
        $sql = "SELECT d.*, u.username as uploader_nome 
                FROM documentos_funcionarios d
                LEFT JOIN usuarios u ON d.uploaded_por = u.id
                WHERE d.funcionario_id = :id AND d.ativo = 1
                ORDER BY d.created_at DESC";

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $funcionarioId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listarVencendo(int $dias = 30): array
    {
        $sql = "SELECT d.*, f.nome_completo 
                FROM documentos_funcionarios d
                JOIN funcionarios f ON d.funcionario_id = f.id
                WHERE d.ativo = 1 
                AND d.data_validade BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL :dias DAY)";

        $conn = $this->db->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':dias', $dias);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function processarUpload(array $arquivo, int $funcionarioId, string $tipo): array
    {
        $permitidos = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
        $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extensao, $permitidos)) {
            throw new Exception("Tipo de arquivo não permitido: $extensao");
        }

        if ($arquivo['size'] > 5 * 1024 * 1024) {
            throw new Exception("Arquivo muito grande. Máximo 5MB.");
        }

        $dir = __DIR__ . '/../../uploads/funcionarios/' . $funcionarioId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $novoNome = $tipo . '_' . time() . '_' . uniqid() . '.' . $extensao;
        $caminhoCompleto = $dir . '/' . $novoNome;

        if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            return [
                'nome_arquivo' => $novoNome,
                'caminho' => 'uploads/funcionarios/' . $funcionarioId . '/' . $novoNome,
                'tamanho_kb' => round($arquivo['size'] / 1024),
                'mime' => $arquivo['type']
            ];
        }

        throw new Exception("Falha ao mover arquivo para o diretório de destino.");
    }
}
