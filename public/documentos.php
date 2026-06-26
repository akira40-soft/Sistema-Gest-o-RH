<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Upload;

$auth = new Auth();
$auth->requireAuth();
$user_id = $auth->getUserId();
$is_admin = $auth->isAdmin();
$role = $auth->getUserRole();

$is_employee = ($role === 'funcionario');

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($is_employee && $action !== 'list' && $action !== 'view') {
    header("Location: documentos.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'upload') {
            if (!$is_admin && $role !== 'gestor_rh' && $role !== 'funcionario_rh') throw new Exception('Sem permissão para upload.');

            $funcionario_id = (int)($_POST['funcionario_id'] ?? 0);
            $tipo_documento = $_POST['tipo_documento'] ?? 'outro';
            $data_validade = !empty($_POST['data_validade']) ? $_POST['data_validade'] : null;
            $observacoes = trim($_POST['observacoes'] ?? '');

            if (!$funcionario_id) throw new Exception('Selecione um funcionário.');
            if (empty($_FILES['arquivo']['name'])) throw new Exception('Selecione um ficheiro.');

            $uploader = new Upload(__DIR__ . '/uploads/documentos');
            $result = $uploader->upload($_FILES['arquivo'], 'doc_' . $funcionario_id . '_' . $tipo_documento);

            $stmt = $db->prepare("INSERT INTO documentos_funcionarios
                (funcionario_id, tipo_documento, nome_original, nome_arquivo, caminho_arquivo, tamanho_kb, mime_type, data_validade, uploaded_por, observacoes, ativo, versao)
                VALUES (:f, :t, :no, :na, :ca, :tk, :mt, :dv, :up, :ob, 1, 1)");
            $stmt->execute([
                ':f' => $funcionario_id, ':t' => $tipo_documento, ':no' => $result['original_name'],
                ':na' => $result['filename'], ':ca' => $result['path'], ':tk' => $result['size_kb'],
                ':mt' => $result['mime_type'], ':dv' => $data_validade, ':up' => $user_id, ':ob' => $observacoes
            ]);
            $newId = $db->lastInsertId();
            Audit::create('documento', $newId, "Upload: {$result['original_name']} para funcionário #$funcionario_id");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Documento enviado!</strong> Arquivo guardado com sucesso.</div></div>';
            $action = 'list';
        } elseif ($action === 'delete' && $id > 0) {
            if (!$is_admin && $role !== 'gestor_rh' && $role !== 'funcionario_rh') throw new Exception('Sem permissão.');
            $stmt = $db->prepare("SELECT * FROM documentos_funcionarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $doc = $stmt->fetch();
            if ($doc) {
                @unlink(__DIR__ . '/' . $doc['caminho_arquivo']);
                $db->prepare("DELETE FROM documentos_funcionarios WHERE id = :id")->execute([':id' => $id]);
                Audit::delete('documento', $id, "Documento '{$doc['nome_original']}' eliminado");
                $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Documento eliminado!</strong></div></div>';
            }
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

$funcionarios = $db->query("SELECT f.id, f.nome_completo, c.nome as cargo FROM funcionarios f
    LEFT JOIN cargos c ON f.cargo_id = c.id
    ORDER BY f.nome_completo")->fetchAll();

$stats = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN data_validade IS NOT NULL AND data_validade < CURDATE() THEN 1 ELSE 0 END) as vencidos,
    SUM(CASE WHEN data_validade IS NOT NULL AND data_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as a_vencer
    FROM documentos_funcionarios WHERE ativo = 1")->fetch();

$pageTitle = 'Documentos de Funcionários';
$pageSubtitle = 'Gestão documental com alertas de validade';
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

                <?php if ($action === 'list'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;"><?php echo $pageTitle; ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>
                        </div>
                        <?php if (!$is_employee): ?>
                        <a href="?action=upload" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Enviar Documento</a>
                        <?php endif; ?>
                    </div>

                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-files"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
                            <div class="stat-label">Total de Documentos</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['a_vencer']; ?></div>
                            <div class="stat-label">A Vencer (30 dias)</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                            <div class="stat-value"><?php echo (int)$stats['vencidos']; ?></div>
                            <div class="stat-label">Vencidos</div>
                        </div>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="flex: 1; min-width: 240px;">
                            <select name="funcionario_id" class="form-select">
                                <option value="">Todos os funcionários</option>
                                <?php foreach ($funcionarios as $f): ?>
                                    <option value="<?php echo $f['id']; ?>" <?php echo (isset($_GET['funcionario_id']) && $_GET['funcionario_id'] == $f['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($f['nome_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 200px;">
                            <select name="tipo_documento" class="form-select">
                                <option value="">Todos os tipos</option>
                                <?php
                                $tipos = ['bi' => 'BI', 'cv' => 'CV', 'certificado_habilitacoes' => 'Certificado Habilitações', 'certificado_farmaceutico' => 'Certificado Farmacêutico', 'registo_criminal' => 'Registo Criminal', 'atestado_medico' => 'Atestado Médico', 'comprovativo_residencia' => 'Comp. Residência', 'foto' => 'Foto', 'contrato' => 'Contrato', 'termo_responsabilidade' => 'Termo Responsabilidade', 'outro' => 'Outro'];
                                foreach ($tipos as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo (isset($_GET['tipo_documento']) && $_GET['tipo_documento'] === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="documentos.php" class="btn btn-ghost" title="Limpar"><i class="bi bi-x-circle"></i></a>
                    </form>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Tipo</th>
                                        <th>Ficheiro</th>
                                        <th>Validade</th>
                                        <th>Tamanho</th>
                                        <th>Upload</th>
                                        <th style="text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $where = "WHERE d.ativo = 1";
                                    $params = [];
                                    if ($is_employee) {
                                        $emp = $db->prepare("SELECT id FROM funcionarios WHERE usuario_id = :uid LIMIT 1");
                                        $emp->execute([':uid' => $user_id]);
                                        $emp = $emp->fetch();
                                        if ($emp) {
                                            $where .= " AND d.funcionario_id = :emp_id";
                                            $params[':emp_id'] = $emp['id'];
                                        }
                                    } elseif (!empty($_GET['funcionario_id'])) {
                                        $where .= " AND d.funcionario_id = :f";
                                        $params[':f'] = (int)$_GET['funcionario_id'];
                                    }
                                    if (!empty($_GET['tipo_documento'])) {
                                        $where .= " AND d.tipo_documento = :t";
                                        $params[':t'] = $_GET['tipo_documento'];
                                    }
                                    $stmt = $db->prepare("SELECT d.*, f.nome_completo FROM documentos_funcionarios d
                                        JOIN funcionarios f ON d.funcionario_id = f.id $where ORDER BY d.data_upload DESC");
                                    $stmt->execute($params);
                                    $docs = $stmt->fetchAll();
                                    if (empty($docs)): ?>
                                        <tr><td colspan="7">
                                            <div class="empty-state">
                                                <i class="bi bi-files"></i>
                                                <h4>Sem documentos</h4>
                                                <p><?php echo $is_employee ? 'Nenhum documento associado.' : 'Faça upload do primeiro documento.'; ?></p>
                                                <?php if (!$is_employee): ?>
                                                <a href="?action=upload" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Upload</a>
                                                <?php endif; ?>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($docs as $d):
                                        $validade = '';
                                        $valCls = '';
                                        if ($d['data_validade']) {
                                            $dias = (strtotime($d['data_validade']) - time()) / 86400;
                                            if ($dias < 0) { $valCls = 'danger'; $validade = 'Vencido há ' . abs((int)$dias) . 'd'; }
                                            elseif ($dias < 30) { $valCls = 'warning'; $validade = (int)$dias . ' dias'; }
                                            else { $valCls = 'success'; $validade = date('d/m/Y', strtotime($d['data_validade'])); }
                                        } else { $validade = '—'; }
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary);">
                                                        <?php echo strtoupper(substr($d['nome_completo'], 0, 1)); ?>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($d['nome_completo']); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $tipoIcons = ['bi' => 'person-vcard', 'cv' => 'file-earmark-person', 'certificado_habilitacoes' => 'mortarboard', 'certificado_farmaceutico' => 'capsule', 'registo_criminal' => 'shield-check', 'atestado_medico' => 'heart-pulse', 'comprovativo_residencia' => 'house', 'foto' => 'image', 'contrato' => 'file-earmark-text', 'termo_responsabilidade' => 'file-earmark-lock', 'outro' => 'file-earmark'];
                                                $icon = $tipoIcons[$d['tipo_documento']] ?? 'file-earmark';
                                                ?>
                                                <span class="badge badge-neutral"><i class="bi <?php echo $icon; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $d['tipo_documento'])); ?></span>
                                            </td>
                                            <td>
                                                <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars($d['nome_original']); ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">v<?php echo (int)$d['versao']; ?></div>
                                            </td>
                                            <td>
                                                <?php if ($validade !== '—'): ?>
                                                    <span class="badge badge-<?php echo $valCls; ?>"><span class="badge-dot"></span> <?php echo $validade; ?></span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">Sem validade</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($d['tamanho_kb'] / 1024, 1); ?> MB</td>
                                            <td><?php echo date('d/m/Y', strtotime($d['data_upload'])); ?></td>
                                            <td>
                                                <div class="d-flex gap-1" style="justify-content: flex-end;">
                                                    <a href="<?php echo htmlspecialchars($d['caminho_arquivo']); ?>" target="_blank" class="btn btn-icon btn-secondary" title="Descarregar"><i class="bi bi-download"></i></a>
                                                    <?php if ($is_admin || $role === 'gestor_rh' || $role === 'funcionario_rh'): ?>
                                                        <button class="btn btn-icon btn-secondary" title="Eliminar" onclick="if(confirm('Eliminar este documento?')) location.href='?action=delete&id=<?php echo $d['id']; ?>'">
                                                            <i class="bi bi-trash" style="color: var(--danger);"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action === 'upload'): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Upload de Documento</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Formatos: PDF, JPG, PNG · Máx 10 MB</p>
                        </div>
                        <a href="documentos.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="card" style="max-width: 700px;">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Funcionário <span class="required">*</span></label>
                                <select name="funcionario_id" class="form-select" required>
                                    <option value="">— Selecione —</option>
                                    <?php foreach ($funcionarios as $f): ?>
                                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo'] . ' — ' . ($f['cargo'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tipo de Documento <span class="required">*</span></label>
                                <select name="tipo_documento" class="form-select" required>
                                    <option value="bi">Bilhete de Identidade</option>
                                    <option value="cv">Currículo (CV)</option>
                                    <option value="certificado_habilitacoes">Certificado de Habilitações</option>
                                    <option value="certificado_farmaceutico">Certificado/Cédula Farmacêutica</option>
                                    <option value="registo_criminal">Registo Criminal</option>
                                    <option value="atestado_medico">Atestado Médico</option>
                                    <option value="comprovativo_residencia">Comprovativo de Residência</option>
                                    <option value="foto">Fotografia</option>
                                    <option value="contrato">Contrato de Trabalho</option>
                                    <option value="termo_responsabilidade">Termo de Responsabilidade</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ficheiro <span class="required">*</span></label>
                                <input type="file" name="arquivo" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                                <small style="color: var(--text-muted);">PDF, JPG, PNG ou WEBP · máximo 10 MB</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Data de Validade</label>
                                <input type="date" name="data_validade" class="form-control">
                                <small style="color: var(--text-muted);">Opcional · preencha se o documento tem prazo de expiração</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control" rows="2" placeholder="Notas adicionais..."></textarea>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                            <a href="documentos.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Enviar</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
