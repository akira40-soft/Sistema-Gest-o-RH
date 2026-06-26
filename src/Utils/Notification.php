<?php
/**
 * Sistema de Notificações in-app + email queue
 *
 * Uso:
 *   Notification::send($userId, 'Título', 'Mensagem', 'info', '/url');
 *   Notification::sendToRole('gestor_rh', 'Nova admissão', 'Maria admitida', 'success');
 *   Notification::sendToAll('Comunicado', 'Ver detalhes', 'warning');
 */

namespace App\Utils;

use App\Database\Database;

class Notification
{
    public const TYPES = ['info', 'success', 'warning', 'danger'];

    /**
     * Envia para um utilizador específico
     */
    public static function send(int $userId, string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null, ?string $canal = 'in_app'): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO notifications
                  (user_id, titulo, mensagem, tipo, link, canal, lida, criado_em)
                VALUES
                  (:uid, :tit, :msg, :tipo, :link, :canal, 0, NOW())
            ");
            $r = $stmt->execute([
                ':uid'   => $userId,
                ':tit'   => $titulo,
                ':msg'   => $mensagem,
                ':tipo'  => $tipo,
                ':link'  => $link,
                ':canal' => $canal
            ]);

            if ($r && $canal === 'email') {
                self::queueEmail($userId, $titulo, $mensagem);
            }

            return $r;
        } catch (\Exception $e) {
            error_log("Notification::send falhou: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia para todos os utilizadores de um papel
     */
    public static function sendToRole(string $role, string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null): int
    {
        $db = Database::getInstance()->getConnection();
        $users = $db->prepare("SELECT id FROM usuarios WHERE tipo_acesso = :r AND ativo = 1");
        $users->execute([':r' => $role]);
        $count = 0;
        while ($u = $users->fetch()) {
            if (self::send((int)$u['id'], $titulo, $mensagem, $tipo, $link)) $count++;
        }
        return $count;
    }

    /**
     * Envia para todos os utilizadores ativos
     */
    public static function sendToAll(string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null): int
    {
        $db = Database::getInstance()->getConnection();
        $users = $db->query("SELECT id FROM usuarios WHERE ativo = 1");
        $count = 0;
        while ($u = $users->fetch()) {
            if (self::send((int)$u['id'], $titulo, $mensagem, $tipo, $link)) $count++;
        }
        return $count;
    }

    /**
     * Envia para todos os funcionários de um departamento
     */
    public static function sendToDepartment(int $departamentoId, string $titulo, string $mensagem, string $tipo = 'info', ?string $link = null): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.id FROM usuarios u
            JOIN funcionarios f ON f.usuario_id = u.id
            WHERE f.departamento_id = :d AND u.ativo = 1
        ");
        $stmt->execute([':d' => $departamentoId]);
        $count = 0;
        while ($u = $stmt->fetch()) {
            if (self::send((int)$u['id'], $titulo, $mensagem, $tipo, $link)) $count++;
        }
        return $count;
    }

    /**
     * Marca como lida
     */
    public static function markRead(int $notificationId, int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE id = :id AND user_id = :uid");
        return $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
    }

    public static function markAllRead(int $userId): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE user_id = :uid AND lida = 0");
        $stmt->execute([':uid' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Lista notificações de um utilizador
     */
    public static function listFor(int $userId, int $limit = 20, bool $onlyUnread = false): array
    {
        $db = Database::getInstance()->getConnection();
        $sql = "SELECT * FROM notifications WHERE user_id = :uid" . ($onlyUnread ? " AND lida = 0" : "") . " ORDER BY criado_em DESC LIMIT :lim";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function countUnread(int $userId): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND lida = 0");
        $stmt->execute([':uid' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Adiciona email à fila (será enviado pelo cron /api/cron.php)
     */
    private static function queueEmail(int $userId, string $assunto, string $mensagem): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT email FROM funcionarios WHERE usuario_id = :uid");
            $stmt->execute([':uid' => $userId]);
            $f = $stmt->fetch();
            if (!$f || empty($f['email'])) return;

            $stmt = $db->prepare("
                INSERT INTO email_queue (para, assunto, mensagem, status, criado_em)
                VALUES (:para, :assunto, :msg, 'pendente', NOW())
            ");
            $stmt->execute([
                ':para'    => $f['email'],
                ':assunto' => $assunto,
                ':msg'     => $mensagem
            ]);
        } catch (\Exception $e) {
            error_log("Notification::queueEmail: " . $e->getMessage());
        }
    }
}
