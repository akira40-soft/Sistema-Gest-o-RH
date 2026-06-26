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
$vaga_id = isset($_GET['vaga_id']) ? (int)$_GET['vaga_id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'update') {
            $cand_id = (int)($_POST['candidato_id'] ?? 0);
            $status = $_POST['status'] ?? 'nova';
            $pontuacao = !empty($_POST['pontuacao']) ? (int)$_POST['pontuacao'] : null;
            $observacoes = trim($_POST['observacoes'] ?? '');
            $data_entrevista = !empty($_POST['data_entrevista']) ? $_POST['data_entrevista'] . ':00' : null;

            $stmt = $db->prepare("UPDATE candidaturas SET status=:s, pontuacao=:p, observacoes=:o, data_entrevista=:de, entrevistado_por=:ep WHERE id=:id");
            $stmt->execute([':s' => $status, ':p' => $pontuacao, ':o' => $observacoes, ':de' => $data_entrevista, ':ep' => $user_id, ':id' => $cand_id]);
            Audit::update('candidatura', $cand_id, "Candidatura atualizada para: $status" . ($pontuacao !== null ? " (nota: $pontuacao)" : ''));
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Candidatura atualizada!</strong></div></div>';
            $action = 'view';
            $id = $cand_id;
        } elseif ($action === 'contratar' && $id > 0) {
            $db->prepare("UPDATE candidaturas SET status='contratada' WHERE id=:id")->execute([':id' => $id]);
            Audit::update('candidatura', $id, "Candidato marcado como contratado");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Candidato marcado como contratado! Use Admissão para criar o registo de funcionário.</strong></div></div>';
        } elseif ($action === 'delete' && $id > 0) {
            $db->prepare("DELETE FROM candidaturas WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('candidatura', $id, "Candidatura #$id eliminada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Candidatura removida!</strong></div></div>';
            $action = 'list';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='nova' THEN 1 ELSE 0 END) as novas,
    SUM(CASE WHEN status='em_analise' THEN 1 ELSE 0 END) as em_analise,
    SUM(CASE WHEN status='pre_selecionada' THEN 1 ELSE 0 END) as pre_selecionadas,
    SUM(CASE WHEN status='entrevista_agendada' THEN 1 ELSE 0 END) as entrevistas,
    SUM(CASE WHEN status='aprovada' THEN 1 ELSE 0 END) as aprovadas,
    SUM(CASE WHEN status='rejeitada' THEN 1 ELSE 0 END) as rejeitadas
    FROM candidaturas")->fetch();

$pageTitle = 'Candidatos';
$pageSubtitle = 'Pipeline de recrutamento e seleção de candidatos';
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
                        <li class="breadcrumb-item"><a href="vagas.php">Vagas</a></li>
                        <li class="breadcrumb-item active">Candidatos</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'):
                    $where = "WHERE 1=1";
                    $params = [];
                    if ($vaga_id) {
                        $where .= " AND c.vaga_id = :v";
                        $params[':v'] = $vaga_id;
                    }
                    if (!empty($_GET['status'])) {
                        $where .= " AND c.status = :s";
                        $params[':s'] = $_GET['status'];
                    }
                    $stmt = $db->prepare("SELECT c.*, v.titulo as vaga_titulo, cg.nome as cargo_nome
                        FROM candidaturas c
                        JOIN vagas v ON c.vaga_id = v.id
                        LEFT JOIN cargos cg ON v.cargo_id = cg.id
                        $where ORDER BY
                            CASE c.status WHEN 'nova' THEN 1 WHEN 'em_analise' THEN 2 WHEN 'pre_selecionada' THEN 3
                                WHEN 'entrevista_agendada' THEN 4 WHEN 'aprovada' THEN 5 WHEN 'contratada' THEN 6
                                WHEN 'rejeitada' THEN 7 ELSE 8 END,
                            c.pontuacao DESC,
                            c.data_candidatura DESC");
                    $stmt->execute($params);
                    $candidatos = $stmt->fetchAll();

                    $vagas_all = $db->query("SELECT id, titulo FROM vagas WHERE status IN ('aberta','em_andamento') ORDER BY data_abertura DESC")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <a href="vagas.php" class="btn btn-secondary"><i class="bi bi-briefcase"></i> Ver Vagas</a>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-inbox"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['novas']; ?></div>
                            <div class="stat-label">Novas</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-search"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['em_analise']; ?></div>
                            <div class="stat-label">Em Análise</div>
                        </div>
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-star"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['pre_selecionadas']; ?></div>
                            <div class="stat-label">Pré-selecionados</div>
                        </div>
                        <div class="stat-card stat-card-info">
                            <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['entrevistas']; ?></div>
                            <div class="stat-label">Em Entrevista</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['aprovadas']; ?></div>
                            <div class="stat-label">Aprovados</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['rejeitadas']; ?></div>
                            <div class="stat-label">Rejeitados</div>
                        </div>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="flex: 1; min-width: 240px;">
                            <select name="vaga_id" class="form-select">
                                <option value="">Todas as vagas</option>
                                <?php foreach ($vagas_all as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo $vaga_id == $v['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['titulo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <select name="status" class="form-select">
                                <option value="">Todos os estados</option>
                                <?php foreach (['nova' => 'Nova', 'em_analise' => 'Em Análise', 'pre_selecionada' => 'Pré-selecionada', 'entrevista_agendada' => 'Entrevista Agendada', 'aprovada' => 'Aprovada', 'contratada' => 'Contratada', 'rejeitada' => 'Rejeitada'] as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo (isset($_GET['status']) && $_GET['status'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="candidatos.php" class="btn btn-ghost" title="Limpar"><i class="bi bi-x-circle"></i></a>
                    </form>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Contacto</th>
                                        <th>Experiência</th>
                                        <th>Disponibilidade</th>
                                        <th>Pontuação</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($candidatos)): ?>
                                        <tr><td colspan="8">
                                            <div class="empty-state">
                                                <i class="bi bi-person-x"></i>
                                                <h4>Sem candidatos</h4>
                                                <p>Nenhuma candidatura corresponde ao filtro.</p>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($candidatos as $c):
                                        $stMap = ['nova' => 'info', 'em_analise' => 'warning', 'pre_selecionada' => 'primary', 'entrevista_agendada' => 'primary', 'aprovada' => 'success', 'contratada' => 'success', 'rejeitada' => 'danger'];
                                        $stCls = $stMap[$c['status']] ?? 'neutral';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <?php echo strtoupper(substr($c['nome_completo'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($c['nome_completo']); ?></strong>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Candidatura: <?php echo date('d/m/Y', strtotime($c['data_candidatura'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem;">
                                                    <strong><?php echo htmlspecialchars($c['vaga_titulo']); ?></strong>
                                                    <div style="color: var(--text-muted);"><?php echo htmlspecialchars($c['cargo_nome'] ?? ''); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.85rem;">
                                                    <div><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($c['email']); ?></div>
                                                    <div style="color: var(--text-muted);"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($c['telefone'] ?? '—'); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo $c['experiencia_anos'] !== null ? $c['experiencia_anos'] . ' anos' : '—'; ?></td>
                                            <td><span class="badge badge-neutral"><?php echo ucfirst(str_replace('_', ' ', $c['disponibilidade'])); ?></span></td>
                                            <td>
                                                <?php if ($c['pontuacao'] !== null): ?>
                                                    <span class="badge badge-info"><?php echo (int)$c['pontuacao']; ?>/10</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge badge-<?php echo $stCls; ?>"><span class="badge-dot"></span> <?php echo ucfirst(str_replace('_', ' ', $c['status'])); ?></span></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="?action=view&id=<?php echo $c['id']; ?>" class="btn btn-icon btn-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                                    <a href="mailto:<?php echo urlencode($c['email']); ?>" class="btn btn-icon btn-secondary" title="Email"><i class="bi bi-envelope"></i></a>
                                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar?')) location.href='?action=delete&id=<?php echo $c['id']; ?>'">
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

                <?php elseif (($action === 'view' || $action === 'update') && $id > 0):
                    $stmt = $db->prepare("SELECT c.*, v.titulo as vaga_titulo, v.cargo_id, v.departamento_id, cg.nome as cargo_nome
                        FROM candidaturas c
                        JOIN vagas v ON c.vaga_id = v.id
                        LEFT JOIN cargos cg ON v.cargo_id = cg.id
                        WHERE c.id = :id");
                    $stmt->execute([':id' => $id]);
                    $c = $stmt->fetch();
                    if (!$c) {
                        echo '<div class="alert alert-danger">Candidatura não encontrada.</div>';
                    } else {
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($c['nome_completo']); ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">
                                Candidato(a) a: <strong><?php echo htmlspecialchars($c['vaga_titulo']); ?></strong>
                            </p>
                        </div>
                        <a href="candidatos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div>
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Contacto</h4>
                                    <p style="margin: 0.25rem 0;"><i class="bi bi-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><?php echo htmlspecialchars($c['email']); ?></a></p>
                                    <p style="margin: 0.25rem 0;"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($c['telefone'] ?? '—'); ?></p>
                                    <?php if ($c['data_nascimento']): ?>
                                        <p style="margin: 0.25rem 0;"><i class="bi bi-calendar"></i> <?php echo date('d/m/Y', strtotime($c['data_nascimento'])); ?> (<?php
                                            $age = (new DateTime($c['data_nascimento']))->diff(new DateTime('now'))->y;
                                            echo $age;
                                        ?> anos)</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Perfil Profissional</h4>
                                    <p style="margin: 0.25rem 0;"><strong>Experiência:</strong> <?php echo $c['experiencia_anos'] !== null ? $c['experiencia_anos'] . ' anos' : '—'; ?></p>
                                    <p style="margin: 0.25rem 0;"><strong>Disponibilidade:</strong> <?php echo ucfirst(str_replace('_', ' ', $c['disponibilidade'])); ?></p>
                                    <?php if ($c['pretensao_salarial']): ?>
                                        <p style="margin: 0.25rem 0;"><strong>Pretensão:</strong> <?php echo kz($c['pretensao_salarial']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($c['carta_motivacao']): ?>
                                <hr style="border-color: var(--border-color); margin: 1rem 0;">
                                <h4 style="font-size: 0.875rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">Carta de Motivação</h4>
                                <p style="background: var(--bg-input); padding: 0.75rem; border-radius: var(--radius-sm); margin: 0;"><?php echo nl2br(htmlspecialchars($c['carta_motivacao'])); ?></p>
                            <?php endif; ?>

                            <?php if ($c['cv_path']): ?>
                                <hr style="border-color: var(--border-color); margin: 1rem 0;">
                                <p style="margin: 0;"><i class="bi bi-file-earmark-pdf"></i> <a href="<?php echo htmlspecialchars($c['cv_path']); ?>" target="_blank">Descarregar CV</a></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-clipboard-check"></i> Processo de Seleção</h3></div>
                        <div class="card-body">
                            <form method="POST" action="?action=update&id=<?php echo $id; ?>">
                                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Estado</label>
                                        <select name="status" class="form-select">
                                            <?php foreach (['nova' => 'Nova', 'em_analise' => 'Em Análise', 'pre_selecionada' => 'Pré-selecionada', 'entrevista_agendada' => 'Entrevista Agendada', 'aprovada' => 'Aprovada', 'contratada' => 'Contratada', 'rejeitada' => 'Rejeitada'] as $k => $v): ?>
                                                <option value="<?php echo $k; ?>" <?php echo $c['status'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Pontuação (0-10)</label>
                                        <input type="number" min="0" max="10" name="pontuacao" class="form-control" value="<?php echo $c['pontuacao']; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data da Entrevista</label>
                                        <input type="datetime-local" name="data_entrevista" class="form-control" value="<?php echo $c['data_entrevista'] ? date('Y-m-d\TH:i', strtotime($c['data_entrevista'])) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Observações</label>
                                    <textarea name="observacoes" class="form-control" rows="3" placeholder="Notas sobre a entrevista, impressões, próximos passos..."><?php echo htmlspecialchars($c['observacoes'] ?? ''); ?></textarea>
                                </div>
                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                    <?php if ($c['status'] === 'aprovada'): ?>
                                        <a href="admissao.php?from_candidato=<?php echo $id; ?>" class="btn btn-success"><i class="bi bi-person-plus"></i> Admitir como Funcionário</a>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Atualizar Estado</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php } endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
