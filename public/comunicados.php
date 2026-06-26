<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$user_role = $auth->getUserRole();
$is_admin = $auth->isAdmin();

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create' || $action === 'edit') {
            if (!$is_admin && $user_role !== 'gestor_rh' && $user_role !== 'lider_farmaceutico') {
                throw new Exception('Sem permissão para publicar comunicados.');
            }
            $titulo = trim($_POST['titulo'] ?? '');
            $conteudo = trim($_POST['conteudo'] ?? '');
            $tipo = $_POST['tipo'] ?? 'informativo';
            $prioridade = $_POST['prioridade'] ?? 'media';
            $destinatarios = $_POST['destinatarios'] ?? 'todos';
            $departamento_id = ($destinatarios === 'departamento' && !empty($_POST['departamento_id'])) ? (int)$_POST['departamento_id'] : null;
            $cargo_id = ($destinatarios === 'cargo' && !empty($_POST['cargo_id'])) ? (int)$_POST['cargo_id'] : null;
            $data_expiracao = !empty($_POST['data_expiracao']) ? $_POST['data_expiracao'] . ' 23:59:59' : null;

            if (empty($titulo)) throw new Exception('Título é obrigatório.');
            if (empty($conteudo)) throw new Exception('Conteúdo é obrigatório.');

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO comunicados
                    (titulo, conteudo, tipo, prioridade, destinatarios, departamento_id, cargo_id, publicado_por, data_expiracao, ativo)
                    VALUES (:t, :c, :tp, :pr, :d, :dep, :cg, :pb, :de, 1)");
                $stmt->execute([
                    ':t' => $titulo, ':c' => $conteudo, ':tp' => $tipo, ':pr' => $prioridade,
                    ':d' => $destinatarios, ':dep' => $departamento_id, ':cg' => $cargo_id,
                    ':pb' => $user_id, ':de' => $data_expiracao
                ]);
                $newId = $db->lastInsertId();

                $count = 0;
                if ($destinatarios === 'todos') {
                    $users = $db->query("SELECT id FROM usuarios WHERE ativo = 1")->fetchAll();
                    $count = count($users);
                    foreach ($users as $u) {
                        Notification::send($u['id'], '[Comunicado] ' . $titulo, mb_substr($conteudo, 0, 200), $prioridade === 'critica' ? 'danger' : 'info', '/comunicados.php?action=view&id=' . $newId);
                    }
                } elseif ($destinatarios === 'departamento' && $departamento_id) {
                    $users = $db->prepare("SELECT u.id FROM usuarios u JOIN funcionarios f ON u.id = f.usuario_id WHERE f.departamento_id = :d AND u.ativo = 1");
                    $users->execute([':d' => $departamento_id]);
                    foreach ($users->fetchAll() as $u) {
                        Notification::send($u['id'], '[Comunicado] ' . $titulo, mb_substr($conteudo, 0, 200), $prioridade === 'critica' ? 'danger' : 'info', '/comunicados.php?action=view&id=' . $newId);
                        $count++;
                    }
                } elseif ($destinatarios === 'cargo' && $cargo_id) {
                    $users = $db->prepare("SELECT u.id FROM usuarios u JOIN funcionarios f ON u.id = f.usuario_id WHERE f.cargo_id = :c AND u.ativo = 1");
                    $users->execute([':c' => $cargo_id]);
                    foreach ($users->fetchAll() as $u) {
                        Notification::send($u['id'], '[Comunicado] ' . $titulo, mb_substr($conteudo, 0, 200), $prioridade === 'critica' ? 'danger' : 'info', '/comunicados.php?action=view&id=' . $newId);
                        $count++;
                    }
                }

                Audit::create('comunicado', $newId, "Comunicado publicado: $titulo (alvo: $count utilizadores)");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Comunicado publicado!</strong> Notificações enviadas a ' . $count . ' destinatário(s).</div></div>';
                $action = 'list';
            } else {
                $stmt = $db->prepare("UPDATE comunicados SET titulo=:t, conteudo=:c, tipo=:tp, prioridade=:pr,
                    destinatarios=:d, departamento_id=:dep, cargo_id=:cg, data_expiracao=:de WHERE id=:id");
                $stmt->execute([
                    ':t' => $titulo, ':c' => $conteudo, ':tp' => $tipo, ':pr' => $prioridade,
                    ':d' => $destinatarios, ':dep' => $departamento_id, ':cg' => $cargo_id,
                    ':de' => $data_expiracao, ':id' => $id
                ]);
                Audit::update('comunicado', $id, "Comunicado atualizado: $titulo");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Comunicado atualizado!</strong></div></div>';
                $action = 'list';
            }
        } elseif ($action === 'delete' && $id > 0) {
            if (!$is_admin && $user_role !== 'gestor_rh') throw new Exception('Sem permissão.');
            $db->prepare("UPDATE comunicados SET ativo = 0 WHERE id = :id")->execute([':id' => $id]);
            Audit::delete('comunicado', $id, "Comunicado #$id arquivado");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Comunicado arquivado!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$departamentos = $db->query("SELECT id, nome FROM departamentos WHERE ativo = 1 ORDER BY nome")->fetchAll();
$cargos = $db->query("SELECT id, nome FROM cargos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$pageTitle = 'Comunicados e Mural';
$pageSubtitle = 'Mural interno e comunicações oficiais da empresa';
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

                <?php if ($action === 'view' && $id > 0):
                    $com = $db->prepare("SELECT c.*, u.username as autor FROM comunicados c JOIN usuarios u ON c.publicado_por = u.id WHERE c.id = :id AND c.ativo = 1");
                    $com->execute([':id' => $id]);
                    $com = $com->fetch();
                    if ($com):
                        $tipoCores = ['informativo'=>'info','urgente'=>'danger','politica'=>'primary','evento'=>'success','felicitacoes'=>'success','advertencia'=>'warning'];
                        $cor = $tipoCores[$com['tipo']] ?? 'info';
                ?>
                <div class="mb-3">
                    <a href="?action=list" style="font-size:0.85rem;color:var(--primary);text-decoration:none;"><i class="bi bi-arrow-left"></i> Voltar</a>
                </div>
                <div class="card" style="border-left:4px solid var(--<?php echo $cor; ?>);">
                    <div class="card-body" style="padding:1.5rem;">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;flex-wrap:wrap;">
                            <span class="badge" style="background:var(--<?php echo $cor; ?>-soft,var(--primary-soft));color:var(--<?php echo $cor; ?>,var(--primary));font-size:0.7rem;padding:4px 10px;border-radius:6px;">
                                <?php echo ucfirst(htmlspecialchars($com['tipo'])); ?>
                            </span>
                            <span class="badge" style="background:var(--bg-body);color:var(--text-muted);font-size:0.65rem;padding:3px 8px;border-radius:6px;">
                                <?php echo ucfirst(htmlspecialchars($com['prioridade'])); ?>
                            </span>
                            <span style="font-size:0.7rem;color:var(--text-muted);margin-left:auto;">
                                <?php echo date('d/m/Y H:i', strtotime($com['data_publicacao'])); ?>
                            </span>
                        </div>
                        <h2 style="font-size:1.3rem;font-weight:800;margin:0 0 0.75rem;"><?php echo htmlspecialchars($com['titulo']); ?></h2>
                        <div style="font-size:0.9rem;color:var(--text);line-height:1.7;white-space:pre-wrap;"><?php echo htmlspecialchars($com['conteudo']); ?></div>
                        <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);font-size:0.75rem;color:var(--text-muted);">
                            Publicado por <strong><?php echo htmlspecialchars($com['autor']); ?></strong>
                            <?php if ($com['data_expiracao']): ?>
                                · Expira em <?php echo date('d/m/Y', strtotime($com['data_expiracao'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Comunicado não encontrado.</div>
                <?php endif; endif; ?>

                <?php if ($action === 'list'): ?>
                    <?php if ($is_admin || $user_role === 'gestor_rh' || $user_role === 'lider_farmaceutico'): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                                <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                            </div>
                            <a href="?action=create" class="btn btn-primary"><i class="bi bi-megaphone"></i> Novo Comunicado</a>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php
                    $comunicados = $db->query("SELECT c.*, u.username as autor, d.nome as dept_nome, cg.nome as cargo_nome
                        FROM comunicados c
                        JOIN usuarios u ON c.publicado_por = u.id
                        LEFT JOIN departamentos d ON c.departamento_id = d.id
                        LEFT JOIN cargos cg ON c.cargo_id = cg.id
                        WHERE c.ativo = 1
                        ORDER BY
                            CASE c.prioridade WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END,
                            c.data_publicacao DESC")->fetchAll();
                    ?>

                    <?php if (empty($comunicados)): ?>
                        <div class="card">
                            <div class="empty-state">
                                <i class="bi bi-megaphone"></i>
                                <h4>Sem comunicados</h4>
                                <p>Ainda não há comunicados publicados.</p>
                            </div>
                        </div>
                    <?php else: foreach ($comunicados as $c):
                        $prioridadeMap = ['critica' => 'danger', 'alta' => 'warning', 'media' => 'info', 'baixa' => 'neutral'];
                        $pCls = $prioridadeMap[$c['prioridade']] ?? 'neutral';
                        $tipoIcons = ['informativo' => 'info-circle', 'urgente' => 'exclamation-triangle', 'politica' => 'shield-check', 'evento' => 'calendar-event', 'felicitacoes' => 'balloon-heart', 'advertencia' => 'exclamation-octagon'];
                        $icon = $tipoIcons[$c['tipo']] ?? 'megaphone';
                        $expired = $c['data_expiracao'] && strtotime($c['data_expiracao']) < time();
                    ?>
                        <div class="card mb-3" style="border-left: 4px solid var(--<?php echo $pCls; ?>); <?php echo $expired ? 'opacity: 0.6;' : ''; ?>">
                            <div class="card-body">
                                <div style="display: flex; justify-content: space-between; align-items: start; gap: 1rem; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 280px;">
                                        <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                            <span class="badge badge-<?php echo $pCls; ?>"><i class="bi bi-<?php echo $icon; ?>"></i> <?php echo ucfirst($c['prioridade']); ?></span>
                                            <span class="badge badge-neutral"><?php echo ucfirst($c['tipo']); ?></span>
                                            <?php if ($c['destinatarios'] !== 'todos'): ?>
                                                <span class="badge badge-info"><i class="bi bi-bullseye"></i>
                                                    <?php
                                                    if ($c['destinatarios'] === 'departamento') echo 'Dept: ' . htmlspecialchars($c['dept_nome'] ?? '?');
                                                    elseif ($c['destinatarios'] === 'cargo') echo 'Cargo: ' . htmlspecialchars($c['cargo_nome'] ?? '?');
                                                    else echo 'Individual';
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($expired): ?><span class="badge badge-warning"><i class="bi bi-clock-history"></i> Expirado</span><?php endif; ?>
                                        </div>
                                        <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($c['titulo']); ?></h3>
                                        <p style="color: var(--text-secondary); margin: 0 0 0.75rem 0; line-height: 1.6;"><?php echo nl2br(htmlspecialchars(mb_substr($c['conteudo'], 0, 400))); ?><?php echo mb_strlen($c['conteudo']) > 400 ? '…' : ''; ?></p>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; gap: 1rem; flex-wrap: wrap;">
                                            <span><i class="bi bi-person"></i> <?php echo htmlspecialchars($c['autor']); ?></span>
                                            <span><i class="bi bi-calendar3"></i> <?php echo date('d/m/Y H:i', strtotime($c['data_publicacao'])); ?></span>
                                            <?php if ($c['data_expiracao']): ?>
                                                <span><i class="bi bi-clock"></i> Expira: <?php echo date('d/m/Y', strtotime($c['data_expiracao'])); ?></span>
                                            <?php endif; ?>
                                            <span><i class="bi bi-eye"></i> <?php echo (int)$c['visualizacoes']; ?> visualizações</span>
                                        </div>
                                    </div>
                                    <?php if ($is_admin || $user_role === 'gestor_rh' || ($c['publicado_por'] == $user_id)): ?>
                                        <div class="d-flex gap-1">
                                            <a href="?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-icon btn-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <button class="btn btn-icon btn-secondary" title="Arquivar" onclick="if(confirm('Arquivar este comunicado?')) location.href='?action=delete&id=<?php echo $c['id']; ?>'">
                                                <i class="bi bi-archive" style="color: var(--warning);"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>

                <?php elseif ($action === 'create' || $action === 'edit'):
                    if (!$is_admin && $user_role !== 'gestor_rh' && $user_role !== 'lider_farmaceutico') {
                        echo '<div class="alert alert-danger">Sem permissão.</div>';
                    } else {
                        $c = ['id' => 0, 'titulo' => '', 'conteudo' => '', 'tipo' => 'informativo', 'prioridade' => 'media',
                            'destinatarios' => 'todos', 'departamento_id' => null, 'cargo_id' => null, 'data_expiracao' => ''];
                        if ($action === 'edit' && $id > 0) {
                            $stmt = $db->prepare("SELECT * FROM comunicados WHERE id = :id");
                            $stmt->execute([':id' => $id]);
                            $row = $stmt->fetch();
                            if ($row) {
                                $c = array_merge($c, $row);
                                if ($c['data_expiracao']) $c['data_expiracao'] = date('Y-m-d', strtotime($c['data_expiracao']));
                            }
                        }
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $action === 'create' ? 'Novo' : 'Editar'; ?> Comunicado</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Publique um aviso para a equipa</p>
                        </div>
                        <a href="comunicados.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" class="card" style="max-width: 800px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Título <span class="required">*</span></label>
                                <input type="text" name="titulo" class="form-control" required value="<?php echo htmlspecialchars($c['titulo']); ?>" placeholder="Ex: Reunião geral sexta-feira">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Conteúdo <span class="required">*</span></label>
                                <textarea name="conteudo" class="form-control" rows="6" required placeholder="Escreva a mensagem completa..."><?php echo htmlspecialchars($c['conteudo']); ?></textarea>
                            </div>
                            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tipo</label>
                                    <select name="tipo" class="form-select">
                                        <option value="informativo" <?php echo $c['tipo'] === 'informativo' ? 'selected' : ''; ?>>Informativo</option>
                                        <option value="urgente" <?php echo $c['tipo'] === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                        <option value="politica" <?php echo $c['tipo'] === 'politica' ? 'selected' : ''; ?>>Política/Regra</option>
                                        <option value="evento" <?php echo $c['tipo'] === 'evento' ? 'selected' : ''; ?>>Evento</option>
                                        <option value="felicitacoes" <?php echo $c['tipo'] === 'felicitacoes' ? 'selected' : ''; ?>>Felícitações</option>
                                        <option value="advertencia" <?php echo $c['tipo'] === 'advertencia' ? 'selected' : ''; ?>>Advertência</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Prioridade</label>
                                    <select name="prioridade" class="form-select">
                                        <option value="baixa" <?php echo $c['prioridade'] === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
                                        <option value="media" <?php echo $c['prioridade'] === 'media' ? 'selected' : ''; ?>>Média</option>
                                        <option value="alta" <?php echo $c['prioridade'] === 'alta' ? 'selected' : ''; ?>>Alta</option>
                                        <option value="critica" <?php echo $c['prioridade'] === 'critica' ? 'selected' : ''; ?>>Crítica</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Expira em</label>
                                    <input type="date" name="data_expiracao" class="form-control" value="<?php echo $c['data_expiracao']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Destinatários</label>
                                <select name="destinatarios" class="form-select" id="dest-select" onchange="document.getElementById('dept-div').style.display=this.value==='departamento'?'block':'none';document.getElementById('cargo-div').style.display=this.value==='cargo'?'block':'none'">
                                    <option value="todos" <?php echo $c['destinatarios'] === 'todos' ? 'selected' : ''; ?>>🌐 Todos os funcionários</option>
                                    <option value="departamento" <?php echo $c['destinatarios'] === 'departamento' ? 'selected' : ''; ?>>🏢 Um departamento</option>
                                    <option value="cargo" <?php echo $c['destinatarios'] === 'cargo' ? 'selected' : ''; ?>>💼 Um cargo</option>
                                </select>
                            </div>
                            <div class="form-group" id="dept-div" style="display: <?php echo $c['destinatarios'] === 'departamento' ? 'block' : 'none'; ?>;">
                                <label class="form-label">Departamento</label>
                                <select name="departamento_id" class="form-select">
                                    <option value="">— Selecione —</option>
                                    <?php foreach ($departamentos as $d): ?>
                                        <option value="<?php echo $d['id']; ?>" <?php echo $c['departamento_id'] == $d['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="cargo-div" style="display: <?php echo $c['destinatarios'] === 'cargo' ? 'block' : 'none'; ?>;">
                                <label class="form-label">Cargo</label>
                                <select name="cargo_id" class="form-select">
                                    <option value="">— Selecione —</option>
                                    <?php foreach ($cargos as $cg): ?>
                                        <option value="<?php echo $cg['id']; ?>" <?php echo $c['cargo_id'] == $cg['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cg['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="comunicados.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-megaphone"></i> Publicar e Notificar</button>
                        </div>
                    </form>
                <?php } endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
