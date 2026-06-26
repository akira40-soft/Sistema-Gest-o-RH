<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isAdmin() && $auth->getUserRole() !== 'lider_farmaceutico') { header('Location: acesso_negado.php'); exit; }

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            $nome = trim($_POST['nome'] ?? '');
            $hora_inicio = $_POST['hora_inicio'] ?? null;
            $hora_fim = $_POST['hora_fim'] ?? null;
            $tipo = $_POST['tipo'] ?? 'integral';
            $duracao = (float)str_replace(',', '.', $_POST['duracao_horas'] ?? '8');
            $intervalo = (int)($_POST['intervalo_minutos'] ?? 60);
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            if (empty($nome) || empty($hora_inicio) || empty($hora_fim)) {
                throw new Exception('Nome, hora início e hora fim são obrigatórios.');
            }

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO turnos (nome, hora_inicio, hora_fim, tipo, duracao_horas, intervalo_minutos, ativo) VALUES (:n, :hi, :hf, :t, :d, :i, :a)");
                $stmt->execute([':n' => $nome, ':hi' => $hora_inicio, ':hf' => $hora_fim, ':t' => $tipo, ':d' => $duracao, ':i' => $intervalo, ':a' => $ativo]);
                $newId = $db->lastInsertId();
                Audit::create('turno', $newId, "Criou turno: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Turno criado!</strong></div></div>';
                $action = 'list';
            } else {
                $stmt = $db->prepare("UPDATE turnos SET nome=:n, hora_inicio=:hi, hora_fim=:hf, tipo=:t, duracao_horas=:d, intervalo_minutos=:i, ativo=:a WHERE id=:id");
                $stmt->execute([':n' => $nome, ':hi' => $hora_inicio, ':hf' => $hora_fim, ':t' => $tipo, ':d' => $duracao, ':i' => $intervalo, ':a' => $ativo, ':id' => $id]);
                Audit::update('turno', $id, "Atualizou turno: $nome");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Turno atualizado!</strong></div></div>';
                $action = 'list';
            }
        } elseif ($action === 'delete' && $id > 0) {
            $check = $db->prepare("SELECT COUNT(*) FROM escalas WHERE turno_id = :id");
            $check->execute([':id' => $id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception('Não é possível eliminar: existem escalas que usam este turno.');
            }
            $db->prepare("DELETE FROM turnos WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('turno', $id, "Eliminou turno #$id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Turno eliminado!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

if ($action === 'list') {
    $turnos = $db->query("SELECT t.*, (SELECT COUNT(*) FROM escalas e WHERE e.turno_id = t.id) as uso_count FROM turnos t ORDER BY hora_inicio")->fetchAll();
}

$pageTitle = 'Turnos';
$pageSubtitle = 'Definição de horários de trabalho';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnos | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item"><a href="escalas.php">Escalas</a></li>
                        <li class="breadcrumb-item active">Turnos</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Turnos de Trabalho</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($turnos); ?> turnos configurados</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="escalas.php" class="btn btn-secondary"><i class="bi bi-calendar3"></i> Ver Escalas</a>
                            <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Novo Turno</a>
                        </div>
                    </div>

                    <div class="grid-3">
                        <?php if (empty($turnos)): ?>
                            <div class="card" style="grid-column: 1 / -1;">
                                <div class="empty-state">
                                    <i class="bi bi-clock"></i>
                                    <h4>Nenhum turno</h4>
                                    <p>Defina turnos para construir escalas de trabalho.</p>
                                    <a href="?action=create" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Criar</a>
                                </div>
                            </div>
                        <?php else: foreach ($turnos as $t): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;"><?php echo htmlspecialchars($t['nome']); ?></h3>
                                            <span class="badge badge-<?php echo ['manha' => 'warning', 'tarde' => 'info', 'noite' => 'primary', 'integral' => 'success', 'flexivel' => 'neutral'][$t['tipo']] ?? 'neutral'; ?>" style="margin-top: 0.4rem;">
                                                <?php echo ucfirst($t['tipo']); ?>
                                            </span>
                                        </div>
                                        <span class="badge <?php echo $t['ativo'] ? 'badge-success' : 'badge-neutral'; ?>">
                                            <span class="badge-dot"></span>
                                            <?php echo $t['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; padding: 1rem 0; color: var(--text-secondary); font-size: 0.95rem;">
                                        <i class="bi bi-clock-fill" style="color: var(--primary); font-size: 1.3rem;"></i>
                                        <strong style="font-family: var(--font-mono); font-size: 1.1rem;"><?php echo substr($t['hora_inicio'], 0, 5); ?> - <?php echo substr($t['hora_fim'], 0, 5); ?></strong>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 0.85rem;">
                                        <div><i class="bi bi-hourglass-split"></i> <?php echo number_format($t['duracao_horas'], 1); ?>h</div>
                                        <div><i class="bi bi-cup-hot"></i> <?php echo $t['intervalo_minutos']; ?> min</div>
                                        <div style="grid-column: 1 / -1;"><i class="bi bi-calendar-check"></i> Usado em <?php echo (int)$t['uso_count']; ?> escalas</div>
                                    </div>
                                </div>
                                <div class="card-footer" style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                                    <a href="?action=edit&id=<?php echo $t['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                    <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar?')) location.href='?action=delete&id=<?php echo $t['id']; ?>'">
                                        <i class="bi bi-trash" style="color: var(--danger);"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>

                <?php elseif ($action === 'create' || $action === 'edit'):
                    $turno = ['id' => 0, 'nome' => '', 'hora_inicio' => '08:00:00', 'hora_fim' => '17:00:00', 'tipo' => 'integral', 'duracao_horas' => 8, 'intervalo_minutos' => 60, 'ativo' => 1];
                    if ($action === 'edit' && $id > 0) {
                        $stmt = $db->prepare("SELECT * FROM turnos WHERE id = :id");
                        $stmt->execute([':id' => $id]);
                        $turno = $stmt->fetch() ?: $turno;
                    }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Turno</h2>
                        <a href="turnos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 640px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Nome <span class="required">*</span></label>
                                <input type="text" name="nome" class="form-control" required value="<?php echo htmlspecialchars($turno['nome']); ?>" placeholder="Ex: Manhã, Tarde, Noite...">
                            </div>
                            <div class="grid-2" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Hora Início <span class="required">*</span></label>
                                    <input type="time" name="hora_inicio" class="form-control" required value="<?php echo htmlspecialchars($turno['hora_inicio']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Hora Fim <span class="required">*</span></label>
                                    <input type="time" name="hora_fim" class="form-control" required value="<?php echo htmlspecialchars($turno['hora_fim']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="manha" <?php echo $turno['tipo'] === 'manha' ? 'selected' : ''; ?>>Manhã</option>
                                        <option value="tarde" <?php echo $turno['tipo'] === 'tarde' ? 'selected' : ''; ?>>Tarde</option>
                                        <option value="noite" <?php echo $turno['tipo'] === 'noite' ? 'selected' : ''; ?>>Noite</option>
                                        <option value="integral" <?php echo $turno['tipo'] === 'integral' ? 'selected' : ''; ?>>Integral</option>
                                        <option value="flexivel" <?php echo $turno['tipo'] === 'flexivel' ? 'selected' : ''; ?>>Flexível</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Duração (h)</label>
                                    <input type="number" name="duracao_horas" class="form-control" step="0.5" min="0" value="<?php echo htmlspecialchars($turno['duracao_horas']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Intervalo (minutos)</label>
                                    <input type="number" name="intervalo_minutos" class="form-control" step="15" min="0" value="<?php echo htmlspecialchars($turno['intervalo_minutos']); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="ativo" class="form-check-input" <?php echo $turno['ativo'] ? 'checked' : ''; ?>>
                                    <span>Turno ativo</span>
                                </label>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="turnos.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
