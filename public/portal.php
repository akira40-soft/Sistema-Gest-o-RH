<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();

$userRole = $auth->getUserRole();
$userId = $auth->getUserId();

// Admin/gestor/RH/lider vão para dashboard
if (in_array($userRole, ['super_admin', 'admin', 'gestor_rh', 'funcionario_rh', 'lider_farmaceutico'])) {
    header("Location: dashboard.php");
    exit;
}

$db = Database::getInstance()->getConnection();

// Buscar funcionário vinculado
$stmt = $db->prepare("SELECT f.*, c.nome as cargo_nome, d.nome as dept_nome FROM funcionarios f LEFT JOIN cargos c ON f.cargo_id = c.id LEFT JOIN departamentos d ON f.departamento_id = d.id WHERE f.usuario_id = :uid");
$stmt->execute([':uid' => $userId]);
$func = $stmt->fetch();

if (!$func) {
    die("<div style='text-align:center;padding:3rem;'><h2>Conta sem vínculo</h2><p>O seu utilizador não está vinculado a um funcionário.<br>Contacte o RH.</p><a href='logout.php'>Sair</a></div>");
}

$funcId = $func['id'];

// Processar ponto
$mensagemPonto = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'bater_ponto') {
    $tipo = $_POST['tipo'] ?? '';
    $lat = $_POST['latitude'] ?? null;
    $lon = $_POST['longitude'] ?? null;

    if (!in_array($tipo, ['entrada', 'saida'])) {
        $mensagemPonto = 'Tipo inválido.';
    } else {
        try {
            $check = $db->prepare("SELECT id, hora_entrada, hora_saida FROM registros_ponto WHERE funcionario_id = :fid AND data = CURRENT_DATE");
            $check->execute([':fid' => $funcId]);
            $hoje = $check->fetch();

            if ($tipo === 'entrada') {
                if ($hoje && $hoje['hora_entrada']) {
                    $mensagemPonto = 'Entrada já registrada hoje.';
                } else {
                    if ($hoje) {
                        $upd = $db->prepare("UPDATE registros_ponto SET hora_entrada = NOW(), metodo = 'portal', observacoes = :obs WHERE id = :id");
                        $upd->execute([':id' => $hoje['id'], ':obs' => "GPS: $lat,$lon"]);
                    } else {
                        $ins = $db->prepare("INSERT INTO registros_ponto (funcionario_id, data, hora_entrada, metodo, observacoes) VALUES (:fid, CURRENT_DATE, NOW(), 'portal', :obs)");
                        $ins->execute([':fid' => $funcId, ':obs' => "GPS: $lat,$lon"]);
                    }
                    $mensagemPonto = 'Entrada registrada com sucesso!';
                }
            } else {
                if (!$hoje || !$hoje['hora_entrada']) {
                    $mensagemPonto = 'Registe a entrada primeiro.';
                } elseif ($hoje['hora_saida']) {
                    $mensagemPonto = 'Saída já registrada hoje.';
                } else {
                    $upd = $db->prepare("UPDATE registros_ponto SET hora_saida = NOW(), observacoes = CONCAT(observacoes, ' | Saída: ', :obs) WHERE id = :id");
                    $upd->execute([':id' => $hoje['id'], ':obs' => "GPS: $lat,$lon"]);
                    $mensagemPonto = 'Saída registrada com sucesso!';
                }
            }
        } catch (Exception $e) {
            $mensagemPonto = 'Erro: ' . $e->getMessage();
        }
    }
}

// Registro de hoje
$hojeStmt = $db->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = :fid AND data = CURRENT_DATE");
$hojeStmt->execute([':fid' => $funcId]);
$pontoHoje = $hojeStmt->fetch();

// Últimos 7 registros
$histStmt = $db->prepare("SELECT data, hora_entrada, hora_saida FROM registros_ponto WHERE funcionario_id = :fid ORDER BY data DESC LIMIT 7");
$histStmt->execute([':fid' => $funcId]);
$historico = $histStmt->fetchAll();

// Notificações
$notifCount = 0;
$notifs = [];
try {
    $ncStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :u AND lida = 0");
    $ncStmt->execute([':u' => $userId]);
    $notifCount = (int) $ncStmt->fetchColumn();
    $nStmt = $db->prepare("SELECT id, titulo, mensagem, tipo, link, lida, criado_em FROM notifications WHERE user_id = :u ORDER BY lida ASC, criado_em DESC LIMIT 5");
    $nStmt->execute([':u' => $userId]);
    $notifs = $nStmt->fetchAll();
} catch (Exception $e) {}

// Licenças pendentes
$licCount = 0;
try {
    $lcStmt = $db->prepare("SELECT COUNT(*) FROM licencas WHERE funcionario_id = :fid AND status IN ('pendente','aprovada')");
    $lcStmt->execute([':fid' => $funcId]);
    $licCount = (int) $lcStmt->fetchColumn();
} catch (Exception $e) {}

$user = [
    'username' => $auth->getUsername(),
    'role' => $auth->getUserRole(),
    'id' => $userId
];
$pageTitle = 'Meu Portal';
$pageSubtitle = 'Área do Colaborador · ' . date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Portal | Gingong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>

            <div class="content-body">

                <!-- Mensagem de ponto -->
                <?php if ($mensagemPonto): ?>
                    <?php $isSuccess = (strpos($mensagemPonto, 'sucesso') !== false || strpos($mensagemPonto, 'registrada') !== false); ?>
                    <div class="alert alert-<?php echo $isSuccess ? 'success' : 'danger'; ?>" style="margin-bottom:1rem;">
                        <i class="bi <?php echo $isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'; ?>"></i>
                        <div class="alert-content"><strong><?php echo htmlspecialchars($mensagemPonto); ?></strong></div>
                    </div>
                <?php endif; ?>

                <!-- Ponto Eletrônico -->
                <div class="card mb-3" style="border-left:4px solid var(--primary);">
                    <div class="card-body" style="padding:1.25rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.75rem;">
                            <div>
                                <h3 style="font-size:1.1rem;font-weight:700;margin:0;"><i class="bi bi-clock-history" style="color:var(--primary);"></i> Ponto Eletrônico</h3>
                                <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">
                                    <?php if ($pontoHoje && $pontoHoje['hora_entrada']): ?>
                                        Entrada: <strong><?php echo date('H:i', strtotime($pontoHoje['hora_entrada'])); ?></strong>
                                        <?php if ($pontoHoje['hora_saida']): ?>
                                            · Saída: <strong><?php echo date('H:i', strtotime($pontoHoje['hora_saida'])); ?></strong>
                                        <?php else: ?>
                                            · Em jornada
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Sem registro hoje
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <span style="font-size:0.7rem;padding:4px 10px;border-radius:8px;background:<?php echo ($pontoHoje && $pontoHoje['hora_entrada']) ? '#d1fae5;color:#059669' : '#f1f5f9;color:#64748b'; ?>;">
                                    <i class="bi <?php echo ($pontoHoje && $pontoHoje['hora_entrada']) ? 'bi-check-circle' : 'bi-clock'; ?>"></i>
                                    <?php echo ($pontoHoje && $pontoHoje['hora_entrada']) ? 'Presente' : 'Ausente'; ?>
                                </span>
                            </div>
                        </div>

                        <form id="pontoForm" method="POST" action="">
                            <input type="hidden" name="acao" value="bater_ponto">
                            <input type="hidden" name="tipo" id="pontoTipo">
                            <input type="hidden" name="latitude" id="pontoLat">
                            <input type="hidden" name="longitude" id="pontoLon">
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <button type="button" class="btn btn-success" onclick="baterPonto('entrada')" style="flex:1;min-width:120px;" <?php echo ($pontoHoje && $pontoHoje['hora_entrada'] && !$pontoHoje['hora_saida']) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-box-arrow-in-right"></i> Entrada
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="baterPonto('saida')" style="flex:1;min-width:120px;" <?php echo (!$pontoHoje || !$pontoHoje['hora_entrada'] || ($pontoHoje && $pontoHoje['hora_saida'])) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-box-arrow-right"></i> Saída
                                </button>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:8px;font-size:0.7rem;color:var(--text-muted);">
                                <span id="gpsDot" style="width:7px;height:7px;border-radius:50%;background:#94a3b8;"></span>
                                <span id="gpsText">GPS a verificar...</span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Serviços Rápidos -->
                <h3 style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.75rem;">Serviços Rápidos</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.75rem;margin-bottom:1.5rem;">
                    <a href="perfil.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-person"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Meu Perfil</span>
                        </div>
                    </a>
                    <a href="recibo_salario.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(37,99,235,0.1);color:#2563eb;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-receipt"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Holerite</span>
                        </div>
                    </a>
                    <a href="licencas.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(217,119,6,0.1);color:#d97706;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-calendar-minus"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Licenças<?php if ($licCount > 0): ?> <span style="background:#dc2626;color:#fff;font-size:0.6rem;padding:1px 5px;border-radius:8px;"><?php echo $licCount; ?></span><?php endif; ?></span>
                        </div>
                    </a>
                    <a href="escalas.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(124,58,237,0.1);color:#7c3aed;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-calendar-week"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Minha Escala</span>
                        </div>
                    </a>
                    <a href="notificacoes.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(8,145,178,0.1);color:#0891b2;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-bell"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Notificações<?php if ($notifCount > 0): ?> <span style="background:#dc2626;color:#fff;font-size:0.6rem;padding:1px 5px;border-radius:8px;"><?php echo $notifCount; ?></span><?php endif; ?></span>
                        </div>
                    </a>
                    <a href="comunicados.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(220,38,38,0.1);color:#dc2626;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-megaphone"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Comunicados</span>
                        </div>
                    </a>
                    <a href="documentos.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-file-earmark-text"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Documentos</span>
                        </div>
                    </a>
                    <a href="beneficios.php" class="card" style="text-decoration:none;color:var(--text);cursor:pointer;border:1px solid var(--border);transition:all 0.2s;">
                        <div class="card-body" style="text-align:center;padding:1rem;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(217,119,6,0.1);color:#d97706;display:flex;align-items:center;justify-content:center;margin:0 auto 0.5rem;font-size:1.1rem;"><i class="bi bi-gift"></i></div>
                            <span style="font-size:0.75rem;font-weight:600;">Benefícios</span>
                        </div>
                    </a>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <!-- Registros da Semana -->
                    <div class="card" style="grid-column:1/-1;">
                        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="font-size:1rem;font-weight:700;margin:0;"><i class="bi bi-clock-history" style="color:var(--primary);"></i> Registros da Semana</h3>
                            <a href="timeclock.php" style="font-size:0.75rem;color:var(--primary);text-decoration:none;font-weight:600;">Ver todos →</a>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php if (empty($historico)): ?>
                                <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">
                                    <i class="bi bi-calendar-x" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>
                                    Sem registros de ponto ainda
                                </div>
                            <?php else: ?>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;font-size:0.8rem;">
                                        <thead>
                                            <tr style="border-bottom:1px solid var(--border);background:var(--bg-body);">
                                                <th style="padding:0.6rem 1rem;text-align:left;font-weight:600;color:var(--text-muted);">Data</th>
                                                <th style="padding:0.6rem 0.75rem;text-align:center;font-weight:600;color:var(--text-muted);">Entrada</th>
                                                <th style="padding:0.6rem 0.75rem;text-align:center;font-weight:600;color:var(--text-muted);">Saída</th>
                                                <th style="padding:0.6rem 0.75rem;text-align:center;font-weight:600;color:var(--text-muted);">Horas</th>
                                                <th style="padding:0.6rem 1rem;text-align:center;font-weight:600;color:var(--text-muted);">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($historico as $h):
                                                $dt = new DateTime($h['data']);
                                                $dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
                                                $trabalhadas = 0;
                                                if ($h['hora_entrada'] && $h['hora_saida']) {
                                                    $trabalhadas = round((strtotime($h['hora_saida']) - strtotime($h['hora_entrada'])) / 3600, 1);
                                                }
                                                if ($h['hora_entrada'] && $h['hora_saida'] && $trabalhadas >= 7.5) {
                                                    $statusCls = 'hs-ok'; $statusTxt = 'Completo';
                                                } elseif ($h['hora_entrada']) {
                                                    $statusCls = 'hs-partial'; $statusTxt = $h['hora_saida'] ? round($trabalhadas,1).'h' : 'Parcial';
                                                } else {
                                                    $statusCls = 'hs-miss'; $statusTxt = 'Faltou';
                                                }
                                            ?>
                                            <tr style="border-bottom:1px solid #f1f5f9;">
                                                <td style="padding:0.6rem 1rem;">
                                                    <div style="font-weight:600;"><?php echo $dias[$dt->format('w')] . ' ' . $dt->format('d/m'); ?></div>
                                                </td>
                                                <td style="padding:0.6rem 0.75rem;text-align:center;font-weight:500;">
                                                    <?php echo $h['hora_entrada'] ? date('H:i', strtotime($h['hora_entrada'])) : '—'; ?>
                                                </td>
                                                <td style="padding:0.6rem 0.75rem;text-align:center;font-weight:500;">
                                                    <?php echo $h['hora_saida'] ? date('H:i', strtotime($h['hora_saida'])) : '—'; ?>
                                                </td>
                                                <td style="padding:0.6rem 0.75rem;text-align:center;font-weight:600;">
                                                    <?php echo ($h['hora_entrada'] && $h['hora_saida']) ? round($trabalhadas,1).'h' : '—'; ?>
                                                </td>
                                                <td style="padding:0.6rem 1rem;text-align:center;">
                                                    <span style="font-size:0.65rem;font-weight:600;padding:3px 8px;border-radius:6px;background:<?php echo $statusCls === 'hs-ok' ? '#d1fae5;color:#059669' : ($statusCls === 'hs-partial' ? '#fef3c7;color:#d97706' : '#fee2e2;color:#dc2626'); ?>;"><?php echo $statusTxt; ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notificações Recentes -->
                    <?php if (!empty($notifs)): ?>
                    <div class="card" style="grid-column:1/-1;">
                        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
                            <h3 style="font-size:1rem;font-weight:700;margin:0;"><i class="bi bi-bell" style="color:var(--primary);"></i> Notificações</h3>
                            <a href="notificacoes.php" style="font-size:0.75rem;color:var(--primary);text-decoration:none;font-weight:600;">Ver todas →</a>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php foreach ($notifs as $n):
                                $icons = ['success' => 'bi-check-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'danger' => 'bi-x-circle-fill', 'info' => 'bi-info-circle-fill'];
                                $icon = $icons[$n['tipo']] ?? 'bi-bell-fill';
                                $dt = new DateTime($n['criado_em'], new DateTimeZone('Africa/Luanda'));
                                $now = new DateTime('now', new DateTimeZone('Africa/Luanda'));
                                $diff = $now->getTimestamp() - $dt->getTimestamp();
                                if ($diff < 60) $rel = 'Agora';
                                elseif ($diff < 3600) $rel = floor($diff/60).'min';
                                elseif ($diff < 86400) $rel = floor($diff/3600).'h';
                                else $rel = floor($diff/86400).'d';
                            ?>
                            <a href="<?php echo htmlspecialchars($n['link'] ?? '#'); ?>" style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;text-decoration:none;color:var(--text);border-bottom:1px solid #f1f5f9;<?php echo !$n['lida'] ? 'background:rgba(5,150,105,0.03);' : ''; ?>">
                                <div style="width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.9rem;background:var(--<?php echo $n['tipo'] ?? 'info'; ?>-soft,var(--primary-soft));color:var(--<?php echo $n['tipo'] ?? 'info'; ?>,var(--primary));">
                                    <i class="bi <?php echo $icon; ?>"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.8rem;font-weight:600;"><?php echo htmlspecialchars($n['titulo'] ?? 'Notificação'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars(mb_strimwidth($n['mensagem'], 0, 60, '...')); ?></div>
                                </div>
                                <span style="font-size:0.65rem;color:var(--text-muted);white-space:nowrap;"><?php echo $rel; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="js/app-2026.js"></script>
    <script>
    let gpsReady = false, gpsLat = null, gpsLon = null;

    function updateGpsUI() {
        const dot = document.getElementById('gpsDot');
        const txt = document.getElementById('gpsText');
        if (gpsReady) {
            dot.style.background = '#10b981';
            txt.textContent = 'GPS ativo ✓';
        } else {
            dot.style.background = '#ef4444';
            txt.textContent = 'GPS inativo — ative para bater ponto';
        }
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) { gpsLat = pos.coords.latitude; gpsLon = pos.coords.longitude; gpsReady = true; updateGpsUI(); },
            function() { gpsReady = false; updateGpsUI(); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function baterPonto(tipo) {
        if (!gpsReady) {
            if (typeof App !== 'undefined' && App.toast) App.toast('Ative o GPS para bater ponto.', 'warning');
            navigator.geolocation.getCurrentPosition(
                function(pos) { gpsLat = pos.coords.latitude; gpsLon = pos.coords.longitude; gpsReady = true; updateGpsUI(); submitPonto(tipo); },
                function() { if (typeof App !== 'undefined' && App.toast) App.toast('Não foi possível obter localização.', 'error'); },
                { enableHighAccuracy: true, timeout: 15000 }
            );
            return;
        }
        submitPonto(tipo);
    }

    function submitPonto(tipo) {
        document.getElementById('pontoTipo').value = tipo;
        document.getElementById('pontoLat').value = gpsLat;
        document.getElementById('pontoLon').value = gpsLon;
        document.getElementById('pontoForm').submit();
    }

    updateGpsUI();
    </script>
</body>
</html>
