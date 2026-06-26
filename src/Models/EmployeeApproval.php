<?php

namespace App\Models;

use App\Database\Database;

/**
 * Model: EmployeeApproval
 * Gerencia aprovações de funcionários
 */
class EmployeeApproval
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Criar solicitação de aprovação
     */
    public function create($funcionario_id, $observacoes = null)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            INSERT INTO employee_approvals (funcionario_id, status, observacoes, criado_em)
            VALUES (?, 'pendente', ?, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([$funcionario_id, $observacoes]);
    }

    /**
     * Obter todas as aprovações pendentes
     */
    public function getPending()
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT 
                ea.id,
                ea.funcionario_id,
                ea.status,
                ea.observacoes,
                ea.criado_em,
                f.nome_completo,
                f.email,
                f.cpf,
                c.nome as cargo_nome,
                d.nome as departamento_nome
            FROM employee_approvals ea
            JOIN funcionarios f ON ea.funcionario_id = f.id
            LEFT JOIN cargos c ON f.cargo_id = c.id
            LEFT JOIN departamentos d ON f.departamento_id = d.id
            WHERE ea.status = 'pendente'
            ORDER BY ea.criado_em DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obter aprovação por ID
     */
    public function getById($id)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT 
                ea.*,
                f.nome_completo,
                f.email,
                f.cpf,
                f.data_nascimento,
                c.nome as cargo_nome,
                d.nome as departamento_nome,
                u.username as aprovado_por_username
            FROM employee_approvals ea
            JOIN funcionarios f ON ea.funcionario_id = f.id
            LEFT JOIN cargos c ON f.cargo_id = c.id
            LEFT JOIN departamentos d ON f.departamento_id = d.id
            LEFT JOIN usuarios u ON ea.aprovado_por = u.id
            WHERE ea.id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Aprovar funcionário
     */
    public function approve($id, $usuario_id, $observacoes = null)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            UPDATE employee_approvals
            SET status = 'aprovado',
                aprovado_por = ?,
                observacoes = ?,
                data_aprovacao = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        if ($stmt->execute([$usuario_id, $observacoes, $id])) {
            // Atualizar status do funcionário
            $approval = $this->getById($id);
            $this->updateFuncionarioStatus($approval['funcionario_id'], 'aprovado', $usuario_id);
            return true;
        }
        
        return false;
    }

    /**
     * Rejeitar funcionário
     */
    public function reject($id, $usuario_id, $motivo)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            UPDATE employee_approvals
            SET status = 'rejeitado',
                aprovado_por = ?,
                motivo_rejeicao = ?,
                data_aprovacao = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        if ($stmt->execute([$usuario_id, $motivo, $id])) {
            // Atualizar status do funcionário
            $approval = $this->getById($id);
            $this->updateFuncionarioStatus($approval['funcionario_id'], 'rejeitado', $usuario_id);
            return true;
        }
        
        return false;
    }

    /**
     * Atualizar status do funcionário
     */
    private function updateFuncionarioStatus($funcionario_id, $status, $aprovado_por)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            UPDATE funcionarios
            SET status_aprovacao = ?,
                aprovado_por = ?,
                data_aprovacao = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $aprovado_por, $funcionario_id]);
    }

    /**
     * Obter histórico de aprovações de um funcionário
     */
    public function getHistory($funcionario_id)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT 
                ea.*,
                u.username as aprovado_por_username
            FROM employee_approvals ea
            LEFT JOIN usuarios u ON ea.aprovado_por = u.id
            WHERE ea.funcionario_id = ?
            ORDER BY ea.criado_em DESC
        ");
        
        $stmt->execute([$funcionario_id]);
        return $stmt->fetchAll();
    }

    /**
     * Contar aprovações por status
     */
    public function countByStatus($status)
    {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM employee_approvals WHERE status = ?
        ");
        
        $stmt->execute([$status]);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Obter estatísticas
     */
    public function getStats()
    {
        return [
            'pendentes' => $this->countByStatus('pendente'),
            'aprovadas' => $this->countByStatus('aprovado'),
            'rejeitadas' => $this->countByStatus('rejeitado'),
        ];
    }
}
