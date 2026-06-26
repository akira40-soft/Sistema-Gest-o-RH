<?php
/**
 * Página de Acesso Negado
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

$auth = new Auth();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado | Farmácia Gingongo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style-new.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: #dc2626;
        }
        
        .error-container h1 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .error-container p {
            color: #64748b;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        
        <h1>Acesso Negado</h1>
        <p>Você não tem permissão para acessar esta página. Se você acredita que isso é um erro, entre em contato com o administrador do sistema.</p>
        
        <div class="d-flex gap-2 justify-content-center">
            <?php if ($auth->isAuthenticated()): ?>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="bi bi-house me-2"></i> Ir para Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Fazer Login
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
