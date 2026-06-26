<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

$userRole = $auth->getUserRole();
$isAdmin = in_array($userRole, ['super_admin', 'admin', 'gestor_rh', 'funcionario_rh', 'lider_farmaceutico']);

// Apenas funcionários comuns veem o portal
if (!$isAdmin) {
    header("Location: portal.php");
    exit;
}

$user = [
    'username' => $auth->getUsername(),
    'role' => $auth->getUserRole(),
    'id' => $auth->getUserId()
];

$db = Database::getInstance()->getConnection();
$isMysql = strpos($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false;

try {
    $dashBg = $db->query("SELECT background, overlay_opacity FROM dashboard_preferencias WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $dashBg = null; }
$dashBackground = $dashBg['background'] ?? 'assets/uploads/backgrounds/default-pharmacy.jpg';
$dashOverlay = isset($dashBg['overlay_opacity']) ? (float)$dashBg['overlay_opacity'] : 0.65;
if (!file_exists(__DIR__ . '/' . $dashBackground)) $dashBackground = 'assets/uploads/backgrounds/default-pharmacy.jpg';

try {
    $totalFuncionarios = (int) $db->query("SELECT COUNT(*) FROM funcionarios WHERE status = 'ativo'")->fetchColumn();

    if ($isMysql) {
        $presentesHoje = (int) $db->query("SELECT COUNT(DISTINCT funcionario_id) FROM registros_ponto WHERE data = CURRENT_DATE AND hora_entrada IS NOT NULL")->fetchColumn();
        $aniversariantesMes = (int) $db->query("SELECT COUNT(*) FROM funcionarios WHERE MONTH(data_nascimento) = MONTH(CURRENT_DATE) AND status = 'ativo'")->fetchColumn();
        $licencasAtivas = (int) $db->query("SELECT COUNT(*) FROM licencas WHERE status = 'aprovada' AND CURRENT_DATE BETWEEN data_inicio AND data_fim")->fetchColumn();
        try {
            $docsVencendo = (int) $db->query("SELECT COUNT(*) FROM documentos_funcionarios WHERE data_validade BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)")->fetchColumn();
        } catch (Exception $e) { $docsVencendo = 0; }
        try {
            $vagasAbertas = (int) $db->query("SELECT COUNT(*) FROM vagas WHERE status = 'aberta'")->fetchColumn();
        } catch (Exception $e) { $vagasAbertas = 0; }
        try {
            $treinamentosAtivos = (int) $db->query("SELECT COUNT(*) FROM treinamentos WHERE status IN ('planejado', 'em_andamento')")->fetchColumn();
        } catch (Exception $e) { $treinamentosAtivos = 0; }

        // Dados para gráfico de presença semanal
        $presencaSemanal = $db->query("
            SELECT DATE_FORMAT(data, '%a') as dia, COUNT(DISTINCT funcionario_id) as total
            FROM registros_ponto
            WHERE data >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY) AND hora_entrada IS NOT NULL
            GROUP BY data ORDER BY data
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Departamentos - distribuição
        $deptStats = $db->query("
            SELECT d.nome, COUNT(f.id) as total
            FROM departamentos d
            LEFT JOIN funcionarios f ON f.departamento_id = d.id AND f.status = 'ativo'
            GROUP BY d.id, d.nome
            ORDER BY total DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Aniversariantes do mês
        $aniversariantes = $db->query("
            SELECT nome_completo, DAY(data_nascimento) as dia, cargo_id
            FROM funcionarios
            WHERE MONTH(data_nascimento) = MONTH(CURRENT_DATE) AND status = 'ativo'
            ORDER BY DAY(data_nascimento) ASC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Últimas admissões
        $ultimasAdmissoes = $db->query("
            SELECT f.nome_completo, f.data_admissao, c.nome as cargo
            FROM funcionarios f
            LEFT JOIN cargos c ON f.cargo_id = c.id
            WHERE f.status = 'ativo'
            ORDER BY f.data_admissao DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $presentesHoje = (int) $db->query("SELECT COUNT(DISTINCT funcionario_id) FROM registros_ponto WHERE data = date('now') AND hora_entrada IS NOT NULL")->fetchColumn();
        $aniversariantesMes = (int) $db->query("SELECT COUNT(*) FROM funcionarios WHERE strftime('%m', data_nascimento) = strftime('%m', 'now') AND status = 'ativo'")->fetchColumn();
        $licencasAtivas = (int) $db->query("SELECT COUNT(*) FROM licencas WHERE status = 'aprovada' AND date('now') BETWEEN data_inicio AND data_fim")->fetchColumn();
        $docsVencendo = 0;
        $vagasAbertas = 0;
        $treinamentosAtivos = 0;
        $presencaSemanal = [];
        $deptStats = [];
        $aniversariantes = [];
        $ultimasAdmissoes = [];
    }

    $assiduidadePct = $totalFuncionarios > 0 ? round(($presentesHoje / $totalFuncionarios) * 100) : 0;

} catch (Exception $e) {
    error_log("Erro no dashboard: " . $e->getMessage());
    $totalFuncionarios = 0;
    $presentesHoje = 0;
    $aniversariantesMes = 0;
    $licencasAtivas = 0;
    $docsVencendo = 0;
    $vagasAbertas = 0;
    $treinamentosAtivos = 0;
    $assiduidadePct = 0;
    $presencaSemanal = [];
    $deptStats = [];
    $aniversariantes = [];
    $ultimasAdmissoes = [];
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do sistema · ' . date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body class="dashboard-body dashboard-bg" style="--dash-bg: url('/<?php echo htmlspecialchars($dashBackground); ?>'); --dash-overlay: <?php echo $dashOverlay; ?>;">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>

            <div class="content-body">

                <!-- Breadcrumb -->
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </nav>

                <!-- Welcome Banner -->
                <div class="welcome-banner">
                    <h1>Olá, <span><?php echo htmlspecialchars(ucfirst($user['username'])); ?></span> 👋</h1>
                    <p>Gestão de Pessoal & RH — Unidade Gingongo · <?php echo date('d \d\e F \d\e Y'); ?></p>
                    <div class="welcome-actions">
                        <a href="admissao.php" class="btn" style="background:rgba(255,255,255,0.15); color:#fff; border-color:rgba(255,255,255,0.25); backdrop-filter:blur(8px);">
                            <i class="bi bi-person-plus"></i> Nova Admissão
                        </a>
                        <a href="folha.php" class="btn" style="background:rgba(255,255,255,0.15); color:#fff; border-color:rgba(255,255,255,0.25); backdrop-filter:blur(8px);">
                            <i class="bi bi-calculator"></i> Gerar Folha
                        </a>
                        <a href="relatorios.php" class="btn" style="background:rgba(255,255,255,0.15); color:#fff; border-color:rgba(255,255,255,0.25); backdrop-filter:blur(8px);">
                            <i class="bi bi-file-earmark-bar-graph"></i> Relatórios
                        </a>
                    </div>
                </div>

                <!-- Admin Clock-In Card -->
                <?php
                $adminFunc = null;
                $adminPonto = null;
                try {
                    $afStmt = $db->prepare("SELECT id, nome_completo FROM funcionarios WHERE usuario_id = :uid");
                    $afStmt->execute([':uid' => $user['id']]);
                    $adminFunc = $afStmt->fetch();
                    if ($adminFunc) {
                        $apStmt = $db->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = :fid AND data = CURRENT_DATE");
                        $apStmt->execute([':fid' => $adminFunc['id']]);
                        $adminPonto = $apStmt->fetch();
                    }
                } catch (Exception $e) {}
                if ($adminFunc):
                ?>
                <div style="background:rgba(255,255,255,0.12);backdrop-filter:blur(12px);border-radius:14px;padding:1rem 1.25rem;margin-top:1rem;border:1px solid rgba(255,255,255,0.15);display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                    <div style="flex:1;min-width:150px;">
                        <div style="font-size:0.7rem;opacity:0.8;">Meu Ponto Hoje</div>
                        <div style="font-size:1rem;font-weight:700;">
                            <?php if ($adminPonto && $adminPonto['hora_entrada']): ?>
                                Entrada: <?php echo date('H:i', strtotime($adminPonto['hora_entrada'])); ?>
                                <?php if ($adminPonto['hora_saida']): ?>
                                    · Saída: <?php echo date('H:i', strtotime($adminPonto['hora_saida'])); ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Sem registro hoje
                            <?php endif; ?>
                        </div>
                    </div>
                    <form id="adminPontoForm" method="POST" action="timeclock.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="acao" value="bater_ponto">
                        <input type="hidden" name="tipo" id="adminPontoTipo">
                        <input type="hidden" name="latitude" value="">
                        <input type="hidden" name="longitude" value="">
                        <button type="button" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.3);font-weight:600;" onclick="document.getElementById('adminPontoTipo').value='entrada';document.getElementById('adminPontoForm').submit();">
                            <i class="bi bi-box-arrow-in-right"></i> Entrada
                        </button>
                        <button type="button" class="btn btn-sm" style="background:rgba(239,68,68,0.3);color:#fff;border:1px solid rgba(239,68,68,0.4);font-weight:600;" onclick="document.getElementById('adminPontoTipo').value='saida';document.getElementById('adminPontoForm').submit();">
                            <i class="bi bi-box-arrow-right"></i> Saída
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="stats-grid">
                    <a href="funcionarios.php" class="stat-card">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Efetivo Ativo</div>
                            <div class="stat-value"><?php echo $totalFuncionarios; ?></div>
                            <div class="stat-trend up"><i class="bi bi-arrow-up-right"></i> Funcionários ativos</div>
                        </div>
                    </a>

                    <a href="pontos.php" class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-clock-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Presentes Hoje</div>
                            <div class="stat-value"><?php echo $presentesHoje; ?></div>
                            <div class="stat-trend up"><i class="bi bi-graph-up"></i> <?php echo $assiduidadePct; ?>% de comparecimento</div>
                        </div>
                    </a>

                    <a href="licencas.php" class="stat-card warning">
                        <div class="stat-icon"><i class="bi bi-file-medical-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Licenças Ativas</div>
                            <div class="stat-value"><?php echo $licencasAtivas; ?></div>
                            <div class="stat-trend"><i class="bi bi-exclamation-triangle"></i> Em curso agora</div>
                        </div>
                    </a>

                    <a href="documentos.php" class="stat-card danger">
                        <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Docs. a Vencer</div>
                            <div class="stat-value"><?php echo $docsVencendo; ?></div>
                            <div class="stat-trend down"><i class="bi bi-clock"></i> Próximos 30 dias</div>
                        </div>
                    </a>

                    <a href="vagas.php" class="stat-card info">
                        <div class="stat-icon"><i class="bi bi-person-plus-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Vagas Abertas</div>
                            <div class="stat-value"><?php echo $vagasAbertas; ?></div>
                            <div class="stat-trend"><i class="bi bi-bullseye"></i> Recrutamento ativo</div>
                        </div>
                    </a>

                    <a href="treinamentos.php" class="stat-card">
                        <div class="stat-icon"><i class="bi bi-mortarboard-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Treinamentos</div>
                            <div class="stat-value"><?php echo $treinamentosAtivos; ?></div>
                            <div class="stat-trend"><i class="bi bi-book"></i> Em planejamento</div>
                        </div>
                    </a>

                    <a href="ferias.php" class="stat-card success">
                        <div class="stat-icon"><i class="bi bi-calendar-heart-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Aniversariantes</div>
                            <div class="stat-value"><?php echo $aniversariantesMes; ?></div>
                            <div class="stat-trend up"><i class="bi bi-gift"></i> Mês de <?php echo date('F'); ?></div>
                        </div>
                    </a>

                    <a href="beneficios.php" class="stat-card info">
                        <div class="stat-icon"><i class="bi bi-gift-fill"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Benefícios</div>
                            <div class="stat-value"><?php echo count($deptStats); ?></div>
                            <div class="stat-trend"><i class="bi bi-stars"></i> Áreas ativas</div>
                        </div>
                    </a>
                </div>

                <!-- Charts & Quick Info -->
                <div class="grid-2" style="margin-top: 1.5rem;">
                    <!-- Gráfico de Presença -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-bar-chart-fill"></i> Presença Semanal</h3>
                            <span class="badge badge-success"><i class="bi bi-circle-fill" style="font-size:6px;"></i> Tempo Real</span>
                        </div>
                        <div class="card-body">
                            <canvas id="assiduidadeChart" style="max-height: 280px;"></canvas>
                        </div>
                    </div>

                    <!-- Distribuição por Departamento -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-pie-chart-fill"></i> Distribuição por Departamento</h3>
                            <a href="departamentos.php" class="btn btn-sm btn-ghost">Ver todos</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($deptStats)): ?>
                                <div style="display:flex; flex-direction:column; gap:0.85rem;">
                                <?php
                                $maxDept = max(array_column($deptStats, 'total')) ?: 1;
                                foreach ($deptStats as $d):
                                    $pct = round(($d['total'] / $maxDept) * 100);
                                ?>
                                <div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.3rem;">
                                        <span style="font-size:0.85rem; color:var(--text-primary); font-weight:500;"><?php echo htmlspecialchars($d['nome']); ?></span>
                                        <span style="font-size:0.8rem; color:var(--text-muted); font-weight:600;"><?php echo $d['total']; ?> pessoas</span>
                                    </div>
                                    <div style="height:8px; background:var(--bg-hover); border-radius:99px; overflow:hidden;">
                                        <div style="height:100%; width:<?php echo $pct; ?>%; background:linear-gradient(90deg, var(--primary), var(--primary-light)); border-radius:99px; transition:width 0.6s ease;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-diagram-3"></i>
                                    <h4>Sem dados ainda</h4>
                                    <p>Cadastre departamentos e funcionários para visualizar a distribuição.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Lists -->
                <div class="grid-2" style="margin-top: 1.5rem;">
                    <!-- Aniversariantes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-gift-fill"></i> Aniversariantes do Mês</h3>
                            <span class="badge badge-primary"><?php echo count($aniversariantes); ?></span>
                        </div>
                        <div class="card-body" style="padding: 0.5rem 0;">
                            <?php if (!empty($aniversariantes)): ?>
                                <?php foreach ($aniversariantes as $a): ?>
                                <div style="display:flex; align-items:center; gap:0.85rem; padding:0.85rem 1rem; border-bottom:1px solid var(--border-color); transition:background 0.2s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='transparent'">
                                    <div class="user-avatar-sm"><?php echo strtoupper(substr($a['nome_completo'], 0, 1)); ?></div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:600; font-size:0.875rem; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($a['nome_completo']); ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);">Aniversário dia <?php echo str_pad($a['dia'], 2, '0', STR_PAD_LEFT); ?></div>
                                    </div>
                                    <i class="bi bi-cake2-fill" style="color:var(--warning); font-size:1.1rem;"></i>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <h4>Nenhum aniversariante</h4>
                                    <p>Não há aniversariantes cadastrados este mês.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Últimas Admissões -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="bi bi-person-plus-fill"></i> Últimas Admissões</h3>
                            <a href="funcionarios.php" class="btn btn-sm btn-ghost">Ver todos</a>
                        </div>
                        <div class="card-body" style="padding: 0.5rem 0;">
                            <?php if (!empty($ultimasAdmissoes)): ?>
                                <?php foreach ($ultimasAdmissoes as $u): ?>
                                <div style="display:flex; align-items:center; gap:0.85rem; padding:0.85rem 1rem; border-bottom:1px solid var(--border-color); transition:background 0.2s;" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='transparent'">
                                    <div class="user-avatar-sm"><?php echo strtoupper(substr($u['nome_completo'], 0, 1)); ?></div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:600; font-size:0.875rem; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($u['nome_completo']); ?></div>
                                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($u['cargo'] ?? 'N/A'); ?></div>
                                    </div>
                                    <span style="font-size:0.7rem; color:var(--text-muted);"><?php echo date('d/m/Y', strtotime($u['data_admissao'])); ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="bi bi-people"></i>
                                    <h4>Sem admissões</h4>
                                    <p>Nenhum funcionário admitido ainda.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Status do Sistema -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3 class="card-title"><i class="bi bi-shield-check"></i> Status do Sistema</h3>
                        <span class="badge badge-success"><span class="badge-dot"></span> Operacional</span>
                    </div>
                    <div class="card-body">
                        <div class="grid-4" style="gap:1rem;">
                            <div style="padding:1rem; background:var(--bg-body); border-radius:var(--radius); border:1px solid var(--border-color);">
                                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Banco de Dados</div>
                                <div style="margin-top:0.4rem; display:flex; align-items:center; gap:0.4rem;">
                                    <span class="badge-dot" style="background:var(--success); box-shadow:0 0 6px var(--success);"></span>
                                    <strong style="font-size:0.95rem; color:var(--text-primary);"><?php echo $isMysql ? 'MySQL Ativo' : 'SQLite (Fallback)'; ?></strong>
                                </div>
                            </div>
                            <div style="padding:1rem; background:var(--bg-body); border-radius:var(--radius); border:1px solid var(--border-color);">
                                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Sessão Ativa</div>
                                <div style="margin-top:0.4rem;">
                                    <strong style="font-size:0.95rem; color:var(--text-primary);"><?php echo htmlspecialchars(ucfirst($user['username'])); ?></strong>
                                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;"><?php echo htmlspecialchars($user['role']); ?></div>
                                </div>
                            </div>
                            <div style="padding:1rem; background:var(--bg-body); border-radius:var(--radius); border:1px solid var(--border-color);">
                                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Fuso Horário</div>
                                <div style="margin-top:0.4rem;">
                                    <strong style="font-size:0.95rem; color:var(--text-primary);">Africa/Luanda</strong>
                                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">UTC+1 · WAT</div>
                                </div>
                            </div>
                            <div style="padding:1rem; background:var(--bg-body); border-radius:var(--radius); border:1px solid var(--border-color);">
                                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; letter-spacing:0.5px;">Versão</div>
                                <div style="margin-top:0.4rem;">
                                    <strong style="font-size:0.95rem; color:var(--text-primary);">SG v2.0.2026</strong>
                                    <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">Estável</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- content-body -->
        </div><!-- main-area -->
    </div><!-- app-wrapper -->

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="js/app-2026.js"></script>
    <script>
    // Gráfico de Presença
    (function() {
        const ctx = document.getElementById('assiduidadeChart');
        if (!ctx) return;

        const presencaData = <?php echo json_encode($presencaSemanal); ?>;
        const dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        const valores = dias.map(d => {
            const found = presencaData.find(p => p.dia && p.dia.toLowerCase().includes(d.toLowerCase()));
            return found ? parseInt(found.total) : 0;
        });

        const isDark = !document.documentElement.hasAttribute('data-theme') || document.documentElement.getAttribute('data-theme') !== 'light';
        const textColor = isDark ? '#cbd5e1' : '#475569';
        const gridColor = isDark ? 'rgba(148,163,184,0.1)' : 'rgba(15,23,42,0.06)';

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dias,
                datasets: [{
                    label: 'Presentes',
                    data: valores,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.12)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#1e293b' : '#ffffff',
                        titleColor: textColor,
                        bodyColor: textColor,
                        borderColor: 'rgba(148,163,184,0.2)',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor, font: { size: 11 } },
                        grid: { color: gridColor, drawBorder: false }
                    },
                    x: {
                        ticks: { color: textColor, font: { size: 11 } },
                        grid: { display: false }
                    }
                }
            }
        });
    })();
    </script>
</body>
</html>
