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
            $titulo = trim($_POST['titulo'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $instrutor = trim($_POST['instrutor'] ?? '');
            $instituicao = trim($_POST['instituicao'] ?? '');
            $data_inicio = $_POST['data_inicio'] ?? '';
            $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
            $duracao_horas = !empty($_POST['duracao_horas']) ? (float)$_POST['duracao_horas'] : null;
            $local = trim($_POST['local'] ?? '');
            $tipo = $_POST['tipo'] ?? 'tecnico';
            $custo = !empty($_POST['custo']) ? (float)$_POST['custo'] : 0;
            $vagas = !empty($_POST['vagas_disponiveis']) ? (int)$_POST['vagas_disponiveis'] : null;
            $status = $_POST['status'] ?? 'planejado';

            if (empty($titulo)) throw new Exception('Título é obrigatório.');
            if (empty($data_inicio)) throw new Exception('Data de início é obrigatória.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO treinamentos
                    (titulo, descricao, instrutor, instituicao, data_inicio, data_fim, duracao_horas, local, tipo, custo, vagas_disponiveis, status)
                    VALUES (:t, :d, :i, :ins, :di, :df, :dh, :l, :tp, :c, :v, :s)");
                $stmt->execute([
                    ':t' => $titulo, ':d' => $descricao, ':i' => $instrutor, ':ins' => $instituicao,
                    ':di' => $data_inicio, ':df' => $data_fim, ':dh' => $duracao_horas, ':l' => $local,
                    ':tp' => $tipo, ':c' => $custo, ':v' => $vagas, ':s' => $status
                ]);
                $newId = $db->lastInsertId();
                Audit::create('treinamento', $newId, "Treinamento criado: $titulo");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Treinamento criado!</strong> Pode agora inscrever participantes.</div></div>';
                $action = 'view';
                $id = $newId;
            } else {
                $stmt = $db->prepare("UPDATE treinamentos SET
                    titulo=:t, descricao=:d, instrutor=:i, instituicao=:ins, data_inicio=:di, data_fim=:df,
                    duracao_horas=:dh, local=:l, tipo=:tp, custo=:c, vagas_disponiveis=:v, status=:s
                    WHERE id=:id");
                $stmt->execute([
                    ':t' => $titulo, ':d' => $descricao, ':i' => $instrutor, ':ins' => $instituicao,
                    ':di' => $data_inicio, ':df' => $data_fim, ':dh' => $duracao_horas, ':l' => $local,
                    ':tp' => $tipo, ':c' => $custo, ':v' => $vagas, ':s' => $status, ':id' => $id
                ]);
                Audit::update('treinamento', $id, "Treinamento atualizado: $titulo");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Treinamento atualizado!</strong></div></div>';
                $action = 'view';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM treinamentos WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('treinamento', $id, "Treinamento #$id eliminado");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Treinamento eliminado!</strong></div></div>';
            $action = 'list';
        } elseif ($action === 'enroll' && $id > 0) {
            $funcionario_id = (int)$_POST['funcionario_id'];
            $check = $db->prepare("SELECT COUNT(*) FROM participacoes_treinamento WHERE treinamento_id = :t AND funcionario_id = :f");
            $check->execute([':t' => $id, ':f' => $funcionario_id]);
            if ($check->fetchColumn() > 0) throw new Exception('Funcionário já inscrito.');
            $stmt = $db->prepare("INSERT INTO participacoes_treinamento (treinamento_id, funcionario_id) VALUES (:t, :f)");
            $stmt->execute([':t' => $id, ':f' => $funcionario_id]);

            $f = $db->prepare("SELECT nome_completo, usuario_id FROM funcionarios WHERE id = :id");
            $f->execute([':id' => $funcionario_id]);
            $fnome = $f->fetch();
            
            $tStmt = $db->prepare("SELECT titulo, data_inicio FROM treinamentos WHERE id = :id");
            $tStmt->execute([':id' => $id]);
            $treinamento = $tStmt->fetch();
            
            if ($fnome && $fnome['usuario_id'] && $treinamento) {
                Notification::send($fnome['usuario_id'], 'Inscrição em Treinamento', "Foi inscrito(a) em: " . $treinamento['titulo'] . " (" . $treinamento['data_inicio'] . ")", 'info', '/treinamentos.php?action=view&id=' . $id);
            }
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Participante inscrito!</strong></div></div>';
            $action = 'view';
        } elseif ($action === 'attendance' && $id > 0) {
            $part_id = (int)$_POST['participacao_id'];
            $presente = isset($_POST['presente']) ? 1 : 0;
            $nota = !empty($_POST['nota']) ? (float)$_POST['nota'] : null;
            $horas_cpd = !empty($_POST['horas_cpd']) ? (float)$_POST['horas_cpd'] : null;
            $aprovado = isset($_POST['aprovado']) ? 1 : 0;
            $observacoes = trim($_POST['observacoes'] ?? '');

            $stmt = $db->prepare("UPDATE participacoes_treinamento SET presente=:p, nota=:n, horas_cpd=:h, aprovado=:a, observacoes=:o WHERE id=:id AND treinamento_id=:t");
            $stmt->execute([':p' => $presente, ':n' => $nota, ':h' => $horas_cpd, ':a' => $aprovado, ':o' => $observacoes, ':id' => $part_id, ':t' => $id]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Registo atualizado!</strong></div></div>';
            $action = 'view';
        } elseif ($action === 'remove_part' && $id > 0) {
            $part_id = (int)$_POST['participacao_id'];
            $db->prepare("DELETE FROM participacoes_treinamento WHERE id = :id AND treinamento_id = :t")->execute([':id' => $part_id, ':t' => $id]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Participante removido!</strong></div></div>';
            $action = 'view';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$funcionarios_list = $db->query("SELECT f.id, f.nome_completo, c.nome as cargo FROM funcionarios f
    LEFT JOIN cargos c ON f.cargo_id = c.id
    WHERE f.status IN ('ativo','ferias','licenca') ORDER BY f.nome_completo")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='planejado' THEN 1 ELSE 0 END) as planejados,
    SUM(CASE WHEN status='em_andamento' THEN 1 ELSE 0 END) as em_andamento,
    SUM(CASE WHEN status='concluido' THEN 1 ELSE 0 END) as concluidos,
    SUM(custo) as custo_total,
    SUM(duracao_horas) as horas_total
    FROM treinamentos")->fetch();

$pageTitle = 'Treinamentos e Capacitação';
$pageSubtitle = 'Gestão de formações com créditos CPD farmacêuticos';
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
                            <i class="bi bi-plus-lg"></i> Novo Treinamento
                        </a>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-mortarboard"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Treinamentos</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['planejados']; ?></div>
                            <div class="stat-label">Planejados</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-play-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['em_andamento']; ?></div>
                            <div class="stat-label">Em Andamento</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['concluidos']; ?></div>
                            <div class="stat-label">Concluídos</div>
                        </div>
                        <div class="stat-card stat-card-neutral">
                            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                            <div class="stat-value"><?php echo $stats['horas_total'] ? number_format($stats['horas_total'], 0) : '0'; ?>h</div>
                            <div class="stat-label">Carga Horária Total</div>
                        </div>
                        <div class="stat-card stat-card-neutral">
                            <div class="stat-icon"><i class="bi bi-cash"></i></div>
                            <div class="stat-value"><?php echo kz($stats['custo_total'] ?? 0); ?></div>
                            <div class="stat-label">Investimento Total</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Título</th>
                                        <th>Tipo</th>
                                        <th>Instrutor</th>
                                        <th>Período</th>
                                        <th>Inscritos</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $treinamentos = $db->query("SELECT t.*, COUNT(p.id) as total_inscritos
                                        FROM treinamentos t LEFT JOIN participacoes_treinamento p ON t.id = p.treinamento_id
                                        GROUP BY t.id ORDER BY t.data_inicio DESC")->fetchAll();
                                    if (empty($treinamentos)): ?>
                                        <tr><td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-mortarboard"></i>
                                                <h4>Sem treinamentos</h4>
                                                <p>Agende o primeiro treinamento da equipa.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($treinamentos as $t):
                                        $stMap = ['planejado' => 'info', 'em_andamento' => 'warning', 'concluido' => 'success', 'cancelado' => 'danger'];
                                        $stCls = $stMap[$t['status']] ?? 'neutral';
                                        $tpMap = ['cpd_farmacia' => 'primary', 'tecnico' => 'info', 'comportamental' => 'warning', 'seguranca' => 'danger', 'obrigatorio' => 'danger', 'outro' => 'neutral'];
                                        $tpCls = $tpMap[$t['tipo']] ?? 'neutral';
                                    ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <i class="bi bi-mortarboard"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($t['titulo']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                            <?php echo $t['duracao_horas'] ? number_format($t['duracao_horas'], 1) . 'h' : ''; ?>
                                                            <?php echo $t['local'] ? ' · ' . htmlspecialchars($t['local']) : ''; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-<?php echo $tpCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $t['tipo'])); ?></span></td>
                                            <td><?php echo htmlspecialchars($t['instrutor'] ?? '—'); ?></td>
                                            <td>
                                                <?php echo date('d/m/Y', strtotime($t['data_inicio'])); ?>
                                                <?php if ($t['data_fim']): ?><br><small style="color: var(--text-muted);">→ <?php echo date('d/m/Y', strtotime($t['data_fim'])); ?></small><?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-neutral">
                                                    <i class="bi bi-people"></i> <?php echo (int)$t['total_inscritos']; ?>
                                                    <?php if ($t['vagas_disponiveis']): ?> / <?php echo (int)$t['vagas_disponiveis']; ?><?php endif; ?>
                                                </span>
                                            </td>
                                            <td><span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=view&id=<?php echo $t['id']; ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                                    <a href="?action=edit&id=<?php echo $t['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar este treinamento?')) location.href='?action=delete&id=<?php echo $t['id']; ?>'">
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
                    $t = ['id' => 0, 'titulo' => '', 'descricao' => '', 'instrutor' => '', 'instituicao' => '',
                        'data_inicio' => date('Y-m-d'), 'data_fim' => '', 'duracao_horas' => '', 'local' => '',
                        'tipo' => 'tecnico', 'custo' => '0', 'vagas_disponiveis' => '', 'status' => 'planejado'];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM treinamentos WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $row = $stmt->fetch();
                        if ($row) $t = array_merge($t, $row);
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Treinamento</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Agende formações, workshops ou seminários</p>
                        </div>
                        <a href="treinamentos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 800px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Título <span class="required">*</span></label>
                                <input type="text" name="titulo" class="form-control" required value="<?php echo htmlspecialchars($t['titulo']); ?>" placeholder="Ex: Atualização em Farmacologia Cardiovascular">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="3" placeholder="Objetivos, programa, pré-requisitos..."><?php echo htmlspecialchars($t['descricao']); ?></textarea>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Instrutor/Formador</label>
                                    <input type="text" name="instrutor" class="form-control" value="<?php echo htmlspecialchars($t['instrutor']); ?>" placeholder="Nome do formador">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instituição</label>
                                    <input type="text" name="instituicao" class="form-control" value="<?php echo htmlspecialchars($t['instituicao']); ?>" placeholder="Ex: Universidade Agostinho Neto">
                                </div>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Data Início <span class="required">*</span></label>
                                    <input type="date" name="data_inicio" class="form-control" required value="<?php echo $t['data_inicio']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Fim</label>
                                    <input type="date" name="data_fim" class="form-control" value="<?php echo $t['data_fim']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Duração (horas)</label>
                                    <input type="number" step="0.5" min="0" name="duracao_horas" class="form-control" value="<?php echo $t['duracao_horas']; ?>" placeholder="Ex: 8">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Local</label>
                                <input type="text" name="local" class="form-control" value="<?php echo htmlspecialchars($t['local']); ?>" placeholder="Sala de formação, plataforma online, etc.">
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="cpd_farmacia" <?php echo $t['tipo'] === 'cpd_farmacia' ? 'selected' : ''; ?>>CPD Farmácia (créditos farmacêuticos)</option>
                                        <option value="tecnico" <?php echo $t['tipo'] === 'tecnico' ? 'selected' : ''; ?>>Técnico</option>
                                        <option value="comportamental" <?php echo $t['tipo'] === 'comportamental' ? 'selected' : ''; ?>>Comportamental / Soft Skills</option>
                                        <option value="seguranca" <?php echo $t['tipo'] === 'seguranca' ? 'selected' : ''; ?>>Segurança e Saúde no Trabalho</option>
                                        <option value="obrigatorio" <?php echo $t['tipo'] === 'obrigatorio' ? 'selected' : ''; ?>>Obrigatório (legal/regulamentar)</option>
                                        <option value="outro" <?php echo $t['tipo'] === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Custo (Kz)</label>
                                    <input type="number" step="0.01" min="0" name="custo" class="form-control" value="<?php echo $t['custo']; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Vagas</label>
                                    <input type="number" min="0" name="vagas_disponiveis" class="form-control" value="<?php echo $t['vagas_disponiveis']; ?>" placeholder="Ilimitado">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-select">
                                    <option value="planejado" <?php echo $t['status'] === 'planejado' ? 'selected' : ''; ?>>Planejado</option>
                                    <option value="em_andamento" <?php echo $t['status'] === 'em_andamento' ? 'selected' : ''; ?>>Em Andamento</option>
                                    <option value="concluido" <?php echo $t['status'] === 'concluido' ? 'selected' : ''; ?>>Concluído</option>
                                    <option value="cancelado" <?php echo $t['status'] === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="treinamentos.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                        </div>
                    </form>

                <?php elseif ($action === 'view' && $id > 0):
                    $stmt = $db->prepare("SELECT * FROM treinamentos WHERE id = :id");
                    $stmt->execute([':id' => $id]);
                    $t = $stmt->fetch();
                    if (!$t) {
                        echo '<div class="alert alert-danger">Treinamento não encontrado.</div>';
                    } else {
                        $participantes = $db->prepare("SELECT p.*, f.nome_completo, f.bi, c.nome as cargo_nome
                            FROM participacoes_treinamento p
                            JOIN funcionarios f ON p.funcionario_id = f.id
                            LEFT JOIN cargos c ON f.cargo_id = c.id
                            WHERE p.treinamento_id = :t ORDER BY p.data_inscricao");
                        $participantes->execute([':t' => $id]);
                        $participantes = $participantes->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($t['titulo']); ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $t['tipo'])); ?> ·
                                <?php echo date('d/m/Y', strtotime($t['data_inicio'])); ?>
                                <?php if ($t['data_fim']): ?> → <?php echo date('d/m/Y', strtotime($t['data_fim'])); ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?action=edit&id=<?php echo $id; ?>" class="btn btn-secondary"><i class="bi bi-pencil"></i> Editar</a>
                            <a href="treinamentos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="form-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Duração</div>
                                    <div style="font-size: 1.1rem; font-weight: 700;"><?php echo $t['duracao_horas'] ? number_format($t['duracao_horas'], 1) . 'h' : '—'; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Instrutor</div>
                                    <div style="font-size: 1.1rem; font-weight: 700;"><?php echo htmlspecialchars($t['instrutor'] ?? '—'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Vagas</div>
                                    <div style="font-size: 1.1rem; font-weight: 700;"><?php echo count($participantes); ?><?php echo $t['vagas_disponiveis'] ? ' / ' . $t['vagas_disponiveis'] : ''; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Estado</div>
                                    <?php
                                    $stMap = ['planejado' => 'info', 'em_andamento' => 'warning', 'concluido' => 'success', 'cancelado' => 'danger'];
                                    $stCls = $stMap[$t['status']] ?? 'neutral';
                                    ?>
                                    <span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?></span>
                                </div>
                            </div>
                            <?php if ($t['descricao']): ?>
                                <hr style="border-color: var(--border-color); margin: 1rem 0;">
                                <p style="margin: 0; color: var(--text-secondary);"><?php echo nl2br(htmlspecialchars($t['descricao'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-people"></i> Participantes (<?php echo count($participantes); ?>)</h3>
                            <button class="btn btn-primary btn-sm" onclick="document.getElementById('enrollForm').style.display='block'">
                                <i class="bi bi-person-plus"></i> Inscrever
                            </button>
                        </div>
                        <div class="card-body" id="enrollForm" style="display: none; background: var(--bg-input); margin: 1rem; border-radius: var(--radius-sm);">
                            <form method="POST" action="?action=enroll&id=<?php echo $id; ?>" style="display: flex; gap: 0.5rem;">
                                <select name="funcionario_id" class="form-select" required style="flex: 1;">
                                    <option value="">— Selecione um funcionário —</option>
                                    <?php
                                    $inscritos_ids = array_column($participantes, 'funcionario_id');
                                    foreach ($funcionarios_list as $f) {
                                        if (in_array($f['id'], $inscritos_ids)) continue;
                                        echo '<option value="' . $f['id'] . '">' . htmlspecialchars($f['nome_completo'] . ' — ' . ($f['cargo'] ?? '')) . '</option>';
                                    }
                                    ?>
                                </select>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-plus"></i> Inscrever</button>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Estado</th>
                                        <th>Presença</th>
                                        <th>Nota</th>
                                        <th>Horas CPD</th>
                                        <th>Observações</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($participantes)): ?>
                                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Nenhum participante inscrito.</td></tr>
                                    <?php else: foreach ($participantes as $p): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <?php echo strtoupper(substr($p['nome_completo'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($p['nome_completo']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($p['cargo_nome'] ?? ''); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($p['aprovado'] === 1): ?>
                                                    <span class="badge badge-success"><i class="bi bi-check-circle"></i> Aprovado</span>
                                                <?php elseif ($p['aprovado'] === '0' || $p['aprovado'] === 0): ?>
                                                    <span class="badge badge-danger"><i class="bi bi-x-circle"></i> Reprovado</span>
                                                <?php else: ?>
                                                    <span class="badge badge-neutral">Pendente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($p['presente']): ?>
                                                    <span class="badge badge-success">Presente</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Ausente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo $p['nota'] !== null ? number_format($p['nota'], 2) : '—'; ?></strong></td>
                                            <td><?php echo $p['horas_cpd'] ? number_format($p['horas_cpd'], 1) . 'h' : '—'; ?></td>
                                            <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($p['observacoes'] ?? ''); ?></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <button class="btn btn-icon btn-secondary" title="Editar presença" onclick="document.getElementById('att_<?php echo $p['id']; ?>').style.display='table-row'">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="?action=remove_part&id=<?php echo $id; ?>" style="display: inline;" onsubmit="return confirm('Remover participante?')">
                                                        <input type="hidden" name="participacao_id" value="<?php echo $p['id']; ?>">
                                                        <button class="btn btn-icon btn-secondary" title="Remover"><i class="bi bi-trash" style="color: var(--danger);"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id="att_<?php echo $p['id']; ?>" style="display: none; background: var(--bg-input);">
                                            <td colspan="7">
                                                <form method="POST" action="?action=attendance&id=<?php echo $id; ?>" style="display: grid; grid-template-columns: auto 1fr 1fr 1fr 2fr auto; gap: 0.5rem; align-items: end;">
                                                    <input type="hidden" name="participacao_id" value="<?php echo $p['id']; ?>">
                                                    <div>
                                                        <label class="form-label" style="font-size: 0.75rem;">Presente?</label>
                                                        <input type="checkbox" name="presente" <?php echo $p['presente'] ? 'checked' : ''; ?>>
                                                    </div>
                                                    <div>
                                                        <label class="form-label" style="font-size: 0.75rem;">Nota (0-10)</label>
                                                        <input type="number" step="0.01" min="0" max="10" name="nota" class="form-control" value="<?php echo $p['nota']; ?>">
                                                    </div>
                                                    <div>
                                                        <label class="form-label" style="font-size: 0.75rem;">Horas CPD</label>
                                                        <input type="number" step="0.5" min="0" name="horas_cpd" class="form-control" value="<?php echo $p['horas_cpd']; ?>">
                                                    </div>
                                                    <div>
                                                        <label class="form-label" style="font-size: 0.75rem;">Aprovado?</label>
                                                        <input type="checkbox" name="aprovado" <?php echo $p['aprovado'] ? 'checked' : ''; ?>>
                                                    </div>
                                                    <div>
                                                        <label class="form-label" style="font-size: 0.75rem;">Observações</label>
                                                        <input type="text" name="observacoes" class="form-control" value="<?php echo htmlspecialchars($p['observacoes'] ?? ''); ?>">
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check"></i></button>
                                                </form>
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
