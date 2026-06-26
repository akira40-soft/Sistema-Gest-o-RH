<?php
/**
 * Admin - Gerenciar Usuários
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

AuthMiddleware::requireAdmin();

$db = \App\Database\Database::getInstance();

// Filtros
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$busca = trim($_GET['busca'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');

// Query base
$where = [];
$params = [];

if (!empty($busca)) {
    $where[] = "u.nome LIKE ? OR u.email LIKE ? OR u.username LIKE ?";
    $params = array_merge($params, ["%$busca%", "%$busca%", "%$busca%"]);
}

if (!empty($tipo)) {
    $where[] = "u.tipo_acesso = ?";
    $params[] = $tipo;
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios u $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// Usuários
$stmt = $db->prepare("
    SELECT u.*
    FROM usuarios u
    $where_sql
    ORDER BY u.criado_em DESC
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Admin</title>
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

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table-wrapper {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table thead th {
            background: #f5f6fa;
            border: none;
            font-weight: 600;
            color: #555;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge-role {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-admin {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-gestor_rh {
            background: #d4edda;
            color: #155724;
        }

        .badge-funcionario {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-ativo {
            background: #d4edda;
            color: #155724;
        }

        .badge-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .btn-action {
            padding: 5px 10px;
            font-size: 0.85rem;
            margin: 0 2px;
        }

        .pagination .page-link {
            color: #667eea;
        }

        .pagination .page-item.active .page-link {
            background: #667eea;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard_admin_advanced.php">
                <i class="fas fa-user-shield"></i> Gerenciar Usuários
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
        <h1 class="mb-4"><i class="fas fa-user-shield"></i> Usuários do Sistema</h1>

        <!-- Filtros -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, email ou usuário..." 
                           value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select">
                        <option value="">Todos os tipos</option>
                        <option value="admin" <?php echo $tipo === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="gestor_rh" <?php echo $tipo === 'gestor_rh' ? 'selected' : ''; ?>>Gestor RH</option>
                        <option value="funcionario" <?php echo $tipo === 'funcionario' ? 'selected' : ''; ?>>Funcionário</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="table-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Total: <strong><?php echo $total; ?></strong> usuários</h5>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Tipo</th>
                            <th>Ativo</th>
                            <th>Último Acesso</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Nenhum usuário encontrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['nome'], 0, 1)); ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge badge-role badge-<?php echo $user['tipo_acesso']; ?>">
                                            <?php 
                                            $tipos = [
                                                'admin' => 'Admin',
                                                'gestor_rh' => 'Gestor RH',
                                                'funcionario' => 'Funcionário'
                                            ];
                                            echo $tipos[$user['tipo_acesso']] ?? $user['tipo_acesso'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-role badge-<?php echo $user['ativo'] ? 'ativo' : 'inativo'; ?>">
                                            <?php echo $user['ativo'] ? 'Sim' : 'Não'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($user['ultimo_login']) {
                                            echo date('d/m/Y H:i', strtotime($user['ultimo_login']));
                                        } else {
                                            echo '<span class="text-muted">Nunca</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-warning btn-action" 
                                                onclick="resetarSenha(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-key"></i> Reset
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-action" 
                                                onclick="desativarUsuario(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-ban"></i> Desativar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>&tipo=<?php echo $tipo; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetarSenha(id) {
            if (confirm('Deseja resetar a senha deste usuário?')) {
                alert('Funcionalidade em desenvolvimento - Email será enviado com nova senha temporária');
            }
        }

        function desativarUsuario(id) {
            if (confirm('Deseja desativar este usuário?')) {
                alert('Funcionalidade em desenvolvimento');
            }
        }
    </script>
</body>
</html>
