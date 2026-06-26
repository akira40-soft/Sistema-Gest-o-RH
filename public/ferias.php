<?php
/**
 * Férias - Atalho para licenças com tipo=ferias
 * Reutiliza a página de licenças com filtro pré-aplicado
 */
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
        if ($action === 'create') {
            $funcionario_id = (int)$_POST['funcionario_id'];
            $data_inicio = $_POST['data_inicio'];
            $data_fim = $_POST['data_fim'];
            $motivo = trim($_POST['motivo'] ?? 'Férias');

            if (strtotime($data_fim) < strtotime($data_inicio)) {
                throw new Exception('Data fim inválida.');
            }
            $dias = \App\Utils\Angola::calcularDiasUteis($data_inicio, $data_fim);
            if ($dias > 22) {
                throw new Exception('Período de férias não pode exceder 22 dias úteis conforme legislação.');
            }

            $stmt = $db->prepare("INSERT INTO licencas (funcionario_id, tipo, data_inicio, data_fim, dias_uteis, motivo, status, remunerada) VALUES (:f, 'ferias', :di, :df, :d, :m, 'pendente', 1)");
            $stmt->execute([':f' => $funcionario_id, ':di' => $data_inicio, ':df' => $data_fim, ':d' => $dias, ':m' => $motivo]);
            $newId = $db->lastInsertId();
            $wf = new Workflow('licenca', $newId);
            $wf->start();
            Audit::create('licenca', $newId, "Férias solicitadas: $dias dias para func=$funcionario_id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Pedido de férias submetido!</strong> Aguarda aprovação.</div></div>';
        } elseif ($action === 'approve' && $id > 0) {
            $wf = new Workflow('licenca', $id);
            $wf->approve($auth->getUserId(), 'Férias aprovadas');
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Férias aprovadas!</strong></div></div>';
        } elseif ($action === 'reject' && $id > 0) {
            $wf = new Workflow('licenca', $id);
            $wf->reject($auth->getUserId(), $_POST['motivo'] ?? 'Sem motivo');
            $msg = '<div class="alert alert-warning"><i class="bi bi-x-circle"></i><div class="alert-content"><strong>Férias rejeitadas.</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$ferias = $db->query("
    SELECT l.*, f.nome_completo, d.nome as departamento
    FROM licencas l
    JOIN funcionarios f ON l.funcionario_id = f.id
    LEFT JOIN departamentos d ON f.departamento_id = d.id
    WHERE l.tipo = 'ferias'
    ORDER BY l.data_inicio DESC LIMIT 100
")->fetchAll();

$funcionarios = $db->query("SELECT f.id, f.nome_completo, d.nome as departamento FROM funcionarios f LEFT JOIN departamentos d ON f.departamento_id = d.id WHERE f.status = 'ativo' ORDER BY f.nome_completo")->fetchAll();

$pageTitle = 'Férias';
$pageSubtitle = 'Planeamento de férias da equipa';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Férias | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item"><a href="licencas.php">Licenças</a></li>
                        <li class="breadcrumb-item active">Férias</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><i class="bi bi-sun"></i> Férias</h2>
                        <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($ferias); ?> pedidos registados</p>
                    </div>
                    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Marcar Férias</a>
                </div>

                <?php if ($action === 'create'): ?>
                    <form method="POST" class="card" style="max-width: 640px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Funcionário <span class="required">*</span></label>
                                <select name="funcionario_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($funcionarios as $f): ?>
                                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid-2" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Data Início <span class="required">*</span></label>
                                    <input type="date" name="data_inicio" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Data Fim <span class="required">*</span></label>
                                    <input type="date" name="data_fim" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Observações</label>
                                <textarea name="motivo" class="form-control" rows="2" placeholder="Opcional"></textarea>
                            </div>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div class="alert-content">
                                    Conforme a Lei Geral do Trabalho Angolana, o período de férias não pode exceder 22 dias úteis.
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="display:flex; gap:0.5rem; justify-content:flex-end;">
                            <a href="ferias.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submeter</button>
                        </div>
                    </form>
                <?php else: ?>

                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Funcionário</th>
                                    <th>Período</th>
                                    <th>Dias</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($ferias)): ?>
                                    <tr><td colspan="5">
                                        <div class="empty-state">
                                            <i class="bi bi-sun"></i>
                                            <h4>Sem pedidos de férias</h4>
                                        </div>
                                    </td></tr>
                                <?php else: foreach ($ferias as $f):
                                    $cls = match($f['status']) {
                                        'pendente' => 'warning', 'aprovada' => 'success',
                                        'rejeitada' => 'danger', 'cancelada' => 'neutral',
                                        default => 'neutral'
                                    };
                                ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm"><?php echo strtoupper(substr($f['nome_completo'], 0, 1)); ?></div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($f['nome_completo']); ?></strong>
                                                    <small style="color: var(--text-muted); display: block;"><?php echo htmlspecialchars($f['departamento'] ?? ''); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-family: var(--font-mono); font-size: 0.85rem;">
                                            <?php echo rg_date($f['data_inicio']); ?> → <?php echo rg_date($f['data_fim']); ?>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo (int)$f['dias_uteis']; ?> dias</span></td>
                                        <td>
                                            <span class="badge badge-<?php echo $cls; ?>">
                                                <span class="badge-dot"></span>
                                                <?php echo ucfirst($f['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if ($f['status'] === 'pendente' && $auth->isAdmin()): ?>
                                                <form method="POST" action="?action=approve&id=<?php echo $f['id']; ?>" style="display:inline;">
                                                    <button class="btn btn-icon" style="background: var(--success-50); color: var(--success);" title="Aprovar"><i class="bi bi-check2"></i></button>
                                                </form>
                                                <form method="POST" action="?action=reject&id=<?php echo $f['id']; ?>" style="display:inline;" onsubmit="return confirm('Rejeitar?')">
                                                    <button class="btn btn-icon" style="background: var(--danger-50); color: var(--danger);" title="Rejeitar"><i class="bi bi-x"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
