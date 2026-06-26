<?php
require_once __DIR__ . '/../src/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
    <style>
        .denied-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; }
        .denied-card { text-align: center; max-width: 480px; padding: 3rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); }
        .denied-icon { font-size: 4rem; color: var(--danger); margin-bottom: 1rem; }
    </style>
</head>
<body class="login-page">
    <div class="denied-card">
        <div class="denied-icon"><i class="bi bi-shield-lock"></i></div>
        <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Acesso Negado</h1>
        <p style="color: var(--text-muted); margin-bottom: 0.25rem;">Você não tem permissão para acessar esta área.</p>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Contacte o Gestor de RH ou Administrador do Sistema.</p>
        <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1.5rem;">
            <i class="bi bi-arrow-left"></i> Voltar ao Início
        </a>
    </div>
</body>
</html>
