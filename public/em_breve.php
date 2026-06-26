<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth\Auth;

$auth = new Auth();
$auth->requireAuth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Em Breve | Farmácia Gingongo</title>
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
            $pageTitle = 'Em Desenvolvimento';
            $pageSubtitle = 'Esta funcionalidade está em construção';
            include 'includes/topbar.php';
            ?>
            <div class="content-body">
                <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
                    <i class="bi bi-cone-striped" style="font-size: 4rem; color: var(--warning); margin-bottom: 1rem;"></i>
                    <h1 style="font-size: 2rem; font-weight: 800; margin: 0 0 0.5rem;">Em Desenvolvimento</h1>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Esta funcionalidade está sendo implementada nas próximas atualizações.</p>
                    <div style="display: flex; justify-content: center; gap: 0.75rem;">
                        <a href="dashboard.php" class="btn btn-ghost">Voltar ao Início</a>
                        <a href="javascript:history.back()" class="btn btn-primary">Página Anterior</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
