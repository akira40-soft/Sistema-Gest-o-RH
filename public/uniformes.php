<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$role = $auth->getUserRole();

if (!$auth->isAdmin() && $role !== 'gestor_rh' && $role !== 'funcionario_rh' && $role !== 'lider_farmaceutico') {
    header('Location: acesso_negado.php'); exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $nome = trim($_POST['nome'] ?? '');
            $tipo = $_POST['tipo'] ?? 'outro';
            $tamanho = $_POST['tamanho'] ?? 'M';
            $genero = $_POST['genero'] ?? 'Unissex';
            $quantidade = (int)($_POST['quantidade_estoque'] ?? 0);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome)) throw new Exception('Nome é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO uniformes (nome, tipo, tamanho, genero, quantidade_estoque, ativo) VALUES (:n, :t, :tm, :g, :q, :a)");
                $stmt->execute([':n' => $nome, ':t' => $tipo, ':tm' => $tamanho, ':g' => $genero, ':q' => $quantidade, ':a' => $ativo]);
                $newId = $db->lastInsertId();
                Audit::create('uniforme', $newId, "Uniforme criado: $nome ($tamanho)");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Uniforme adicionado ao stock!</strong></div></div>';
            } else {
                $stmt = $db->prepare("UPDATE uniformes SET nome=:n, tipo=:t, tamanho=:tm, genero=:g, quantidade_estoque=:q, ativo=:a WHERE id=:id");
                $stmt->execute([':n' => $nome, ':t' => $tipo, ':tm' => $tamanho, ':g' => $genero, ':q' => $quantidade, ':a' => $ativo, ':id' => $id]);
                Audit::update('uniforme', $id, "Uniforme atualizado: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Uniforme atualizado!</strong></div></div>';
            }
            $action = 'list';
        } elseif ($action === 'delete' && $id > 0) {
            $check = $db->prepare("SELECT COUNT(*) FROM entregas_uniformes WHERE uniforme_id = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) throw new Exception('Não é possível eliminar: existem entregas registadas. Desative o uniforme em vez de eliminar.');
            $db->prepare("DELETE FROM uniformes WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('uniforme', $id, "Uniforme #$id eliminado");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Uniforme eliminado!</strong></div></div>';
            $action = 'list';
        } elseif ($action === 'entrega') {
            $uniforme_id = (int)($_POST['uniforme_id'] ?? 0);
            $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
            $quantidade = (int)($_POST['quantidade'] ?? 1);
            $data_entrega = $_POST['data_entrega'] ?? date('Y-m-d');
            $motivo = trim($_POST['motivo'] ?? '');
            $observacoes = trim($_POST['observacoes'] ?? '');

            if (!$uniforme_id || !$funcionario_id || $quantidade < 1) throw new Exception('Preencha todos os campos.');

            $db->beginTransaction();
            $stock = $db->prepare("SELECT quantidade_estoque, nome FROM uniformes WHERE id = :id FOR UPDATE");
            $stock->execute([':id' => $uniforme_id]);
            $row = $stock->fetch();
            if (!$row) throw new Exception('Uniforme não encontrado.');
            if ($row['quantidade_estoque'] < $quantidade) throw new Exception("Stock insuficiente. Disponível: {$row['quantidade_estoque']}");

            $db->prepare("UPDATE uniformes SET quantidade_estoque = quantidade_estoque - :q WHERE id = :id")->execute([':q' => $quantidade, ':id' => $uniforme_id]);
            $db->prepare("INSERT INTO entregas_uniformes (funcionario_id, uniforme_id, quantidade, data_entrega, motivo, observacoes, entregue_por) VALUES (:f, :u, :q, :d, :m, :o, :e)")->execute([':f' => $funcionario_id, ':u' => $uniforme_id, ':q' => $quantidade, ':d' => $data_entrega, ':m' => $motivo, ':o' => $observacoes, ':e' => $user_id]);
            $db->commit();
            Audit::create('entrega_uniforme', $uniforme_id, "Entregue $quantidade× {$row['nome']} ao funcionário #$funcionario_id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Entrega registada!</strong> Stock atualizado.</div></div>';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$funcionarios = $db->query("SELECT id, nome_completo, sexo FROM funcionarios WHERE status IN ('ativo','ferias','licenca') ORDER BY nome_completo")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total_tipos,
    SUM(quantidade_estoque) as total_pecas,
    SUM(CASE WHEN quantidade_estoque = 0 THEN 1 ELSE 0 END) as em_falta,
    (SELECT COUNT(*) FROM entregas_uniformes) as total_entregas
    FROM uniformes WHERE ativo = 1")->fetch();

$pageTitle = 'Gestão de Uniformes';
$pageSubtitle = 'Stock, entregas e devoluções de uniformes';
?>
<!DOCTYPE html>
<html lang="pt-AO">
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
                        <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'):
                    $tab = $_GET['tab'] ?? 'stock';
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-primary" onclick="document.getElementById('entregaForm').style.display='block'">
                                <i class="bi bi-box-arrow-up"></i> Registar Entrega
                            </button>
                            <a href="?action=create" class="btn btn-secondary"><i class="bi bi-plus-lg"></i> Novo Item</a>
                        </div>
                    </div>

                    <div class="card mb-3" id="entregaForm" style="display: none;">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-box-arrow-up"></i> Registar Entrega de Uniforme</h3></div>
                        <div class="card-body">
                            <form method="POST" action="?action=entrega" style="display: grid; grid-template-columns: 2fr 2fr 1fr 1fr 2fr auto; gap: 0.5rem; align-items: end;">
                                <div>
                                    <label class="form-label">Uniforme</label>
                                    <select name="uniforme_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php
                                        $uni = $db->query("SELECT id, nome, tamanho, quantidade_estoque FROM uniformes WHERE ativo=1 AND quantidade_estoque > 0 ORDER BY nome, tamanho")->fetchAll();
                                        foreach ($uni as $u) echo '<option value="' . $u['id'] . '">' . htmlspecialchars($u['nome']) . ' - ' . $u['tamanho'] . ' (stock: ' . $u['quantidade_estoque'] . ')</option>';
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Funcionário</label>
                                    <select name="funcionario_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($funcionarios as $f) echo '<option value="' . $f['id'] . '">' . htmlspecialchars($f['nome_completo']) . '</option>'; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Qtd</label>
                                    <input type="number" min="1" name="quantidade" class="form-control" required value="1">
                                </div>
                                <div>
                                    <label class="form-label">Data</label>
                                    <input type="date" name="data_entrega" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Motivo</label>
                                    <input type="text" name="motivo" class="form-control" placeholder="Admissão, substituição, dano...">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> Entregar</button>
                            </form>
                        </div>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-boxes"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total_tipos']; ?></div>
                            <div class="stat-label">Tipos de Uniformes</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-stack"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total_pecas']; ?></div>
                            <div class="stat-label">Peças em Stock</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['em_falta']; ?></div>
                            <div class="stat-label">Em Falta</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-check2-square"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total_entregas']; ?></div>
                            <div class="stat-label">Entregas Totais</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin: 1.5rem 0 1rem;">
                        <a href="?tab=stock" class="btn <?php echo $tab === 'stock' ? 'btn-primary' : 'btn-ghost'; ?>"><i class="bi bi-box"></i> Stock</a>
                        <a href="?tab=entregas" class="btn <?php echo $tab === 'entregas' ? 'btn-primary' : 'btn-ghost'; ?>"><i class="bi bi-truck"></i> Entregas</a>
                    </div>

                    <?php if ($tab === 'stock'):
                        $uniformes = $db->query("SELECT u.*, (SELECT COUNT(*) FROM entregas_uniformes WHERE uniforme_id = u.id) as total_entregas FROM uniformes u ORDER BY u.ativo DESC, u.nome, u.tamanho")->fetchAll();
                    ?>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Tipo</th>
                                            <th>Tamanho</th>
                                            <th>Género</th>
                                            <th>Stock</th>
                                            <th>Entregas</th>
                                            <th>Estado</th>
                                            <th style="text-align: right;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($uniformes)): ?>
                                            <tr><td colspan="8">
                                                <div class="empty-state">
                                                    <i class="bi bi-box-seam"></i>
                                                    <h4>Sem uniformes em stock</h4>
                                                    <p>Adicione os primeiros itens.</p>
                                                    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Adicionar</a>
                                                </div>
                                            </td></tr>
                                        <?php else: foreach ($uniformes as $u):
                                            $tpIcons = ['camisa' => 'bi-person', 'calca' => 'bi-person-standing', 'bata' => 'bi-thermometer', 'sapato' => 'bi-shoe-prints', 'capa' => 'bi-shield', 'avental' => 'bi-shield-check', 'outro' => 'bi-box'];
                                            $icon = $tpIcons[$u['tipo']] ?? 'bi-box';
                                            $stockCls = $u['quantidade_estoque'] == 0 ? 'danger' : ($u['quantidade_estoque'] < 5 ? 'warning' : 'success');
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                            <i class="bi <?php echo $icon; ?>"></i>
                                                        </div>
                                                        <strong><?php echo htmlspecialchars($u['nome']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><span class="badge badge-neutral"><?php echo ucfirst($u['tipo']); ?></span></td>
                                                <td><strong><?php echo $u['tamanho']; ?></strong></td>
                                                <td><?php echo $u['genero']; ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $stockCls; ?>">
                                                        <i class="bi bi-box"></i> <?php echo (int)$u['quantidade_estoque']; ?> un.
                                                    </span>
                                                </td>
                                                <td><?php echo (int)$u['total_entregas']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $u['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                        <span class="badge-dot"></span> <?php echo $u['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                        <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                        <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar?')) location.href='?action=delete&id=<?php echo $u['id']; ?>'">
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
                    <?php else:
                        $entregas = $db->query("SELECT e.*, u.nome as uni_nome, u.tamanho, f.nome_completo, ue.username as entreg_por
                            FROM entregas_uniformes e
                            JOIN uniformes u ON e.uniforme_id = u.id
                            JOIN funcionarios f ON e.funcionario_id = f.id
                            JOIN usuarios ue ON e.entregue_por = ue.id
                            ORDER BY e.data_entrega DESC, e.created_at DESC LIMIT 200")->fetchAll();
                    ?>
                        <div class="card">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Funcionário</th>
                                            <th>Uniforme</th>
                                            <th>Tam.</th>
                                            <th>Qtd</th>
                                            <th>Motivo</th>
                                            <th>Entregue por</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($entregas)): ?>
                                            <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhuma entrega registada.</td></tr>
                                        <?php else: foreach ($entregas as $e): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($e['data_entrega'])); ?></td>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);"><?php echo strtoupper(substr($e['nome_completo'], 0, 1)); ?></div>
                                                        <strong><?php echo htmlspecialchars($e['nome_completo']); ?></strong>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($e['uni_nome']); ?></td>
                                                <td><?php echo $e['tamanho']; ?></td>
                                                <td><strong><?php echo (int)$e['quantidade']; ?></strong></td>
                                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($e['motivo'] ?? ''); ?></td>
                                                <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($e['entreg_por']); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php elseif ($action === 'create' || $action === 'edit'):
                    $u = ['id' => 0, 'nome' => '', 'tipo' => 'camisa', 'tamanho' => 'M', 'genero' => 'Unissex', 'quantidade_estoque' => 0, 'ativo' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM uniformes WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $row = $stmt->fetch();
                        if ($row) $u = array_merge($u, $row);
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Item de Uniforme</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Adicione ao stock</p>
                        </div>
                        <a href="uniformes.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 640px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nome <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($u['nome']); ?>" placeholder="Ex: Jaleco Branco, Bata Azul...">
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="camisa" <?php echo $u['tipo'] === 'camisa' ? 'selected' : ''; ?>>Camisa</option>
                                        <option value="calca" <?php echo $u['tipo'] === 'calca' ? 'selected' : ''; ?>>Calça</option>
                                        <option value="bata" <?php echo $u['tipo'] === 'bata' ? 'selected' : ''; ?>>Bata</option>
                                        <option value="sapato" <?php echo $u['tipo'] === 'sapato' ? 'selected' : ''; ?>>Sapato</option>
                                        <option value="capa" <?php echo $u['tipo'] === 'capa' ? 'selected' : ''; ?>>Capa</option>
                                        <option value="avental" <?php echo $u['tipo'] === 'avental' ? 'selected' : ''; ?>>Avental</option>
                                        <option value="outro" <?php echo $u['tipo'] === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tamanho</label>
                                    <select name="tamanho" class="form-select">
                                        <?php foreach (['PP', 'P', 'M', 'G', 'GG', 'XGG', 'UNICO'] as $t): ?>
                                            <option value="<?php echo $t; ?>" <?php echo $u['tamanho'] === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Género</label>
                                    <select name="genero" class="form-select">
                                        <option value="Unissex" <?php echo $u['genero'] === 'Unissex' ? 'selected' : ''; ?>>Unissex</option>
                                        <option value="M" <?php echo $u['genero'] === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo $u['genero'] === 'F' ? 'selected' : ''; ?>>Feminino</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quantidade em Stock</label>
                                <input type="number" min="0" name="quantidade_estoque" class="form-control" value="<?php echo (int)$u['quantidade_estoque']; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="ativo" class="form-check-input" <?php echo $u['ativo'] ? 'checked' : ''; ?>>
                                    <span>Item ativo e disponível para entrega</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="uniformes.php" class="btn btn-ghost">Cancelar</a>
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
