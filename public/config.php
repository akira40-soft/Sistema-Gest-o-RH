<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

// Apenas Admin
if (!$auth->isAdmin()) {
    die("Acesso negado.");
}

$db = Database::getInstance()->getConnection();
$erro = '';
$sucesso = '';

// Salvar Configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'dashboard_bg' && $auth->isAdmin()) {
            $bg = trim($_POST['background'] ?? '');
            $opacity = (float)($_POST['overlay_opacity'] ?? 0.65);
            if (!preg_match('#^(assets/|uploads/)[a-zA-Z0-9_\-/\.]+$#', $bg)) {
                throw new Exception('Caminho da imagem inválido. Deve começar com assets/ ou uploads/.');
            }
            $opacity = max(0.0, min(1.0, $opacity));
            try {
                $isSQLite = ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
                $db->exec("CREATE TABLE IF NOT EXISTS dashboard_preferencias (
                    id INTEGER PRIMARY KEY,
                    background TEXT DEFAULT 'assets/uploads/backgrounds/default-pharmacy.jpg',
                    overlay_opacity REAL DEFAULT 0.65,
                    updated_at TEXT DEFAULT (datetime('now'))
                )");
                if ($isSQLite) {
                    $stmt = $db->prepare("INSERT OR REPLACE INTO dashboard_preferencias (id, background, overlay_opacity) VALUES (1, :b, :o)");
                    $stmt->execute([':b' => $bg, ':o' => $opacity]);
                } else {
                    $stmt = $db->prepare("INSERT INTO dashboard_preferencias (id, background, overlay_opacity) VALUES (1, :b, :o) ON DUPLICATE KEY UPDATE background=:b2, overlay_opacity=:o2");
                    $stmt->execute([':b' => $bg, ':o' => $opacity, ':b2' => $bg, ':o2' => $opacity]);
                }
                $sucesso = "Imagem de fundo do dashboard atualizada!";
            } catch (Exception $e) {
                throw new Exception('Erro ao atualizar imagem: ' . $e->getMessage());
            }
        } else {
            $isSQLite = ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            if ($isSQLite) {
                $stmt = $db->prepare("INSERT OR REPLACE INTO configuracoes_sistema (chave, valor) VALUES (:chave, :valor)");
            } else {
                $stmt = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (:chave, :valor) ON DUPLICATE KEY UPDATE valor = :valor_v");
            }

            foreach ($_POST as $chave => $valor) {
                if ($chave === 'acao')
                    continue;
                if ($isSQLite) {
                    $stmt->execute([':chave' => $chave, ':valor' => $valor]);
                } else {
                    $stmt->execute([':chave' => $chave, ':valor' => $valor, ':valor_v' => $valor]);
                }
            }
            $sucesso = "Configurações atualizadas com sucesso!";
        }
    }
    catch (Exception $e) {
        $erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// Carregar Configurações Atuais
$configs = [];
try {
    $stmt = $db->query("SELECT chave, valor FROM configuracoes_sistema");
    while ($row = $stmt->fetch()) {
        $configs[$row['chave']] = $row['valor'];
    }
} catch (Exception $e) {
    error_log("configuracoes_sistema table não existe — usando defaults: " . $e->getMessage());
}

// Carregar preferência de fundo do dashboard
$dashPref = ['background' => 'assets/uploads/backgrounds/default-pharmacy.jpg', 'overlay_opacity' => 0.65];
try {
    $s = $db->query("SELECT background, overlay_opacity FROM dashboard_preferencias WHERE id = 1");
    $row = $s ? $s->fetch(PDO::FETCH_ASSOC) : null;
    if ($row) $dashPref = array_merge($dashPref, $row);
} catch (Exception $e) { /* default */ }

// Listar imagens de fundo disponíveis
$bgDir = __DIR__ . '/assets/uploads/backgrounds';
$bgFiles = [];
if (is_dir($bgDir)) {
    foreach (scandir($bgDir) as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp'], true)) {
            $bgFiles[] = 'assets/uploads/backgrounds/' . $f;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | Farmácia Gingongo</title>
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
            <?php
            $pageTitle = 'Configurações do Sistema';
            $pageSubtitle = 'Dados da empresa, fundo do dashboard e ações sensíveis';
            include 'includes/topbar.php';
            ?>
            <div class="content-body">
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo $erro; ?></div>
                <?php
endif; ?>
                <?php if ($sucesso): ?>
                    <div class="alert alert-success"><?php echo $sucesso; ?></div>
                <?php
endif; ?>

                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item active">Configurações</li>
                    </ol>
                </nav>

                <div class="card">
                    <div class="card-header">Dados da Empresa</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="acao" value="salvar">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nome da Empresa</label>
                                    <input type="text" name="nome_empresa" class="form-control" value="<?php echo htmlspecialchars($configs['nome_empresa'] ?? 'Farmácia Gingongo RG'); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NIF</label>
                                    <input type="text" name="nif" class="form-control" value="<?php echo htmlspecialchars($configs['nif'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Endereço Completo</label>
                                <textarea name="endereco" class="form-control" rows="2"><?php echo htmlspecialchars($configs['endereco'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email de Contato</label>
                                    <input type="email" name="email_contato" class="form-control" value="<?php echo htmlspecialchars($configs['email_contato'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($configs['telefone'] ?? ''); ?>">
                                </div>
                            </div>

                            <hr>
                            <h5>Configurações Regionais</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Fuso Horário</label>
                                    <select name="timezone" class="form-select">
                                        <option value="Africa/Luanda" selected>Luanda (GMT+1)</option>
                                        <option value="UTC">UTC</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Moeda</label>
                                    <input type="text" class="form-control" value="Kwanza (AOA)" disabled>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header"><i class="bi bi-image"></i> Imagem de Fundo do Dashboard</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="acao" value="dashboard_bg">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Imagem</label>
                                    <select name="background" class="form-select">
                                        <?php foreach ($bgFiles as $bf): ?>
                                            <option value="<?php echo htmlspecialchars($bf); ?>" <?php echo $dashPref['background'] === $bf ? 'selected' : ''; ?>><?php echo basename($bf); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Adicione mais imagens em public/assets/uploads/backgrounds/</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Opacidade do overlay (0-1)</label>
                                    <input type="number" step="0.05" min="0" max="1" name="overlay_opacity" class="form-control" value="<?php echo htmlspecialchars($dashPref['overlay_opacity']); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Aplicar Fundo</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4 border-danger">
                    <div class="card-header bg-danger text-white">Zona de Perigo</div>
                    <div class="card-body">
                        <p>Ações irreversíveis.</p>
                        <button class="btn btn-outline-danger" onclick="alert('Funcionalidade bloqueada por segurança.')">Resetar Banco de Dados</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
