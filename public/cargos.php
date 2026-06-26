<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isAdmin()) { header('Location: acesso_negado.php'); exit; }

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $salario_base = (float)str_replace(',', '.', $_POST['salario_base'] ?? '0');
            $nivel = $_POST['nivel_hierarquico'] ?? 'operacional';
            $requer_cert = isset($_POST['requer_certificacao']) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome)) throw new Exception('Nome é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO cargos (nome, descricao, salario_base, nivel_hierarquico, requer_certificacao, ativo) VALUES (:n, :d, :s, :nh, :rc, :a)");
                $stmt->execute([':n' => $nome, ':d' => $descricao, ':s' => $salario_base, ':nh' => $nivel, ':rc' => $requer_cert, ':a' => $ativo]);
                $newId = $db->lastInsertId();
                Audit::create('cargo', $newId, "Criou cargo: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Cargo criado!</strong></div></div>';
                $action = 'list';
            } else {
                $stmt = $db->prepare("UPDATE cargos SET nome=:n, descricao=:d, salario_base=:s, nivel_hierarquico=:nh, requer_certificacao=:rc, ativo=:a WHERE id=:id");
                $stmt->execute([':n' => $nome, ':d' => $descricao, ':s' => $salario_base, ':nh' => $nivel, ':rc' => $requer_cert, ':a' => $ativo, ':id' => $id]);
                Audit::update('cargo', $id, "Atualizou cargo: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Cargo atualizado!</strong></div></div>';
                $action = 'list';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $check = $db->prepare("SELECT COUNT(*) FROM funcionarios WHERE cargo_id = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Não é possível eliminar: existem funcionários com este cargo.');
            }
            $db->prepare("DELETE FROM cargos WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('cargo', $id, "Eliminou cargo #$id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Cargo eliminado!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

if ($action === 'list') {
    $busca = trim($_GET['busca'] ?? '');
    $nivel = $_GET['nivel'] ?? '';
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM funcionarios f WHERE f.cargo_id = c.id AND f.status = 'ativo') as total
            FROM cargos c WHERE 1=1";
    $params = [];
    if (!empty($busca)) {
        $sql .= " AND (c.nome LIKE :b OR c.descricao LIKE :b)";
        $params[':b'] = "%$busca%";
    }
    if (!empty($nivel)) {
        $sql .= " AND c.nivel_hierarquico = :n";
        $params[':n'] = $nivel;
    }
    $sql .= " ORDER BY c.nivel_hierarquico, c.nome";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cargos = $stmt->fetchAll();
}

$pageTitle = 'Cargos';
$pageSubtitle = 'Funções, níveis hierárquicos e salários base';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargos | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Cargos</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Cargos & Funções</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($cargos); ?> cargos definidos</p>
                        </div>
                        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Cargo</a>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="flex: 2; min-width: 220px;">
                            <input type="text" name="busca" class="form-control" placeholder="🔍 Pesquisar por nome ou descrição..." value="<?php echo htmlspecialchars($busca); ?>">
                        </div>
                        <div style="flex: 1; min-width: 160px;">
                            <select name="nivel" class="form-select">
                                <option value="">Todos os níveis</option>
                                <option value="operacional" <?php echo $nivel === 'operacional' ? 'selected' : ''; ?>>Operacional</option>
                                <option value="tecnico" <?php echo $nivel === 'tecnico' ? 'selected' : ''; ?>>Técnico</option>
                                <option value="gerencial" <?php echo $nivel === 'gerencial' ? 'selected' : ''; ?>>Gerencial</option>
                                <option value="diretivo" <?php echo $nivel === 'diretivo' ? 'selected' : ''; ?>>Diretivo</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                    </form>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Cargo</th>
                                        <th>Nível</th>
                                        <th>Salário Base</th>
                                        <th>Certificação</th>
                                        <th>Efetivo</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cargos)): ?>
                                        <tr><td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-briefcase"></i>
                                                <h4>Nenhum cargo</h4>
                                                <p>Crie cargos para classificar os funcionários.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($cargos as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-50); color: var(--primary);">
                                                        <i class="bi bi-briefcase"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($c['nome']); ?></strong>
                                                        <?php if ($c['descricao']): ?>
                                                            <small style="color: var(--text-muted); display: block; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($c['descricao']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo ['operacional' => 'neutral', 'tecnico' => 'info', 'gerencial' => 'primary', 'diretivo' => 'warning'][$c['nivel_hierarquico']] ?? 'neutral'; ?>">
                                                    <?php echo ucfirst($c['nivel_hierarquico']); ?>
                                                </span>
                                            </td>
                                            <td style="font-family: var(--font-mono); font-weight: 600;">
                                                <?php echo kz($c['salario_base']); ?>
                                            </td>
                                            <td>
                                                <?php if ($c['requer_certificacao']): ?>
                                                    <span class="badge badge-warning"><i class="bi bi-capsule"></i> Requer</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-faint);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (int)$c['total']; ?></td>
                                            <td>
                                                <span class="badge <?php echo $c['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <span class="badge-dot"></span>
                                                    <?php echo $c['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar este cargo?')) location.href='?action=delete&id=<?php echo $c['id']; ?>'">
                                                        <i class="bi bi-trash" style="color: var(--danger);"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action === 'create' || $action === 'edit'):
                    $cargo = ['id' => 0, 'nome' => '', 'descricao' => '', 'salario_base' => 0, 'nivel_hierarquico' => 'operacional', 'requer_certificacao' => 0, 'ativo' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM cargos WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $cargo = $stmt->fetch() ?: $cargo;
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Cargo</h2>
                        </div>
                        <a href="cargos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 720px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nome <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($cargo['nome']); ?>" placeholder="Ex: Farmacêutico Adjunto">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3"><?php echo htmlspecialchars($cargo['descricao']); ?></textarea>
                            </div>
                            <div class="grid-2" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Nível Hierárquico</label>
                                    <select name="nivel_hierarquico" class="form-select">
                                        <option value="operacional" <?php echo $cargo['nivel_hierarquico'] === 'operacional' ? 'selected' : ''; ?>>Operacional</option>
                                        <option value="tecnico" <?php echo $cargo['nivel_hierarquico'] === 'tecnico' ? 'selected' : ''; ?>>Técnico</option>
                                        <option value="gerencial" <?php echo $cargo['nivel_hierarquico'] === 'gerencial' ? 'selected' : ''; ?>>Gerencial</option>
                                        <option value="diretivo" <?php echo $cargo['nivel_hierarquico'] === 'diretivo' ? 'selected' : ''; ?>>Diretivo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Salário Base (Kz)</label>
                                    <input type="number" name="salario_base" class="form-control" step="0.01" min="0" value="<?php echo htmlspecialchars($cargo['salario_base']); ?>" placeholder="0.00">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="requer_certificacao" class="form-check-input" <?php echo $cargo['requer_certificacao'] ? 'checked' : ''; ?>>
                                    <span><i class="bi bi-capsule"></i> Requer certificação farmacêutica</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="ativo" class="form-check-input" <?php echo $cargo['ativo'] ? 'checked' : ''; ?>>
                                    <span>Cargo ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="cargos.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
