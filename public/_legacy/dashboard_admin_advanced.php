<?php
/**
 * Dashboard Admin Avançado
 * Estatísticas, gráficos, e controles administrativos
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

// Verificar acesso
AuthMiddleware::requireAdmin();

$db = \App\Database\Database::getInstance();

// Estatísticas
$stats = [];

// Total de funcionários
$stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios");
$stats['total_funcionarios'] = $stmt->fetch()['total'] ?? 0;

// Funcionários ativos
$stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'ativo'");
$stats['funcionarios_ativos'] = $stmt->fetch()['total'] ?? 0;

// Funcionários inativos
$stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios WHERE status = 'inativo'");
$stats['funcionarios_inativos'] = $stmt->fetch()['total'] ?? 0;

// Aprovações pendentes
$stmt = $db->query("SELECT COUNT(*) as total FROM employee_approvals WHERE status = 'pendente'");
$stats['aprovacoes_pendentes'] = $stmt->fetch()['total'] ?? 0;

// Total de RGs
$stmt = $db->query("SELECT COUNT(*) as total FROM rgs");
$stats['total_rgs'] = $stmt->fetch()['total'] ?? 0;

// RGs ativos
$stmt = $db->query("SELECT COUNT(*) as total FROM rgs WHERE status = 'ativo'");
$stats['rgs_ativos'] = $stmt->fetch()['total'] ?? 0;

// Total de usuários
$stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
$stats['total_usuarios'] = $stmt->fetch()['total'] ?? 0;

// Funcionários por departamento
$stmt = $db->query("
    SELECT d.nome, COUNT(f.id) as total 
    FROM departamentos d
    LEFT JOIN funcionarios f ON d.id = f.departamento_id
    GROUP BY d.id, d.nome
    ORDER BY total DESC
");
$departamentos_data = $stmt->fetchAll();

// Funcionários por cargo (top 10)
$stmt = $db->query("
    SELECT c.nome, COUNT(f.id) as total 
    FROM cargos c
    LEFT JOIN funcionarios f ON c.id = f.cargo_id
    GROUP BY c.id, c.nome
    ORDER BY total DESC
    LIMIT 10
");
$cargos_data = $stmt->fetchAll();

// Status de RGs
$stmt = $db->query("
    SELECT status, COUNT(*) as total 
    FROM rgs 
    GROUP BY status
");
$rgs_status = $stmt->fetchAll();

// Aprovações por status
$stmt = $db->query("
    SELECT status, COUNT(*) as total 
    FROM employee_approvals 
    GROUP BY status
");
$approval_status = $stmt->fetchAll();

// Últimos registros
$stmt = $db->query("
    SELECT f.nome_completo, f.email, f.data_admissao 
    FROM funcionarios f 
    ORDER BY f.criado_em DESC 
    LIMIT 5
");
$ultimos_funcionarios = $stmt->fetchAll();

// Atividade recente (aprovações)
$stmt = $db->query("
    SELECT ea.*, f.nome_completo, u.username 
    FROM employee_approvals ea
    JOIN funcionarios f ON ea.funcionario_id = f.id
    LEFT JOIN usuarios u ON ea.aprovado_por = u.id
    ORDER BY ea.criado_em DESC 
    LIMIT 10
");
$atividade_recente = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - RG Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="/css/style-new.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            height: 100vh;
            position: sticky;
            top: 0;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-link {
            color: #666;
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: #667eea;
            background: #f5f6fa;
            border-left-color: #667eea;
        }

        .nav-link.active {
            color: #667eea;
            background: #f5f6fa;
            border-left-color: #667eea;
            font-weight: 600;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card.danger {
            border-left-color: #dc3545;
        }

        .stat-card.success {
            border-left-color: #28a745;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.info {
            border-left-color: #17a2b8;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .stat-icon {
            font-size: 2rem;
            color: #667eea;
            opacity: 0.3;
            float: right;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .table-wrapper {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f5f6fa;
            border: none;
            font-weight: 600;
            color: #555;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: #f0f0f0;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .main-content {
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            color: #333;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .stat-card {
                margin-bottom: 15px;
            }

            .stat-number {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard_admin_advanced.php">
                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/perfil_usuario.php">
                            <i class="fas fa-user"></i> Meu Perfil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout_new.php">
                            <i class="fas fa-sign-out-alt"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar d-md-block">
                <div class="sidebar-nav">
                    <a href="/dashboard_admin_advanced.php" class="nav-link active">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                    <a href="/admin-funcionarios-pendentes.php" class="nav-link">
                        <i class="fas fa-hourglass-half"></i> Aprovações
                        <?php if ($stats['aprovacoes_pendentes'] > 0): ?>
                            <span class="badge badge-danger"><?php echo $stats['aprovacoes_pendentes']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin-funcionarios.php" class="nav-link">
                        <i class="fas fa-users"></i> Funcionários
                    </a>
                    <a href="/admin-rgs.php" class="nav-link">
                        <i class="fas fa-id-card"></i> RGs
                    </a>
                    <a href="/admin-usuarios.php" class="nav-link">
                        <i class="fas fa-user-shield"></i> Usuários
                    </a>
                    <a href="/admin-configuracoes.php" class="nav-link">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                    <a href="/admin-logs.php" class="nav-link">
                        <i class="fas fa-history"></i> Logs de Auditoria
                    </a>
                </div>
            </nav>

            <!-- Conteúdo Principal -->
            <main class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-chart-bar"></i> Dashboard Administrativo</h1>
                        <p class="text-muted mb-0">Visão geral do sistema</p>
                    </div>
                    <div>
                        <span class="badge bg-info">Atualizado: <?php echo date('d/m/Y H:i'); ?></span>
                    </div>
                </div>

                <!-- Estatísticas Rápidas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <i class="fas fa-users stat-icon"></i>
                            <div class="stat-label">Funcionários</div>
                            <div class="stat-number"><?php echo $stats['total_funcionarios']; ?></div>
                            <small class="text-muted"><?php echo $stats['funcionarios_ativos']; ?> ativos</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <i class="fas fa-hourglass-half stat-icon"></i>
                            <div class="stat-label">Aprovações Pendentes</div>
                            <div class="stat-number"><?php echo $stats['aprovacoes_pendentes']; ?></div>
                            <small class="text-muted">Aguardando aprovação</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <i class="fas fa-id-card stat-icon"></i>
                            <div class="stat-label">RGs Ativos</div>
                            <div class="stat-number"><?php echo $stats['rgs_ativos']; ?></div>
                            <small class="text-muted">De <?php echo $stats['total_rgs']; ?> total</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <i class="fas fa-user-circle stat-icon"></i>
                            <div class="stat-label">Usuários Cadastrados</div>
                            <div class="stat-number"><?php echo $stats['total_usuarios']; ?></div>
                            <small class="text-muted">Contas ativas</small>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-title">📊 Funcionários por Departamento</div>
                            <canvas id="chartDepartamentos"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-title">🎯 Status de RGs</div>
                            <canvas id="chartRGs"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-title">✅ Aprovações</div>
                            <canvas id="chartApprovals"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-title">👔 Top 10 Cargos</div>
                            <canvas id="chartCargos"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Seção de Atividades -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="table-wrapper">
                            <h5 class="chart-title">🆕 Últimos Funcionários Adicionados</h5>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Data Admissão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimos_funcionarios as $func): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($func['nome_completo']); ?></td>
                                            <td><?php echo htmlspecialchars($func['email']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($func['data_admissao'] ?? 'now')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="table-wrapper">
                            <h5 class="chart-title">📋 Atividade Recente de Aprovações</h5>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($atividade_recente, 0, 5) as $ativ): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ativ['nome_completo']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $ativ['status']; ?>">
                                                    <?php echo ucfirst($ativ['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($ativ['criado_em'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cores
        const colors = {
            primary: '#667eea',
            success: '#28a745',
            danger: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8',
        };

        // Gráfico: Departamentos
        const ctxDept = document.getElementById('chartDepartamentos').getContext('2d');
        new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(fn($d) => $d['nome'], $departamentos_data)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(fn($d) => $d['total'], $departamentos_data)); ?>,
                    backgroundColor: [
                        '#667eea', '#764ba2', '#f093fb', '#4facfe',
                        '#43e97b', '#fa709a', '#ffa502', '#fbab7e'
                    ],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Gráfico: Status RGs
        const ctxRG = document.getElementById('chartRGs').getContext('2d');
        new Chart(ctxRG, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_map(fn($r) => ucfirst($r['status']), $rgs_status)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(fn($r) => $r['total'], $rgs_status)); ?>,
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Gráfico: Aprovações
        const ctxApp = document.getElementById('chartApprovals').getContext('2d');
        new Chart(ctxApp, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(fn($a) => ucfirst($a['status']), $approval_status)); ?>,
                datasets: [{
                    label: 'Quantidade',
                    data: <?php echo json_encode(array_map(fn($a) => $a['total'], $approval_status)); ?>,
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            }
        });

        // Gráfico: Cargos
        const ctxCargo = document.getElementById('chartCargos').getContext('2d');
        new Chart(ctxCargo, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(fn($c) => $c['nome'], array_slice($cargos_data, 0, 10))); ?>,
                datasets: [{
                    label: 'Funcionários',
                    data: <?php echo json_encode(array_map(fn($c) => $c['total'], array_slice($cargos_data, 0, 10))); ?>,
                    backgroundColor: '#667eea',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
