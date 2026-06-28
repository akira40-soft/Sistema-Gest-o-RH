<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Upload;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$db = Database::getInstance()->getConnection();

$action = $_GET['action'] ?? 'view';
$msg = '';
$msgType = '';

$user_data = $db->prepare("SELECT u.*, f.id as func_id, f.nome_completo, f.email as func_email, f.telefone, f.bi, f.data_nascimento, f.sexo, COALESCE(f.foto, u.foto) as foto
    FROM usuarios u LEFT JOIN funcionarios f ON u.id = f.usuario_id
    WHERE u.id = :id");
$user_data->execute([':id' => $user_id]);
$ud = $user_data->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'update_info') {
            $nome = trim($_POST['nome_completo'] ?? '');
            $email = trim($_POST['func_email'] ?? '');
            $telefone = trim($_POST['telefone'] ?? '');

            if ($ud && $ud['func_id']) {
                $stmt = $db->prepare("UPDATE funcionarios SET nome_completo=:n, email=:e, telefone=:t WHERE usuario_id=:u");
                $stmt->execute([':n' => $nome, ':e' => $email, ':t' => $telefone, ':u' => $user_id]);
                Audit::update('perfil', $user_id, "Dados pessoais atualizados");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Perfil atualizado!</strong></div></div>';
            } else {
                $db->prepare("INSERT INTO funcionarios (usuario_id, nome_completo, email, telefone, data_admissao, status) VALUES (:u, :n, :e, :t, CURDATE(), 'ativo')")
                    ->execute([':u' => $user_id, ':n' => $nome, ':e' => $email, ':t' => $telefone]);
                Audit::update('perfil', $user_id, "Dados pessoais criados (novo registo de funcionário)");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Perfil criado e atualizado!</strong></div></div>';
            }
        } elseif ($action === 'upload_photo') {
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception('Selecione uma imagem.');
            }
            $uploader = new Upload(__DIR__ . '/assets/uploads/perfis', ['jpg','jpeg','png','webp','gif','bmp','tiff','svg'], 2 * 1024 * 1024);
            $result = $uploader->upload($_FILES['foto'], 'user_' . $user_id . '_' . time());
            $relPath = 'assets/uploads/perfis/' . $result['filename'];
            if ($ud && $ud['func_id']) {
                if (!empty($ud['foto']) && file_exists(__DIR__ . '/' . $ud['foto'])) {
                    @unlink(__DIR__ . '/' . $ud['foto']);
                }
                $db->prepare("UPDATE funcionarios SET foto=:f WHERE id=:id")
                    ->execute([':f' => $relPath, ':id' => $ud['func_id']]);
            } else {
                $db->prepare("UPDATE usuarios SET foto=:f WHERE id=:id")
                    ->execute([':f' => $relPath, ':id' => $user_id]);
            }
            Audit::update('perfil', $user_id, "Foto de perfil atualizada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Foto atualizada com sucesso!</strong></div></div>';
        } elseif ($action === 'remove_photo') {
            if (!empty($ud['foto']) && file_exists(__DIR__ . '/' . $ud['foto'])) {
                @unlink(__DIR__ . '/' . $ud['foto']);
            }
            if ($ud && $ud['func_id']) {
                $db->prepare("UPDATE funcionarios SET foto=NULL WHERE id=:id")
                    ->execute([':id' => $ud['func_id']]);
            } else {
                $db->prepare("UPDATE usuarios SET foto=NULL WHERE id=:id")
                    ->execute([':id' => $user_id]);
            }
            Audit::update('perfil', $user_id, "Foto de perfil removida");
            $msg = '<div class="alert alert-info"><i class="bi bi-info-circle"></i><div class="alert-content">Foto removida.</div></div>';
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($current) || empty($new) || empty($confirm)) throw new Exception('Preencha todos os campos.');
            if ($new !== $confirm) throw new Exception('A nova password e a confirmação não coincidem.');
            if (strlen($new) < 8) throw new Exception('A nova password deve ter pelo menos 8 caracteres.');
            if (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) throw new Exception('A nova password deve ter pelo menos uma maiúscula e um dígito.');

            $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            $row = $stmt->fetch();
            if (!password_verify($current, $row['password_hash'])) throw new Exception('Password atual incorreta.');

            $newHash = password_hash($new, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET password_hash = :h WHERE id = :id")->execute([':h' => $newHash, ':id' => $user_id]);
            Audit::update('perfil', $user_id, "Password alterada");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Password alterada com sucesso!</strong> Na próxima sessão use a nova password.</div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }

    $user_data->execute([':id' => $user_id]);
    $ud = $user_data->fetch();
}

$audits = $db->prepare("SELECT * FROM audit_logs WHERE user_id = :u ORDER BY criado_em DESC LIMIT 15");
$audits->execute([':u' => $user_id]);
$audits = $audits->fetchAll();

$pageTitle = 'Meu Perfil';
$pageSubtitle = 'Configurações da sua conta e segurança';

$fotoUrl = !empty($ud['foto']) && file_exists(__DIR__ . '/' . $ud['foto'])
    ? $ud['foto'] . '?v=' . filemtime(__DIR__ . '/' . $ud['foto'])
    : null;
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

                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
                    <div>
                        <div class="card">
                            <div class="card-body" style="text-align: center; padding: 2rem 1rem;">
                                <div class="profile-photo-wrap" style="position: relative; width: 120px; height: 120px; margin: 0 auto 1rem;">
                                    <?php if ($fotoUrl): ?>
                                        <img src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="Foto de perfil" class="profile-photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-light); box-shadow: 0 8px 24px rgba(59,130,246,0.3);">
                                    <?php else: ?>
                                        <div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; box-shadow: 0 8px 24px rgba(59,130,246,0.3);">
                                            <?php echo strtoupper(substr($ud['username'] ?? 'U', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <label for="foto-input" class="profile-photo-edit" title="Alterar foto" style="position: absolute; bottom: 0; right: 0; width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid var(--bg-card); box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: var(--transition);">
                                        <i class="bi bi-camera-fill"></i>
                                    </label>
                                </div>
                                <form id="form-photo" method="POST" action="?action=upload_photo" enctype="multipart/form-data" style="display: none;">
                                    <input type="file" id="foto-input" name="foto" accept="image/*" onchange="document.getElementById('form-photo').submit();">
                                </form>
                                <?php if ($fotoUrl): ?>
                                    <form method="POST" action="?action=remove_photo" style="margin: 0 0 1rem 0;" onsubmit="return confirm('Remover a foto de perfil?');">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="font-size: 0.75rem;"><i class="bi bi-trash"></i> Remover foto</button>
                                    </form>
                                <?php endif; ?>
                                <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.25rem 0;"><?php echo htmlspecialchars($ud['nome_completo'] ?? $ud['username']); ?></h3>
                                <p style="color: var(--text-muted); margin: 0 0 1rem 0;">@<?php echo htmlspecialchars($ud['username']); ?></p>
                                <span class="badge badge-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                                    <i class="bi bi-shield-check"></i> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ud['tipo_acesso']))); ?>
                                </span>
                                <hr style="border-color: var(--border-color); margin: 1.5rem 0;">
                                <div style="text-align: left;">
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0;">
                                        <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($ud['func_email'] ?? '—'); ?>
                                    </p>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0;">
                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($ud['telefone'] ?? '—'); ?>
                                    </p>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0;">
                                        <i class="bi bi-clock"></i> Último login: <?php echo $ud['ultimo_login'] ? date('d/m/Y H:i', strtotime($ud['ultimo_login'])) : '—'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header"><h4 style="font-size: 0.95rem; font-weight: 700; margin: 0;"><i class="bi bi-clock-history"></i> Atividade Recente</h4></div>
                            <div class="card-body" style="padding: 0.5rem 1rem;">
                                <?php if (empty($audits)): ?>
                                    <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem;">Sem atividade registada.</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($audits, 0, 8) as $a): ?>
                                        <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--border-color); font-size: 0.85rem;">
                                            <div style="display: flex; justify-content: space-between; gap: 0.5rem;">
                                                <span style="font-weight: 500;"><?php echo htmlspecialchars($a['acao']); ?> <?php echo htmlspecialchars($a['entidade'] ?? ''); ?></span>
                                                <span style="color: var(--text-muted); white-space: nowrap;"><?php echo date('d/m H:i', strtotime($a['criado_em'])); ?></span>
                                            </div>
                                            <?php if ($a['detalhes']): ?>
                                                <div style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.125rem;"><?php echo htmlspecialchars(mb_substr($a['detalhes'], 0, 80)); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="card mb-3">
                            <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-person-vcard"></i> Dados Pessoais</h3></div>
                            <div class="card-body">
                                <form method="POST" action="?action=update_info">
                                    <div class="form-group">
                                        <label class="form-label">Nome Completo</label>
                                        <input type="text" name="nome_completo" class="form-control" value="<?php echo htmlspecialchars($ud['nome_completo'] ?? ''); ?>">
                                    </div>
                                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="func_email" class="form-control" value="<?php echo htmlspecialchars($ud['func_email'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Telefone</label>
                                            <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($ud['telefone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Guardar</button>
                                </form>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><h3 style="font-size: 1rem; font-weight: 700; margin: 0;"><i class="bi bi-key"></i> Alterar Password</h3></div>
                            <div class="card-body">
                                <form method="POST" action="?action=change_password">
                                    <div class="form-group">
                                        <label class="form-label">Password Atual</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                        <div class="form-group">
                                            <label class="form-label">Nova Password</label>
                                            <input type="password" name="new_password" class="form-control" required minlength="8">
                                            <small style="color: var(--text-muted);">Mínimo 8 caracteres</small>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Confirmar Password</label>
                                            <input type="password" name="confirm_password" class="form-control" required minlength="8">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i> Alterar Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
