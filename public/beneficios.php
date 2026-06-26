<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();
$is_employee = in_array($auth->getUserRole(), ['funcionario', 'lider_farmaceutico']);
if (!$auth->isHRStaff() && !$is_employee) {
    header('Location: acesso_negado.php'); exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $auth->getUserId();
$is_employee_view = in_array($auth->getUserRole(), ['funcionario', 'lider_farmaceutico']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $nome = trim($_POST['nome'] ?? '');
            $tipo = $_POST['tipo'] ?? 'outro';
            $valor = !empty($_POST['valor_mensal']) ? (float)$_POST['valor_mensal'] : null;
            $descricao = trim($_POST['descricao'] ?? '');
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome)) throw new Exception('Nome é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO beneficios (nome, tipo, valor_mensal, descricao, ativo) VALUES (:n, :t, :v, :d, :a)");
                $stmt->execute([':n' => $nome, ':t' => $tipo, ':v' => $valor, ':d' => $descricao, ':a' => $ativo]);
                $newId = $db->lastInsertId();
                Audit::create('beneficio', $newId, "Benefício criado: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Benefício criado!</strong></div></div>';
            } else {
                $stmt = $db->prepare("UPDATE beneficios SET nome=:n, tipo=:t, valor_mensal=:v, descricao=:d, ativo=:a WHERE id=:id");
                $stmt->execute([':n' => $nome, ':t' => $tipo, ':v' => $valor, ':d' => $descricao, ':a' => $ativo, ':id' => $id]);
                Audit::update('beneficio', $id, "Benefício atualizado: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Benefício atualizado!</strong></div></div>';
            }
            $action = 'list';
        } elseif ($action === 'delete' && $id > 0) {
            $check = $db->prepare("SELECT COUNT(*) FROM funcionarios_beneficios WHERE beneficio_id = :id AND ativo = 1");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) throw new Exception('Não é possível eliminar: existem funcionários a receber este benefício. Desative-o em vez de eliminar.');
            $db->prepare("DELETE FROM beneficios WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('beneficio', $id, "Benefício #$id eliminado");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Benefício eliminado!</strong></div></div>';
            $action = 'list';
        } elseif ($action === 'assign' && $id > 0) {
            $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
            $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
            $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
            $valor_personalizado = !empty($_POST['valor_personalizado']) ? (float)$_POST['valor_personalizado'] : null;

            if (!$funcionario_id) throw new Exception('Selecione um funcionário.');

            $stmt = $db->prepare("INSERT INTO funcionarios_beneficios (funcionario_id, beneficio_id, data_inicio, data_fim, valor_personalizado, ativo) VALUES (:f, :b, :di, :df, :v, 1)");
            $stmt->execute([':f' => $funcionario_id, ':b' => $id, ':di' => $data_inicio, ':df' => $data_fim, ':v' => $valor_personalizado]);

            $b = $db->prepare("SELECT nome FROM beneficios WHERE id = :id");
            $b->execute([':id' => $id]);
            $bnome = $b->fetchColumn();

            $f = $db->prepare("SELECT nome_completo, usuario_id FROM funcionarios WHERE id = :id");
            $f->execute([':id' => $funcionario_id]);
            $fd = $f->fetch();
            if ($fd && $fd['usuario_id']) {
                Notification::send($fd['usuario_id'], 'Novo Benefício', "Foi-lhe atribuído o benefício: $bnome", 'success', '/portal.php?tab=beneficios');
            }

            Audit::create('beneficio_atribuicao', $id, "Benefício #$id ($bnome) atribuído ao funcionário #$funcionario_id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Benefício atribuído!</strong> O funcionário foi notificado.</div></div>';
            $action = 'view';
        } elseif ($action === 'revoke' && $id > 0) {
            $fb_id = (int)$_POST['fb_id'];
            $db->prepare("UPDATE funcionarios_beneficios SET ativo = 0, data_fim = CURDATE() WHERE id = :id")->execute([':id' => $fb_id]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Benefício desativado para este funcionário.</strong></div></div>';
            $action = 'view';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$pageTitle = 'Gestão de Benefícios';
$pageSubtitle = 'Vales, planos, subsídios e atribuições a funcionários';
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
                    if ($is_employee_view):
                        $emp = $db->prepare("SELECT id FROM funcionarios WHERE usuario_id = :uid LIMIT 1");
                        $emp->execute([':uid' => $user_id]);
                        $emp = $emp->fetch();
                        $emp_id = $emp['id'] ?? 0;
                        $beneficios = $db->prepare("SELECT b.*, fb.valor_personalizado, fb.data_inicio as atribuida_em
                            FROM funcionarios_beneficios fb
                            JOIN beneficios b ON fb.beneficio_id = b.id
                            WHERE fb.funcionario_id = :eid AND fb.ativo = 1
                            ORDER BY b.nome");
                        $beneficios->execute([':eid' => $emp_id]);
                        $beneficios = $beneficios->fetchAll();
                    else:
                        $beneficios = $db->query("SELECT b.*, COUNT(fb.id) as total_atribuicoes
                            FROM beneficios b LEFT JOIN funcionarios_beneficios fb ON b.id = fb.beneficio_id AND fb.ativo = 1
                            GROUP BY b.id ORDER BY b.ativo DESC, b.nome")->fetchAll();
                    endif;
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Benefício</a>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                        <?php
                        $st = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN ativo=1 THEN 1 ELSE 0 END) as ativos FROM beneficios")->fetch();
                        $sa = $db->query("SELECT COUNT(DISTINCT funcionario_id) as total FROM funcionarios_beneficios WHERE ativo = 1")->fetch();
                        $sv = $db->query("SELECT COALESCE(SUM(COALESCE(fb.valor_personalizado, b.valor_mensal)), 0) as total
                            FROM funcionarios_beneficios fb JOIN beneficios b ON fb.beneficio_id = b.id WHERE fb.ativo = 1")->fetch();
                        ?>
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-gift"></i></div>
                            <div class="stat-value"><?php echo (int)$st['total']; ?></div>
                            <div class="stat-label">Tipos de Benefícios</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$st['ativos']; ?></div>
                            <div class="stat-label">Ativos</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-value"><?php echo (int)$sa['total']; ?></div>
                            <div class="stat-label">Funcionários Abrangidos</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-cash-coin"></i></div>
                            <div class="stat-value"><?php echo kz($sv['total']); ?></div>
                            <div class="stat-label">Investimento Mensal</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Benefício</th>
                                        <th>Tipo</th>
                                        <th>Valor Mensal</th>
                                        <th>Atribuições</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($beneficios)): ?>
                                        <tr><td colspan="6">
                                            <div class="empty-state">
                                                <i class="bi bi-gift"></i>
                                                <h4>Sem benefícios</h4>
                                                <p>Crie o primeiro tipo de benefício.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($beneficios as $b):
                                        $tpIcons = ['vale_transporte' => 'bus-front', 'vale_alimentacao' => 'cup-hot', 'plano_saude' => 'heart-pulse', 'seguro_vida' => 'shield-plus', 'subsidio_formacao' => 'mortarboard', 'outro' => 'gift'];
                                        $icon = $tpIcons[$b['tipo']] ?? 'gift';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <i class="bi bi-<?php echo $icon; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($b['nome']); ?></strong>
                                                        <?php if ($b['descricao']): ?>
                                                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(mb_substr($b['descricao'], 0, 60)); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-neutral"><?php echo ucfirst(str_replace('_', ' ', $b['tipo'])); ?></span></td>
                                            <td><strong><?php echo $b['valor_mensal'] ? kz($b['valor_mensal']) : '—'; ?></strong></td>
                                            <td>
                                                <span class="badge badge-info"><i class="bi bi-person-check"></i> <?php echo (int)$b['total_atribuicoes']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $b['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <span class="badge-dot"></span> <?php echo $b['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=view&id=<?php echo $b['id']; ?>" class="btn btn-icon btn-secondary" title="Ver / Atribuir"><i class="bi bi-eye"></i></a>
                                                    <a href="?action=edit&id=<?php echo $b['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar?')) location.href='?action=delete&id=<?php echo $b['id']; ?>'">
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
                    $b = ['id' => 0, 'nome' => '', 'tipo' => 'outro', 'valor_mensal' => '', 'descricao' => '', 'ativo' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM beneficios WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $row = $stmt->fetch();
                        if ($row) $b = array_merge($b, $row);
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Benefício</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Defina o benefício a atribuir</p>
                        </div>
                        <a href="beneficios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 640px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nome <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($b['nome']); ?>" placeholder="Ex: Subsídio de Alimentação">
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="vale_transporte" <?php echo $b['tipo'] === 'vale_transporte' ? 'selected' : ''; ?>>Vale Transporte</option>
                                        <option value="vale_alimentacao" <?php echo $b['tipo'] === 'vale_alimentacao' ? 'selected' : ''; ?>>Vale Alimentação</option>
                                        <option value="plano_saude" <?php echo $b['tipo'] === 'plano_saude' ? 'selected' : ''; ?>>Plano de Saúde</option>
                                        <option value="seguro_vida" <?php echo $b['tipo'] === 'seguro_vida' ? 'selected' : ''; ?>>Seguro de Vida</option>
                                        <option value="subsidio_formacao" <?php echo $b['tipo'] === 'subsidio_formacao' ? 'selected' : ''; ?>>Subsídio de Formação</option>
                                        <option value="outro" <?php echo $b['tipo'] === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Valor Mensal (Kz)</label>
                                    <input type="number" step="0.01" min="0" name="valor_mensal" class="form-control" value="<?php echo $b['valor_mensal']; ?>" placeholder="0.00">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2" placeholder="Detalhes, regras, condições..."><?php echo htmlspecialchars($b['descricao']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="ativo" class="form-check-input" <?php echo $b['ativo'] ? 'checked' : ''; ?>>
                                    <span>Benefício ativo e disponível para atribuição</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="beneficios.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                        </div>
                    </form>

                <?php elseif ($action === 'view' && $id > 0):
                    $stmt = $db->prepare("SELECT * FROM beneficios WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $b = $stmt->fetch();
                    if (!$b) {
                        echo '<div class="alert alert-danger">Benefício não encontrado.</div>';
                    } else {
                        $atribuicoes = $db->prepare("SELECT fb.*, f.nome_completo, c.nome as cargo, d.nome as dept
                            FROM funcionarios_beneficios fb
                            JOIN funcionarios f ON fb.funcionario_id = f.id
                            LEFT JOIN cargos c ON f.cargo_id = c.id
                            LEFT JOIN departamentos d ON f.departamento_id = d.id
                            WHERE fb.beneficio_id = :b ORDER BY fb.ativo DESC, f.nome_completo");
                        $atribuicoes->execute([':b' => $id]);
                        $atribuicoes = $atribuicoes->fetchAll();

                        $funcionarios_all = $db->query("SELECT id, nome_completo FROM funcionarios WHERE status IN ('ativo','ferias','licenca') ORDER BY nome_completo")->fetchAll();
                        $assigned_ids = array_column(array_filter($atribuicoes, fn($x) => $x['ativo']), 'funcionario_id');
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($b['nome']); ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $b['tipo'])); ?>
                                <?php if ($b['valor_mensal']): ?> · <?php echo kz($b['valor_mensal']); ?>/mês<?php endif; ?>
                            </p>
                        </div>
                        <a href="beneficios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-person-plus"></i> Atribuir a Funcionário</h3></div>
                        <div class="card-body">
                            <form method="POST" action="?action=assign&id=<?php echo $id; ?>" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 0.5rem; align-items: end;">
                                <div>
                                    <label class="form-label">Funcionário</label>
                                    <select name="funcionario_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($funcionarios_all as $f): if (in_array($f['id'], $assigned_ids)) continue; ?>
                                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Início</label>
                                    <input type="date" name="data_inicio" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label class="form-label">Fim</label>
                                    <input type="date" name="data_fim" class="form-control">
                                </div>
                                <div>
                                    <label class="form-label">Valor Kz</label>
                                    <input type="number" step="0.01" min="0" name="valor_personalizado" class="form-control" placeholder="<?php echo $b['valor_mensal'] ?? '0'; ?>">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i> Atribuir</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-people"></i> Funcionários com este benefício (<?php echo count($atribuicoes); ?>)</h3></div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Departamento</th>
                                        <th>Início</th>
                                        <th>Fim</th>
                                        <th>Valor</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($atribuicoes)): ?>
                                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhum funcionário recebeu este benefício ainda.</td></tr>
                                    <?php else: foreach ($atribuicoes as $a): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);"><?php echo strtoupper(substr($a['nome_completo'], 0, 1)); ?></div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($a['nome_completo']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($a['cargo'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($a['dept'] ?? '—'); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($a['data_inicio'])); ?></td>
                                            <td><?php echo $a['data_fim'] ? date('d/m/Y', strtotime($a['data_fim'])) : '—'; ?></td>
                                            <td><strong><?php echo kz($a['valor_personalizado'] ?? $b['valor_mensal'] ?? 0); ?></strong></td>
                                            <td>
                                                <span class="badge <?php echo $a['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                                    <span class="badge-dot"></span> <?php echo $a['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($a['ativo']): ?>
                                                    <form method="POST" action="?action=revoke&id=<?php echo $id; ?>" style="display: inline;" onsubmit="return confirm('Desativar benefício para este funcionário?')">
                                                        <input type="hidden" name="fb_id" value="<?php echo $a['id']; ?>">
                                                        <button class="btn btn-icon btn-secondary" title="Desativar"><i class="bi bi-x-circle" style="color: var(--warning);"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
