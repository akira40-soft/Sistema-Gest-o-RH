<?php
/**
 * API de Notificações — Pontos de extremidade REST para AJAX.
 *
 * GET  ?action=count           → { success, unread }
 * GET  ?action=list&limit=N    → { success, notifications[] }
 * POST ?action=mark_read       → { success }  (body: id)
 * POST ?action=mark_all        → { success }
 * POST ?action=delete          → { success }  (body: id)
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Notification;

header('Content-Type: application/json; charset=utf-8');

$auth = new \App\Auth\Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$userId  = $auth->getUserId();
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];

try {
    $db = \App\Database\Database::getInstance()->getConnection();

    switch ($action) {

        /* ── Contagem de não lidas ─────────────────────── */
        case 'count':
            $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND lida = 0");
            $stmt->execute([':u' => $userId]);
            $unread = (int) $stmt->fetchColumn();
            echo json_encode(['success' => true, 'unread' => $unread]);
            break;

        /* ── Lista de notificações ─────────────────────── */
        case 'list':
            $limit = min(max((int)($_GET['limit'] ?? 15), 1), 50);
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            $stmt = $db->prepare("
                SELECT id, titulo, mensagem, tipo, link, canal, lida, criado_em
                FROM notifications
                WHERE user_id = :u
                ORDER BY lida ASC, criado_em DESC
                LIMIT :l OFFSET :o
            ");
            $stmt->bindValue(':u', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notifications = array_map(function ($r) {
                $dt = new DateTime($r['criado_em'], new DateTimeZone('Africa/Luanda'));
                $now = new DateTime('now', new DateTimeZone('Africa/Luanda'));
                $diff = $now->getTimestamp() - $dt->getTimestamp();
                if ($diff < 60)       $rel = 'Agora';
                elseif ($diff < 3600) $rel = floor($diff / 60) . ' min';
                elseif ($diff < 86400) $rel = floor($diff / 3600) . ' h';
                else                  $rel = floor($diff / 86400) . ' d';
                $r['relative_time'] = $rel;
                $r['criado_em_formatada'] = $dt->format('d/m/Y H:i');
                return $r;
            }, $rows);

            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        /* ── Marcar uma como lida ──────────────────────── */
        case 'mark_read':
            if ($method !== 'POST') { http_response_code(405); throw new \Exception('Método não permitido.'); }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID inválido.');
            $stmt = $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE id = :id AND user_id = :u AND lida = 0");
            $stmt->execute([':id' => $id, ':u' => $userId]);
            echo json_encode(['success' => true]);
            break;

        /* ── Marcar todas como lidas ───────────────────── */
        case 'mark_all':
            if ($method !== 'POST') { http_response_code(405); throw new \Exception('Método não permitido.'); }
            $stmt = $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE user_id = :u AND lida = 0");
            $stmt->execute([':u' => $userId]);
            echo json_encode(['success' => true]);
            break;

        /* ── Eliminar uma notificação ──────────────────── */
        case 'delete':
            if ($method !== 'POST') { http_response_code(405); throw new \Exception('Método não permitido.'); }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new \Exception('ID inválido.');
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :u");
            $stmt->execute([':id' => $id, ':u' => $userId]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
    }

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
