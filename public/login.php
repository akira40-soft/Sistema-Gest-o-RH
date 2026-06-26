<?php
require_once __DIR__ . '/../src/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-brand">
<div class="logo-mark">
    <i class="bi bi-capsule-pill"></i>
</div>
            <h1>GINGONGO<span>RG</span></h1>
            <p>Sistema de Gestão de RH &amp; Farmácia</p>
        </div>

        <?php if (isset($_SESSION['login_error'])): ?>
            <div class="alert alert-danger" id="loginError">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div class="alert-content"><?php echo htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?></div>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login_process.php" class="login-form" autocomplete="on">
            <div class="form-group">
                <label class="form-label" for="username"><i class="bi bi-person"></i> Usuário</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Digite seu usuário" required autocomplete="username" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="password"><i class="bi bi-lock"></i> Senha</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required autocomplete="current-password" style="border-radius:var(--radius-sm) 0 0 var(--radius-sm);">
                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Mostrar/Ocultar senha">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="login-options">
                <label class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <span class="form-check-label">Manter conectado</span>
                </label>
                <a href="#">Esqueci a senha</a>
            </div>

            <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                <i class="bi bi-box-arrow-in-right"></i>
                Entrar no Sistema
            </button>
        </form>

        <div class="login-footer">
            <p>Acesso restrito · © <?php echo date('Y'); ?> Farmácia Gingongo</p>
            <a href="#" onclick="event.preventDefault(); App.applyTheme(document.documentElement.getAttribute('data-theme')==='light'?'dark':'light');">
                <i class="bi bi-circle-half"></i> Alternar tema
            </a>
        </div>
    </div>

    <script src="js/app-2026.js"></script>
    <script>
    (function() {
        // Aplicar tema
        const saved = localStorage.getItem('rg_theme') || 'dark';
        App.applyTheme(saved);

        // Toggle de senha
        const toggleBtn = document.getElementById('togglePassword');
        const passInput = document.getElementById('password');
        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passInput.type === 'password';
                passInput.type = isPassword ? 'text' : 'password';
                toggleBtn.querySelector('i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }

        // Validação do formulário
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');
        if (form) {
            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = passInput.value;

                // Limpar erros anteriores
                const oldError = form.parentElement.querySelector('.alert-danger');
                if (oldError) oldError.remove();

                if (!username || username.length < 3) {
                    e.preventDefault();
                    showError('Por favor, informe um usuário válido (mínimo 3 caracteres).');
                    return;
                }
                if (!password || password.length < 6) {
                    e.preventDefault();
                    showError('A senha deve ter no mínimo 6 caracteres.');
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px;"></span> Autenticando...';
            });
        }

        function showError(msg) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">${msg}</div>`;
            form.parentElement.insertBefore(alert, form);
            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    })();
    </script>
</body>
</html>
