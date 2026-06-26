<?php
/**
 * Admin - Gerenciar Funcionários
 * CRUD completo de funcionários com filtros
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

AuthMiddleware::requireAdmin();

$db = \App\Database\Database::getInstance();

// Filtros e busca
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$busca = trim($_GET['busca'] ?? '');
$status = trim($_GET['status'] ?? '');
$departamento = intval($_GET['departamento'] ?? 0);

// Query base
$where = [];
$params = [];

if (!empty($busca)) {
    $where[] = "f.nome_completo LIKE ? OR f.email LIKE ? OR f.cpf LIKE ?";
    $params = array_merge($params, ["%$busca%", "%$busca%", "%$busca%"]);
}

if (!empty($status)) {
    $where[] = "f.status = ?";
    $params[] = $status;
}

if ($departamento > 0) {
    $where[] = "f.departamento_id = ?";
    $params[] = $departamento;
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total de registros
$stmt = $db->prepare("SELECT COUNT(*) as total FROM funcionarios f $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// Funcionários
$stmt = $db->prepare("
    SELECT f.*, d.nome as departamento_nome, c.nome as cargo_nome
    FROM funcionarios f
    LEFT JOIN departamentos d ON f.departamento_id = d.id
    LEFT JOIN cargos c ON f.cargo_id = c.id
    $where_sql
    ORDER BY f.criado_em DESC
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$funcionarios = $stmt->fetchAll();

// Departamentos (para filtro)
$stmt = $db->query("SELECT * FROM departamentos ORDER BY nome");
$departamentos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Funcionários - Admin</title>
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

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 10px;
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

        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-ativo {
            background: #d4edda;
            color: #155724;
        }

        .badge-inativo {
            background: #f8d7da;
            color: #721c24;
        }

        .btn-action {
            padding: 5px 10px;
            font-size: 0.85rem;
            margin: 0 2px;
        }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .pagination {
            margin-top: 20px;
        }

        .pagination .page-link {
            color: #667eea;
        }

        .pagination .page-link:hover {
            color: #764ba2;
            background: #f5f6fa;
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
                <i class="fas fa-users"></i> Gerenciar Funcionários
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
        <h1 class="mb-4"><i class="fas fa-users"></i> Funcionários</h1>

        <!-- Filtros -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, email ou CPF..." 
                           value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="departamento" class="form-select">
                        <option value="">Todos departamentos</option>
                        <?php foreach ($departamentos as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $departamento === $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabela -->
        <div class="table-wrapper">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5>Total: <strong><?php echo $total; ?></strong> funcionários</h5>
                <a href="/admin/novo-funcionario.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Novo Funcionário
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>CPF</th>
                            <th>Departamento</th>
                            <th>Cargo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($funcionarios)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Nenhum funcionário encontrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($funcionarios as $func): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($func['nome_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($func['email']); ?></td>
                                    <td><?php echo htmlspecialchars($func['cpf'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($func['departamento_nome'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($func['cargo_nome'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-status badge-<?php echo $func['status'] === 'ativo' ? 'ativo' : 'inativo'; ?>">
                                            <?php echo ucfirst($func['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/admin/editar-funcionario.php?id=<?php echo $func['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger btn-action" 
                                                onclick="deletarFuncionario(<?php echo $func['id']; ?>)">
                                            <i class="fas fa-trash"></i> Deletar
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
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo $status; ?>&departamento=<?php echo $departamento; ?>">
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
        function deletarFuncionario(id) {
            if (confirm('Tem certeza que deseja deletar este funcionário?')) {
                // Implementar delete via API
                alert('Funcionalidade em desenvolvimento');
            }
        }
    </script>
</body>
</html>
