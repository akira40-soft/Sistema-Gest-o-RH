<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\AuthMiddleware;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isAdmin()) { header('Location: acesso_negado.php'); exit; }

$db = Database::getInstance();
$action = $_GET['action'] ?? null;
$id = (int)($_GET['id'] ?? 0);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action'] ?? null;

    if ($action_type === 'create' || $action_type === 'update') {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'tipo_acesso' => $_POST['tipo_acesso'] ?? 'funcionario',
            'ativo' => isset($_POST['ativo']) ? 1 : 0
        ];

        if ($action_type === 'create') {
            if (empty($data['username']) || empty($_POST['password'])) {
                $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">Usuário e senha são obrigatórios.</div></div>';
            } else {
                try {
                    $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->insert('usuarios', [
                        'username' => $data['username'],
                        'password_hash' => $hash,
                        'tipo_acesso' => $data['tipo_acesso'],
                        'ativo' => $data['ativo']
                    ]);
                    $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Usuário criado!</strong></div></div>';
                    $action = null;
                } catch (\Exception $e) {
                    $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">Erro: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
                }
            }
        } elseif ($action_type === 'update') {
            try {
                $update_data = ['tipo_acesso' => $data['tipo_acesso'], 'ativo' => $data['ativo']];
                if (!empty($_POST['password'])) {
                    $update_data['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                }
                $db->update('usuarios', $update_data, ['id' => $id]);
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Usuário atualizado!</strong></div></div>';
                $action = null;
            } catch (\Exception $e) {
                $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">Erro: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
            }
        }
    } elseif ($action_type === 'delete') {
        try {
            $db->update('usuarios', ['ativo' => 0], ['id' => $id]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Usuário desativado!</strong></div></div>';
        } catch (\Exception $e) {
            $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content">Erro ao desativar.</div></div>';
        }
    }
}

try {
    $usuarios = $db->select('usuarios', [], false);
} catch (\Exception $e) {
    $usuarios = [];
}

$user_data = null;
if ($action === 'edit' && $id > 0) {
    $user_data = $db->select('usuarios', ['id' => $id], true);
}

$pageTitle = 'Gerenciar Usuários';
$pageSubtitle = 'Criação, edição e controle de acessos';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | SG Farmácia Gingongo</title>
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
            <?php include 'includes/topbar.php'; ?>
            <div class="content-body">

                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item active">Usuários</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                    <div>
                        <div class="card">
                            <div class="card-header">
                                <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">
                                    <i class="bi bi-person-plus"></i> <?php echo $user_data ? 'Editar' : 'Novo'; ?> Usuário
                                </h3>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="<?php echo $user_data ? 'update' : 'create'; ?>">
                                    <input type="hidden" name="id" value="<?php echo $user_data ? $user_data['id'] : ''; ?>">

                                    <div class="form-group">
                                        <label class="form-label">Usuário *</label>
                                        <input type="text" name="username" class="form-control" required
                                               value="<?php echo $user_data ? htmlspecialchars($user_data['username']) : ''; ?>"
                                               <?php echo $user_data ? 'readonly' : ''; ?>>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Senha <?php echo !$user_data ? '*' : ''; ?></label>
                                        <input type="password" name="password" class="form-control"
                                               <?php echo !$user_data ? 'required' : ''; ?>
                                               placeholder="Deixe em branco para não alterar">
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Tipo de Acesso</label>
                                        <select name="tipo_acesso" class="form-select">
                                            <option value="funcionario" <?php echo (!$user_data || $user_data['tipo_acesso'] == 'funcionario') ? 'selected' : ''; ?>>Funcionário</option>
                                            <option value="lider_farmaceutico" <?php echo ($user_data && $user_data['tipo_acesso'] == 'lider_farmaceutico') ? 'selected' : ''; ?>>Líder Farmacêutico</option>
                                            <option value="funcionario_rh" <?php echo ($user_data && $user_data['tipo_acesso'] == 'funcionario_rh') ? 'selected' : ''; ?>>Funcionário RH</option>
                                            <option value="gestor_rh" <?php echo ($user_data && $user_data['tipo_acesso'] == 'gestor_rh') ? 'selected' : ''; ?>>Gestor RH</option>
                                            <option value="admin" <?php echo ($user_data && $user_data['tipo_acesso'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-check">
                                            <input type="checkbox" name="ativo" class="form-check-input"
                                                   <?php echo (!$user_data || $user_data['ativo']) ? 'checked' : ''; ?>>
                                            <span>Usuário Ativo</span>
                                        </label>
                                    </div>

                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                                            <i class="bi bi-check-circle"></i> <?php echo $user_data ? 'Atualizar' : 'Criar'; ?>
                                        </button>
                                        <?php if ($user_data): ?>
                                            <a href="usuarios_admin.php" class="btn btn-ghost">Cancelar</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card">
                            <div class="card-header">
                                <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">
                                    <i class="bi bi-people"></i> Usuários Cadastrados
                                </h3>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Tipo de Acesso</th>
                                            <th>Status</th>
                                            <th style="text-align: right;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($usuarios)): ?>
                                            <tr><td colspan="4">
                                                <div class="empty-state">
                                                    <i class="bi bi-people"></i>
                                                    <h4>Nenhum usuário</h4>
                                                </div>
                                            </td></tr>
                                        <?php else: foreach ($usuarios as $u): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar-sm"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
                                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $u['tipo_acesso'])); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $u['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                        <span class="badge-dot"></span>
                                                        <?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                        <a href="usuarios_admin.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-icon btn-secondary" title="Editar">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if ($u['id'] != $auth->getUserId()): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit" class="btn btn-icon btn-secondary" title="Desativar" onclick="return confirm('Desativar este usuário?')">
                                                                    <i class="bi bi-trash" style="color: var(--danger);"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
