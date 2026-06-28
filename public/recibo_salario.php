<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/FolhaPagamento.php';
require_once __DIR__ . '/../src/Utils/PdfGenerator.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Models\FolhaPagamento;
use App\Utils\PdfGenerator;

$auth = new Auth();
$auth->requireAuth();

$userId = $auth->getUserId();
$userRole = $auth->getUserRole();
$db = Database::getInstance()->getConnection();

// Buscar funcionário vinculado
$funcStmt = $db->prepare("SELECT id, nome_completo FROM funcionarios WHERE usuario_id = :uid");
$funcStmt->execute([':uid' => $userId]);
$funcionario = $funcStmt->fetch();

$folhaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Se não tem ID, mostra a lista de recibos do funcionário
if (!$folhaId && $funcionario) {
    $stmt = $db->prepare("SELECT f.*
        FROM folha_pagamento f
        WHERE f.funcionario_id = :fid
        ORDER BY f.ano DESC, f.mes DESC
        LIMIT 12");
    $stmt->execute([':fid' => $funcionario['id']]);
    $recibos = $stmt->fetchAll();

    $pageTitle = 'Holerite';
    $pageSubtitle = 'Recibos de vencimento';
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holerite | Gingong</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>
            <div class="content-body">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="portal.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item active">Holerite</li>
                    </ol>
                </nav>

                <div class="mb-3">
                    <h2 style="font-size:1.5rem;font-weight:800;margin:0;">Meus Recibos</h2>
                    <p style="color:var(--text-muted);margin:0.25rem 0 0;font-size:0.875rem;">Recibos de vencimento de <?php echo htmlspecialchars($funcionario['nome_completo']); ?></p>
                </div>

                <?php if (empty($recibos)): ?>
                <div class="card">
                    <div class="card-body text-center" style="padding:3rem;">
                        <i class="bi bi-receipt" style="font-size:3rem;color:var(--text-muted);opacity:0.4;"></i>
                        <h5 class="mt-3" style="color:var(--text-muted);">Sem recibos disponíveis</h5>
                        <p style="color:var(--text-muted);font-size:0.875rem;">Ainda não há recibos de vencimento processados.</p>
                    </div>
                </div>
                <?php else: ?>
                <div style="display:grid;gap:0.75rem;">
                    <?php foreach ($recibos as $r):
                        $meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
                        $mesNome = $meses[(int)$r['mes']] ?? $r['mes'];
                        $liquido = isset($r['salario_liquido']) ? number_format($r['salario_liquido'], 2, ',', '.') : 'N/A';
                    ?>
                    <a href="?id=<?php echo $r['id']; ?>" style="text-decoration:none;color:var(--text);">
                        <div class="card" style="border:1px solid var(--border);transition:all 0.2s;cursor:pointer;">
                            <div class="card-body" style="display:flex;align-items:center;gap:1rem;padding:1rem;">
                                <div style="width:48px;height:48px;border-radius:12px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="bi bi-receipt" style="font-size:1.3rem;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:700;font-size:0.95rem;"><?php echo $mesNome . ' ' . $r['ano']; ?></div>
                                    <div style="font-size:0.75rem;color:var(--text-muted);">
                                        Processado: <?php echo date('d/m/Y', strtotime($r['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:700;color:var(--primary);font-size:0.95rem;"><?php echo $liquido; ?> Kz</div>
                                    <div style="font-size:0.7rem;color:var(--text-muted);">Líquido</div>
                                </div>
                                <i class="bi bi-chevron-right" style="color:var(--text-muted);"></i>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
<?php
    exit;
}

// Se tem ID, mostra o recibo específico
if ($folhaId) {
    $folhaModel = new FolhaPagamento();
    $recibo = $folhaModel->obterPorId($folhaId);

    if (!$recibo) {
        die("Recibo não encontrado.");
    }
    
    // Verificar autorização: RH/Admin pode ver qualquer recibo, funcionário só o seu
    if (!$auth->isHRStaff()) {
        $funcVinculado = $db->prepare("SELECT id FROM funcionarios WHERE usuario_id = :uid AND id = :fid");
        $funcVinculado->execute([':uid' => $userId, ':fid' => $recibo['funcionario_id']]);
        if (!$funcVinculado->fetch()) {
            die("Acesso negado. Você não tem permissão para ver este recibo.");
        }
    }
    
    // Gerar PDF se solicitado
    if (isset($_GET['pdf'])) {
        $pdf = new PdfGenerator();
        $pdf->reciboSalario($recibo);
        exit;
    }
} else {
    die("ID da folha não especificado.");
}

// Formatar valores
function kz($valor)
{
    return number_format($valor, 2, ',', '.') . ' Kz';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Salário - <?php echo htmlspecialchars($recibo['nome_completo']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f5f5; padding: 20px; }
        .recibo-container {
            background: white;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; }
        .info-empresa { text-align: right; font-size: 12px; }
        .dados-func { margin-bottom: 30px; }
        .table-valores th { background: #f8f9fa; }
        .total-row { font-size: 1.2em; font-weight: bold; background: #e9ecef !important; }
        .assinaturas { margin-top: 80px; display: flex; justify-content: space-between; }
        .assinatura-box { border-top: 1px solid #000; width: 40%; text-align: center; padding-top: 10px; }
        
        @media print {
            body { background: white; padding: 0; }
            .recibo-container { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="text-center mb-4 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg">🖨️ Imprimir</button>
        <a href="?id=<?php echo $folhaId; ?>&pdf=1" class="btn btn-success btn-lg" target="_blank">📥 Baixar PDF</a>
        <button onclick="window.close()" class="btn btn-secondary btn-lg">Fechar</button>
    </div>

    <div class="recibo-container">
        <div class="header d-flex justify-content-between">
            <div class="logo">FARMÁCIA VALÓDIA RG</div>
            <div class="info-empresa">
                <strong>Recibo de Vencimento</strong><br>
                Mês/Ano: <?php echo $recibo['mes'] . '/' . $recibo['ano']; ?><br>
                Processado em: <?php echo date('d/m/Y', strtotime($recibo['created_at'])); ?>
            </div>
        </div>

        <div class="dados-func row">
            <div class="col-6">
                <strong>Funcionário:</strong> <?php echo htmlspecialchars($recibo['nome_completo']); ?><br>
                <strong>Cargo:</strong> <?php echo htmlspecialchars($recibo['cargo']); ?><br>
                <strong>Departamento:</strong> <?php echo htmlspecialchars($recibo['departamento']); ?>
            </div>
            <div class="col-6 text-end">
                <strong>NIF:</strong> <?php echo htmlspecialchars($recibo['nif_angolano'] ?? 'N/A'); ?><br>
                <strong>BI:</strong> <?php echo htmlspecialchars($recibo['bi'] ?? 'N/A'); ?>
            </div>
        </div>

        <table class="table table-bordered table-valores">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-end" width="150">Ganhos (+)</th>
                    <th class="text-end" width="150">Descontos (-)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Salário Base</td>
                    <td class="text-end"><?php echo kz($recibo['salario_base']); ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td>Subsídio Alimentação</td>
                    <td class="text-end"><?php echo kz($recibo['subsidio_alimentacao']); ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td>Subsídio Transporte</td>
                    <td class="text-end"><?php echo kz($recibo['subsidio_transporte']); ?></td>
                    <td></td>
                </tr>
                <?php if ($recibo['horas_extras'] > 0): ?>
                <tr>
                    <td>Horas Extras</td>
                    <td class="text-end"><?php echo kz($recibo['horas_extras']); ?></td>
                    <td></td>
                </tr>
                <?php
endif; ?>
                <?php if ($recibo['bonus'] > 0): ?>
                <tr>
                    <td>Bônus / Prêmios</td>
                    <td class="text-end"><?php echo kz($recibo['bonus']); ?></td>
                    <td></td>
                </tr>
                <?php
endif; ?>

                <!-- Descontos -->
                <tr>
                    <td>Segurança Social (3%)</td>
                    <td></td>
                    <td class="text-end"><?php echo kz($recibo['desconto_inss_trabalhador']); ?></td>
                </tr>
                <tr>
                    <td>IRT (Imposto de Renda)</td>
                    <td></td>
                    <td class="text-end"><?php echo kz($recibo['desconto_irt']); ?></td>
                </tr>
                <?php if ($recibo['desconto_faltas'] > 0): ?>
                <tr>
                    <td>Faltas / Atrasos</td>
                    <td></td>
                    <td class="text-end"><?php echo kz($recibo['desconto_faltas']); ?></td>
                </tr>
                <?php
endif; ?>

                <tr class="table-light fw-bold">
                    <td>Totais</td>
                    <td class="text-end"><?php echo kz($recibo['total_proventos']); ?></td>
                    <td class="text-end"><?php echo kz($recibo['total_descontos']); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="2" class="text-end">LÍQUIDO A RECEBER</td>
                    <td class="text-end text-success"><?php echo kz($recibo['salario_liquido']); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="row mt-4">
            <div class="col-12 text-muted small">
                Este recibo foi processado eletronicamente. Declaro ter recebido a importância líquida discriminada neste recibo.
            </div>
        </div>

        <div class="assinaturas">
            <div class="assinatura-box">
                Entidade Empregadora
            </div>
            <div class="assinatura-box">
                O Funcionário
            </div>
        </div>
    </div>

    <?php
// Auto-imprimir se solicitado via GET
if (isset($_GET['print'])) {
    echo "<script>window.print();</script>";
}
?>
</body>
</html>
