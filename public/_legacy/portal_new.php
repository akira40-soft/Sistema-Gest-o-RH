<?php
/**
 * Portal do Funcionário
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

$middleware = new AuthMiddleware();
$middleware->requireAuth();

$auth = $middleware->getAuth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Funcionário | Farmácia Gingongo RG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style-new.css">
    <style>
        :root {
            --sidebar-width: 280px;
        }
        
        body {
            display: flex;
            background: #f8fafc;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .topbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .logo-area {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo-area h5 {
            margin: 0;
            font-weight: 700;
            font-size: 18px;
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
        }
        
        .nav-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .nav-menu li a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 20px;
        }
        
        .nav-menu li.active a {
            background: rgba(255,255,255,0.2);
            color: white;
            border-left: 3px solid white;
            padding-left: 12px;
        }
        
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .stat-card {
            padding: 20px;
            border-radius: 8px;
            background: white;
            border-left: 4px solid #2563eb;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #2563eb;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #64748b;
            margin-top: 8px;
        }
        
        .menu-item-card {
            padding: 20px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .menu-item-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
            color: #2563eb;
        }
        
        .menu-item-card i {
            font-size: 32px;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo-area">
            <h5><i class="bi bi-capsule-pill"></i> Gingongo</h5>
            <p class="small">RH & Farmácia</p>
        </div>

        <nav class="nav-menu" style="position: relative; min-height: 70vh;">
            <li class="active">
                <a href="portal.php">
                    <i class="bi bi-house-heart"></i>
                    Meu Portal
                </a>
            </li>
            <li>
                <a href="perfil.php">
                    <i class="bi bi-person"></i>
                    Meu Perfil
                </a>
            </li>
            <li>
                <a href="recibo_salario.php">
                    <i class="bi bi-file-earmark-pdf"></i>
                    Recibos
                </a>
            </li>
            <li>
                <a href="pontos.php">
                    <i class="bi bi-clock-history"></i>
                    Ponto
                </a>
            </li>
            <li>
                <a href="comunicados.php">
                    <i class="bi bi-megaphone"></i>
                    Comunicados
                </a>
            </li>

            <div style="position: absolute; bottom: 20px; left: 0; right: 0; padding: 0 15px;">
                <a href="logout_new.php" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(255,255,255,0.1); color: white; border-radius: 8px; text-decoration: none; font-size: 14px; transition: all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <h6 style="margin: 0;">Meu Portal</h6>
            <div class="d-flex align-items-center gap-3">
                <span style="color: #64748b; font-size: 14px;">
                    <i class="bi bi-person-circle"></i>
                    <?php echo htmlspecialchars($auth->getUsername()); ?>
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                Bem-vindo ao Portal de Funcionários da Farmácia Gingongo RG!
            </div>

            <!-- Menu de Ações Rápidas -->
            <h5 class="mb-4">Ações Rápidas</h5>
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <a href="perfil.php" class="menu-item-card">
                        <i class="bi bi-person"></i>
                        <strong>Meu Perfil</strong>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="recibo_salario.php" class="menu-item-card">
                        <i class="bi bi-file-earmark-pdf"></i>
                        <strong>Recibos</strong>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="pontos.php" class="menu-item-card">
                        <i class="bi bi-clock-history"></i>
                        <strong>Ponto</strong>
                    </a>
                </div>
                <div class="col-md-3 mb-3">
                    <a href="comunicados.php" class="menu-item-card">
                        <i class="bi bi-megaphone"></i>
                        <strong>Comunicados</strong>
                    </a>
                </div>
            </div>

            <!-- Informações -->
            <h5 class="mb-4">Informações</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                Atenção
                            </h6>
                            <p class="card-text small text-muted">
                                Você tem <strong>2 tarefas</strong> pendentes para completar.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-calendar-check text-success me-2"></i>
                                Próximas Férias
                            </h6>
                            <p class="card-text small text-muted">
                                Você pode solicitar férias em <strong>15 dias</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
