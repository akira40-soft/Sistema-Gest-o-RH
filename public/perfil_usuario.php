<?php
/**
 * Página de Perfil do Usuário
 * Permite editar dados pessoais, foto, senha e preferências
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

// Verificar autenticação
$middleware = new AuthMiddleware();
$middleware->requireAuth();

$usuario_id = $_SESSION['usuario_id'] ?? null;
$nome_usuario = $_SESSION['nome_usuario'] ?? 'Usuário';
$tipo_acesso = $_SESSION['tipo_acesso'] ?? '';

if (!$usuario_id) {
    header('Location: /login.php');
    exit;
}

// Obter dados do usuário
$db = \App\Database\Database::getInstance();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die('Usuário não encontrado');
}

// Obter foto
$photoModel = new UserPhoto();
$fotoUrl = $photoModel->getPhotoUrl($usuario_id);
if (!$fotoUrl) {
    $fotoUrl = UserPhoto::getGravatarUrl($usuario['email']);
}

$mensagem = '';
$tipo_mensagem = '';

// Processar atualizações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'dados-pessoais') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tema = trim($_POST['tema'] ?? 'dark');

        if (empty($nome) || empty($email)) {
            $mensagem = 'Nome e email são obrigatórios';
            $tipo_mensagem = 'erro';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE usuarios 
                    SET nome = ?, email = ?, telefone = ?, tema = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $email, $telefone, $tema, $usuario_id]);

                // Atualizar sessão
                $_SESSION['nome_usuario'] = $nome;

                // Recarregar dados
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $usuario = $stmt->fetch();

                $mensagem = '✅ Dados atualizados com sucesso!';
                $tipo_mensagem = 'sucesso';
            } catch (\Exception $e) {
                $mensagem = 'Erro ao atualizar: ' . $e->getMessage();
                $tipo_mensagem = 'erro';
            }
        }
    } elseif ($acao === 'senha') {
        $senha_atual = trim($_POST['senha_atual'] ?? '');
        $senha_nova = trim($_POST['senha_nova'] ?? '');
        $senha_confirma = trim($_POST['senha_confirma'] ?? '');

        if (empty($senha_atual) || empty($senha_nova)) {
            $mensagem = 'Preencha todos os campos de senha';
            $tipo_mensagem = 'erro';
        } elseif ($senha_nova !== $senha_confirma) {
            $mensagem = 'As senhas não coincidem';
            $tipo_mensagem = 'erro';
        } elseif (strlen($senha_nova) < 8) {
            $mensagem = 'Senha deve ter pelo menos 8 caracteres';
            $tipo_mensagem = 'erro';
        } else {
            // Verificar senha atual
            if (!password_verify($senha_atual, $usuario['senha'])) {
                $mensagem = 'Senha atual incorreta';
                $tipo_mensagem = 'erro';
            } else {
                try {
                    $senha_hash = password_hash($senha_nova, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmt->execute([$senha_hash, $usuario_id]);

                    $mensagem = '✅ Senha alterada com sucesso!';
                    $tipo_mensagem = 'sucesso';
                } catch (\Exception $e) {
                    $mensagem = 'Erro ao alterar senha: ' . $e->getMessage();
                    $tipo_mensagem = 'erro';
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - RG Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style-2026.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 20px;
            color: white;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .photo-upload-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .photo-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #667eea;
            border: 2px solid white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .photo-upload-btn:hover {
            background: #764ba2;
            transform: scale(1.1);
        }

        .photo-upload-input {
            display: none;
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .card-header {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            padding: 20px;
        }

        .card-header h5 {
            margin: 0;
            color: #333;
            font-weight: 600;
        }

        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 5px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: #667eea;
            border: none;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .alert {
            border: none;
            border-radius: 5px;
            padding: 15px 20px;
        }

        .alert-sucesso {
            background: #d4edda;
            color: #155724;
        }

        .alert-erro {
            background: #f8d7da;
            color: #721c24;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
        }

        .badge-role {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-admin {
            background: #fee;
            color: #c33;
        }

        .badge-rh {
            background: #efe;
            color: #3c3;
        }

        .badge-funcionario {
            background: #eef;
            color: #33c;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: #667eea;">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard.php">
                <i class="fas fa-home"></i> RG Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/perfil_usuario.php">
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

    <div class="container my-5">
        <!-- Cabeçalho do Perfil -->
        <div class="profile-header text-center">
            <div class="photo-upload-container mb-3">
                <img src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="<?php echo htmlspecialchars($usuario['nome']); ?>" class="profile-photo" id="profilePhoto">
                <label for="fotoInput" class="photo-upload-btn" title="Alterar foto">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="fotoInput" class="photo-upload-input" accept="image/*">
            </div>
            <h2><?php echo htmlspecialchars($usuario['nome']); ?></h2>
            <p class="mb-0">
                <span class="badge-role badge-<?php echo $tipo_acesso === 'admin' ? 'admin' : ($tipo_acesso === 'gestor_rh' ? 'rh' : 'funcionario'); ?>">
                    <?php 
                    echo match($tipo_acesso) {
                        'admin' => '👨‍💼 Administrador',
                        'gestor_rh' => '👥 Gestor RH',
                        default => '👤 Funcionário'
                    };
                    ?>
                </span>
            </p>
        </div>

        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-lg-4">
                <!-- Informações Rápidas -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle"></i> Informações da Conta</h5>
                    </div>
                    <div class="card-body">
                        <div class="info-section">
                            <div class="info-row">
                                <span class="info-label">Usuário:</span>
                                <span class="info-value"><?php echo htmlspecialchars($usuario['username']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">E-mail:</span>
                                <span class="info-value text-break"><?php echo htmlspecialchars($usuario['email']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tipo de Acesso:</span>
                                <span class="info-value"><?php echo htmlspecialchars($tipo_acesso); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Último Acesso:</span>
                                <span class="info-value">
                                    <?php 
                                    if ($usuario['ultimo_login']) {
                                        echo date('d/m/Y H:i', strtotime($usuario['ultimo_login']));
                                    } else {
                                        echo 'Primeira vez';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Membro desde:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preferências -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-sliders-h"></i> Preferências</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="form-label">Tema:</label>
                            <select class="form-select" id="temaSeletor">
                                <option value="dark" <?php echo $usuario['tema'] === 'dark' ? 'selected' : ''; ?>>🌙 Escuro</option>
                                <option value="light" <?php echo $usuario['tema'] === 'light' ? 'selected' : ''; ?>>☀️ Claro</option>
                                <option value="auto" <?php echo $usuario['tema'] === 'auto' ? 'selected' : ''; ?>>🔄 Automático</option>
                            </select>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notificacoes" 
                                   <?php echo $usuario['notificacoes_ativas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notificacoes">
                                Receber notificações
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Coluna Direita -->
            <div class="col-lg-8">
                <!-- Dados Pessoais -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-edit"></i> Dados Pessoais</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="dados-pessoais">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nome Completo</label>
                                    <input type="text" class="form-control" name="nome" 
                                           value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">E-mail</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="tel" class="form-control" name="telefone" 
                                       value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>" 
                                       placeholder="(11) 98765-4321">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tema Preferido</label>
                                <select class="form-select" name="tema">
                                    <option value="dark" <?php echo $usuario['tema'] === 'dark' ? 'selected' : ''; ?>>🌙 Escuro</option>
                                    <option value="light" <?php echo $usuario['tema'] === 'light' ? 'selected' : ''; ?>>☀️ Claro</option>
                                    <option value="auto" <?php echo $usuario['tema'] === 'auto' ? 'selected' : ''; ?>>🔄 Automático</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Alterações
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Alterar Senha -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-key"></i> Segurança</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="senha">

                            <div class="mb-3">
                                <label class="form-label">Senha Atual</label>
                                <input type="password" class="form-control" name="senha_atual" required>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nova Senha</label>
                                    <input type="password" class="form-control" name="senha_nova" required>
                                    <small class="form-text text-muted">Mínimo 8 caracteres</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar Senha</label>
                                    <input type="password" class="form-control" name="senha_confirma" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Alterar Senha
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Upload de foto
        document.getElementById('fotoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('foto', file);

            const btn = this;
            btn.disabled = true;

            fetch('/api/upload-foto.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('profilePhoto').src = '/' + data.data.caminho + '?t=' + Date.now();
                    alert('✅ Foto enviada com sucesso!');
                } else {
                    alert('❌ Erro: ' + data.message);
                }
            })
            .catch(err => alert('❌ Erro: ' + err.message))
            .finally(() => btn.disabled = false);
        });

        // Tema dinâmico
        document.getElementById('temaSeletor').addEventListener('change', function(e) {
            localStorage.setItem('tema', e.target.value);
            // Aqui você pode aplicar o tema dinamicamente
        });
    </script>
</body>
</html>
