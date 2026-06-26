<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// Aprovar/registrar ponto manual (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'register') {
    try {
        $funcionario_id = (int)$_POST['funcionario_id'];
        $data = $_POST['data'];
        $hora_entrada = !empty($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
        $hora_saida = !empty($_POST['hora_saida']) ? $_POST['hora_saida'] : null;
        $tipo = $_POST['tipo'] ?? 'presenca';
        $justificativa = trim($_POST['justificativa'] ?? '');

        if (!$hora_entrada && !$hora_saida && $tipo === 'presenca') {
            throw new Exception('Informe pelo menos a hora de entrada ou saída.');
        }

        $horas_trabalhadas = null;
        if ($hora_entrada && $hora_saida) {
            $entrada = strtotime("$data $hora_entrada");
            $saida = strtotime("$data $hora_saida");
            $diff = ($saida - $entrada) / 3600;
            $horas_trabalhadas = max(0, round($diff, 2));
        }

        $stmt = $db->prepare("SELECT id FROM registros_ponto WHERE funcionario_id = :f AND data = :d");
        $stmt->execute([':f' => $funcionario_id, ':d' => $data]);
        $existing = $stmt->fetch();

        if ($existing) {
            $updates = [];
            $params = [':id' => $existing['id']];
            if ($hora_entrada) { $updates[] = 'hora_entrada = :he'; $params[':he'] = $hora_entrada; }
            if ($hora_saida) { $updates[] = 'hora_saida = :hs'; $params[':hs'] = $hora_saida; }
            if ($horas_trabalhadas !== null) { $updates[] = 'horas_trabalhadas = :ht'; $params[':ht'] = $horas_trabalhadas; }
            $updates[] = 'tipo = :tp'; $params[':tp'] = $tipo;
            $updates[] = 'justificativa = :ju'; $params[':ju'] = $justificativa;
            $updates[] = 'aprovado_por = :ap'; $params[':ap'] = $auth->getUserId();

            $sql = "UPDATE registros_ponto SET " . implode(', ', $updates) . " WHERE id = :id";
            $db->prepare($sql)->execute($params);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Registro atualizado!</strong></div></div>';
        } else {
            $stmt = $db->prepare("INSERT INTO registros_ponto (funcionario_id, data, hora_entrada, hora_saida, horas_trabalhadas, tipo, metodo_registro, justificativa, aprovado_por) VALUES (:f, :d, :he, :hs, :ht, :tp, 'manual', :ju, :ap)");
            $stmt->execute([':f' => $funcionario_id, ':d' => $data, ':he' => $hora_entrada, ':hs' => $hora_saida, ':ht' => $horas_trabalhadas, ':tp' => $tipo, ':ju' => $justificativa, ':ap' => $auth->getUserId()]);
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Registro criado!</strong></div></div>';
        }
        Audit::log('registro_ponto', 'registros_ponto', $id, "Registro de ponto func=$funcionario_id em $data");
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$userId = $auth->getUserId();
$isAdmin = $auth->isAdmin();
$userRole = $auth->getUserRole();

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-t');
$funcionario_id = $_GET['funcionario_id'] ?? '';

$sql = "SELECT r.*, f.nome_completo, f.departamento_id, d.nome as departamento
        FROM registros_ponto r
        JOIN funcionarios f ON r.funcionario_id = f.id
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        WHERE r.data BETWEEN :ini AND :fim";
$params = [':ini' => $data_inicio, ':fim' => $data_fim];

if (!$isAdmin && $userRole === 'funcionario') {
    $sql .= " AND f.usuario_id = :uid";
    $params[':uid'] = $userId;
}
if (!empty($funcionario_id)) {
    $sql .= " AND r.funcionario_id = :f";
    $params[':f'] = $funcionario_id;
}

$sql .= " ORDER BY r.data DESC, f.nome_completo LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$funcionarios = $db->query("SELECT f.id, f.nome_completo FROM funcionarios f WHERE f.status = 'ativo' ORDER BY f.nome_completo LIMIT 100")->fetchAll();

$pageTitle = 'Registros de Ponto';
$pageSubtitle = 'Controle de assiduidade';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ponto | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Registros de Ponto</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><i class="bi bi-clock-history"></i> Registros de Ponto</h2>
                        <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($registros); ?> registros no período</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="timeclock.php" class="btn btn-secondary"><i class="bi bi-stopwatch"></i> Bater Ponto</a>
                        <?php if ($isAdmin): ?>
                            <a href="?action=register" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Registro Manual</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($action === 'register' && $isAdmin): ?>
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
                            <div class="grid-3" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Data <span class="required">*</span></label>
                                    <input type="date" name="data" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Hora Entrada</label>
                                    <input type="time" name="hora_entrada" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Hora Saída</label>
                                    <input type="time" name="hora_saida" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="presenca">Presença</option>
                                    <option value="falta_justificada">Falta Justificada</option>
                                    <option value="falta_injustificada">Falta Injustificada</option>
                                    <option value="atestado">Atestado</option>
                                    <option value="ferias">Férias</option>
                                    <option value="licenca">Licença</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Justificativa</label>
                                <textarea name="justificativa" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="pontos.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Registar</button>
                        </div>
                    </form>
                <?php else: ?>

                <form class="filter-bar" method="GET">
                    <input type="date" name="inicio" class="form-control" value="<?php echo $data_inicio; ?>" style="max-width: 160px;">
                    <input type="date" name="fim" class="form-control" value="<?php echo $data_fim; ?>" style="max-width: 160px;">
                    <?php if ($isAdmin): ?>
                        <select name="funcionario_id" class="form-select" style="max-width: 240px;">
                            <option value="">Todos funcionários</option>
                            <?php foreach ($funcionarios as $f): ?>
                                <option value="<?php echo $f['id']; ?>" <?php echo $funcionario_id == $f['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($f['nome_completo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button class="btn btn-primary"><i class="bi bi-funnel"></i></button>
                </form>

                <div class="card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Funcionário</th>
                                    <th>Entrada</th>
                                    <th>Saída</th>
                                    <th>Horas</th>
                                    <th>Tipo</th>
                                    <th>Método</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($registros)): ?>
                                    <tr><td colspan="7">
                                        <div class="empty-state">
                                            <i class="bi bi-clock"></i>
                                            <h4>Sem registros no período</h4>
                                            <?php if ($isAdmin): ?>
                                                <a href="?action=register" class="btn btn-primary mt-2"><i class="bi bi-plus-lg"></i> Adicionar Registro</a>
                                            <?php endif; ?>
                                        </div>
                                    </td></tr>
                                <?php else: foreach ($registros as $r):
                                    $tipoBadge = match($r['tipo']) {
                                        'presenca' => 'success', 'atestado' => 'info', 'ferias' => 'warning',
                                        'licenca' => 'warning', 'falta_justificada' => 'neutral',
                                        'falta_injustificada' => 'danger', default => 'neutral'
                                    };
                                ?>
                                    <tr>
                                        <td style="font-family: var(--font-mono); font-size: 0.85rem;">
                                            <?php echo rg_date($r['data']); ?>
                                            <small style="color: var(--text-muted); display: block;"><?php echo strftime('%a', strtotime($r['data'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar-sm"><?php echo strtoupper(substr($r['nome_completo'], 0, 1)); ?></div>
                                                <strong><?php echo htmlspecialchars($r['nome_completo']); ?></strong>
                                            </div>
                                        </td>
                                        <td style="font-family: var(--font-mono);"><?php echo $r['hora_entrada'] ? substr($r['hora_entrada'], 0, 5) : '—'; ?></td>
                                        <td style="font-family: var(--font-mono);"><?php echo $r['hora_saida'] ? substr($r['hora_saida'], 0, 5) : '—'; ?></td>
                                        <td><?php echo $r['horas_trabalhadas'] ? number_format($r['horas_trabalhadas'], 1) . 'h' : '—'; ?></td>
                                        <td><span class="badge badge-<?php echo $tipoBadge; ?>"><?php echo ucfirst(str_replace('_', ' ', $r['tipo'])); ?></span></td>
                                        <td>
                                            <span class="badge badge-neutral">
                                                <i class="bi bi-<?php echo $r['metodo_registro'] === 'mobile' ? 'phone' : ($r['metodo_registro'] === 'biometrico' ? 'fingerprint' : 'keyboard'); ?>"></i>
                                                <?php echo ucfirst($r['metodo_registro']); ?>
                                            </span>
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
