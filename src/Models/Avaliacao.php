<?php
namespace App\Models;

use App\Database\Database;
use PDO;

class Avaliacao
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function iniciar(array $dados): int
    {
        return (int) $this->db->insert('avaliacoes', [
            'funcionario_id' => $dados['funcionario_id'],
            'avaliador_id' => $dados['avaliador_id'],
            'periodo_inicio' => $dados['periodo_inicio'],
            'periodo_fim' => $dados['periodo_fim'],
            'tipo' => $dados['tipo'],
            'status' => 'rascunho'
        ]);
    }

    public function salvar(int $id, array $dados): bool
    {
        $soma = $dados['atendimento_cliente'] +
            $dados['conhecimento_tecnico'] +
            $dados['pontualidade'] +
            $dados['trabalho_equipe'] +
            $dados['cumprimento_metas'] +
            $dados['proatividade'];

        $notaFinal = round($soma / 6, 2);

        $classificacao = 'regular';
        if ($notaFinal >= 4.5) {
            $classificacao = 'excelente';
        } elseif ($notaFinal >= 3.5) {
            $classificacao = 'muito_bom';
        } elseif ($notaFinal >= 2.5) {
            $classificacao = 'bom';
        } elseif ($notaFinal < 2.0) {
            $classificacao = 'insuficiente';
        }

        $dadosUpdate = [
            'atendimento_cliente' => $dados['atendimento_cliente'],
            'conhecimento_tecnico' => $dados['conhecimento_tecnico'],
            'pontualidade' => $dados['pontualidade'],
            'trabalho_equipe' => $dados['trabalho_equipe'],
            'cumprimento_metas' => $dados['cumprimento_metas'],
            'proatividade' => $dados['proatividade'],
            'nota_final' => $notaFinal,
            'classificacao' => $classificacao,
            'pontos_fortes' => $dados['pontos_fortes'],
            'pontos_melhoria' => $dados['pontos_melhoria'],
            'plano_desenvolvimento' => $dados['plano_desenvolvimento']
        ];

        if (isset($dados['finalizar']) && $dados['finalizar']) {
            $dadosUpdate['status'] = 'finalizada';
            $dadosUpdate['data_assinatura_avaliador'] = date('Y-m-d H:i:s');
        }

        return $this->db->update('avaliacoes', $dadosUpdate, ['id' => $id]);
    }

    public function listarPorFuncionario(int $funcionarioId): array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT a.*, u.username as avaliador FROM avaliacoes a JOIN usuarios u ON a.avaliador_id = u.id WHERE a.funcionario_id = :fid ORDER BY a.periodo_fim DESC");
        $stmt->execute([':fid' => $funcionarioId]);
        return $stmt->fetchAll();
    }

    public function obterPorId(int $id): ?array
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("SELECT a.*, f.nome_completo as funcionario_nome FROM avaliacoes a JOIN funcionarios f ON a.funcionario_id = f.id WHERE a.id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
