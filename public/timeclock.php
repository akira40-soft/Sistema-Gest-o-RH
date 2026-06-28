<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

$userId = $auth->getUserId();
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT f.*, u.id as user_id, u.tipo_acesso FROM funcionarios f JOIN usuarios u ON f.usuario_id = u.id WHERE u.id = ?");
$stmt->execute([$userId]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: perfil.php?erro=' . urlencode('Página requer funcionário vinculado. Contacte o RH.'));
    exit;
}

$today = date('Y-m-d');

$stmt = $db->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = ? AND data = ?");
$stmt->execute([$employee['id'], $today]);
$todayRecord = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = ? ORDER BY data DESC, hora_entrada DESC LIMIT 5");
$stmt->execute([$employee['id']]);
$recent_logs = $stmt->fetchAll();

$pageTitle = 'TimeClock';
$pageSubtitle = 'Registo de Presença';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
    <style>
        .tc-container { max-width: 500px; margin: 2rem auto; }
        .tc-status { padding: 1.5rem; border-radius: var(--radius); text-align: center; margin-bottom: 1rem; }
        .tc-status.entrada { background: var(--success-soft); border: 2px solid rgba(16,185,129,0.3); }
        .tc-status.saida { background: var(--danger-soft); border: 2px solid rgba(239,68,68,0.3); }
        .tc-status.neutral { background: var(--bg-body); border: 2px solid var(--border-color); }
        .tc-btn { width: 100%; padding: 1rem; font-size: 1rem; font-weight: 700; border: none; border-radius: var(--radius); cursor: pointer; transition: var(--transition); margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .tc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .tc-btn.entrada { background: var(--success); color: #fff; }
        .tc-btn.saida { background: var(--danger); color: #fff; }
        .tc-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .tc-log { padding: 0.75rem; margin-bottom: 0.5rem; background: var(--bg-body); border-left: 4px solid var(--primary); border-radius: var(--radius-sm); font-size: 0.85rem; }
        .tc-log-time { font-family: var(--font-mono); font-weight: 600; }
    </style>
</head>
<body class="dashboard-body">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>
            <div class="content-body">
                <div class="tc-container">

                    <div class="card mb-3">
                        <div class="card-body">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="user-avatar-sm"><?php echo strtoupper(substr($employee['nome_completo'], 0, 1)); ?></div>
                                <div>
                                    <strong><?php echo htmlspecialchars($employee['nome_completo']); ?></strong>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('d/m/Y'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($todayRecord): ?>
                        <div class="tc-status <?php echo $todayRecord['hora_saida'] ? 'saida' : 'entrada'; ?>">
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">Estado Hoje</div>
                            <div style="font-size: 1.25rem; font-weight: 800;">
                                <?php if ($todayRecord['hora_saida']): ?>
                                    <i class="bi bi-check-circle-fill" style="color: var(--success);"></i>
                                    Jornada Completa
                                <?php else: ?>
                                    <i class="bi bi-clock-fill" style="color: var(--warning);"></i>
                                    Em curso
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.85rem; margin-top: 0.5rem; color: var(--text-secondary);">
                                Entrada: <span class="tc-log-time"><?php echo date('H:i', strtotime($todayRecord['hora_entrada'])); ?></span>
                                <?php if ($todayRecord['hora_saida']): ?>
                                    · Saída: <span class="tc-log-time"><?php echo date('H:i', strtotime($todayRecord['hora_saida'])); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="tc-status neutral">
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">Estado Hoje</div>
                            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-muted);">
                                <i class="bi bi-clock"></i> Sem registo
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="pontoFeedback" style="display: none;" class="alert mb-3"></div>

                    <?php if (!$todayRecord || (!$todayRecord['hora_entrada'])): ?>
                        <button class="tc-btn entrada" id="btnEntrada" onclick="baterPonto('entrada')">
                            <i class="bi bi-box-arrow-in-right"></i> ENTRADA
                        </button>
                    <?php elseif (!$todayRecord['hora_saida']): ?>
                        <button class="tc-btn saida" id="btnSaida" onclick="baterPonto('saida')">
                            <i class="bi bi-box-arrow-right"></i> SAÍDA
                        </button>
                    <?php else: ?>
                        <button class="tc-btn entrada" disabled>
                            <i class="bi bi-check-circle"></i> PONTO REGISTADO HOJE
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($recent_logs)): ?>
                        <h4 style="font-size: 0.9rem; font-weight: 700; margin: 1.5rem 0 0.75rem;"><i class="bi bi-clock-history"></i> Últimos Registos</h4>
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="tc-log">
                                <strong><?php echo htmlspecialchars(ucfirst($log['tipo'])); ?></strong>
                                <span style="color: var(--text-muted);"> — <?php echo date('d/m/Y', strtotime($log['data'])); ?></span>
                                <span class="tc-log-time"><?php echo date('H:i', strtotime($log['hora_entrada'])); ?></span>
                                <?php if ($log['hora_saida']): ?>
                                    <span> — </span>
                                    <span class="tc-log-time"><?php echo date('H:i', strtotime($log['hora_saida'])); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="js/app-2026.js"></script>
    <script>
    const EMPLOYEE_ID = <?php echo $employee['id']; ?>;

    async function baterPonto(tipo) {
        const btn = document.getElementById(tipo === 'entrada' ? 'btnEntrada' : 'btnSaida');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span> A registar...'; }

        try {
            const r = await fetch('/api/timeclock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ funcionario_id: EMPLOYEE_ID, tipo: tipo })
            });
            const result = await r.json();

            const fb = document.getElementById('pontoFeedback');
            if (result.success) {
                fb.className = 'alert alert-success mb-3';
                fb.innerHTML = '<i class="bi bi-check-circle-fill"></i><div class="alert-content">' + result.message + '</div>';
                fb.style.display = '';
                setTimeout(() => location.reload(), 1200);
            } else {
                fb.className = 'alert alert-danger mb-3';
                fb.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">' + result.message + '</div>';
                fb.style.display = '';
                if (btn) { btn.disabled = false; btn.innerHTML = tipo === 'entrada' ? '<i class="bi bi-box-arrow-in-right"></i> ENTRADA' : '<i class="bi bi-box-arrow-right"></i> SAÍDA'; }
            }
        } catch(e) {
            alert('Erro de conexão: ' + e.message);
            if (btn) { btn.disabled = false; }
        }
    }
    </script>
</body>
</html>
