<?php
/**
 * Workflow de Aprovações Multi-Nível
 *
 * Para licenças, férias, dispensas, requisições, etc.
 *
 * Uso:
 *   $wf = new Workflow('licenca', $licencaId);
 *   $wf->start();                            // inicia workflow
 *   $wf->approve($userId, 'Aprovado pelo RH');
 *   $wf->reject($userId, 'Falta de documentação');
 *   $wf->status();                           // 'pendente', 'aprovado', 'rejeitado', 'em_aprovacao'
 */

namespace App\Utils;

use App\Database\Database;

class Workflow
{
    private string $tipo;
    private $entidadeId;
    private array $etapas;        // [['nome' => 'rh', 'role' => 'gestor_rh', 'ordem' => 1], ...]
    private array $aprovadores;   // IDs dos aprovadores
    private string $estado;       // 'pendente', 'em_aprovacao', 'aprovado', 'rejeitado', 'cancelado'

    public function __construct(string $tipo, $entidadeId)
    {
        $this->tipo = $tipo;
        $this->entidadeId = $entidadeId;
        $this->etapas = $this->loadEtapas();
        $this->aprovadores = $this->loadAprovadores();
        $this->estado = $this->loadEstado();
    }

    public function start(): bool
    {
        if ($this->estado !== 'pendente' && $this->estado !== 'em_aprovacao') {
            return false;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO workflow_aprovacoes (tipo, entidade_id, etapa_atual, estado, criado_em)
            VALUES (:tipo, :eid, 1, 'em_aprovacao', NOW())
        ");
        $r = $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);

        if ($r) {
            $this->estado = 'em_aprovacao';
            Audit::log('workflow_start', $this->tipo, $this->entidadeId, 'Workflow iniciado');
            $this->notifyCurrentApprovers();
        }
        return $r;
    }

    public function approve(int $userId, ?string $comentario = null): bool
    {
        if ($this->estado !== 'em_aprovacao') {
            return false;
        }

        $db = Database::getInstance()->getConnection();

        $wfRow = $this->getWorkflowRow();
        $etapaAtual = $wfRow['etapa_atual'] ?? 1;
        $totalEtapas = count($this->etapas);

        $stmt = $db->prepare("
            UPDATE workflow_aprovacoes
            SET estado = 'aprovado', atualizado_em = NOW()
            WHERE tipo = :tipo AND entidade_id = :eid
        ");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);

        $stmt = $db->prepare("
            INSERT INTO workflow_historico
              (workflow_tipo, workflow_entidade_id, etapa, acao, user_id, comentario, criado_em)
            VALUES
              (:tipo, :eid, :etapa, 'aprovado', :uid, :coment, NOW())
        ");
        $stmt->execute([
            ':tipo'   => $this->tipo,
            ':eid'    => $this->entidadeId,
            ':etapa'  => $etapaAtual,
            ':uid'    => $userId,
            ':coment' => $comentario
        ]);

        if ($etapaAtual >= $totalEtapas) {
            $this->estado = 'aprovado';
            $this->onApproved();
            Audit::log('approve', $this->tipo, $this->entidadeId, $comentario ?? 'Aprovação final', $userId);
        } else {
            $novaEtapa = $etapaAtual + 1;
            $stmt = $db->prepare("UPDATE workflow_aprovacoes SET etapa_atual = :e WHERE tipo = :tipo AND entidade_id = :eid");
            $stmt->execute([':e' => $novaEtapa, ':tipo' => $this->tipo, ':eid' => $this->entidadeId]);
            $this->notifyCurrentApprovers();
        }

        return true;
    }

    public function reject(int $userId, string $motivo): bool
    {
        if ($this->estado !== 'em_aprovacao' && $this->estado !== 'pendente') {
            return false;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            UPDATE workflow_aprovacoes
            SET estado = 'rejeitado', atualizado_em = NOW()
            WHERE tipo = :tipo AND entidade_id = :eid
        ");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);

        $stmt = $db->prepare("
            INSERT INTO workflow_historico
              (workflow_tipo, workflow_entidade_id, etapa, acao, user_id, comentario, criado_em)
            VALUES
              (:tipo, :eid, 0, 'rejeitado', :uid, :motivo, NOW())
        ");
        $stmt->execute([
            ':tipo'   => $this->tipo,
            ':eid'    => $this->entidadeId,
            ':uid'    => $userId,
            ':motivo' => $motivo
        ]);

        $this->estado = 'rejeitado';
        $this->onRejected($motivo);
        Audit::log('reject', $this->tipo, $this->entidadeId, $motivo, $userId);

        return true;
    }

    public function cancel(int $userId, ?string $motivo = null): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE workflow_aprovacoes SET estado = 'cancelado', atualizado_em = NOW() WHERE tipo = :tipo AND entidade_id = :eid");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);

        $this->estado = 'cancelado';
        Audit::log('cancel', $this->tipo, $this->entidadeId, $motivo, $userId);
        return true;
    }

    public function status(): string
    {
        return $this->estado;
    }

    public function history(): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT h.*, u.username
            FROM workflow_historico h
            LEFT JOIN usuarios u ON h.user_id = u.id
            WHERE h.workflow_tipo = :tipo AND h.workflow_entidade_id = :eid
            ORDER BY h.criado_em ASC
        ");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadEtapas(): array
    {
        switch ($this->tipo) {
            case 'licenca':
                return [
                    ['nome' => 'Líder Direto',  'role' => 'lider_farmaceutico', 'ordem' => 1],
                    ['nome' => 'RH',            'role' => 'gestor_rh',          'ordem' => 2]
                ];
            case 'folha_pagamento':
                return [
                    ['nome' => 'RH',                  'role' => 'gestor_rh',    'ordem' => 1],
                    ['nome' => 'Direção',             'role' => 'super_admin',  'ordem' => 2]
                ];
            case 'advertencia':
                return [
                    ['nome' => 'RH',  'role' => 'gestor_rh',   'ordem' => 1],
                    ['nome' => 'Dir', 'role' => 'super_admin', 'ordem' => 2]
                ];
            case 'vaga':
                return [
                    ['nome' => 'RH',  'role' => 'gestor_rh',   'ordem' => 1]
                ];
            default:
                return [['nome' => 'RH', 'role' => 'gestor_rh', 'ordem' => 1]];
        }
    }

    private function loadAprovadores(): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username FROM usuarios WHERE tipo_acesso IN ('gestor_rh', 'super_admin', 'lider_farmaceutico') AND ativo = 1");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadEstado(): string
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT estado FROM workflow_aprovacoes WHERE tipo = :tipo AND entidade_id = :eid");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);
        $row = $stmt->fetch();
        return $row['estado'] ?? 'pendente';
    }

    private function getWorkflowRow(): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM workflow_aprovacoes WHERE tipo = :tipo AND entidade_id = :eid");
        $stmt->execute([':tipo' => $this->tipo, ':eid' => $this->entidadeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function notifyCurrentApprovers(): void
    {
        $wfRow = $this->getWorkflowRow();
        $etapaAtual = $wfRow['etapa_atual'] ?? 1;
        $etapa = $this->etapas[$etapaAtual - 1] ?? null;
        if (!$etapa) return;

        Notification::sendToRole(
            $etapa['role'],
            'Aprovação pendente',
            "{$etapa['nome']}: {$this->tipo} #{$this->entidadeId} aguarda aprovação",
            'warning',
            "/{$this->tipo}.php?id={$this->entidadeId}"
        );
    }

    private function onApproved(): void
    {
        // Hook para ações pós-aprovação
        if ($this->tipo === 'licenca') {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE licencas SET status = 'aprovada', data_aprovacao = NOW() WHERE id = :id");
            $stmt->execute([':id' => $this->entidadeId]);
        }
    }

    private function onRejected(string $motivo): void
    {
        if ($this->tipo === 'licenca') {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE licencas SET status = 'rejeitada', observacoes = CONCAT(IFNULL(observacoes,''), '\nRejeitado: ', :motivo) WHERE id = :id");
            $stmt->execute([':id' => $this->entidadeId, ':motivo' => $motivo]);
        }
    }
}
