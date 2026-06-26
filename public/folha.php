<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Utils/PdfGenerator.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Audit;
use App\Utils\Notification;
use App\Models\FolhaPagamento;
use App\Utils\TaxasAngola;
use App\Utils\PdfGenerator;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isHRStaff()) { header('Location: acesso_negado.php'); exit; }

$db = Database::getInstance()->getConnection();
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// Processar folha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'process') {
    try {
        $mes = (int)$_POST['mes'];
        $ano = (int)$_POST['ano'];
        $funcionario_id = !empty($_POST['funcionario_id']) ? (int)$_POST['funcionario_id'] : null;

        $fp = new FolhaPagamento();

        if ($funcionario_id) {
            $extras = [
                'horas_extras' => (float)($_POST['horas_extras'] ?? 0),
                'subsidio_alimentacao' => (float)($_POST['subsidio_alimentacao'] ?? 0),
                'subsidio_transporte' => (float)($_POST['subsidio_transporte'] ?? 0),
                'bonus' => (float)($_POST['bonus'] ?? 0),
                'desconto_faltas' => (float)($_POST['desconto_faltas'] ?? 0),
            ];
            $fp->processarFolha($funcionario_id, $mes, $ano, $extras, $auth->getUserId());
            Audit::create('folha_pagamento', null, "Folha processada func=$funcionario_id $mes/$ano");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Folha processada para o funcionário!</strong></div></div>';
        } else {
            // Processar em massa
            $funcs = $db->query("SELECT id, nome_completo FROM funcionarios WHERE status = 'ativo'")->fetchAll();
            $count = 0;
            foreach ($funcs as $f) {
                try {
                    $fp->processarFolha($f['id'], $mes, $ano, [], $auth->getUserId());
                    $count++;
                } catch (Exception $e) {
                    error_log("Folha func {$f['id']}: " . $e->getMessage());
                }
            }
            Audit::log('process', 'folha_pagamento', null, "Folha processada em massa: $count funcionários em $mes/$ano");
            $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Folha processada para ' . $count . ' funcionários!</strong></div></div>';
        }
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i><div class="alert-content"><strong>Erro:</strong> ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    }
}

// Marcar como pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'pay' && $id > 0) {
    $db->prepare("UPDATE folha_pagamento SET status = 'pago', data_pagamento = CURDATE() WHERE id = :id")->execute([':id' => $id]);
    Audit::update('folha_pagamento', $id, 'Marcada como paga');
    $msg = '<div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div class="alert-content"><strong>Folha marcada como paga!</strong></div></div>';
    $action = 'list';
}

// Gerar PDFs
$mes = (int)($_GET['mes'] ?? date('n'));
$ano = (int)($_GET['ano'] ?? date('Y'));

if (in_array($action, ['pdf_inss', 'pdf_irt', 'pdf_folha'])) {
    $folhasPdf = $db->prepare("
        SELECT fp.*, f.nome_completo, f.bi, f.nif, d.nome as departamento, c.nome as cargo
        FROM folha_pagamento fp
        JOIN funcionarios f ON fp.funcionario_id = f.id
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        LEFT JOIN cargos c ON f.cargo_id = c.id
        WHERE fp.mes = :mes AND fp.ano = :ano
        ORDER BY f.nome_completo
    ");
    $folhasPdf->execute([':mes' => $mes, ':ano' => $ano]);
    $folhasPdf = $folhasPdf->fetchAll();
    
    $pdf = new PdfGenerator();
    
    if ($action === 'pdf_inss') {
        $pdf->mapaINSS($folhasPdf, $mes, $ano);
    } elseif ($action === 'pdf_irt') {
        $pdf->mapaIRT($folhasPdf, $mes, $ano);
    } elseif ($action === 'pdf_folha') {
        $totais = [
            'proventos' => array_sum(array_column($folhasPdf, 'total_proventos')),
            'inss' => array_sum(array_column($folhasPdf, 'desconto_inss_trabalhador')),
            'irt' => array_sum(array_column($folhasPdf, 'desconto_irt')),
            'descontos' => array_sum(array_column($folhasPdf, 'total_descontos')),
            'liquido' => array_sum(array_column($folhasPdf, 'salario_liquido')),
        ];
        $pdf->folhaPagamento($folhasPdf, $mes, $ano, $totais);
    }
    exit;
}

if ($action === 'list') {
    $folhas = $db->prepare("
        SELECT fp.*, f.nome_completo, f.bi, d.nome as departamento, c.nome as cargo
        FROM folha_pagamento fp
        JOIN funcionarios f ON fp.funcionario_id = f.id
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        LEFT JOIN cargos c ON f.cargo_id = c.id
        WHERE fp.mes = :mes AND fp.ano = :ano
        ORDER BY f.nome_completo
    ");
    $folhas->execute([':mes' => $mes, ':ano' => $ano]);
    $folhas = $folhas->fetchAll();

    // Totais
    $totalProventos = array_sum(array_column($folhas, 'total_proventos'));
    $totalDescontos = array_sum(array_column($folhas, 'total_descontos'));
    $totalLiquido = array_sum(array_column($folhas, 'salario_liquido'));
    $totalINSS = array_sum(array_column($folhas, 'desconto_inss_trabalhador'));
    $totalIRT = array_sum(array_column($folhas, 'desconto_irt'));
}

$funcionarios = $db->query("SELECT id, nome_completo, salario_atual FROM funcionarios WHERE status = 'ativo' ORDER BY nome_completo")->fetchAll();

$pageTitle = 'Folha de Pagamento';
$pageSubtitle = 'Cálculo salarial · Lei Angolana';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folha | SG Farmácia Gingongo</title>
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
                        <li class="breadcrumb-item active">Folha de Pagamento</li>
                    </ol>
                </nav>

                <?php if ($msg) echo $msg; ?>

                <?php if ($action === 'list'): ?>
                    <div class="page-header">
                        <div>
                            <h2 class="page-title"><i class="bi bi-cash-stack"></i> Folha de Pagamento</h2>
                            <p class="page-subtitle"><?php echo strftime('%B %Y', strtotime("$ano-$mes-01")); ?> · <?php echo count($folhas); ?> funcionários</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="?action=pdf_inss&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Mapa INSS PDF</a>
                            <a href="?action=pdf_irt&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Mapa IRT PDF</a>
                            <a href="?action=pdf_folha&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-secondary"><i class="bi bi-file-earmark-pdf"></i> Folha PDF</a>
                            <a href="?action=process&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-primary"><i class="bi bi-calculator"></i> Processar Folha</a>
                        </div>
                    </div>

                    <form class="filter-bar" method="GET">
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <a href="?mes=<?php echo $mes == 1 ? 12 : $mes - 1; ?>&ano=<?php echo $mes == 1 ? $ano - 1 : $ano; ?>" class="btn btn-icon btn-secondary"><i class="bi bi-chevron-left"></i></a>
                            <strong><?php echo strftime('%B %Y', strtotime("$ano-$mes-01")); ?></strong>
                            <a href="?mes=<?php echo $mes == 12 ? 1 : $mes + 1; ?>&ano=<?php echo $mes == 12 ? $ano + 1 : $ano; ?>" class="btn btn-icon btn-secondary"><i class="bi bi-chevron-right"></i></a>
                        </div>
                    </form>

                    <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="stat-card">
                            <div class="stat-content">
                                <div class="stat-label">Total Proventos</div>
                                <div class="stat-value" style="font-size: 1.1rem;"><?php echo kz($totalProventos); ?></div>
                            </div>
                        </div>
                        <div class="stat-card danger">
                            <div class="stat-content">
                                <div class="stat-label">INSS (3%)</div>
                                <div class="stat-value" style="font-size: 1.1rem;"><?php echo kz($totalINSS); ?></div>
                            </div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-content">
                                <div class="stat-label">IRT</div>
                                <div class="stat-value" style="font-size: 1.1rem;"><?php echo kz($totalIRT); ?></div>
                            </div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-content">
                                <div class="stat-label">Total Descontos</div>
                                <div class="stat-value" style="font-size: 1.1rem;"><?php echo kz($totalDescontos); ?></div>
                            </div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-content">
                                <div class="stat-label">Líquido a Pagar</div>
                                <div class="stat-value" style="font-size: 1.1rem;"><?php echo kz($totalLiquido); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Salário Base</th>
                                        <th>H.Extra</th>
                                        <th>Sub.Alim</th>
                                        <th>INSS</th>
                                        <th>IRT</th>
                                        <th>Líquido</th>
                                        <th>Status</th>
                                        <th class="text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($folhas)): ?>
                                        <tr><td colspan="9">
                                            <div class="empty-state">
                                                <i class="bi bi-cash-stack"></i>
                                                <h4>Sem folha processada para este mês</h4>
                                                <a href="?action=process&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-primary mt-2"><i class="bi bi-calculator"></i> Processar Agora</a>
                                            </div>
                                        </td></tr>
                                    <?php else: foreach ($folhas as $f):
                                        $statusCls = match($f['status']) {
                                            'pago' => 'success', 'processado' => 'warning',
                                            'rascunho' => 'neutral', 'cancelado' => 'danger',
                                            default => 'neutral'
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="avatar-sm"><?php echo strtoupper(substr($f['nome_completo'], 0, 1)); ?></div>
                                                    <div class="user-cell-info">
                                                        <span class="user-cell-name"><?php echo htmlspecialchars($f['nome_completo']); ?></span>
                                                        <span class="user-cell-sub"><?php echo htmlspecialchars($f['cargo'] ?? ''); ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-mono"><?php echo kz($f['salario_base']); ?></td>
                                            <td class="text-mono"><?php echo kz($f['horas_extras']); ?></td>
                                            <td class="text-mono"><?php echo kz($f['subsidio_alimentacao']); ?></td>
                                            <td class="text-mono text-danger">-<?php echo kz($f['desconto_inss_trabalhador']); ?></td>
                                            <td class="text-mono text-warning">-<?php echo kz($f['desconto_irt']); ?></td>
                                            <td class="text-mono font-bold text-success"><?php echo kz($f['salario_liquido']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $statusCls; ?>">
                                                    <span class="badge-dot"></span>
                                                    <?php echo ucfirst($f['status']); ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <a href="recibo_salario.php?id=<?php echo $f['id']; ?>" class="btn btn-icon btn-secondary" title="Recibo"><i class="bi bi-receipt"></i></a>
                                                <?php if ($f['status'] !== 'pago'): ?>
                                                    <form method="POST" action="?action=pay&id=<?php echo $f['id']; ?>" style="display:inline;" onsubmit="return confirm('Marcar como paga?')">
                                                        <button class="btn btn-icon btn-success" title="Marcar paga"><i class="bi bi-check2-circle"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($action === 'process'): ?>
                    <div class="page-header">
                        <h2 class="page-title">Processar Folha</h2>
                        <a href="folha.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    <form method="POST" class="card" style="max-width: 720px;">
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <div class="alert-content">
                                    <strong>Lei Angolana:</strong> INSS 3% (trabalhador) + 8% (entidade empregadora) sobre salário base. IRT calculado pela tabela progressiva sobre (base + subsídios sujeitos - INSS).
                                </div>
                            </div>
                            <div class="grid-2" style="gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Mês <span class="required">*</span></label>
                                    <select name="mes" class="form-select" required>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $m == $mes ? 'selected' : ''; ?>><?php echo strftime('%B', mktime(0, 0, 0, $m, 1)); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ano <span class="required">*</span></label>
                                    <input type="number" name="ano" class="form-control" required value="<?php echo $ano; ?>" min="2020" max="2099">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Funcionário Específico (opcional)</label>
                                <select name="funcionario_id" class="form-select">
                                    <option value="">— Todos os funcionários ativos (processar em massa) —</option>
                                    <?php foreach ($funcionarios as $f): ?>
                                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo']); ?> · Salário: <?php echo kz($f['salario_atual']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="extras" style="display: none;">
                                <h4 class="section-title">Proventos Variáveis</h4>
                                <div class="grid-2" style="gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Horas Extras (qtd)</label>
                                        <input type="number" name="horas_extras" class="form-control" step="0.5" min="0" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Subsídio Alimentação (Kz)</label>
                                        <input type="number" name="subsidio_alimentacao" class="form-control" step="0.01" min="0" value="30000">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Subsídio Transporte (Kz)</label>
                                        <input type="number" name="subsidio_transporte" class="form-control" step="0.01" min="0" value="30000">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Bónus (Kz)</label>
                                        <input type="number" name="bonus" class="form-control" step="0.01" min="0" value="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Desconto por Faltas (Kz)</label>
                                        <input type="number" name="desconto_faltas" class="form-control" step="0.01" min="0" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <a href="folha.php" class="btn btn-ghost">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-calculator"></i> Processar</button>
                        </div>
                    </form>
                    <script>
                        document.querySelector('[name="funcionario_id"]').addEventListener('change', function() {
                            document.getElementById('extras').style.display = this.value ? 'block' : 'none';
                        });
                    </script>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
