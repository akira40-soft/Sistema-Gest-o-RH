<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

$currentUser = [
    'id' => $auth->getUserId(),
    'role' => $auth->getUserRole()
];

$db = Database::getInstance()->getConnection();

$funcionarioId = (int)($_GET['id'] ?? 0);
if ($funcionarioId <= 0) {
    header("Location: portal.php");
    exit;
}

try {
    $stmt = $db->prepare("
        SELECT f.id, f.nome_completo, f.email, f.telefone, f.foto,
               f.data_admissao, f.status, f.sexo,
               d.nome as departamento_nome, c.nome as cargo_nome
        FROM funcionarios f
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        LEFT JOIN cargos c ON f.cargo_id = c.id
        WHERE f.id = :id
    ");
    $stmt->execute([':id' => $funcionarioId]);
    $func = $stmt->fetch();

    if (!$func) {
        header("Location: portal.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Erro ao buscar funcionário: " . $e->getMessage());
    header("Location: portal.php");
    exit;
}

$pageTitle = $func['nome_completo'];
$pageSubtitle = $func['cargo_nome'] ?? 'Colaborador';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($func['nome_completo']); ?> | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item"><a href="portal.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($func['nome_completo']); ?></li>
                    </ol>
                </nav>

                <div class="card" style="max-width: 640px;">
                    <div class="card-body" style="padding: 2rem;">

                        <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.5rem;">
                            <?php if (!empty($func['foto']) && file_exists(__DIR__ . '/' . $func['foto'])): ?>
                                <img src="<?php echo htmlspecialchars($func['foto']); ?>" alt="<?php echo htmlspecialchars($func['nome_completo']); ?>"
                                     style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-light);">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; flex-shrink: 0;">
                                    <?php echo strtoupper(mb_substr($func['nome_completo'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($func['nome_completo']); ?></h2>
                                <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($func['cargo_nome'] ?? '—'); ?>
                                    <?php if ($func['departamento_nome']): ?>
                                        · <?php echo htmlspecialchars($func['departamento_nome']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Email</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($func['email'] ?? '—'); ?>
                                </p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Telefone</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($func['telefone'] ?? '—'); ?>
                                </p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Departamento</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($func['departamento_nome'] ?? '—'); ?>
                                </p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Cargo</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($func['cargo_nome'] ?? '—'); ?>
                                </p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Data de Admissão</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php echo !empty($func['data_admissao']) ? date('d/m/Y', strtotime($func['data_admissao'])) : '—'; ?>
                                </p>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Status</label>
                                <p style="margin: 0.25rem 0 0; font-size: 0.95rem;">
                                    <?php
                                    $statusClass = match($func['status'] ?? '') {
                                        'ativo' => 'badge-success',
                                        'ferias' => 'badge-warning',
                                        'afastado' => 'badge-info',
                                        'demitido' => 'badge-danger',
                                        default => 'badge-neutral'
                                    };
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <span class="badge-dot"></span>
                                        <?php echo htmlspecialchars(ucfirst($func['status'] ?? '—')); ?>
                                    </span>
                                </p>
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
