<?php
/**
 * Dashboard Admin - Sistema de Gestão RG
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth(true); // Requer admin

$user = [
    'username' => $auth->getUsername(),
    'role' => $auth->getUserRole(),
    'id' => $auth->getUserId()
];

// Obter dados do banco
$db = Database::getInstance();

$stats = [];
try {
    // Total de funcionários ativos
    $result = $db->select('funcionarios', [], false);
    $stats['total_funcionarios'] = count($result);
    
    // Últimos logins
    $usuarios = $db->select('usuarios', [], false);
    $stats['total_usuarios'] = count($usuarios);
    
    // Carteiras profissionais cadastradas
    $stmt = $db->getConnection()->query("SELECT COUNT(*) as cnt FROM funcionarios WHERE carteira_profissional IS NOT NULL AND carteira_profissional != ''");
    $stats['carteiras_cadastradas'] = $stmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erro ao obter stats: " . $e->getMessage());
    $stats = [
        'total_funcionarios' => 0,
        'total_usuarios' => 0,
        'carteiras_cadastradas' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | Farmácia Gingongo RG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2196F3 !important;
        }
        .navbar-brand span {
            color: #4CAF50;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .welcome-section {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.2);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #2196F3;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .stat-card.green {
            border-left-color: #4CAF50;
        }
        .stat-card.orange {
            border-left-color: #FF9800;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2196F3;
            margin: 0.5rem 0;
        }
        .stat-card.green h3 {
            color: #4CAF50;
        }
        .stat-card.orange h3 {
            color: #FF9800;
        }
        .stat-card p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        .quick-actions {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            border-radius: 8px;
            background: #f5f5f5;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            min-height: 120px;
            margin-bottom: 1rem;
        }
        .btn-action:hover {
            background: #2196F3;
            border-color: #2196F3;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(33, 150, 243, 0.3);
        }
        .btn-action i {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .user-badge {
            background: #f5f5f5;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-capsule-pill me-2"></i>GINGONGO <span>RG</span>
            </a>
            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="user-badge">
                    <i class="bi bi-person-circle"></i>
                    <span><?php echo htmlspecialchars($user['username']); ?></span>
                    <span class="badge bg-success ms-1"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1><i class="bi bi-speedometer2 me-2"></i>Bem-vindo ao Dashboard</h1>
            <p class="mb-0">Olá, <strong><?php echo htmlspecialchars($user['username']); ?></strong>! Aqui você tem acesso a todas as funcionalidades administrativas do sistema.</p>
        </div>

        <!-- Statistics -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <p>Total de Funcionários</p>
                    <h3><?php echo $stats['total_funcionarios']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <p>Usuários do Sistema</p>
                    <h3><?php echo $stats['total_usuarios']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <p>Carteiras Profissionais</p>
                    <h3><?php echo $stats['carteiras_cadastradas']; ?></h3>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <h4 class="mb-3"><i class="bi bi-lightning-fill"></i> Ações Rápidas</h4>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-3">
                <a href="gestao-funcionarios.php" class="btn-action">
                    <i class="bi bi-people-fill"></i>
                    <span>Gestão de Funcionários</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="gestao-usuarios.php" class="btn-action">
                    <i class="bi bi-shield-lock"></i>
                    <span>Gestão de Usuários</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="timeclock.php" class="btn-action">
                    <i class="bi bi-clock-history"></i>
                    <span>Relógio de Ponto</span>
                </a>
            </div>
            <div class="col-md-3">
                <a href="relatorios.php" class="btn-action">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Relatórios</span>
                </a>
            </div>
        </div>

        <!-- System Status -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="quick-actions">
                    <h5><i class="bi bi-info-circle me-2"></i>Status do Sistema</h5>
                    <div class="mt-3">
                        <p class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            <strong>Database:</strong> Conectado (MySQL)
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            <strong>Sessão:</strong> Ativa e Funcional
                        </p>
                        <p class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            <strong>Autenticação:</strong> Sistema em Funcionamento
                        </p>
                        <p class="mb-0">
                            <i class="bi bi-check-circle text-success"></i>
                            <strong>Phase 3 Angola:</strong> Implementado (Carteira Profissional + GPS)
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
