<?php
/**
 * Admin - Gerenciar RGs
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

$authMiddleware = new AuthMiddleware();
$authMiddleware->requireAdmin();

$db = \App\Database\Database::getInstance()->getConnection();

// Filtros
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$busca = trim($_GET['busca'] ?? '');
$status = trim($_GET['status'] ?? '');

// Query base
$where = [];
$params = [];

if (!empty($busca)) {
    $where[] = "r.numero_rg LIKE ? OR r.descricao LIKE ?";
    $params = array_merge($params, ["%$busca%", "%$busca%"]);
}

if (!empty($status)) {
    $where[] = "r.status = ?";
    $params[] = $status;
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM rgs r $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// RGs
$stmt = $db->prepare("
    SELECT r.*, f.nome_completo
    FROM rgs r
    LEFT JOIN funcionarios f ON r.funcionario_id = f.id
    $where_sql
    ORDER BY r.criado_em DESC
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$rgs = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar RGs - Admin</title>
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

        .badge-pendente {
            background: #fff3cd;
            color: #856404;
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
                <i class="fas fa-id-card"></i> Gerenciar RGs
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
        <h1 class="mb-4"><i class="fas fa-id-card"></i> RGs Cadastrados</h1>

        <!-- Filtros -->
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="busca" class="form-control" placeholder="Buscar por número ou descrição..." 
                           value="<?php echo htmlspecialchars($busca); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Todos os status</option>
                        <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                        <option value="inativo" <?php echo $status === 'inativo' ? 'selected' : ''; ?>>Inativo</option>
                        <option value="pendente" <?php echo $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
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
                <h5>Total: <strong><?php echo $total; ?></strong> RGs</h5>
                <a href="/rg_form.php" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Novo RG
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número RG</th>
                            <th>Funcionário</th>
                            <th>Descrição</th>
                            <th>Status</th>
                            <th>Data Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rgs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Nenhum RG encontrado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rgs as $rg): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($rg['numero_rg']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($rg['nome_completo'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($rg['descricao'] ?? '', 0, 50)); ?>...</td>
                                    <td>
                                        <span class="badge badge-status badge-<?php echo $rg['status'] ?? 'pendente'; ?>">
                                            <?php echo ucfirst($rg['status'] ?? 'pendente'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($rg['criado_em'])); ?></td>
                                    <td>
                                        <a href="/rg_form.php?id=<?php echo $rg['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary btn-action">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <button class="btn btn-sm btn-outline-danger btn-action" 
                                                onclick="deletarRG(<?php echo $rg['id']; ?>)">
                                            <i class="fas fa-trash"></i>
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
                                <a class="page-link" href="?pagina=<?php echo $i; ?>&busca=<?php echo urlencode($busca); ?>&status=<?php echo $status; ?>">
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
        function deletarRG(id) {
            if (confirm('Tem certeza que deseja deletar este RG?')) {
                alert('Funcionalidade em desenvolvimento');
            }
        }
    </script>
</body>
</html>
