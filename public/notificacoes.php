<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$user_role = $auth->getUserRole();
$is_admin = $auth->isAdmin();

$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'list';

// Mark single as read
if ($action === 'read' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header('Location: notificacoes.php');
    exit;
}

// Mark all as read
if ($action === 'read_all') {
    $db->prepare("UPDATE notifications SET lida = 1, lida_em = NOW() WHERE user_id = ? AND lida = 0")->execute([$user_id]);
    header('Location: notificacoes.php');
    exit;
}

// Delete notification
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
    header('Location: notificacoes.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'todas';
$where = "n.user_id = :uid";
$params = [':uid' => $user_id];

if ($filter === 'nao_lidas') {
    $where .= " AND n.lida = 0";
}

$countAll = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$countAll->execute([$user_id]);
$totalNotif = $countAll->fetchColumn();

$countUnread = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lida = 0");
$countUnread->execute([$user_id]);
$unreadNotif = $countUnread->fetchColumn();

$stmt = $db->prepare("
    SELECT n.* FROM notifications n
    WHERE $where
    ORDER BY n.criado_em DESC
    LIMIT 100
");
$stmt->execute($params);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações | Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body class="dashboard-body">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-area" id="mainArea">
            <?php
            $pageTitle = 'Notificações';
            $pageSubtitle = $unreadNotif > 0 ? "$unreadNotif não lida(s)" : 'Todas as notificações do sistema';
            include 'includes/topbar.php';
            ?>
            <div class="content-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Central de Notificações</h2>
                        <p class="text-muted mb-0 small">Gerencie suas notificações do sistema</p>
                    </div>
                    <div class="d-flex gap-2">
                        <div class="btn-group">
                            <a href="?filter=todas" class="btn btn-sm <?php echo $filter === 'todas' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Todas (<?php echo $totalNotif; ?>)</a>
                            <a href="?filter=nao_lidas" class="btn btn-sm <?php echo $filter === 'nao_lidas' ? 'btn-primary' : 'btn-outline-secondary'; ?>">Não lidas (<?php echo $unreadNotif; ?>)</a>
                        </div>
                        <?php if ($unreadNotif > 0): ?>
                        <a href="?action=read_all" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check2-all me-1"></i>Marcar lidas
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($notifications)): ?>
                <div class="card bg-dark-glass text-center py-5">
                    <div class="card-body">
                        <i class="bi bi-bell-slash" style="font-size: 3rem; color: var(--text-muted); opacity: 0.4;"></i>
                        <h5 class="mt-3 text-muted">Nenhuma notificação encontrada</h5>
                        <p class="text-muted small"><?php echo $filter === 'nao_lidas' ? 'Todas as notificações foram lidas.' : 'Ainda não há notificações no sistema.'; ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $n):
                        $tipoIcon = match($n['tipo']) {
                            'success' => 'bi-check-circle-fill text-success',
                            'warning' => 'bi-exclamation-triangle-fill text-warning',
                            'danger' => 'bi-x-circle-fill text-danger',
                            default => 'bi-info-circle-fill text-info'
                        };
                        $tipoBg = match($n['tipo']) {
                            'success' => 'bg-success-soft',
                            'warning' => 'bg-warning-soft',
                            'danger' => 'bg-danger-soft',
                            default => 'bg-info-soft'
                        };
                    ?>
                    <div class="card bg-dark-glass mb-2 notification-item-custom <?php echo !$n['lida'] ? 'unread' : ''; ?>">
                        <div class="card-body d-flex align-items-start gap-3 py-3">
                            <div class="notif-icon <?php echo $tipoBg; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; flex-shrink: 0;">
                                <i class="bi <?php echo $tipoIcon; ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1 fw-semibold <?php echo !$n['lida'] ? 'text-light' : 'text-muted'; ?>">
                                            <?php echo htmlspecialchars($n['titulo']); ?>
                                            <?php if (!$n['lida']): ?><span class="badge-custom badge-primary ms-2" style="font-size: 0.6rem;">NOVA</span><?php endif; ?>
                                        </h6>
                                        <p class="mb-1 small text-muted"><?php echo nl2br(htmlspecialchars($n['mensagem'])); ?></p>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="small text-muted">
                                                <i class="bi bi-clock me-1"></i><?php echo date('d/m/Y H:i', strtotime($n['criado_em'])); ?>
                                            </span>
                                            <?php if ($n['canal'] === 'email'): ?>
                                                <span class="badge-custom badge-info small">Email</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0 ms-2">
                                        <?php if (!$n['lida']): ?>
                                        <a href="?action=read&id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Marcar lida">
                                            <i class="bi bi-check2"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="?action=delete&id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-danger" title="Remover" onclick="return confirm('Remover notificação?')">
                                            <i class="bi bi-trash3"></i>
                                        </a>
                                    </div>
                                </div>
                                <?php if ($n['link']): ?>
                                <a href="<?php echo htmlspecialchars($n['link']); ?>" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-arrow-right"></i> Ver detalhes
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app-2026.js"></script>
</body>
</html>
