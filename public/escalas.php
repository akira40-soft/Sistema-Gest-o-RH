<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isAdmin() && $auth->getUserRole() !== 'lider_farmaceutico' && $auth->getUserRole() !== 'funcionario_rh' && $auth->getUserRole() !== 'funcionario') { header('Location: acesso_negado.php'); exit; }

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$msg = '';

// Atribuir escala
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign') {
    try {
        $funcionario_id = (int)$_POST['funcionario_id'];
        $turno_id = (int)$_POST['turno_id'];
        $data = $_POST['data'];
        $status = $_POST['status'] ?? 'agendado';
        $motivo = trim($_POST['motivo_substituicao'] ?? '');

        $check = $db->prepare("SELECT id FROM escalas WHERE funcionario_id = :f AND turno_id = :t AND data = :d");
        $check->execute([':f' => $funcionario_id, ':t' => $turno_id, ':d' => $data]);
        if ($check->fetch()) {
            throw new Exception('Já existe uma escala para este funcionário, turno e data.');
        }

        $stmt = $db->prepare("INSERT INTO escalas (funcionario_id, turno_id, data, status, motivo_substituicao, criado_por) VALUES (:f, :t, :d, :s, :m, :uid)");
        $stmt->execute([':f' => $funcionario_id, ':t' => $turno_id, ':d' => $data, ':s' => $status, ':m' => $motivo, ':uid' => $auth->getUserId()]);
        $newId = $db->lastInsertId();

        // Notifica o funcionário
        $stmtF = $db->prepare("SELECT u.id FROM funcionarios f JOIN usuarios u ON f.usuario_id = u.id WHERE f.id = :id");
        $stmtF->execute([':id' => $funcionario_id]);
        $fUser = $stmtF->fetch();
        if ($fUser) {
            Notification::send((int)$fUser['id'], 'Nova Escala', "Tem nova escala atribuída para $data", 'info', '/portal.php');
        }

        Audit::create('escala', $newId, "Escala: func=$funcionario_id, turno=$turno_id, data=$data");
        $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Escala atribuída!</strong></div></div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

if ($action === 'delete' && isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = (int)$_GET['id'];
        $db->prepare("DELETE FROM escalas WHERE id = :id")->execute([':id' => $id]);
        Audit::delete('escala', $id, "Escala eliminada #$id");
        $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Escala eliminada!</strong></div></div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));
$dept_id = $_GET['departamento_id'] ?? '';

$inicio = date('Y-m-01', strtotime("$ano-$mes-01"));
$fim = date('Y-m-t', strtotime("$ano-$mes-01"));

$sql = "SELECT e.*, f.nome_completo, f.departamento_id, t.nome as turno_nome, t.hora_inicio, t.hora_fim, t.tipo as turno_tipo, d.nome as departamento
        FROM escalas e
        JOIN funcionarios f ON e.funcionario_id = f.id
        JOIN turnos t ON e.turno_id = t.id
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        WHERE e.data BETWEEN :ini AND :fim";
$params = [':ini' => $inicio, ':fim' => $fim];

$is_employee_view = in_array($auth->getUserRole(), ['funcionario', 'lider_farmaceutico']);
if ($is_employee_view) {
    $emp = $db->prepare("SELECT id FROM funcionarios WHERE usuario_id = :uid LIMIT 1");
    $emp->execute([':uid' => $auth->getUserId()]);
    $emp = $emp->fetch();
    if ($emp) {
        $sql .= " AND e.funcionario_id = :emp_id";
        $params[':emp_id'] = $emp['id'];
    }
}

if (!empty($dept_id)) {
    $sql .= " AND f.departamento_id = :d";
    $params[':d'] = $dept_id;
}
$sql .= " ORDER BY e.data, t.hora_inicio";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$escalas = $stmt->fetchAll();

// Agrupar por data
$escalasPorData = [];
foreach ($escalas as $e) {
    $escalasPorData[$e['data']][] = $e;
}

$funcionarios = $db->query("SELECT f.id, f.nome_completo, d.nome as departamento FROM funcionarios f LEFT JOIN departamentos d ON f.departamento_id = d.id WHERE f.status = 'ativo' ORDER BY f.nome_completo")->fetchAll();
$turnos = $db->query("SELECT * FROM turnos WHERE ativo = 1 ORDER BY hora_inicio")->fetchAll();
$departamentos = $db->query("SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$pageTitle = 'Escalas';
$pageSubtitle = 'Planeamento mensal de turnos';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escalas | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Escalas</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Escalas de Turnos</h2>
                        <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($escalas); ?> escalas em <?php echo strftime('%B %Y', strtotime("$ano-$mes-01")); ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="turnos.php" class="btn btn-secondary"><i class="bi bi-clock"></i> Turnos</a>
                        <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nova Escala</a>
                    </div>
                </div>

                <form class="filter-bar" method="GET">
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <a href="?mes=<?php echo $mes == 1 ? 12 : $mes - 1; ?>&ano=<?php echo $mes == 1 ? $ano - 1 : $ano; ?><?php echo $dept_id ? '&departamento_id='.$dept_id : ''; ?>" class="btn btn-icon btn-secondary"><i class="bi bi-chevron-left"></i></a>
                        <strong style="padding: 0 0.5rem;"><?php echo strftime('%B %Y', strtotime("$ano-$mes-01")); ?></strong>
                        <a href="?mes=<?php echo $mes == 12 ? 1 : $mes + 1; ?>&ano=<?php echo $mes == 12 ? $ano + 1 : $ano; ?><?php echo $dept_id ? '&departamento_id='.$dept_id : ''; ?>" class="btn btn-icon btn-secondary"><i class="bi bi-chevron-right"></i></a>
                    </div>
                    <select name="departamento_id" class="form-select" style="max-width: 200px;" onchange="this.form.submit()">
                        <option value="">Todos departamentos</option>
                        <?php foreach ($departamentos as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo $dept_id == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nome']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Funcionário</th>
                                    <th>Departamento</th>
                                    <th>Turno</th>
                                    <th>Horário</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($escalas)): ?>
                                    <tr><td colspan="7">
                                        <div class="empty-state">
                                            <i class="bi bi-calendar3"></i>
                                            <h4>Nenhuma escala neste mês</h4>
                                            <p>Comece por criar turnos e atribuir escalas.</p>
                                            <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Atribuir Escala</a>
                                        </div>
                                    </td></tr>
                                <?php else: foreach ($escalas as $e): ?>
                                    <tr>
                                        <td>
                                            <strong style="font-family: var(--font-mono);"><?php echo rg_date($e['data']); ?></strong>
                                            <small style="color: var(--text-muted); display: block;"><?php echo strftime('%a', strtotime($e['data'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm"><?php echo strtoupper(substr($e['nome_completo'], 0, 1)); ?></div>
                                                <strong><?php echo htmlspecialchars($e['nome_completo']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($e['departamento'] ?? '—'); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($e['turno_nome']); ?></span>
                                        </td>
                                        <td style="font-family: var(--font-mono);"><?php echo substr($e['hora_inicio'], 0, 5); ?> - <?php echo substr($e['hora_fim'], 0, 5); ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($e['status']) {
                                                'agendado' => 'badge-neutral',
                                                'confirmado' => 'badge-success',
                                                'substituido' => 'badge-warning',
                                                'cancelado' => 'badge-danger',
                                                default => 'badge-neutral'
                                            };
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <span class="badge-dot"></span>
                                                <?php echo ucfirst($e['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="?action=delete&id=<?php echo $e['id']; ?>" style="display:inline;" onsubmit="return confirm('Eliminar esta escala?')">
                                                <button class="btn btn-icon btn-secondary" title="Eliminar"><i class="bi bi-trash" style="color: var(--danger);"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php if ($action === 'create'): ?>
    <div class="modal-overlay" id="modalEscala" style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 9999;">
        <div class="card" style="max-width: 520px; width: 90%;">
            <form method="POST" action="?action=assign">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-calendar-plus"></i> Atribuir Escala</h3>
                    <a href="escalas.php" class="btn btn-icon btn-ghost"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Funcionário <span class="required">*</span></label>
                        <select name="funcionario_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo']); ?> <?php echo $f['departamento'] ? '(' . htmlspecialchars($f['departamento']) . ')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Turno <span class="required">*</span></label>
                        <select name="turno_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($turnos as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['nome']); ?> (<?php echo substr($t['hora_inicio'], 0, 5); ?>-<?php echo substr($t['hora_fim'], 0, 5); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid-2" style="gap:1rem;">
                        <div class="form-group">
                            <label class="form-label">Data <span class="required">*</span></label>
                            <input type="date" name="data" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="agendado">Agendado</option>
                                <option value="confirmado">Confirmado</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Observações</label>
                        <textarea name="motivo_substituicao" class="form-control" rows="2" placeholder="Opcional"></textarea>
                    </div>
                </div>
                <div class="card-footer" style="display:flex; gap:0.5rem; justify-content:flex-end;">
                    <a href="escalas.php" class="btn btn-ghost">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Atribuir</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="js/app-2026.js"></script>
</body>
</html>
