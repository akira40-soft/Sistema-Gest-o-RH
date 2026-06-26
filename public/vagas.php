<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isAdmin() && $auth->getUserRole() !== 'gestor_rh' && $auth->getUserRole() !== 'lider_farmaceutico') {
    header('Location: acesso_negado.php'); exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $auth->getUserId();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $titulo = trim($_POST['titulo'] ?? '');
            $cargo_id = (int)($_POST['cargo_id'] ?? 0);
            $departamento_id = (int)($_POST['departamento_id'] ?? 0);
            $descricao = trim($_POST['descricao'] ?? '');
            $requisitos = trim($_POST['requisitos'] ?? '');
            $responsabilidades = trim($_POST['responsabilidades'] ?? '');
            $beneficios = trim($_POST['beneficios'] ?? '');
            $salario_min = !empty($_POST['salario_min']) ? (float)$_POST['salario_min'] : null;
            $salario_max = !empty($_POST['salario_max']) ? (float)$_POST['salario_max'] : null;
            $numero_vagas = (int)($_POST['numero_vagas'] ?? 1);
            $tipo_contrato = $_POST['tipo_contrato'] ?? 'CLT';
            $regime = $_POST['regime'] ?? 'full_time';
            $data_fechamento = !empty($_POST['data_fechamento']) ? $_POST['data_fechamento'] : null;
            $status = $_POST['status'] ?? 'aberta';
            $publicada = isset($_POST['publicada']) ? 1 : 0;

            if (empty($titulo)) throw new Exception('Título é obrigatório.');
            if (!$cargo_id) throw new Exception('Selecione um cargo.');
            if (!$departamento_id) throw new Exception('Selecione um departamento.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO vagas
                    (titulo, cargo_id, departamento_id, descricao, requisitos, responsabilidades, beneficios,
                     salario_min, salario_max, numero_vagas, tipo_contrato, regime, data_fechamento, status, publicada, data_abertura)
                    VALUES (:t, :c, :d, :de, :r, :re, :b, :smi, :sma, :nv, :tc, :rg, :df, :s, :p, CURDATE())");
                $stmt->execute([
                    ':t' => $titulo, ':c' => $cargo_id, ':d' => $departamento_id, ':de' => $descricao,
                    ':r' => $requisitos, ':re' => $responsabilidades, ':b' => $beneficios,
                    ':smi' => $salario_min, ':sma' => $salario_max, ':nv' => $numero_vagas,
                    ':tc' => $tipo_contrato, ':rg' => $regime, ':df' => $data_fechamento,
                    ':s' => $status, ':p' => $publicada
                ]);
                $newId = $db->lastInsertId();
                Audit::create('vaga', $newId, "Vaga aberta: $titulo");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Vaga criada!</strong></div></div>';
            } else {
                $stmt = $db->prepare("UPDATE vagas SET titulo=:t, cargo_id=:c, departamento_id=:d, descricao=:de, requisitos=:r,
                    responsabilidades=:re, beneficios=:b, salario_min=:smi, salario_max=:sma, numero_vagas=:nv,
                    tipo_contrato=:tc, regime=:rg, data_fechamento=:df, status=:s, publicada=:p WHERE id=:id");
                $stmt->execute([
                    ':t' => $titulo, ':c' => $cargo_id, ':d' => $departamento_id, ':de' => $descricao,
                    ':r' => $requisitos, ':re' => $responsabilidades, ':b' => $beneficios,
                    ':smi' => $salario_min, ':sma' => $salario_max, ':nv' => $numero_vagas,
                    ':tc' => $tipo_contrato, ':rg' => $regime, ':df' => $data_fechamento,
                    ':s' => $status, ':p' => $publicada, ':id' => $id
                ]);
                Audit::update('vaga', $id, "Vaga atualizada: $titulo");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Vaga atualizada!</strong></div></div>';
            }
            $action = 'list';
        } elseif ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM vagas WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('vaga', $id, "Vaga #$id eliminada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Vaga eliminada!</strong></div></div>';
            $action = 'list';
        } elseif ($action === 'toggle' && $id > 0) {
            $db->prepare("UPDATE vagas SET publicada = NOT publicada WHERE id = :id")->execute([':id' => $id]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Estado de publicação atualizado!</strong></div></div>';
            $action = 'list';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$cargos = $db->query("SELECT id, nome FROM cargos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$departamentos = $db->query("SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='aberta' THEN 1 ELSE 0 END) as abertas,
    SUM(CASE WHEN status='em_andamento' THEN 1 ELSE 0 END) as em_andamento,
    SUM(CASE WHEN status='fechada' THEN 1 ELSE 0 END) as fechadas,
    (SELECT COUNT(*) FROM candidaturas) as total_candidatos,
    SUM(numero_vagas) as vagas_total
    FROM vagas")->fetch();

$pageTitle = 'Recrutamento e Seleção';
$pageSubtitle = 'Gestão de vagas, candidatos e processo seletivo';
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
                        <li class="breadcrumb-item"><a href="candidatos.php">Candidatos</a></li>
                        <li class="breadcrumb-item active">Vagas</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'):
                    $vagas = $db->query("SELECT v.*, c.nome as cargo_nome, d.nome as dept_nome,
                        (SELECT COUNT(*) FROM candidaturas WHERE vaga_id = v.id) as total_candidatos,
                        (SELECT COUNT(*) FROM candidaturas WHERE vaga_id = v.id AND status IN ('pre_selecionada','entrevista_agendada','aprovada')) as em_processo
                        FROM vagas v
                        LEFT JOIN cargos c ON v.cargo_id = c.id
                        LEFT JOIN departamentos d ON v.departamento_id = d.id
                        ORDER BY
                            CASE v.status WHEN 'aberta' THEN 1 WHEN 'em_andamento' THEN 2 WHEN 'pausada' THEN 3 WHEN 'fechada' THEN 4 ELSE 5 END,
                            v.data_abertura DESC")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Vagas Abertas</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="candidatos.php" class="btn btn-secondary"><i class="bi bi-people"></i> Ver Candidatos</a>
                            <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Vaga</a>
                        </div>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-briefcase"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Vagas Totais</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-megaphone"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['abertas']; ?></div>
                            <div class="stat-label">Vagas Abertas</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['em_andamento']; ?></div>
                            <div class="stat-label">Em Andamento</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total_candidatos']; ?></div>
                            <div class="stat-label">Candidatos</div>
                        </div>
                        <div class="stat-card stat-card-neutral">
                            <div class="stat-icon"><i class="bi bi-person-plus"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['vagas_total']; ?></div>
                            <div class="stat-label">Posições a Preencher</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Vaga</th>
                                        <th>Cargo · Departamento</th>
                                        <th>Tipo</th>
                                        <th>Salário</th>
                                        <th>Candidatos</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($vagas)): ?>
                                        <tr><td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-briefcase"></i>
                                                <h4>Sem vagas abertas</h4>
                                                <p>Crie a primeira vaga para iniciar o recrutamento.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($vagas as $v):
                                        $stMap = ['aberta' => 'success', 'em_andamento' => 'info', 'pausada' => 'warning', 'fechada' => 'neutral', 'cancelada' => 'danger'];
                                        $stCls = $stMap[$v['status']] ?? 'neutral';
                                    ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($v['titulo']); ?></strong>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?php echo (int)$v['numero_vagas']; ?> vaga(s) · Aberta em <?php echo date('d/m/Y', strtotime($v['data_abertura'])); ?>
                                                        <?php if ($v['publicada']): ?> · <i class="bi bi-globe"></i> Publicada<?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem;">
                                                    <div><strong><?php echo htmlspecialchars($v['cargo_nome']); ?></strong></div>
                                                    <div style="color: var(--text-muted);"><?php echo htmlspecialchars($v['dept_nome']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-neutral"><?php echo ucfirst(str_replace('_', ' ', $v['tipo_contrato'])); ?></span>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo ucfirst(str_replace('_', ' ', $v['regime'])); ?></div>
                                            </td>
                                            <td style="font-size: 0.85rem;">
                                                <?php if ($v['salario_min'] || $v['salario_max']): ?>
                                                    <?php echo $v['salario_min'] ? kz($v['salario_min']) : '?'; ?> –
                                                    <?php echo $v['salario_max'] ? kz($v['salario_max']) : '?'; ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">A combinar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo (int)$v['total_candidatos']; ?> total</span>
                                                <?php if ($v['em_processo'] > 0): ?>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo (int)$v['em_processo']; ?> em processo</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $v['status'])); ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="candidatos.php?vaga_id=<?php echo $v['id']; ?>" class="btn btn-icon btn-secondary" title="Ver candidatos"><i class="bi bi-people"></i></a>
                                                    <a href="?action=edit&id=<?php echo $v['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Publicar/Despublicar" onclick="location.href='?action=toggle&id=<?php echo $v['id']; ?>'">
                                                        <i class="bi bi-<?php echo $v['publicada'] ? 'eye-slash' : 'eye'; ?>" style="color: var(--<?php echo $v['publicada'] ? 'warning' : 'success'; ?>);"></i>
                                                    </button>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar vaga e todos os candidatos?')) location.href='?action=delete&id=<?php echo $v['id']; ?>'">
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
                    $v = ['id' => 0, 'titulo' => '', 'cargo_id' => 0, 'departamento_id' => 0, 'descricao' => '', 'requisitos' => '',
                        'responsabilidades' => '', 'beneficios' => '', 'salario_min' => '', 'salario_max' => '',
                        'numero_vagas' => 1, 'tipo_contrato' => 'CLT', 'regime' => 'full_time', 'data_fechamento' => '',
                        'status' => 'aberta', 'publicada' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM vagas WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $row = $stmt->fetch();
                        if ($row) $v = array_merge($v, $row);
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Nova' : 'Editar'; ?> Vaga</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Publique uma oportunidade</p>
                        </div>
                        <a href="vagas.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 900px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Título da Vaga <span class="required">*</span></label>
                                <input type="text" name="titulo" class="form-control" required value="<?php echo htmlspecialchars($v['titulo']); ?>" placeholder="Ex: Farmacêutico (a) Sénior">
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Cargo <span class="required">*</span></label>
                                    <select name="cargo_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($cargos as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo $v['cargo_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Departamento <span class="required">*</span></label>
                                    <select name="departamento_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo $v['departamento_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3" placeholder="Sobre a posição, contexto, equipa..."><?php echo htmlspecialchars($v['descricao']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Requisitos</label>
                                <textarea name="requisitos" class="form-control" rows="3" placeholder="Formação, experiência, certificações, idiomas..."><?php echo htmlspecialchars($v['requisitos']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Responsabilidades</label>
                                <textarea name="responsabilidades" class="form-control" rows="3" placeholder="Funções e responsabilidades principais..."><?php echo htmlspecialchars($v['responsabilidades']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Benefícios</label>
                                <textarea name="beneficios" class="form-control" rows="2" placeholder="Salário, subsídios, formação, etc."><?php echo htmlspecialchars($v['beneficios']); ?></textarea>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Salário Mínimo (Kz)</label>
                                    <input type="number" step="0.01" min="0" name="salario_min" class="form-control" value="<?php echo $v['salario_min']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Salário Máximo (Kz)</label>
                                    <input type="number" step="0.01" min="0" name="salario_max" class="form-control" value="<?php echo $v['salario_max']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Nº de Vagas</label>
                                    <input type="number" min="1" name="numero_vagas" class="form-control" value="<?php echo (int)$v['numero_vagas']; ?>">
                                </div>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo de Contrato</label>
                                    <select name="tipo_contrato" class="form-select">
                                        <option value="CLT" <?php echo $v['tipo_contrato'] === 'CLT' ? 'selected' : ''; ?>>CLT (efetivo)</option>
                                        <option value="prazo_determinado" <?php echo $v['tipo_contrato'] === 'prazo_determinado' ? 'selected' : ''; ?>>Prazo Determinado</option>
                                        <option value="estagio" <?php echo $v['tipo_contrato'] === 'estagio' ? 'selected' : ''; ?>>Estágio</option>
                                        <option value="temporario" <?php echo $v['tipo_contrato'] === 'temporario' ? 'selected' : ''; ?>>Temporário</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Regime</label>
                                    <select name="regime" class="form-select">
                                        <option value="full_time" <?php echo $v['regime'] === 'full_time' ? 'selected' : ''; ?>>Tempo Integral</option>
                                        <option value="part_time" <?php echo $v['regime'] === 'part_time' ? 'selected' : ''; ?>>Part-time</option>
                                        <option value="turnos" <?php echo $v['regime'] === 'turnos' ? 'selected' : ''; ?>>Turnos</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data de Fecho</label>
                                    <input type="date" name="data_fechamento" class="form-control" value="<?php echo $v['data_fechamento']; ?>">
                                </div>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Estado</label>
                                    <select name="status" class="form-select">
                                        <option value="aberta" <?php echo $v['status'] === 'aberta' ? 'selected' : ''; ?>>Aberta (recebe candidatos)</option>
                                        <option value="em_andamento" <?php echo $v['status'] === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento (em processo)</option>
                                        <option value="pausada" <?php echo $v['status'] === 'pausada' ? 'selected' : ''; ?>>Pausada</option>
                                        <option value="fechada" <?php echo $v['status'] === 'fechada' ? 'selected' : ''; ?>>Fechada (vaga preenchida)</option>
                                        <option value="cancelada" <?php echo $v['status'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <label class="form-check" style="padding-top: 0.5rem;">
                                        <input type="checkbox" name="publicada" class="form-check-input" <?php echo $v['publicada'] ? 'checked' : ''; ?>>
                                        <span>Publicar externamente</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="vagas.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar Vaga</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
