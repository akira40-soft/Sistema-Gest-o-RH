<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;
use App\Utils\Workflow;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$user_role = $auth->getUserRole();
$is_admin = $auth->isAdmin();

if (!$is_admin && $user_role !== 'gestor_rh' && $user_role !== 'lider_farmaceutico') {
    header('Location: acesso_negado.php'); exit;
}

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? 'verbal';
            $gravidade = $_POST['gravidade'] ?? 'leve';
            $motivo = trim($_POST['motivo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $data_ocorrencia = $_POST['data_ocorrencia'] ?? date('Y-m-d');
            $dias_suspensao = ($tipo === 'suspensao' && !empty($_POST['dias_suspensao'])) ? (int)$_POST['dias_suspensao'] : 0;
            $data_fim_suspensao = null;
            if ($tipo === 'suspensao' && $dias_suspensao > 0) {
                $data_fim_suspensao = date('Y-m-d', strtotime("$data_ocorrencia + $dias_suspensao days"));
            }
            $observacoes = trim($_POST['observacoes'] ?? '');

            if (!$funcionario_id) throw new Exception('Selecione um funcionário.');
            if (empty($motivo)) throw new Exception('Motivo é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO advertencias
                    (funcionario_id, tipo, gravidade, motivo, descricao, data_ocorrencia, data_fim_suspensao, dias_suspensao, status, aplicada_por, observacoes)
                    VALUES (:f, :t, :g, :m, :d, :do, :dfs, :ds, 'pendente', :ap, :o)");
                $stmt->execute([
                    ':f' => $funcionario_id, ':t' => $tipo, ':g' => $gravidade, ':m' => $motivo, ':d' => $descricao,
                    ':do' => $data_ocorrencia, ':dfs' => $data_fim_suspensao, ':ds' => $dias_suspensao,
                    ':ap' => $user_id, ':o' => $observacoes
                ]);
                $newId = $db->lastInsertId();

                $wf = new Workflow('advertencia', $newId);
                $wf->start();

                $f = $db->prepare("SELECT nome_completo, usuario_id FROM funcionarios WHERE id = :id");
                $f->execute([':id' => $funcionario_id]);
                $fnome = $f->fetch();
                if ($fnome && $fnome['usuario_id']) {
                    Notification::send($fnome['usuario_id'], 'Registo Disciplinar', "Foi registada uma advertência do tipo '$tipo' em seu nome. Aguarde notificação de decisão.", 'warning', '/portal.php?tab=advertencias');
                }
                Notification::sendToRole('gestor_rh', 'Advertência Aguarda Aprovação', "Nova advertência #$newId para aprovação", 'warning', '/advertencias.php?action=view&id=' . $newId);

                Audit::create('advertencia', $newId, "Advertência $tipo criada para funcionário #$funcionario_id");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Advertência registada!</strong> Aguarda aprovação do RH.</div></div>';
                $action = 'view';
                $id = $newId;
            }
        } elseif ($action === 'approve' && $id > 0) {
            $comentario = trim($_POST['comentario'] ?? '');
            $wf = new Workflow('advertencia', $id);
            $etapa_atual = 1;
            $stmt = $db->prepare("SELECT etapa_atual, estado FROM workflow_aprovacoes WHERE tipo='advertencia' AND entidade_id=:id");
            $stmt->execute([':id' => $id]);
            $wfRow = $stmt->fetch();
            if ($wfRow) $etapa_atual = (int)$wfRow['etapa_atual'];
            if ($etapa_atual === 1 && $user_role !== 'gestor_rh' && !$is_admin) throw new Exception('Sem permissão para aprovar etapa RH.');
            if ($etapa_atual === 2 && !$is_admin) throw new Exception('Sem permissão para aprovar etapa Direção.');

            if ($etapa_atual === 1) {
                $stmt = $db->prepare("UPDATE advertencias SET aprovada_rh_por=:u, aprovada_rh_em=NOW() WHERE id=:id");
                $stmt->execute([':u' => $user_id, ':id' => $id]);
                $wf->approve($user_id, $comentario ?: 'Aprovado pelo RH');
                Notification::sendToRole('super_admin', 'Advertência Aguarda Aprovação Final', "Advertência #$id aprovada pelo RH. Aguarda decisão da Direção.", 'warning', '/advertencias.php?action=view&id=' . $id);
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Etapa RH aprovada!</strong> Encaminhada para a Direção.</div></div>';
            } elseif ($etapa_atual === 2) {
                $stmt = $db->prepare("UPDATE advertencias SET aprovada_direcao_por=:u, aprovada_direcao_em=NOW(), status='ativa' WHERE id=:id");
                $stmt->execute([':u' => $user_id, ':id' => $id]);
                $stmt_hist = $db->prepare("INSERT INTO workflow_historico (workflow_tipo, workflow_entidade_id, etapa, acao, user_id, comentario, criado_em) VALUES ('advertencia', :eid, 2, 'aprovado', :uid, :c, NOW())");
                $stmt_hist->execute([':eid' => $id, ':uid' => $user_id, ':c' => $comentario ?: 'Aprovado pela Direção']);
                $stmt = $db->prepare("SELECT funcionario_id, tipo FROM advertencias WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $adv = $stmt->fetch();
                $fs = $db->prepare("SELECT usuario_id FROM funcionarios WHERE id = :id");
                $fs->execute([':id' => $adv['funcionario_id']]);
                $fuid = $fs->fetchColumn();
                if ($fuid) Notification::send($fuid, 'Advertência Ativa', "A sua advertência do tipo '{$adv['tipo']}' foi aprovada pela Direção e está ativa.", 'danger', '/portal.php?tab=advertencias');
                Audit::update('advertencia', $id, "Advertência totalmente aprovada e ativada");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Advertência aprovada pela Direção e ativada.</strong></div></div>';
            }
        } elseif ($action === 'reject' && $id > 0) {
            $comentario = trim($_POST['comentario'] ?? '');
            if (empty($comentario)) throw new Exception('Indique o motivo da rejeição.');
            $wf = new Workflow('advertencia', $id);
            $wf->reject($user_id, $comentario);
            $db->prepare("UPDATE advertencias SET status='revogada' WHERE id=:id")->execute([':id' => $id]);
            Audit::update('advertencia', $id, "Advertência rejeitada: $comentario");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Advertência rejeitada.</strong></div></div>';
        } elseif ($action === 'revoke' && $id > 0) {
            $db->prepare("UPDATE advertencias SET status='revogada' WHERE id=:id")->execute([':id' => $id]);
            Audit::update('advertencia', $id, "Advertência revogada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Advertência revogada!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$funcionarios = $db->query("SELECT f.id, f.nome_completo, c.nome as cargo FROM funcionarios f LEFT JOIN cargos c ON f.cargo_id = c.id WHERE f.status != 'demitido' ORDER BY f.nome_completo")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status='ativa' THEN 1 ELSE 0 END) as ativas,
    SUM(CASE WHEN tipo='verbal' THEN 1 ELSE 0 END) as verbais,
    SUM(CASE WHEN tipo='escrita' THEN 1 ELSE 0 END) as escritas,
    SUM(CASE WHEN tipo='suspensao' THEN 1 ELSE 0 END) as suspensoes
    FROM advertencias")->fetch();

$pageTitle = 'Gestão Disciplinar';
$pageSubtitle = 'Advertências com workflow de aprovação em 2 níveis';
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
                    $advertencias = $db->query("SELECT a.*, f.nome_completo, c.nome as cargo,
                        ar.username as rh_nome, ad.username as dir_nome, ua.username as aplicada_por_nome
                        FROM advertencias a
                        JOIN funcionarios f ON a.funcionario_id = f.id
                        LEFT JOIN cargos c ON f.cargo_id = c.id
                        LEFT JOIN usuarios ar ON a.aprovada_rh_por = ar.id
                        LEFT JOIN usuarios ad ON a.aprovada_direcao_por = ad.id
                        LEFT JOIN usuarios ua ON a.aplicada_por = ua.id
                        ORDER BY a.created_at DESC")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Registar Advertência</a>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="stat-card stat-card-neutral">
                            <div class="stat-icon"><i class="bi bi-files"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Total de Registos</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['pendentes']; ?></div>
                            <div class="stat-label">Aguardam Aprovação</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['ativas']; ?></div>
                            <div class="stat-label">Ativas</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-chat-left-text"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['verbais']; ?></div>
                            <div class="stat-label">Verbais</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-pencil-square"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['escritas']; ?></div>
                            <div class="stat-label">Escritas</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-x-octagon"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['suspensoes']; ?></div>
                            <div class="stat-label">Suspensões</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Tipo</th>
                                        <th>Gravidade</th>
                                        <th>Ocorrência</th>
                                        <th>Dias</th>
                                        <th>Estado</th>
                                        <th>Aplicada por</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($advertencias)): ?>
                                        <tr><td colspan="8">
                                            <div class="empty-state">
                                                <i class="bi bi-shield-check"></i>
                                                <h4>Sem advertências</h4>
                                                <p>Excelente! Nenhuma ocorrência disciplinar registada.</p>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($advertencias as $a):
                                        $stMap = ['pendente' => 'warning', 'aprovada_rh' => 'info', 'aprovada_direcao' => 'info', 'ativa' => 'danger', 'revogada' => 'neutral', 'expirada' => 'neutral'];
                                        $stCls = $stMap[$a['status']] ?? 'neutral';
                                        $tpIcons = ['verbal' => 'chat-left-text', 'escrita' => 'pencil-square', 'suspensao' => 'x-octagon'];
                                        $tpColors = ['verbal' => 'info', 'escrita' => 'warning', 'suspensao' => 'danger'];
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--<?php echo $tpColors[$a['tipo']] ?? 'primary'; ?>-soft, rgba(239,68,68,0.12); color: var(--<?php echo $tpColors[$a['tipo']] ?? 'primary'; ?>);">
                                                        <i class="bi bi-<?php echo $tpIcons[$a['tipo']]; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($a['nome_completo']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($a['cargo'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-<?php echo $tpColors[$a['tipo']] ?? 'neutral'; ?>"><?php echo ucfirst($a['tipo']); ?></span></td>
                                            <td><span class="badge badge-neutral"><?php echo ucfirst($a['gravidade']); ?></span></td>
                                            <td><?php echo date('d/m/Y', strtotime($a['data_ocorrencia'])); ?></td>
                                            <td><?php echo $a['dias_suspensao'] ?: '—'; ?></td>
                                            <td><span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $a['status'])); ?></span></td>
                                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($a['aplicada_por_nome']); ?></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=view&id=<?php echo $a['id']; ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                                    <?php if ($a['status'] === 'ativa' && ($is_admin || $user_role === 'gestor_rh')): ?>
                                                        <button class="btn btn-icon btn-secondary" title="Revogar" onclick="if(confirm('Revogar esta advertência?')) location.href='?action=revoke&id=<?php echo $a['id']; ?>'">
                                                            <i class="bi bi-x-circle" style="color: var(--warning);"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action === 'create'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Registar Advertência</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Ocorrência disciplinar com workflow de 2 níveis</p>
                        </div>
                        <a href="advertencias.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 720px;">
                        <div class="card-body">
                            <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Funcionário <span class="required">*</span></label>
                                    <select name="funcionario_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($funcionarios as $f): ?>
                                            <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo'] . ' — ' . ($f['cargo'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tipo <span class="required">*</span></label>
                                    <select name="tipo" class="form-select" id="tipo-select" onchange="document.getElementById('susp-div').style.display=this.value==='suspensao'?'block':'none'">
                                        <option value="verbal">Verbal (advertência oral)</option>
                                        <option value="escrita">Escrita</option>
                                        <option value="suspensao">Suspensão</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Gravidade</label>
                                    <select name="gravidade" class="form-select">
                                        <option value="leve">Leve</option>
                                        <option value="media" selected>Média</option>
                                        <option value="grave">Grave</option>
                                        <option value="gravissima">Gravíssima</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Data da Ocorrência <span class="required">*</span></label>
                                    <input type="date" name="data_ocorrencia" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group" id="susp-div" style="display: none;">
                                    <label class="form-label">Dias de Suspensão</label>
                                    <input type="number" min="1" max="30" name="dias_suspensao" class="form-control" placeholder="Ex: 3">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Motivo <span class="required">*</span></label>
                                <textarea name="motivo" class="form-control" rows="2" required placeholder="Descrição sumária do motivo..."></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição Detalhada</label>
                                <textarea name="descricao" class="form-control" rows="3" placeholder="Contexto, testemunhas, circunstâncias..."></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2" placeholder="Notas adicionais, ações de melhoria..."></textarea>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="advertencias.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-danger"><i class="bi bi-send"></i> Registar e Submeter a Aprovação</button>
                        </div>
                    </form>

                <?php elseif ($action === 'view' && $id > 0):
                    $stmt = $db->prepare("SELECT a.*, f.nome_completo, f.bi, c.nome as cargo, d.nome as dept,
                        ar.username as rh_nome, ad.username as dir_nome, ua.username as aplicada_por_nome
                        FROM advertencias a
                        JOIN funcionarios f ON a.funcionario_id = f.id
                        LEFT JOIN cargos c ON f.cargo_id = c.id
                        LEFT JOIN departamentos d ON f.departamento_id = d.id
                        LEFT JOIN usuarios ar ON a.aprovada_rh_por = ar.id
                        LEFT JOIN usuarios ad ON a.aprovada_direcao_por = ad.id
                        LEFT JOIN usuarios ua ON a.aplicada_por = ua.id
                        WHERE a.id = :id");
                    $stmt->execute([':id' => $id]);
                    $a = $stmt->fetch();
                    if (!$a) {
                        echo '<div class="alert alert-danger">Advertência não encontrada.</div>';
                    } else {
                        $wf = new Workflow('advertencia', $id);
                        $hist = $wf->history();
                        $wfState = $wf->status();
                        $current = null;
                        $st = $db->prepare("SELECT etapa_atual, estado FROM workflow_aprovacoes WHERE tipo='advertencia' AND entidade_id=:id");
                        $st->execute([':id' => $id]);
                        $wfRow = $st->fetch();
                        if ($wfRow) $current = ['etapa_atual' => (int)$wfRow['etapa_atual'], 'estado' => $wfRow['estado']];
                        $canApprove = $current && $current['estado'] === 'em_aprovacao' && (($current['etapa_atual'] == 1 && $user_role === 'gestor_rh') || ($current['etapa_atual'] == 2 && $is_admin));
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Advertência #<?php echo $a['id']; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                                <?php echo ucfirst($a['tipo']); ?> · <?php echo ucfirst($a['gravidade']); ?> · <?php echo date('d/m/Y', strtotime($a['data_ocorrencia'])); ?>
                            </p>
                        </div>
                        <a href="advertencias.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div>
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Funcionário</h4>
                                    <p style="font-size: 1.1rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($a['nome_completo']); ?></p>
                                    <p style="color: var(--text-muted); margin: 0.25rem 0;"><?php echo htmlspecialchars($a['cargo'] ?? ''); ?> · <?php echo htmlspecialchars($a['dept'] ?? ''); ?></p>
                                    <p style="font-size: 0.85rem; color: var(--text-muted);">BI: <?php echo htmlspecialchars($a['bi'] ?? '—'); ?></p>
                                </div>
                                <div>
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Estado Atual</h4>
                                    <?php
                                    $stMap = ['pendente' => 'warning', 'aprovada_rh' => 'info', 'aprovada_direcao' => 'info', 'ativa' => 'danger', 'revogada' => 'neutral', 'expirada' => 'neutral'];
                                    $stCls = $stMap[$a['status']] ?? 'neutral';
                                    ?>
                                    <span class="badge badge-<?php echo $stCls; ?>" style="font-size: 0.9rem; padding: 0.5rem 1rem;"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $a['status'])); ?></span>
                                    <?php if ($a['dias_suspensao']): ?>
                                        <p style="margin: 0.5rem 0 0; color: var(--text-muted);"><i class="bi bi-calendar-x"></i> <?php echo $a['dias_suspensao']; ?> dias de suspensão<?php if ($a['data_fim_suspensao']): ?> · até <?php echo date('d/m/Y', strtotime($a['data_fim_suspensao'])); ?><?php endif; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr style="border-color: var(--border-color); margin: 1.5rem 0;">

                            <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Motivo</h4>
                            <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0 0 1rem 0;"><?php echo nl2br(htmlspecialchars($a['motivo'])); ?></p>
                            <?php if ($a['descricao']): ?>
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Descrição Detalhada</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0 0 1rem 0;"><?php echo nl2br(htmlspecialchars($a['descricao'])); ?></p>
                            <?php endif; ?>
                            <?php if ($a['observacoes']): ?>
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Observações</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0;"><?php echo nl2br(htmlspecialchars($a['observacoes'])); ?></p>
                            <?php endif; ?>

                            <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
                            <div style="font-size: 0.85rem; color: var(--text-muted); display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                <div><strong>Aplicada por:</strong> <?php echo htmlspecialchars($a['aplicada_por_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></div>
                                <?php if ($a['aprovada_rh_por']): ?>
                                    <div><strong>Aprovada RH:</strong> <?php echo htmlspecialchars($a['rh_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime($a['aprovada_rh_em'])); ?></div>
                                <?php endif; ?>
                                <?php if ($a['aprovada_direcao_por']): ?>
                                    <div><strong>Aprovada Direção:</strong> <?php echo htmlspecialchars($a['dir_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime($a['aprovada_direcao_em'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-diagram-3"></i> Workflow de Aprovação</h3></div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; gap: 0.5rem; align-items: center;">
                                <div style="padding: 1rem; background: var(--bg-input); border-radius: var(--radius); text-align: center; border: 2px solid <?php echo $a['aprovada_rh_em'] ? 'var(--success)' : 'var(--border-color)'; ?>;">
                                    <i class="bi bi-<?php echo $a['aprovada_rh_em'] ? 'check-circle-fill' : 'hourglass-split'; ?>" style="font-size: 1.5rem; color: var(--<?php echo $a['aprovada_rh_em'] ? 'success' : 'warning'; ?>);"></i>
                                    <p style="margin: 0.5rem 0 0; font-weight: 700; font-size: 0.85rem;">1. Aprovação RH</p>
                                    <p style="margin: 0; font-size: 0.75rem; color: var(--text-muted);">Gestor RH</p>
                                </div>
                                <i class="bi bi-arrow-right" style="color: var(--text-muted);"></i>
                                <div style="padding: 1rem; background: var(--bg-input); border-radius: var(--radius); text-align: center; border: 2px solid <?php echo $a['aprovada_direcao_em'] ? 'var(--success)' : 'var(--border-color)'; ?>;">
                                    <i class="bi bi-<?php echo $a['aprovada_direcao_em'] ? 'check-circle-fill' : 'hourglass-split'; ?>" style="font-size: 1.5rem; color: var(--<?php echo $a['aprovada_direcao_em'] ? 'success' : 'warning'; ?>);"></i>
                                    <p style="margin: 0.5rem 0 0; font-weight: 700; font-size: 0.85rem;">2. Aprovação Direção</p>
                                    <p style="margin: 0; font-size: 0.75rem; color: var(--text-muted);">Super Admin</p>
                                </div>
                                <i class="bi bi-arrow-right" style="color: var(--text-muted);"></i>
                                <div style="padding: 1rem; background: var(--bg-input); border-radius: var(--radius); text-align: center; border: 2px solid <?php echo $a['status'] === 'ativa' ? 'var(--danger)' : 'var(--border-color)'; ?>;">
                                    <i class="bi bi-<?php echo $a['status'] === 'ativa' ? 'exclamation-triangle-fill' : 'shield'; ?>" style="font-size: 1.5rem; color: var(--<?php echo $a['status'] === 'ativa' ? 'danger' : 'text-muted'; ?>);"></i>
                                    <p style="margin: 0.5rem 0 0; font-weight: 700; font-size: 0.85rem;">3. Ativação</p>
                                    <p style="margin: 0; font-size: 0.75rem; color: var(--text-muted);">Estado final</p>
                                </div>
                            </div>

                            <?php if ($canApprove): ?>
                                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                    <h4 style="margin-bottom: 0.5rem;">A sua decisão (Etapa <?php echo $current['etapa_atual']; ?>)</h4>
                                    <form method="POST" style="display: flex; gap: 0.5rem; align-items: end; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 240px;">
                                            <label class="form-label">Comentário</label>
                                            <input type="text" name="comentario" class="form-control" placeholder="Observações sobre a decisão..." required>
                                        </div>
                                        <?php if ($action === 'view'): ?>
                                            <input type="hidden" name="approval_action" value="approve">
                                        <?php endif; ?>
                                        <button type="submit" formaction="?action=approve&id=<?php echo $id; ?>" class="btn btn-success"><i class="bi bi-check-circle"></i> Aprovar</button>
                                        <button type="submit" formaction="?action=reject&id=<?php echo $id; ?>" class="btn btn-danger" onclick="return confirm('Rejeitar esta advertência?')"><i class="bi bi-x-circle"></i> Rejeitar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($hist)): ?>
                    <div class="card">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-clock-history"></i> Histórico</h3></div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Data</th><th>Etapa</th><th>Ação</th><th>Por</th><th>Comentário</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hist as $h):
                                        $ac = ['criado' => 'info', 'aprovado' => 'success', 'rejeitado' => 'danger', 'comentario' => 'neutral'];
                                        $acCls = $ac[$h['acao']] ?? 'neutral';
                                    ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($h['criado_em'])); ?></td>
                                        <td>Etapa <?php echo (int)$h['etapa']; ?></td>
                                        <td><span class="badge badge-<?php echo $acCls; ?>"><?php echo ucfirst($h['acao']); ?></span></td>
                                        <td><?php echo htmlspecialchars($h['user_id'] ?? '—'); ?></td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($h['comentario'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php } endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
