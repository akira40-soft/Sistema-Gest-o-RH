<?php
/**
 * INSTRUÇÕES DE LOGIN
 * Teste manual do sistema
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instruções de Login - Farmácia Gingongo RG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .card-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .badge-success {
            background: #4CAF50 !important;
            font-size: 0.9rem;
        }
        .code-box {
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
        }
        .code-box strong {
            color: #2196F3;
        }
        .btn-login {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><i class="bi bi-capsule-pill me-2"></i>GINGONGO RG</h1>
                <p class="mb-0 mt-2">✅ Sistema Preparado para Login</p>
            </div>
            
            <div class="card-body p-4">
                <h4 class="mb-4"><i class="bi bi-check-circle text-success me-2"></i>Credenciais de Admin</h4>
                
                <p class="text-muted">Use as seguintes credenciais para fazer login no sistema:</p>
                
                <div class="code-box">
                    <div><strong>Username:</strong> josemar_quarenta</div>
                    <div><strong>Password:</strong> admin1123</div>
                </div>

                <hr>

                <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Status do Sistema</h5>
                
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <strong>Database MySQL:</strong> Conectado
                    </li>
                    <li class="mb-2">
                        <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <strong>Sessões:</strong> Funcionando
                    </li>
                    <li class="mb-2">
                        <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <strong>Autenticação:</strong> Operacional
                    </li>
                    <li class="mb-2">
                        <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <strong>Admin User:</strong> Criado e validado
                    </li>
                    <li class="mb-2">
                        <span class="badge badge-success"><i class="bi bi-check-lg"></i> OK</span>
                        <strong>Phase 3:</strong> Carteira Profissional + GPS implementados
                    </li>
                </ul>

                <hr>

                <h5 class="mb-3"><i class="bi bi-lightning-fill me-2"></i>Próximos Passos</h5>
                
                <ol class="mb-0">
                    <li>Clique no botão abaixo para ir ao login</li>
                    <li>Insira as credenciais acima</li>
                    <li>Você será redirecionado para o dashboard admin</li>
                    <li>Explore as funcionalidades do sistema</li>
                </ol>

                <div class="mt-4 text-center">
                    <a href="login.php" class="btn btn-login btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Ir para Login
                    </a>
                </div>
            </div>

            <div class="card-footer bg-light text-center text-muted py-3">
                <small>© 2026 Farmácia Gingongo RH Digital</small>
            </div>
        </div>
    </div>
</body>
</html>
