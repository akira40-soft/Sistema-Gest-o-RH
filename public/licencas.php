<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;
use App\Utils\Workflow;

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $funcionario_id = (int)$_POST['funcionario_id'];
            $tipo = $_POST['tipo'];
            $data_inicio = $_POST['data_inicio'];
            $data_fim = $_POST['data_fim'];
            $motivo = trim($_POST['motivo'] ?? '');
            $documento_path = null;
            $remunerada = isset($_POST['remunerada']) ? 1 : 0;

            if (empty($funcionario_id) || empty($tipo) || empty($data_inicio) || empty($data_fim)) {
                throw new Exception('Funcionário, tipo e datas são obrigatórios.');
            }
            if (strtotime($data_fim) < strtotime($data_inicio)) {
                throw new Exception('Data fim não pode ser antes do início.');
            }

            // Upload documento comprovativo
            if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
                $up = \App\Utils\Upload::save($_FILES['documento'], 'licencas', ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024);
                if ($up['success']) $documento_path = $up['path'];
            }

            // Calcular dias úteis
            $dias_uteis = \App\Utils\Angola::calcularDiasUteis($data_inicio, $data_fim);

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO licencas (funcionario_id, tipo, data_inicio, data_fim, dias_uteis, motivo, documento_comprovativo, status, remunerada) VALUES (:f, :t, :di, :df, :du, :m, :d, 'pendente', :r)");
                $stmt->execute([':f' => $funcionario_id, ':t' => $tipo, ':di' => $data_inicio, ':df' => $data_fim, ':du' => $dias_uteis, ':m' => $motivo, ':d' => $documento_path, ':r' => $remunerada]);
                $newId = $db->lastInsertId();

                // Inicia workflow de aprovação
                $wf = new Workflow('licenca', $newId);
                $wf->start();

                Audit::create('licenca', $newId, "Pediu licença tipo=$tipo para func=$funcionario_id");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Pedido submetido!</strong> Aguarda aprovação.</div></div>';
            } else {
                $stmt = $db->prepare("UPDATE licencas SET tipo=:t, data_inicio=:di, data_fim=:df, dias_uteis=:du, motivo=:m, remunerada=:r WHERE id=:id");
                $stmt->execute([':t' => $tipo, ':di' => $data_inicio, ':df' => $data_fim, ':du' => $dias_uteis, ':m' => $motivo, ':r' => $remunerada, ':id' => $id]);
                Audit::update('licenca', $id, "Editou licença");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Pedido atualizado!</strong></div></div>';
            }
            $action = 'list';
        } elseif ($action === 'approve' && $id > 0) {
            $wf = new Workflow('licenca', $id);
            $wf->approve($auth->getUserId(), $_POST['comentario'] ?? null);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Licença aprovada!</strong></div></div>';
        } elseif ($action === 'reject' && $id > 0) {
            $motivo = $_POST['motivo'] ?? 'Sem motivo';
            $wf = new Workflow('licenca', $id);
            $wf->reject($auth->getUserId(), $motivo);
            $msg = '<div class="alert alert-warning"><i class="bi bi-x-circle-fill"></i><div class="alert-content"><strong>Licença rejeitada.</strong></div></div>';
        } elseif ($action === 'cancel' && $id > 0) {
            $db->prepare("UPDATE licencas SET status = 'cancelada' WHERE id = :id")->execute([':id' => $id]);
            Audit::log('cancel', 'licenca', $id, 'Cancelado pelo utilizador');
            $msg = '<div class="alert alert-warning"><i class="bi bi-x-circle"></i><div class="alert-content"><strong>Pedido cancelado.</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$userRole = $auth->getUserRole();
$userId = $auth->getUserId();
$isAdmin = $auth->isAdmin();

if ($action === 'list') {
    $status = $_GET['status'] ?? '';
    $tipo = $_GET['tipo'] ?? '';
    $busca = trim($_GET['busca'] ?? '');

    $sql = "SELECT l.*, f.nome_completo, f.departamento_id, d.nome as departamento,
                   (SELECT nome_completo FROM funcionarios WHERE id = l.aprovado_por) as aprovado_por_nome
            FROM licencas l
            JOIN funcionarios f ON l.funcionario_id = f.id
            LEFT JOIN departamentos d ON f.departamento_id = d.id
            WHERE 1=1";
    $params = [];

    // Funcionário comum só vê as suas
    if (!$isAdmin && $userRole === 'funcionario') {
        $sql .= " AND f.usuario_id = :uid";
        $params[':uid'] = $userId;
    }

    if (!empty($status)) {
        $sql .= " AND l.status = :s";
        $params[':s'] = $status;
    }
    if (!empty($tipo)) {
        $sql .= " AND l.tipo = :t";
        $params[':t'] = $tipo;
    }
    if (!empty($busca)) {
        $sql .= " AND (f.nome_completo LIKE :b OR l.motivo LIKE :b)";
        $params[':b'] = "%$busca%";
    }

    $sql .= " ORDER BY l.data_inicio DESC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $licencas = $stmt->fetchAll();
}

$funcionarios = $db->query("SELECT f.id, f.nome_completo, d.nome as departamento FROM funcionarios f LEFT JOIN departamentos d ON f.departamento_id = d.id WHERE f.status = 'ativo' ORDER BY f.nome_completo")->fetchAll();

$pageTitle = 'Licenças';
$pageSubtitle = 'Gestão de férias e ausências';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licenças | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Licenças</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Licenças e Férias</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($licencas); ?> pedidos</p>
                        </div>
                        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Pedido</a>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="flex: 2; min-width: 200px;">
                            <input type="text" name="busca" class="form-control" placeholder="🔍 Pesquisar..." value="<?php echo htmlspecialchars($busca); ?>">
                        </div>
                        <select name="tipo" class="form-select" style="max-width: 180px;">
                            <option value="">Todos tipos</option>
                            <option value="ferias" <?php echo $tipo === 'ferias' ? 'selected' : ''; ?>>Férias</option>
                            <option value="medica" <?php echo $tipo === 'medica' ? 'selected' : ''; ?>>Médica</option>
                            <option value="maternidade" <?php echo $tipo === 'maternidade' ? 'selected' : ''; ?>>Maternidade</option>
                            <option value="paternidade" <?php echo $tipo === 'paternidade' ? 'selected' : ''; ?>>Paternidade</option>
                            <option value="luto" <?php echo $tipo === 'luto' ? 'selected' : ''; ?>>Luto</option>
                            <option value="casamento" <?php echo $tipo === 'casamento' ? 'selected' : ''; ?>>Casamento</option>
                            <option value="estudos" <?php echo $tipo === 'estudos' ? 'selected' : ''; ?>>Estudos</option>
                            <option value="outro" <?php echo $tipo === 'outro' ? 'selected' : ''; ?>>Outro</option>
                        </select>
                        <select name="status" class="form-select" style="max-width: 160px;">
                            <option value="">Todos status</option>
                            <option value="pendente" <?php echo $status === 'pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="aprovada" <?php echo $status === 'aprovada' ? 'selected' : ''; ?>>Aprovada</option>
                            <option value="rejeitada" <?php echo $status === 'rejeitada' ? 'selected' : ''; ?>>Rejeitada</option>
                            <option value="cancelada" <?php echo $status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                    </form>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Tipo</th>
                                        <th>Período</th>
                                        <th>Dias</th>
                                        <th>Status</th>
                                        <th>Aprovado por</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($licencas)): ?>
                                        <tr><td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-calendar-event"></i>
                                                <h4>Nenhum pedido de licença</h4>
                                                <p>Submeta um novo pedido de férias ou ausência.</p>
                                                <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Pedido</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($licencas as $l):
                                        $tipoIcon = match($l['tipo']) {
                                            'ferias' => 'sun', 'medica' => 'heart-pulse', 'maternidade' => 'person-hearts',
                                            'paternidade' => 'person', 'luto' => 'flower1', 'casamento' => 'heart-fill',
                                            'estudos' => 'book', 'sem_vencimento' => 'wallet2', default => 'calendar-event'
                                        };
                                        $statusClass = match($l['status']) {
                                            'pendente' => 'badge-warning', 'aprovada' => 'badge-success',
                                            'rejeitada' => 'badge-danger', 'cancelada' => 'badge-neutral',
                                            default => 'badge-neutral'
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm"><?php echo strtoupper(substr($l['nome_completo'], 0, 1)); ?></div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($l['nome_completo']); ?></strong>
                                                        <small style="color: var(--text-muted); display: block;"><?php echo htmlspecialchars($l['departamento'] ?? ''); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="bi bi-<?php echo $tipoIcon; ?>"></i>
                                                <?php echo ucfirst($l['tipo']); ?>
                                                <?php if (!$l['remunerada']): ?>
                                                    <span class="badge badge-neutral" style="font-size: 0.7rem;">Sem vencimento</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-family: var(--font-mono); font-size: 0.85rem;">
                                                <?php echo rg_date($l['data_inicio']); ?> → <?php echo rg_date($l['data_fim']); ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo (int)$l['dias_uteis']; ?> dias úteis</span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <span class="badge-dot"></span>
                                                    <?php echo ucfirst($l['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($l['aprovado_por_nome']): ?>
                                                    <small><?php echo htmlspecialchars($l['aprovado_por_nome']); ?></small>
                                                <?php else: ?>
                                                    <span style="color: var(--text-faint);">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <?php if ($l['status'] === 'pendente' && $isAdmin): ?>
                                                        <form method="POST" action="?action=approve&id=<?php echo $l['id']; ?>" style="display:inline;">
                                                            <button class="btn btn-icon" style="background: var(--success-50); color: var(--success);" title="Aprovar">
                                                                <i class="bi bi-check2-circle"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="?action=reject&id=<?php echo $l['id']; ?>" style="display:inline;" onsubmit="return confirm('Rejeitar este pedido?')">
                                                            <button class="btn btn-icon" style="background: var(--danger-50); color: var(--danger);" title="Rejeitar">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <a href="?action=view&id=<?php echo $l['id']; ?>" class="btn btn-icon btn-secondary" title="Ver detalhes"><i class="bi bi-eye"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action === 'create' || $action === 'edit'):
                    $lic = ['funcionario_id' => $isAdmin ? '' : $userId, 'tipo' => 'ferias', 'data_inicio' => date('Y-m-d'), 'data_fim' => date('Y-m-d', strtotime('+5 days')), 'motivo' => '', 'remunerada' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM licencas WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $lic = $stmt->fetch() ?: $lic;
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Novo Pedido de Licença</h2>
                        <a href="licencas.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="card" style="max-width: 720px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Funcionário <span class="required">*</span></label>
                                <?php if ($isAdmin): ?>
                                    <select name="funcionario_id" class="form-select" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($funcionarios as $f): ?>
                                            <option value="<?php echo $f['id']; ?>" <?php echo $lic['funcionario_id'] == $f['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($f['nome_completo']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <?php
                                    $me = $db->prepare("SELECT id, nome_completo FROM funcionarios WHERE usuario_id = :uid");
                                    $me->execute([':uid' => $userId]);
                                    $me = $me->fetch();
                                    ?>
                                    <input type="hidden" name="funcionario_id" value="<?php echo $me['id']; ?>">
                                    <input type="text" class="form-control" disabled value="<?php echo htmlspecialchars($me['nome_completo']); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="grid-3" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo <span class="required">*</span></label>
                                    <select name="tipo" class="form-select" required>
                                        <option value="ferias" <?php echo $lic['tipo'] === 'ferias' ? 'selected' : ''; ?>>Férias</option>
                                        <option value="medica" <?php echo $lic['tipo'] === 'medica' ? 'selected' : ''; ?>>Licença Médica</option>
                                        <option value="maternidade" <?php echo $lic['tipo'] === 'maternidade' ? 'selected' : ''; ?>>Maternidade</option>
                                        <option value="paternidade" <?php echo $lic['tipo'] === 'paternidade' ? 'selected' : ''; ?>>Paternidade</option>
                                        <option value="luto" <?php echo $lic['tipo'] === 'luto' ? 'selected' : ''; ?>>Luto</option>
                                        <option value="casamento" <?php echo $lic['tipo'] === 'casamento' ? 'selected' : ''; ?>>Casamento</option>
                                        <option value="estudos" <?php echo $lic['tipo'] === 'estudos' ? 'selected' : ''; ?>>Estudos</option>
                                        <option value="sem_vencimento" <?php echo $lic['tipo'] === 'sem_vencimento' ? 'selected' : ''; ?>>Sem Vencimento</option>
                                        <option value="outro" <?php echo $lic['tipo'] === 'outro' ? 'selected' : ''; ?>>Outro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Início <span class="required">*</span></label>
                                    <input type="date" name="data_inicio" class="form-control" required value="<?php echo htmlspecialchars($lic['data_inicio']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Fim <span class="required">*</span></label>
                                    <input type="date" name="data_fim" class="form-control" required value="<?php echo htmlspecialchars($lic['data_fim']); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Motivo / Justificação</label>
                                <textarea name="motivo" class="form-control" rows="3" placeholder="Detalhe o motivo do pedido"><?php echo htmlspecialchars($lic['motivo']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Documento Comprovativo</label>
                                <input type="file" name="documento" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small style="color: var(--text-muted);">PDF, JPG ou PNG. Máximo 5MB.</small>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="remunerada" class="form-check-input" <?php echo $lic['remunerada'] ? 'checked' : ''; ?>>
                                    <span>Licença remunerada</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="licencas.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submeter Pedido</button>
                        </div>
                    </form>
                <?php elseif ($action === 'view' && $id > 0):
                    $stmt = $db->prepare("SELECT l.*, f.nome_completo, d.nome as departamento FROM licencas l JOIN funcionarios f ON l.funcionario_id = f.id LEFT JOIN departamentos d ON f.departamento_id = d.id WHERE l.id = :id");
                    $stmt->execute([':id' => $id]);
                    $lic = $stmt->fetch();
                    $wf = new Workflow('licenca', $id);
                    $historico = $wf->history();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Detalhes da Licença #<?php echo $id; ?></h2>
                        <a href="licencas.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    <div class="grid-2" style="gap: 1rem;">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Informações</h3></div>
                            <div class="card-body">
                                <dl style="display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem 1rem; margin: 0;">
                                    <dt style="color: var(--text-muted);">Funcionário:</dt><dd><?php echo htmlspecialchars($lic['nome_completo']); ?></dd>
                                    <dt style="color: var(--text-muted);">Departamento:</dt><dd><?php echo htmlspecialchars($lic['departamento'] ?? '—'); ?></dd>
                                    <dt style="color: var(--text-muted);">Tipo:</dt><dd><?php echo ucfirst($lic['tipo']); ?></dd>
                                    <dt style="color: var(--text-muted);">Período:</dt><dd><?php echo rg_date($lic['data_inicio']); ?> → <?php echo rg_date($lic['data_fim']); ?></dd>
                                    <dt style="color: var(--text-muted);">Dias úteis:</dt><dd><?php echo (int)$lic['dias_uteis']; ?></dd>
                                    <dt style="color: var(--text-muted);">Remunerada:</dt><dd><?php echo $lic['remunerada'] ? 'Sim' : 'Não'; ?></dd>
                                    <dt style="color: var(--text-muted);">Status:</dt><dd><?php echo ucfirst($lic['status']); ?></dd>
                                    <dt style="color: var(--text-muted);">Motivo:</dt><dd><?php echo nl2br(htmlspecialchars($lic['motivo'])); ?></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Histórico de Aprovação</h3></div>
                            <div class="card-body">
                                <?php if (empty($historico)): ?>
                                    <p style="color: var(--text-muted);">Sem movimentos ainda.</p>
                                <?php else: ?>
                                    <ul style="list-style: none; padding: 0; margin: 0;">
                                        <?php foreach ($historico as $h): ?>
                                            <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                                <strong><?php echo htmlspecialchars($h['username'] ?? 'Sistema'); ?></strong>
                                                <span class="badge badge-<?php echo $h['acao'] === 'aprovado' ? 'success' : 'danger'; ?>"><?php echo $h['acao']; ?></span>
                                                <small style="color: var(--text-muted); display: block;"><?php echo $h['criado_em']; ?></small>
                                                <?php if ($h['comentario']): ?>
                                                    <em style="color: var(--text-secondary);">"<?php echo htmlspecialchars($h['comentario']); ?>"</em>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
