<?php
/**
 * Dashboard RH User
 * Dashboard específico para gestores de RH
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

// Verificar acesso - RH ou Admin
AuthMiddleware::requireRoles(['admin', 'gestor_rh']);

$db = \App\Database\Database::getInstance();

// Estatísticas
$stats = [];

// Total de funcionários
$stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios");
$stats['total_funcionarios'] = $stmt->fetch()['total'] ?? 0;

// Funcionários por status
$stmt = $db->query("SELECT status, COUNT(*) as total FROM funcionarios GROUP BY status");
$status_breakdown = $stmt->fetchAll();

// Aprovações pendentes
$stmt = $db->query("SELECT COUNT(*) as total FROM employee_approvals WHERE status = 'pendente'");
$stats['aprovacoes_pendentes'] = $stmt->fetch()['total'] ?? 0;

// Departamentos
$stmt = $db->query("
    SELECT d.id, d.nome, COUNT(f.id) as total_funcionarios,
           SUM(CASE WHEN f.status = 'ativo' THEN 1 ELSE 0 END) as ativos
    FROM departamentos d
    LEFT JOIN funcionarios f ON d.id = f.departamento_id
    GROUP BY d.id
    ORDER BY total_funcionarios DESC
");
$departamentos = $stmt->fetchAll();

// Cargos mais comuns
$stmt = $db->query("
    SELECT c.nome, COUNT(f.id) as total
    FROM cargos c
    LEFT JOIN funcionarios f ON c.id = f.cargo_id
    GROUP BY c.id
    ORDER BY total DESC
    LIMIT 8
");
$cargos = $stmt->fetchAll();

// Aniversariantes do mês
$stmt = $db->query("
    SELECT nome_completo, data_nascimento
    FROM funcionarios
    WHERE MONTH(data_nascimento) = MONTH(CURRENT_DATE)
    AND status = 'ativo'
    ORDER BY DAY(data_nascimento)
");
$aniversariantes = $stmt->fetchAll();

// Funcionários recém admitidos (últimos 30 dias)
$stmt = $db->query("
    SELECT f.*, d.nome as departamento_nome, c.nome as cargo_nome
    FROM funcionarios f
    LEFT JOIN departamentos d ON f.departamento_id = d.id
    LEFT JOIN cargos c ON f.cargo_id = c.id
    WHERE DATE(f.data_admissao) >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ORDER BY f.data_admissao DESC
");
$novos_funcionarios = $stmt->fetchAll();

// Últimas atividades
$stmt = $db->query("
    SELECT ea.*, f.nome_completo, u.username
    FROM employee_approvals ea
    JOIN funcionarios f ON ea.funcionario_id = f.id
    LEFT JOIN usuarios u ON ea.aprovado_por = u.id
    ORDER BY ea.criado_em DESC
    LIMIT 8
");
$atividades = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard RH - RG Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="/css/style-new.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(90deg, #0e7490 0%, #0c5e75 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .main-content {
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            font-weight: 700;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-top: 4px solid #0e7490;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0e7490;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .stat-icon {
            font-size: 2rem;
            color: #0e7490;
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
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-ativo {
            background: #d4edda;
            color: #155724;
        }

        .badge-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .birthday-item {
            padding: 12px;
            border-left: 3px solid #0e7490;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .birthday-name {
            font-weight: 600;
            color: #333;
        }

        .birthday-date {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard_rh_user.php">
                <i class="fas fa-users-cog"></i> RH Dashboard
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

    <div class="container-fluid main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> Dashboard de Recursos Humanos</h1>
                <p class="text-muted">Gestão de funcionários e aprovações</p>
            </div>
        </div>

        <!-- Estatísticas Rápidas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-label">Total de Funcionários</div>
                    <div class="stat-number"><?php echo $stats['total_funcionarios']; ?></div>
                    <small class="text-muted">Registros ativos</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-top-color: #ffc107;">
                    <i class="fas fa-hourglass-half stat-icon" style="color: #ffc107;"></i>
                    <div class="stat-label">Aprovações Pendentes</div>
                    <div class="stat-number" style="color: #ffc107;"><?php echo $stats['aprovacoes_pendentes']; ?></div>
                    <small class="text-muted">Aguardando análise</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-top-color: #17a2b8;">
                    <i class="fas fa-building stat-icon" style="color: #17a2b8;"></i>
                    <div class="stat-label">Departamentos</div>
                    <div class="stat-number" style="color: #17a2b8;"><?php echo count($departamentos); ?></div>
                    <small class="text-muted">Áreas da empresa</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-top-color: #dc3545;">
                    <i class="fas fa-cake-candles stat-icon" style="color: #dc3545;"></i>
                    <div class="stat-label">Aniversariantes</div>
                    <div class="stat-number" style="color: #dc3545;"><?php echo count($aniversariantes); ?></div>
                    <small class="text-muted">Este mês</small>
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
                    <div class="chart-title">👔 Top 8 Cargos</div>
                    <canvas id="chartCargos"></canvas>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-title">📈 Status dos Funcionários</div>
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-title">✅ Aprovações por Status</div>
                    <canvas id="chartApprovals"></canvas>
                </div>
            </div>
        </div>

        <!-- Seções de Informações -->
        <div class="row">
            <div class="col-lg-4">
                <div class="table-wrapper">
                    <h5 class="chart-title">🎂 Aniversariantes do Mês</h5>
                    <?php if (count($aniversariantes) > 0): ?>
                        <?php foreach ($aniversariantes as $person): ?>
                            <div class="birthday-item">
                                <div class="birthday-name"><?php echo htmlspecialchars($person['nome_completo']); ?></div>
                                <div class="birthday-date">
                                    🎉 <?php echo date('d/m', strtotime($person['data_nascimento'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Nenhum aniversariante neste mês</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="table-wrapper">
                    <h5 class="chart-title">🆕 Funcionários Recém Admitidos (30 dias)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Cargo</th>
                                    <th>Departamento</th>
                                    <th>Data Admissão</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($novos_funcionarios, 0, 5) as $func): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($func['nome_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($func['cargo_nome'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($func['departamento_nome'] ?? '-'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($func['data_admissao'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Atividades Recentes -->
        <div class="table-wrapper mt-4">
            <h5 class="chart-title">📋 Atividades Recentes</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Ação</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($atividades, 0, 10) as $ativ): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ativ['nome_completo']); ?></td>
                                <td>
                                    <?php 
                                    $actions = [
                                        'pendente' => 'Aguardando aprovação',
                                        'aprovado' => 'Funcionário aprovado',
                                        'rejeitado' => 'Funcionário rejeitado',
                                    ];
                                    echo $actions[$ativ['status']] ?? $ativ['status'];
                                    ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: 
                                        <?php echo $ativ['status'] === 'pendente' ? '#ffc107' : ($ativ['status'] === 'aprovado' ? '#28a745' : '#dc3545'); ?>;
                                        color: white;">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico: Departamentos
        const ctxDept = document.getElementById('chartDepartamentos').getContext('2d');
        new Chart(ctxDept, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(fn($d) => $d['nome'], $departamentos)); ?>,
                datasets: [{
                    label: 'Funcionários',
                    data: <?php echo json_encode(array_map(fn($d) => $d['total_funcionarios'], $departamentos)); ?>,
                    backgroundColor: '#0e7490',
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
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(fn($c) => $c['nome'], $cargos)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(fn($c) => $c['total'], $cargos)); ?>,
                    backgroundColor: [
                        '#0e7490', '#0c5e75', '#06b6d4', '#0891b2',
                        '#0d9955', '#0a8352', '#078245', '#067038'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Gráfico: Status
        const statusData = <?php echo json_encode($status_breakdown); ?>;
        const ctxStatus = document.getElementById('chartStatus').getContext('2d');
        new Chart(ctxStatus, {
            type: 'pie',
            data: {
                labels: statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1)),
                datasets: [{
                    data: statusData.map(s => s.total),
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Gráfico: Aprovações (placeholder)
        const ctxApp = document.getElementById('chartApprovals').getContext('2d');
        new Chart(ctxApp, {
            type: 'bar',
            data: {
                labels: ['Pendentes', 'Aprovadas', 'Rejeitadas'],
                datasets: [{
                    label: 'Quantidade',
                    data: [<?php echo $stats['aprovacoes_pendentes']; ?>, 0, 0],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>
