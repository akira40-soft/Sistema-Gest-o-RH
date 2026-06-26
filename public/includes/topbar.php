<?php
/**
 * Topbar Reutilizável - Sistema de Gestão RG
 * @param string $pageTitle Título da página
 * @param string $pageSubtitle Subtítulo (opcional)
 */
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
if (!isset($pageSubtitle)) $pageSubtitle = '';

$__topbarUserId = $user['id'] ?? ($_SESSION['user_id'] ?? null);
$__topbarFoto = null;
if ($__topbarUserId) {
    try {
        $__pdo = \App\Database\Database::getInstance()->getConnection();
        $__s = $__pdo->prepare("SELECT COALESCE(f.foto, u.foto) FROM usuarios u LEFT JOIN funcionarios f ON f.usuario_id = u.id WHERE u.id = :u AND COALESCE(f.foto, u.foto) IS NOT NULL LIMIT 1");
        $__s->execute([':u' => $__topbarUserId]);
        $__topbarFoto = $__s->fetchColumn() ?: null;
    } catch (\Throwable $e) { $__topbarFoto = null; }
}
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle" title="Menu">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
            <?php if ($pageSubtitle): ?>
                <p class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="topbar-right">
        <div class="global-search">
            <i class="bi bi-search global-search-icon"></i>
            <input type="text"
                   class="global-search-input"
                   id="globalSearchInput"
                   placeholder="Pesquisar funcionários, departamentos..."
                   autocomplete="off">
            <span class="global-search-shortcut">Ctrl K</span>
        </div>

        <?php
        $__notifCount = 0;
        $__notifList = [];
        if ($__topbarUserId) {
            try {
                $__notifCountStmt = $__pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND lida = 0");
                $__notifCountStmt->execute([':u' => $__topbarUserId]);
                $__notifCount = (int) $__notifCountStmt->fetchColumn();
                $__notifStmt = $__pdo->prepare("SELECT id, titulo, mensagem, tipo, link, lida, criado_em FROM notifications WHERE user_id = :u ORDER BY lida ASC, criado_em DESC LIMIT 8");
                $__notifStmt->execute([':u' => $__topbarUserId]);
                $__notifList = $__notifStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) { $__notifCount = 0; $__notifList = []; }
        }
        $__notifIconMap = ['success' => 'bi-check-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'danger' => 'bi-x-circle-fill', 'info' => 'bi-info-circle-fill'];
        ?>
        <div id="notificationsBtn" class="action-btn" title="Notificações">
            <i class="bi bi-bell"></i>
            <span class="action-badge" id="notifBadge" style="<?php echo $__notifCount > 0 ? '' : 'display:none;'; ?>"><?php echo $__notifCount > 9 ? '9+' : $__notifCount; ?></span>
            <div class="notifications-dropdown" id="notifDropdown">
                <div class="notifications-header">
                    <h6>Notificações</h6>
                    <button id="markAllRead" onclick="notifMarkAllRead()">Marcar lidas</button>
                </div>
                <div class="notifications-list" id="notifList">
                    <?php if (empty($__notifList)): ?>
                        <div class="notification-item" style="text-align:center;color:var(--text-muted);padding:1.5rem;">
                            <i class="bi bi-bell-slash" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                            Sem notificações
                        </div>
                    <?php else: foreach ($__notifList as $__n):
                        $__nIcon = $__notifIconMap[$__n['tipo']] ?? 'bi-bell-fill';
                        $__dt = new DateTime($__n['criado_em'], new DateTimeZone('Africa/Luanda'));
                        $__now = new DateTime('now', new DateTimeZone('Africa/Luanda'));
                        $__diff = $__now->getTimestamp() - $__dt->getTimestamp();
                        if ($__diff < 60) $__rel = 'Agora';
                        elseif ($__diff < 3600) $__rel = floor($__diff / 60) . ' min';
                        elseif ($__diff < 86400) $__rel = floor($__diff / 3600) . ' h';
                        else $__rel = floor($__diff / 86400) . ' d';
                    ?>
                        <div class="notification-item <?php echo $__n['lida'] ? '' : 'unread'; ?>" data-id="<?php echo $__n['id']; ?>" <?php echo $__n['link'] ? 'onclick="window.location.href=\'' . htmlspecialchars($__n['link']) . '\'" style="cursor:pointer;"' : ''; ?>>
                            <div class="notification-icon <?php echo $__n['tipo'] ?? 'info'; ?>"><i class="bi <?php echo $__nIcon; ?>"></i></div>
                            <div class="notification-content">
                                <p class="notification-title"><?php echo htmlspecialchars($__n['titulo'] ?? 'Notificação'); ?></p>
                                <p class="notification-text"><?php echo htmlspecialchars(mb_strimwidth($__n['mensagem'], 0, 80, '...')); ?></p>
                                <span class="notification-time"><?php echo $__rel; ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="notifications-footer">
                    <a href="notificacoes.php">Ver todas as notificações →</a>
                </div>
            </div>
        </div>

        <div id="profileBtn" class="action-btn" title="Perfil" style="position:relative;">
            <?php if ($__topbarFoto && file_exists(__DIR__ . '/../' . $__topbarFoto)): ?>
                <img src="<?php echo htmlspecialchars($__topbarFoto . '?v=' . filemtime(__DIR__ . '/../' . $__topbarFoto)); ?>" alt="Perfil" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-light);">
            <?php else: ?>
                <i class="bi bi-person-circle"></i>
            <?php endif; ?>
            <div class="profile-dropdown">
                <div class="profile-dropdown-header">
                    <strong><?php echo htmlspecialchars($user['username'] ?? $_SESSION['username'] ?? 'Utilizador'); ?></strong>
                    <span><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role'] ?? 'funcionario'))); ?></span>
                </div>
                <a href="perfil.php"><i class="bi bi-person"></i> Meu Perfil</a>
                <a href="perfil.php?action=upload_photo"><i class="bi bi-camera"></i> Foto de Perfil</a>
                <a href="config.php"><i class="bi bi-gear"></i> Configurações</a>
                <a href="alterar_senha.php"><i class="bi bi-key"></i> Alterar Senha</a>
                <div class="divider"></div>
                <a href="logout.php" class="danger"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
        </div>
    </div>
</header>

<script>
(function(){
    const badge = document.getElementById('notifBadge');
    const list  = document.getElementById('notifList');
    const btn   = document.getElementById('notificationsBtn');
    if (!badge || !list || !btn) return;

    /* ── Toggle dropdown (delegado para app-2026.js) ── */

    /* ── Polling a cada 30s ──────────────────────── */
    async function pollNotifs(){
        try {
            const r = await fetch('/api/notifications.php?action=count');
            const d = await r.json();
            if (!d.success) return;
            if (d.unread > 0) {
                badge.style.display = '';
                badge.textContent = d.unread > 9 ? '9+' : d.unread;
            } else {
                badge.style.display = 'none';
                badge.textContent = '0';
            }
        } catch(_){}
    }
    setInterval(pollNotifs, 30000);

    /* ── Marcar todas como lidas ─────────────────── */
    window.notifMarkAllRead = async function(){
        try {
            await fetch('/api/notifications.php?action=mark_all', {method:'POST'});
            badge.style.display = 'none';
            badge.textContent = '0';
            list.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
        } catch(_){}
    };
})();
</script>
