<?php
/**
 * Admin - Logs de Auditoria
 * Visualizar atividades dos usuários
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;

$authMiddleware = new AuthMiddleware();
$authMiddleware->requireAdmin();

$db = \App\Database\Database::getInstance()->getConnection();

// Filtros
$pagina = intval($_GET['pagina'] ?? 1);
$por_pagina = 30;
$offset = ($pagina - 1) * $por_pagina;

$usuario_filtro = intval($_GET['usuario'] ?? 0);
$acao_filtro = trim($_GET['acao'] ?? '');
$data_inicio = trim($_GET['data_inicio'] ?? '');
$data_fim = trim($_GET['data_fim'] ?? '');

// Query base
$where = [];
$params = [];

if ($usuario_filtro > 0) {
    $where[] = "al.usuario_id = ?";
    $params[] = $usuario_filtro;
}

if (!empty($acao_filtro)) {
    $where[] = "al.acao = ?";
    $params[] = $acao_filtro;
}

if (!empty($data_inicio)) {
    $where[] = "DATE(al.criado_em) >= ?";
    $params[] = $data_inicio;
}

if (!empty($data_fim)) {
    $where[] = "DATE(al.criado_em) <= ?";
    $params[] = $data_fim;
}

$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs_detailed al $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'] ?? 0;
$total_paginas = ceil($total / $por_pagina);

// Logs
$stmt = $db->prepare("
    SELECT al.*, u.username
    FROM audit_logs_detailed al
    LEFT JOIN usuarios u ON al.usuario_id = u.id
    $where_sql
    ORDER BY al.criado_em DESC
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Usuários (para filtro)
$stmt = $db->query("SELECT DISTINCT u.usuario_id, us.username FROM (
    SELECT usuario_id FROM audit_logs_detailed WHERE usuario_id IS NOT NULL
) u
LEFT JOIN usuarios us ON u.usuario_id = us.id
ORDER BY us.username");
$usuarios = [];
foreach ($stmt->fetchAll() as $user) {
    if ($user['usuario_id']) {
        $usuarios[$user['usuario_id']] = $user['username'] ?? 'Sistema';
    }
}

// Ações disponíveis
$stmt = $db->query("SELECT DISTINCT acao FROM audit_logs_detailed ORDER BY acao");
$acoes = array_map(fn($row) => $row['acao'], $stmt->fetchAll());

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - Admin</title>
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

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .log-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .log-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .log-usuario {
            font-weight: 600;
            color: #333;
        }

        .log-acao {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .log-acao.create {
            background: #d4edda;
            color: #155724;
        }

        .log-acao.update {
            background: #cce5ff;
            color: #004085;
        }

        .log-acao.delete {
            background: #f8d7da;
            color: #721c24;
        }

        .log-acao.login {
            background: #d1ecf1;
            color: #0c5460;
        }

        .log-acao.approve {
            background: #fff3cd;
            color: #856404;
        }

        .log-datetime {
            color: #666;
            font-size: 0.9rem;
        }

        .log-details {
            margin-top: 8px;
            font-size: 0.9rem;
            color: #555;
        }

        .log-ip {
            color: #999;
            font-size: 0.85rem;
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
                <i class="fas fa-history"></i> Logs de Auditoria
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
        <h1 class="mb-4"><i class="fas fa-history"></i> Logs de Auditoria</h1>

        <!-- Filtros -->
        <div class="filter-box">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="usuario" class="form-select">
                        <option value="">Todos os usuários</option>
                        <?php foreach ($usuarios as $id => $nome): ?>
                            <option value="<?php echo $id; ?>" <?php echo $usuario_filtro === $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($nome); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="acao" class="form-select">
                        <option value="">Todas as ações</option>
                        <?php foreach ($acoes as $acao): ?>
                            <option value="<?php echo $acao; ?>" <?php echo $acao_filtro === $acao ? 'selected' : ''; ?>>
                                <?php echo ucfirst($acao); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="data_inicio" class="form-control" 
                           value="<?php echo $data_inicio; ?>" placeholder="Data início">
                </div>
                <div class="col-md-2">
                    <input type="date" name="data_fim" class="form-control" 
                           value="<?php echo $data_fim; ?>" placeholder="Data fim">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle"></i> Total de registros: <strong><?php echo $total; ?></strong>
        </div>

        <!-- Logs -->
        <div>
            <?php if (empty($logs)): ?>
                <div class="alert alert-warning">
                    Nenhum log encontrado com os filtros selecionados.
                </div>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <div class="log-header">
                            <div>
                                <div class="log-usuario">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($log['nome'] ?? $log['username'] ?? 'Sistema'); ?>
                                </div>
                                <div class="log-datetime">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['criado_em'])); ?>
                                </div>
                            </div>
                            <span class="log-acao <?php echo strtolower($log['acao']); ?>">
                                <?php echo ucfirst($log['acao']); ?>
                            </span>
                        </div>

                        <div class="log-details">
                            <strong><?php echo htmlspecialchars($log['tabela'] ?? 'N/A'); ?></strong>
                            - <?php echo htmlspecialchars($log['descricao'] ?? ''); ?>
                            
                            <?php if ($log['ip_address']): ?>
                                <div class="log-ip">
                                    IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $i; ?>&usuario=<?php echo $usuario_filtro; ?>&acao=<?php echo urlencode($acao_filtro); ?>&data_inicio=<?php echo $data_inicio; ?>&data_fim=<?php echo $data_fim; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
