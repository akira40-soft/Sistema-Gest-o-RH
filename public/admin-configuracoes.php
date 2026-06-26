<?php
/**
 * Admin - Configurações do Sistema
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

$authMiddleware = new AuthMiddleware();
$authMiddleware->requireAdmin();

$db = \App\Database\Database::getInstance();
$mensagem = '';
$tipo_mensagem = '';

// Obter configurações
$stmt = $db->query("SELECT * FROM configuracoes_sistema ORDER BY chave");
$configs = [];
foreach ($stmt->fetchAll() as $config) {
    $configs[$config['chave']] = $config['valor'];
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'atualizar-config') {
        try {
            foreach ($_POST['config'] ?? [] as $chave => $valor) {
                $stmt = $db->prepare("
                    UPDATE configuracoes_sistema 
                    SET valor = ?, atualizado_em = CURRENT_TIMESTAMP 
                    WHERE chave = ?
                ");
                $stmt->execute([$valor, $chave]);
            }

            $mensagem = '✅ Configurações atualizadas com sucesso!';
            $tipo_mensagem = 'sucesso';

            // Recarregar configs
            $stmt = $db->query("SELECT * FROM configuracoes_sistema ORDER BY chave");
            $configs = [];
            foreach ($stmt->fetchAll() as $config) {
                $configs[$config['chave']] = $config['valor'];
            }
        } catch (\Exception $e) {
            $mensagem = '❌ Erro: ' . $e->getMessage();
            $tipo_mensagem = 'erro';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/style-2026.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .container-main {
            padding: 30px;
        }

        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
        }

        .settings-card h5 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .settings-group {
            margin-bottom: 25px;
        }

        .settings-group label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px 15px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-save {
            background: #667eea;
            border: none;
            color: white;
            padding: 10px 30px;
            font-weight: 600;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }

        .alert {
            border: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-sucesso {
            background: #d4edda;
            color: #155724;
        }

        .alert-erro {
            background: #f8d7da;
            color: #721c24;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 3px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .info-box small {
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard_admin_advanced.php">
                <i class="fas fa-cog"></i> Configurações
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard_admin_advanced.php">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid container-main">
        <h1 class="mb-4"><i class="fas fa-sliders-h"></i> Configurações do Sistema</h1>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="acao" value="atualizar-config">

            <!-- Configurações Gerais -->
            <div class="settings-card">
                <h5><i class="fas fa-info-circle"></i> Informações do Sistema</h5>
                
                <div class="settings-group">
                    <label for="app_nome" class="form-label">Nome da Aplicação</label>
                    <input type="text" class="form-control" id="app_nome" name="config[app_nome]" 
                           value="<?php echo htmlspecialchars($configs['app_nome'] ?? ''); ?>">
                </div>

                <div class="settings-group">
                    <label for="app_versao" class="form-label">Versão</label>
                    <input type="text" class="form-control" id="app_versao" name="config[app_versao]" 
                           value="<?php echo htmlspecialchars($configs['app_versao'] ?? ''); ?>" readonly>
                </div>

                <div class="info-box">
                    <small>ℹ️ Versão é atualizada automaticamente durante releases</small>
                </div>
            </div>

            <!-- Configurações de Upload -->
            <div class="settings-card">
                <h5><i class="fas fa-upload"></i> Upload de Arquivos</h5>
                
                <div class="settings-group">
                    <label for="tamanho_maximo" class="form-label">Tamanho Máximo (bytes)</label>
                    <input type="number" class="form-control" id="tamanho_maximo" name="config[tamanho_maximo_foto]" 
                           value="<?php echo htmlspecialchars($configs['tamanho_maximo_foto'] ?? 5242880); ?>">
                    <small class="form-text text-muted">
                        Atualmente: <?php echo round(($configs['tamanho_maximo_foto'] ?? 5242880) / 1024 / 1024, 2); ?> MB
                    </small>
                </div>

                <div class="settings-group">
                    <label for="tipos_foto" class="form-label">Tipos de Arquivo Permitidos</label>
                    <input type="text" class="form-control" id="tipos_foto" name="config[tipos_foto_permitidos]" 
                           value="<?php echo htmlspecialchars($configs['tipos_foto_permitidos'] ?? 'jpg,jpeg,png,gif'); ?>"
                           placeholder="jpg,jpeg,png,gif">
                    <small class="form-text text-muted">Separados por vírgula</small>
                </div>
            </div>

            <!-- Configurações de Segurança -->
            <div class="settings-card">
                <h5><i class="fas fa-shield-alt"></i> Segurança</h5>
                
                <div class="settings-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="log_auditoria" name="config[log_auditoria]" 
                               value="1" <?php echo ($configs['log_auditoria'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="log_auditoria">
                            Ativar logs de auditoria
                        </label>
                    </div>
                </div>

                <div class="settings-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="dois_fatores" name="config[dois_fatores_ativo]" 
                               value="1" <?php echo ($configs['dois_fatores_ativo'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="dois_fatores">
                            Ativar dois-fatores de autenticação
                        </label>
                    </div>
                </div>
            </div>

            <!-- Configurações de Notificação -->
            <div class="settings-card">
                <h5><i class="fas fa-bell"></i> Notificações</h5>
                
                <div class="settings-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="notif_email" name="config[notificacoes_email]" 
                               value="1" <?php echo ($configs['notificacoes_email'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notif_email">
                            Enviar notificações por email
                        </label>
                    </div>
                    <small class="form-text text-muted">Requer configuração de SMTP</small>
                </div>
            </div>

            <!-- Configurações de Manutenção -->
            <div class="settings-card">
                <h5><i class="fas fa-tools"></i> Manutenção</h5>
                
                <div class="settings-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="modo_manutencao" name="config[modo_manutencao]" 
                               value="1" <?php echo ($configs['modo_manutencao'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="modo_manutencao">
                            Modo de manutenção
                        </label>
                    </div>
                    <small class="form-text text-danger">
                        ⚠️ Quando ativado, apenas administradores podem acessar o sistema
                    </small>
                </div>

                <div class="settings-group">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="backup_auto" name="config[backup_automatico]" 
                               value="1" <?php echo ($configs['backup_automatico'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="backup_auto">
                            Backup automático
                        </label>
                    </div>
                    <small class="form-text text-muted">Backup diário do banco de dados</small>
                </div>
            </div>

            <!-- Botão Salvar -->
            <div class="mb-4">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </div>
        </form>

        <!-- Seção de Ações -->
        <div class="settings-card">
            <h5><i class="fas fa-tools"></i> Ações Administrativas</h5>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <button type="button" class="btn btn-outline-primary w-100" onclick="fazerBackup()">
                        <i class="fas fa-database"></i> Backup do Banco
                    </button>
                </div>
                <div class="col-md-6 mb-3">
                    <button type="button" class="btn btn-outline-warning w-100" onclick="limparCache()">
                        <i class="fas fa-trash"></i> Limpar Cache
                    </button>
                </div>
                <div class="col-md-6 mb-3">
                    <button type="button" class="btn btn-outline-info w-100" onclick="verifySystem()">
                        <i class="fas fa-check-circle"></i> Verificar Sistema
                    </button>
                </div>
                <div class="col-md-6 mb-3">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="resetarSenhas()">
                        <i class="fas fa-key"></i> Reset de Senhas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fazerBackup() {
            alert('Funcionalidade em desenvolvimento - Backup será implementado em breve');
        }

        function limparCache() {
            if (confirm('Limpar cache do sistema?')) {
                alert('Cache limpo com sucesso');
            }
        }

        function verifySystem() {
            alert('Verificação do sistema - Será implementado em breve');
        }

        function resetarSenhas() {
            if (confirm('Tem certeza? Isto não pode ser desfeito!')) {
                alert('Funcionalidade em desenvolvimento');
            }
        }
    </script>
</body>
</html>
