<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

if ($auth->getUserRole() === 'funcionario') {
    header("Location: portal.php");
    exit;
}

$user = [
    'username' => $auth->getUsername(),
    'role' => $auth->getUserRole(),
    'id' => $auth->getUserId()
];

$db = Database::getInstance()->getConnection();
$isMysql = strpos($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false;

// Filtros
$busca = trim($_GET['busca'] ?? '');
$dept_id = $_GET['departamento_id'] ?? '';
$status = $_GET['status'] ?? '';

try {
    $sql = "SELECT f.*, d.nome as departamento_nome, c.nome as cargo_nome
            FROM funcionarios f
            LEFT JOIN departamentos d ON f.departamento_id = d.id
            LEFT JOIN cargos c ON f.cargo_id = c.id
            WHERE 1=1";
    $params = [];

    if (!empty($busca)) {
        $sql .= " AND (f.nome_completo LIKE :busca1 OR f.cpf LIKE :busca2 OR f.email LIKE :busca3)";
        $params[':busca1'] = "%$busca%";
        $params[':busca2'] = "%$busca%";
        $params[':busca3'] = "%$busca%";
    }
    if (!empty($dept_id)) {
        $sql .= " AND f.departamento_id = :dept";
        $params[':dept'] = $dept_id;
    }
    if (!empty($status)) {
        $sql .= " AND f.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY f.nome_completo ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll();

    $departamentos = $db->query("SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    $funcionarios = [];
    $departamentos = [];
    error_log("Erro ao listar funcionários: " . $e->getMessage());
}

$pageTitle = 'Funcionários';
$pageSubtitle = count($funcionarios) . ' colaboradores encontrados';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funcionários | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item">RH & Pessoal</li>
                        <li class="breadcrumb-item active">Funcionários</li>
                    </ol>
                </nav>

                <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Gestão de Funcionários</h2>
                        <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Listagem completa do efetivo da farmácia</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="departamentos.php" class="btn btn-secondary">
                            <i class="bi bi-diagram-3"></i> Departamentos
                        </a>
                        <a href="admissao.php" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Nova Admissão
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <form class="filter-bar" method="GET">
                    <div style="flex: 2; min-width: 220px;">
                        <input type="text" name="busca" class="form-control" placeholder="🔍 Pesquisar por nome, CPF ou e-mail..." value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    <div style="flex: 1; min-width: 160px;">
                        <select name="departamento_id" class="form-select">
                            <option value="">Todos os Departamentos</option>
                            <?php foreach ($departamentos as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $dept_id == $d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="status" class="form-select">
                            <option value="">Todos os Status</option>
                            <option value="ativo" <?php echo $status === 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="ferias" <?php echo $status === 'ferias' ? 'selected' : ''; ?>>Férias</option>
                            <option value="afastado" <?php echo $status === 'afastado' ? 'selected' : ''; ?>>Afastado</option>
                            <option value="demitido" <?php echo $status === 'demitido' ? 'selected' : ''; ?>>Demitido</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <?php if (!empty($busca) || !empty($dept_id) || !empty($status)): ?>
                        <a href="funcionarios.php" class="btn btn-ghost" title="Limpar filtros">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Tabela -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Departamento</th>
                                    <th>Cargo</th>
                                    <th>Admissão</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($funcionarios)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="bi bi-people"></i>
                                                <h4>Nenhum funcionário encontrado</h4>
                                                <p>Ajuste os filtros ou adicione um novo colaborador.</p>
                                                <a href="admissao.php" class="btn btn-primary">
                                                    <i class="bi bi-person-plus"></i> Admissão
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($funcionarios as $f): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm">
                                                        <?php echo strtoupper(substr($f['nome_completo'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-cell-info">
                                                        <strong><?php echo htmlspecialchars($f['nome_completo']); ?></strong>
                                                        <small><?php echo htmlspecialchars($f['email'] ?? $f['cpf'] ?? '—'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($f['departamento_nome'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($f['cargo_nome'] ?? '—'); ?></td>
                                            <td>
                                                <?php if (!empty($f['data_admissao'])): ?>
                                                    <span style="font-family: var(--font-mono); font-size: 0.8rem;">
                                                        <?php echo date('d/m/Y', strtotime($f['data_admissao'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-faint);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = match($f['status'] ?? '') {
                                                    'ativo' => 'badge-success',
                                                    'ferias' => 'badge-warning',
                                                    'afastado' => 'badge-info',
                                                    'demitido' => 'badge-danger',
                                                    default => 'badge-neutral'
                                                };
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <span class="badge-dot"></span>
                                                    <?php echo htmlspecialchars(ucfirst($f['status'] ?? '—')); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="perfil.php?id=<?php echo $f['id']; ?>" class="btn btn-icon btn-secondary" title="Ver perfil">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="editar_funcionario.php?id=<?php echo $f['id']; ?>" class="btn btn-icon btn-secondary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-icon btn-secondary" title="Mais opções" onclick="App.toast('Menu de ações em desenvolvimento', 'info')">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- content-body -->
        </div><!-- main-area -->
    </div><!-- app-wrapper -->

    <script src="js/app-2026.js"></script>
</body>
</html>
