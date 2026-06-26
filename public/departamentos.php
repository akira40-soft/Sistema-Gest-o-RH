<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

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
            $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome)) throw new Exception('Nome é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO departamentos (nome, descricao, responsavel_id, ativo) VALUES (:n, :d, :r, :a)");
                $stmt->execute([':n' => $nome, ':d' => $descricao, ':r' => $responsavel_id, ':a' => $ativo]);
                $newId = $db->lastInsertId();
                Audit::create('departamento', $newId, "Criou departamento: $nome");
                Notification::sendToRole('gestor_rh', 'Novo Departamento', "Departamento '$nome' criado", 'success');
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Departamento criado!</strong></div></div>';
                $action = 'list';
            } else {
                $stmt = $db->prepare("UPDATE departamentos SET nome=:n, descricao=:d, responsavel_id=:r, ativo=:a WHERE id=:id");
                $stmt->execute([':n' => $nome, ':d' => $descricao, ':r' => $responsavel_id, ':a' => $ativo, ':id' => $id]);
                Audit::update('departamento', $id, "Atualizou departamento: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Departamento atualizado!</strong></div></div>';
                $action = 'list';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $check = $db->prepare("SELECT COUNT(*) FROM funcionarios WHERE departamento_id = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Não é possível eliminar: existem funcionários vinculados a este departamento.');
            }
            $stmt = $db->prepare("DELETE FROM departamentos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            Audit::delete('departamento', $id, "Eliminou departamento #$id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Departamento eliminado!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

if ($action === 'list') {
    $busca = trim($_GET['busca'] ?? '');
    $sql = "SELECT d.*, 
                (SELECT COUNT(*) FROM funcionarios f WHERE f.departamento_id = d.id AND f.status = 'ativo') as total_funcionarios,
                (SELECT nome_completo FROM funcionarios WHERE id = d.responsavel_id) as responsavel_nome
            FROM departamentos d WHERE 1=1";
    $params = [];
    if (!empty($busca)) {
        $sql .= " AND (d.nome LIKE :b OR d.descricao LIKE :b)";
        $params[':b'] = "%$busca%";
    }
    $sql .= " ORDER BY d.nome";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $departamentos = $stmt->fetchAll();
}

$pageTitle = 'Departamentos';
$pageSubtitle = 'Gestão de áreas e equipas da farmácia';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departamentos | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Departamentos</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Departamentos</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($departamentos); ?> áreas cadastradas</p>
                        </div>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Novo Departamento
                        </a>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="flex: 1; min-width: 240px;">
                            <input type="text" name="busca" class="form-control" placeholder="🔍 Pesquisar por nome ou descrição..." value="<?php echo htmlspecialchars($busca); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                        <?php if (!empty($busca)): ?>
                            <a href="departamentos.php" class="btn btn-ghost" title="Limpar"><i class="bi bi-x-circle"></i></a>
                        <?php endif; ?>
                    </form>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Departamento</th>
                                        <th>Descrição</th>
                                        <th>Responsável</th>
                                        <th>Efetivo</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($departamentos)): ?>
                                        <tr><td colspan="6">
                                            <div class="empty-state">
                                                <i class="bi bi-diagram-3"></i>
                                                <h4>Nenhum departamento</h4>
                                                <p>Crie o primeiro departamento para organizar a equipa.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($departamentos as $d): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-50); color: var(--primary);">
                                                        <i class="bi bi-diagram-3"></i>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($d['nome']); ?></strong>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($d['descricao'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($d['responsavel_nome'] ?? '—'); ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <span class="badge-dot"></span>
                                                    <?php echo (int)$d['total_funcionarios']; ?> pessoas
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $d['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <span class="badge-dot"></span>
                                                    <?php echo $d['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=edit&id=<?php echo $d['id']; ?>" class="btn btn-icon btn-secondary" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="funcionarios.php?departamento_id=<?php echo $d['id']; ?>" class="btn btn-icon btn-secondary" title="Ver funcionários">
                                                        <i class="bi bi-people"></i>
                                                    </a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar este departamento?')) location.href='?action=delete&id=<?php echo $d['id']; ?>'">
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
                    $dept = ['id' => 0, 'nome' => '', 'descricao' => '', 'responsavel_id' => null, 'ativo' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM departamentos WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $dept = $stmt->fetch() ?: $dept;
                    }
                    $responsaveis = $db->query("SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo' ORDER BY nome_completo")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Departamento</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Preencha os dados da área</p>
                        </div>
                        <a href="departamentos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 720px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nome <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($dept['nome']); ?>" placeholder="Ex: Farmácia, Logística, Administrativo...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3" placeholder="Breve descrição das responsabilidades da área"><?php echo htmlspecialchars($dept['descricao']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Responsável</label>
                                <select name="responsavel_id" class="form-select">
                                    <option value="">— Sem responsável definido —</option>
                                    <?php foreach ($responsaveis as $r): ?>
                                        <option value="<?php echo (int)$r['id']; ?>" <?php echo $dept['responsavel_id'] == $r['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['nome_completo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="ativo" class="form-check-input" <?php echo $dept['ativo'] ? 'checked' : ''; ?>>
                                    <span>Departamento ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="departamentos.php" class="btn btn-ghost">Cancelar</a>
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
