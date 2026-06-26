<?php
/**
 * Audit Logger — Regista todas as ações sensíveis
 *
 * Uso:
 *   Audit::log('create', 'funcionario', $id, 'Admitiu Maria');
 *   Audit::log('login', 'sessao', null, 'Login bem-sucedido');
 *   Audit::log('update', 'folha_pagamento', $id, ['antes' => $a, 'depois' => $b]);
 */

namespace App\Utils;

use App\Database\Database;

class Audit
{
    /**
     * Registra uma ação no log de auditoria
     */
    public static function log(string $acao, string $entidade, $entidadeId = null, $detalhes = null, ?int $userId = null): bool
    {
        try {
            $db = Database::getInstance()->getConnection();

            if ($userId === null) {
                $userId = $_SESSION['user_id'] ?? null;
            }

            $detalhesJson = is_array($detalhes) || is_object($detalhes)
                ? json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : $detalhes;

            $stmt = $db->prepare("
                INSERT INTO audit_logs
                  (user_id, acao, entidade, entidade_id, detalhes, ip_address, user_agent, criado_em)
                VALUES
                  (:uid, :acao, :ent, :eid, :det, :ip, :ua, NOW())
            ");

            return $stmt->execute([
                ':uid'  => $userId,
                ':acao' => $acao,
                ':ent'  => $entidade,
                ':eid'  => $entidadeId,
                ':det'  => $detalhesJson,
                ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        } catch (\Exception $e) {
            error_log("Audit::log falhou: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logs de um utilizador específico
     */
    public static function byUser(int $userId, int $limit = 50): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT a.*, u.username
            FROM audit_logs a
            LEFT JOIN usuarios u ON a.user_id = u.id
            WHERE a.user_id = :uid
            ORDER BY a.criado_em DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Logs de uma entidade específica
     */
    public static function byEntity(string $entidade, $entidadeId, int $limit = 20): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT a.*, u.username
            FROM audit_logs a
            LEFT JOIN usuarios u ON a.user_id = u.id
            WHERE a.entidade = :ent AND a.entidade_id = :eid
            ORDER BY a.criado_em DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':ent', $entidade);
        $stmt->bindValue(':eid', $entidadeId);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Atividade recente do sistema
     */
    public static function recent(int $limit = 30): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT a.*, u.username
            FROM audit_logs a
            LEFT JOIN usuarios u ON a.user_id = u.id
            ORDER BY a.criado_em DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ações pré-definidas (helpers de domínio)
     */
    public static function login(?int $userId = null, bool $sucesso = true): void
    {
        self::log(
            $sucesso ? 'login' : 'login_falha',
            'sessao',
            null,
            $sucesso ? 'Login bem-sucedido' : 'Tentativa de login falhada',
            $userId
        );
    }

    public static function logout(int $userId): void
    {
        self::log('logout', 'sessao', $userId, 'Sessão terminada', $userId);
    }

    public static function create(string $entidade, $id, $descricao = null, ?int $userId = null): void
    {
        self::log('create', $entidade, $id, $descricao, $userId);
    }

    public static function update(string $entidade, $id, $diferencas = null, ?int $userId = null): void
    {
        self::log('update', $entidade, $id, $diferencas, $userId);
    }

    public static function delete(string $entidade, $id, $descricao = null, ?int $userId = null): void
    {
        self::log('delete', $entidade, $id, $descricao, $userId);
    }

    public static function approve(string $entidade, $id, $descricao = null, ?int $userId = null): void
    {
        self::log('approve', $entidade, $id, $descricao, $userId);
    }

    public static function reject(string $entidade, $id, $motivo, ?int $userId = null): void
    {
        self::log('reject', $entidade, $id, $motivo, $userId);
    }
}
