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

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
            $periodo_inicio = $_POST['periodo_inicio'] ?? '';
            $periodo_fim = $_POST['periodo_fim'] ?? '';
            $tipo = $_POST['tipo'] ?? 'anual';
            $atendimento = (int)$_POST['atendimento_cliente'];
            $conhecimento = (int)$_POST['conhecimento_tecnico'];
            $pontualidade = (int)$_POST['pontualidade'];
            $equipe = (int)$_POST['trabalho_equipe'];
            $metas = (int)$_POST['cumprimento_metas'];
            $proatividade = (int)$_POST['proatividade'];
            $pontos_fortes = trim($_POST['pontos_fortes'] ?? '');
            $pontos_melhoria = trim($_POST['pontos_melhoria'] ?? '');
            $plano = trim($_POST['plano_desenvolvimento'] ?? '');
            $status = $_POST['status'] ?? 'rascunho';

            if (!$funcionario_id) throw new Exception('Selecione um funcionário.');
            if (empty($periodo_inicio) || empty($periodo_fim)) throw new Exception('Período é obrigatório.');

            $nota = ($atendimento + $conhecimento + $pontualidade + $equipe + $metas + $proatividade) / 6.0;
            if ($nota < 1.5) $classificacao = 'insuficiente';
            elseif ($nota < 2.5) $classificacao = 'regular';
            elseif ($nota < 3.5) $classificacao = 'bom';
            elseif ($nota < 4.5) $classificacao = 'muito_bom';
            else $classificacao = 'excelente';

            $avaliador_id = $auth->getUserId();
            $data_assinatura = ($status === 'finalizada') ? date('Y-m-d H:i:s') : null;

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO avaliacoes
                    (funcionario_id, avaliador_id, periodo_inicio, periodo_fim, tipo, atendimento_cliente, conhecimento_tecnico,
                     pontualidade, trabalho_equipe, cumprimento_metas, proatividade, nota_final, classificacao,
                     pontos_fortes, pontos_melhoria, plano_desenvolvimento, status, data_assinatura_avaliador)
                    VALUES (:f, :a, :pi, :pf, :t, :ac, :ct, :pn, :te, :cm, :pr, :nf, :cl, :pf2, :pm, :pl, :st, :das)");
                $stmt->execute([
                    ':f' => $funcionario_id, ':a' => $avaliador_id, ':pi' => $periodo_inicio, ':pf' => $periodo_fim,
                    ':t' => $tipo, ':ac' => $atendimento, ':ct' => $conhecimento, ':pn' => $pontualidade,
                    ':te' => $equipe, ':cm' => $metas, ':pr' => $proatividade, ':nf' => $nota, ':cl' => $classificacao,
                    ':pf2' => $pontos_fortes, ':pm' => $pontos_melhoria, ':pl' => $plano, ':st' => $status, ':das' => $data_assinatura
                ]);
                $newId = $db->lastInsertId();
                Audit::create('avaliacao', $newId, "Avaliação criada para funcionário #$funcionario_id — nota $nota ($classificacao)");

                $f = $db->prepare("SELECT nome_completo FROM funcionarios WHERE id = :id");
                $f->execute([':id' => $funcionario_id]);
                $fnome = $f->fetchColumn();
                Notification::sendToRole('lider_farmaceutico', 'Nova Avaliação', "Nova avaliação de desempenho para $fnome", 'info', '/avaliacoes.php?action=view&id=' . $newId);

                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Avaliação registada!</strong> Nota final: ' . number_format($nota, 2) . ' (' . ucfirst(str_replace('_', ' ', $classificacao)) . ').</div></div>';
                $action = 'list';
            } else {
                $stmt = $db->prepare("UPDATE avaliacoes SET
                    funcionario_id=:f, periodo_inicio=:pi, periodo_fim=:pf, tipo=:t,
                    atendimento_cliente=:ac, conhecimento_tecnico=:ct, pontualidade=:pn,
                    trabalho_equipe=:te, cumprimento_metas=:cm, proatividade=:pr,
                    nota_final=:nf, classificacao=:cl, pontos_fortes=:pf2, pontos_melhoria=:pm,
                    plano_desenvolvimento=:pl, status=:st WHERE id=:id");
                $stmt->execute([
                    ':f' => $funcionario_id, ':pi' => $periodo_inicio, ':pf' => $periodo_fim, ':t' => $tipo,
                    ':ac' => $atendimento, ':ct' => $conhecimento, ':pn' => $pontualidade,
                    ':te' => $equipe, ':cm' => $metas, ':pr' => $proatividade, ':nf' => $nota, ':cl' => $classificacao,
                    ':pf2' => $pontos_fortes, ':pm' => $pontos_melhoria, ':pl' => $plano, ':st' => $status, ':id' => $id
                ]);
                Audit::update('avaliacao', $id, "Avaliação atualizada — nota $nota");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Avaliação atualizada!</strong></div></div>';
                $action = 'list';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $stmt = $db->prepare("DELETE FROM avaliacoes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            Audit::delete('avaliacao', $id, "Avaliação #$id eliminada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Avaliação eliminada!</strong></div></div>';
        } elseif ($action === 'sign' && $id > 0) {
            $comentarios = trim($_POST['comentarios_funcionario'] ?? '');
            $stmt = $db->prepare("UPDATE avaliacoes SET comentarios_funcionario=:c, data_assinatura_funcionario=NOW(), status='finalizada' WHERE id=:id");
            $stmt->execute([':c' => $comentarios, ':id' => $id]);
            Audit::update('avaliacao', $id, "Funcionário assinou avaliação");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Assinatura registada!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$funcionarios_list = $db->query("SELECT f.id, f.nome_completo, c.nome as cargo, d.nome as departamento
    FROM funcionarios f LEFT JOIN cargos c ON f.cargo_id = c.id LEFT JOIN departamentos d ON f.departamento_id = d.id
    WHERE f.status IN ('ativo','ferias','licenca') ORDER BY f.nome_completo")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='rascunho' THEN 1 ELSE 0 END) as rascunhos,
    SUM(CASE WHEN status='finalizada' THEN 1 ELSE 0 END) as finalizadas,
    AVG(nota_final) as media_geral
    FROM avaliacoes")->fetch();

$pageTitle = 'Avaliações de Desempenho';
$pageSubtitle = 'Avaliação 360° com 6 critérios técnicos e comportamentais';
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

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <a href="?action=create" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Nova Avaliação
                        </a>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-trophy"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Total de Avaliações</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-pencil-square"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['rascunhos']; ?></div>
                            <div class="stat-label">Em Rascunho</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['finalizadas']; ?></div>
                            <div class="stat-label">Finalizadas</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-bar-chart"></i></div>
                            <div class="stat-value"><?php echo $stats['media_geral'] ? number_format($stats['media_geral'], 2) : '—'; ?></div>
                            <div class="stat-label">Média Geral</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Tipo</th>
                                        <th>Período</th>
                                        <th>Nota</th>
                                        <th>Classificação</th>
                                        <th>Estado</th>
                                        <th>Data</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $avaliacoes = $db->query("SELECT a.*, f.nome_completo as func_nome, c.nome as cargo_nome
                                        FROM avaliacoes a
                                        JOIN funcionarios f ON a.funcionario_id = f.id
                                        LEFT JOIN cargos c ON f.cargo_id = c.id
                                        ORDER BY a.created_at DESC")->fetchAll();
                                    if (empty($avaliacoes)): ?>
                                        <tr><td colspan="8">
                                            <div class="empty-state">
                                                <i class="bi bi-trophy"></i>
                                                <h4>Sem avaliações</h4>
                                                <p>Crie a primeira avaliação de desempenho.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($avaliacoes as $av):
                                        $cls = ['insuficiente' => 'danger', 'regular' => 'warning', 'bom' => 'info', 'muito_bom' => 'primary', 'excelente' => 'success'][$av['classificacao']] ?? 'neutral';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <?php echo strtoupper(substr($av['func_nome'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($av['func_nome']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($av['cargo_nome'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-neutral"><?php echo ucfirst($av['tipo']); ?></span></td>
                                            <td><?php echo date('d/m/Y', strtotime($av['periodo_inicio'])) . ' → ' . date('d/m/Y', strtotime($av['periodo_fim'])); ?></td>
                                            <td><strong style="font-size: 1.1rem;"><?php echo number_format($av['nota_final'], 2); ?></strong><span style="color: var(--text-muted);">/5</span></td>
                                            <td><span class="badge badge-<?php echo $cls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $av['classificacao'])); ?></span></td>
                                            <td>
                                                <?php
                                                $stMap = ['rascunho' => 'warning', 'aguardando_assinatura' => 'info', 'finalizada' => 'success', 'cancelada' => 'neutral'];
                                                $stCls = $stMap[$av['status']] ?? 'neutral';
                                                ?>
                                                <span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $av['status'])); ?></span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($av['created_at'])); ?></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=view&id=<?php echo $av['id']; ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                                    <a href="?action=edit&id=<?php echo $av['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar esta avaliação?')) location.href='?action=delete&id=<?php echo $av['id']; ?>'">
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
                    $av = ['id' => 0, 'funcionario_id' => 0, 'periodo_inicio' => date('Y-m-d', strtotime('-3 months')), 'periodo_fim' => date('Y-m-d'),
                        'tipo' => 'trimestral', 'atendimento_cliente' => 3, 'conhecimento_tecnico' => 3, 'pontualidade' => 3,
                        'trabalho_equipe' => 3, 'cumprimento_metas' => 3, 'proatividade' => 3, 'pontos_fortes' => '',
                        'pontos_melhoria' => '', 'plano_desenvolvimento' => '', 'status' => 'rascunho'];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM avaliacoes WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $row = $stmt->fetch();
                        if ($row) $av = array_merge($av, $row);
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Nova' : 'Editar'; ?> Avaliação</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Avalie 6 critérios em escala 1-5</p>
                        </div>
                        <a href="avaliacoes.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 900px;">
                        <div class="card-body">
                            <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Funcionário <span class="required">*</span></label>
                                    <select name="funcionario_id" class="form-select" required>
                                        <option value="">— Selecione —</option>
                                        <?php foreach ($funcionarios_list as $f): ?>
                                            <option value="<?php echo $f['id']; ?>" <?php echo $av['funcionario_id'] == $f['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($f['nome_completo'] . ' — ' . ($f['cargo'] ?? 'Sem cargo')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <?php foreach (['trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual', 'experiencia' => 'Período Experimental'] as $k => $v): ?>
                                            <option value="<?php echo $k; ?>" <?php echo $av['tipo'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Início</label>
                                    <input type="date" name="periodo_inicio" class="form-control" required value="<?php echo $av['periodo_inicio']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Fim</label>
                                    <input type="date" name="periodo_fim" class="form-control" required value="<?php echo $av['periodo_fim']; ?>">
                                </div>
                            </div>

                            <h4 style="margin-top: 1.5rem; font-size: 1rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Critérios (escala 1-5)</h4>
                            <div class="rating-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                <?php
                                $criterios = [
                                    'atendimento_cliente' => ['label' => 'Atendimento ao Cliente', 'icon' => 'bi-people'],
                                    'conhecimento_tecnico' => ['label' => 'Conhecimento Técnico', 'icon' => 'bi-mortarboard'],
                                    'pontualidade' => ['label' => 'Pontualidade e Assiduidade', 'icon' => 'bi-clock'],
                                    'trabalho_equipe' => ['label' => 'Trabalho em Equipa', 'icon' => 'bi-people-fill'],
                                    'cumprimento_metas' => ['label' => 'Cumprimento de Metas', 'icon' => 'bi-bullseye'],
                                    'proatividade' => ['label' => 'Proatividade e Iniciativa', 'icon' => 'bi-lightbulb'],
                                ];
                                foreach ($criterios as $key => $c):
                                ?>
                                    <div class="form-group" style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm);">
                                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <i class="bi <?php echo $c['icon']; ?>" style="color: var(--primary);"></i>
                                            <?php echo $c['label']; ?>
                                        </label>
                                        <div class="rating-input" style="display: flex; gap: 0.25rem;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <label style="cursor: pointer; flex: 1;">
                                                    <input type="radio" name="<?php echo $key; ?>" value="<?php echo $i; ?>" style="display: none;" <?php echo (int)$av[$key] === $i ? 'checked' : ''; ?> required>
                                                    <span class="rating-star" data-value="<?php echo $i; ?>" style="display: block; text-align: center; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: var(--bg-surface); transition: var(--transition); <?php echo (int)$av[$key] === $i ? 'background: var(--primary); color: white; border-color: var(--primary);' : ''; ?>">
                                                        <strong><?php echo $i; ?></strong>
                                                    </span>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h4 style="margin-top: 1.5rem; font-size: 1rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Feedback Qualitativo</h4>
                            <div class="form-group">
                                <label class="form-label"><i class="bi bi-star-fill" style="color: var(--success);"></i> Pontos Fortes</label>
                                <textarea name="pontos_fortes" class="form-control" rows="2" placeholder="O que o funcionário faz bem?"><?php echo htmlspecialchars($av['pontos_fortes']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="bi bi-arrow-up-circle" style="color: var(--warning);"></i> Pontos de Melhoria</label>
                                <textarea name="pontos_melhoria" class="form-control" rows="2" placeholder="Aspetos a desenvolver..."><?php echo htmlspecialchars($av['pontos_melhoria']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="bi bi-clipboard-check" style="color: var(--info);"></i> Plano de Desenvolvimento</label>
                                <textarea name="plano_desenvolvimento" class="form-control" rows="2" placeholder="Ações concretas de formação, acompanhamento, metas..."><?php echo htmlspecialchars($av['plano_desenvolvimento']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="rascunho" <?php echo $av['status'] === 'rascunho' ? 'selected' : ''; ?>>Rascunho (editável)</option>
                                    <option value="aguardando_assinatura" <?php echo $av['status'] === 'aguardando_assinatura' ? 'selected' : ''; ?>>Aguarda Assinatura do Funcionário</option>
                                    <option value="finalizada" <?php echo $av['status'] === 'finalizada' ? 'selected' : ''; ?>>Finalizada (ass. do avaliador)</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="avaliacoes.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar Avaliação</button>
                        </div>
                    </form>

                <?php elseif ($action === 'view' && $id > 0):
                    $stmt = $db->prepare("SELECT a.*, f.nome_completo as func_nome, f.email as func_email, f.bi as func_bi,
                        c.nome as cargo_nome, d.nome as dept_nome, av.username as aval_nome
                        FROM avaliacoes a
                        JOIN funcionarios f ON a.funcionario_id = f.id
                        LEFT JOIN cargos c ON f.cargo_id = c.id
                        LEFT JOIN departamentos d ON f.departamento_id = d.id
                        LEFT JOIN usuarios av ON a.avaliador_id = av.id
                        WHERE a.id = :id");
                    $stmt->execute([':id' => $id]);
                    $av = $stmt->fetch();
                    if (!$av) {
                        echo '<div class="alert alert-danger">Avaliação não encontrada.</div>';
                    } else {
                        $cls = ['insuficiente' => 'danger', 'regular' => 'warning', 'bom' => 'info', 'muito_bom' => 'primary', 'excelente' => 'success'][$av['classificacao']] ?? 'neutral';
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Avaliação #<?php echo $av['id']; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                                <?php echo ucfirst($av['tipo']); ?> · <?php echo date('d/m/Y', strtotime($av['periodo_inicio'])) . ' → ' . date('d/m/Y', strtotime($av['periodo_fim'])); ?>
                            </p>
                        </div>
                        <a href="avaliacoes.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                <div>
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Funcionário</h4>
                                    <p style="font-size: 1.1rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($av['func_nome']); ?></p>
                                    <p style="color: var(--text-muted); margin: 0.25rem 0;"><?php echo htmlspecialchars($av['cargo_nome'] ?? ''); ?> · <?php echo htmlspecialchars($av['dept_nome'] ?? ''); ?></p>
                                    <p style="font-size: 0.85rem; color: var(--text-muted);">BI: <?php echo htmlspecialchars($av['func_bi']); ?></p>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 3rem; font-weight: 800; color: var(--primary); line-height: 1;"><?php echo number_format($av['nota_final'], 2); ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.85rem;">/5.00</div>
                                    <span class="badge badge-<?php echo $cls; ?>" style="margin-top: 0.5rem;"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $av['classificacao'])); ?></span>
                                </div>
                            </div>

                            <hr style="border-color: var(--border-color); margin: 1.5rem 0;">

                            <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 1rem;">Critérios</h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                                <?php
                                $items = [
                                    'atendimento_cliente' => 'Atendimento ao Cliente',
                                    'conhecimento_tecnico' => 'Conhecimento Técnico',
                                    'pontualidade' => 'Pontualidade',
                                    'trabalho_equipe' => 'Trabalho em Equipa',
                                    'cumprimento_metas' => 'Cumprimento de Metas',
                                    'proatividade' => 'Proatividade'
                                ];
                                foreach ($items as $k => $lbl):
                                    $v = (int)$av[$k];
                                    $pct = $v * 20;
                                ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                            <span style="font-size: 0.875rem;"><?php echo $lbl; ?></span>
                                            <strong><?php echo $v; ?>/5</strong>
                                        </div>
                                        <div style="height: 8px; background: var(--bg-input); border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; background: var(--primary); width: <?php echo $pct; ?>%; transition: width 0.5s;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($av['pontos_fortes']): ?>
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin: 1.5rem 0 0.5rem;"><i class="bi bi-star-fill" style="color: var(--success);"></i> Pontos Fortes</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0;"><?php echo nl2br(htmlspecialchars($av['pontos_fortes'])); ?></p>
                            <?php endif; ?>
                            <?php if ($av['pontos_melhoria']): ?>
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin: 1rem 0 0.5rem;"><i class="bi bi-arrow-up-circle" style="color: var(--warning);"></i> Pontos de Melhoria</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0;"><?php echo nl2br(htmlspecialchars($av['pontos_melhoria'])); ?></p>
                            <?php endif; ?>
                            <?php if ($av['plano_desenvolvimento']): ?>
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin: 1rem 0 0.5rem;"><i class="bi bi-clipboard-check" style="color: var(--info);"></i> Plano de Desenvolvimento</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0;"><?php echo nl2br(htmlspecialchars($av['plano_desenvolvimento'])); ?></p>
                            <?php endif; ?>

                            <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                                <strong>Avaliador:</strong> <?php echo htmlspecialchars($av['aval_nome'] ?? '—'); ?> ·
                                <strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($av['created_at'])); ?>
                                <?php if ($av['data_assinatura_funcionario']): ?>
                                    · <strong>Assinado em:</strong> <?php echo date('d/m/Y H:i', strtotime($av['data_assinatura_funcionario'])); ?>
                                <?php endif; ?>
                            </p>

                            <?php if ($av['status'] === 'aguardando_assinatura' && $av['comentarios_funcionario'] === null): ?>
                                <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
                                <form method="POST" action="?action=sign&id=<?php echo $id; ?>">
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Assinatura do Funcionário</h4>
                                    <textarea name="comentarios_funcionario" class="form-control" rows="3" placeholder="Comentários (opcional)"></textarea>
                                    <button type="submit" class="btn btn-success mt-2"><i class="bi bi-pen"></i> Assinar Avaliação</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php } endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
    <script>
    document.querySelectorAll('.rating-input').forEach(group => {
        group.querySelectorAll('label').forEach(label => {
            label.addEventListener('click', () => {
                group.querySelectorAll('span.rating-star').forEach(s => s.style.cssText = 'display:block;text-align:center;padding:0.5rem;border:1px solid var(--border-color);border-radius:var(--radius-sm);background:var(--bg-surface);');
                const span = label.querySelector('span.rating-star');
                span.style.cssText = 'display:block;text-align:center;padding:0.5rem;border:1px solid var(--primary);border-radius:var(--radius-sm);background:var(--primary);color:white;';
            });
        });
    });
    </script>
</body>
</html>
