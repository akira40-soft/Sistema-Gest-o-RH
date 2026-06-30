<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\TaxasAngola;
use App\Utils\Angola;

$auth = new Auth();
$auth->requireAuth();
if (!$auth->isHRStaff()) {
    header('Location: acesso_negado.php'); exit;
}

$db = Database::getInstance()->getConnection();
$type = $_GET['type'] ?? 'home';
$msg = '';

$pageTitle = 'Relatórios e Análises';
$pageSubtitle = 'Mapas INSS/IRT, indicadores de RH e exportações';

function exportCsv($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out);
    exit;
}

if (isset($_GET['export'])) {
    $mes = (int)($_GET['mes'] ?? date('n'));
    $ano = (int)($_GET['ano'] ?? date('Y'));
    $stmt = $db->prepare("SELECT f.nome_completo, f.bi, f.nif_angolano, f.iban, fp.*,
        c.nome as cargo, d.nome as departamento
        FROM folha_pagamento fp
        JOIN funcionarios f ON fp.funcionario_id = f.id
        LEFT JOIN cargos c ON f.cargo_id = c.id
        LEFT JOIN departamentos d ON f.departamento_id = d.id
        WHERE fp.mes = :m AND fp.ano = :a ORDER BY f.nome_completo");
    $stmt->execute([':m' => $mes, ':a' => $ano]);
    $rows = $stmt->fetchAll();
    if ($_GET['export'] === 'mapa_inss') {
        $headers = ['Nº', 'Funcionário', 'BI', 'Departamento', 'Salário Base', 'Outros Rendimentos', 'Total Rendimentos', 'INSS 3%', 'IRT', 'Líquido'];
        $data = [];
        foreach ($rows as $i => $r) {
            $data[] = [$i+1, $r['nome_completo'], $r['bi'], $r['departamento'], $r['salario_base'], $r['total_proventos'] - $r['salario_base'], $r['total_proventos'], $r['desconto_inss_trabalhador'], $r['desconto_irt'], $r['salario_liquido']];
        }
        exportCsv("mapa_inss_{$ano}_{$mes}.csv", $headers, $data);
    } elseif ($_GET['export'] === 'funcionarios') {
        $rows2 = $db->query("SELECT f.nome_completo, f.bi, f.data_nascimento, f.sexo, f.telefone, f.email, f.data_admissao, f.tipo_contrato, f.status, c.nome as cargo, d.nome as departamento, f.salario_atual
            FROM funcionarios f LEFT JOIN cargos c ON f.cargo_id = c.id LEFT JOIN departamentos d ON f.departamento_id = d.id ORDER BY f.nome_completo")->fetchAll();
        $headers = ['Nome', 'BI', 'Data Nasc.', 'Sexo', 'Telefone', 'Email', 'Data Admissão', 'Tipo Contrato', 'Estado', 'Cargo', 'Departamento', 'Salário'];
        $data = array_map(fn($r) => [$r['nome_completo'], $r['bi'], $r['data_nascimento'], $r['sexo'], $r['telefone'], $r['email'], $r['data_admissao'], $r['tipo_contrato'], $r['status'], $r['cargo'], $r['departamento'], $r['salario_atual']], $rows2);
        exportCsv('lista_funcionarios.csv', $headers, $data);
    } elseif ($_GET['export'] === 'turnover') {
        $rows2 = $db->query("SELECT
            YEAR(data_admissao) as ano, MONTH(data_admissao) as mes,
            SUM(CASE WHEN status='ativo' THEN 1 ELSE 0 END) as ativos,
            SUM(CASE WHEN status='demitido' THEN 1 ELSE 0 END) as saidas,
            COUNT(*) as total
            FROM funcionarios GROUP BY YEAR(data_admissao), MONTH(data_admissao) ORDER BY ano DESC, mes DESC LIMIT 24")->fetchAll();
        $headers = ['Ano', 'Mês', 'Admitidos', 'Saídas', 'Saldo', 'Taxa Saída %'];
        $data = [];
        foreach ($rows2 as $r) {
            $data[] = [$r['ano'], $r['mes'], $r['total'], $r['saidas'], $r['total'] - $r['saidas'], $r['ativos'] > 0 ? round($r['saidas'] / $r['ativos'] * 100, 2) : 0];
        }
        exportCsv('turnover.csv', $headers, $data);
    } elseif ($_GET['export'] === 'presenca') {
        $rows2 = $db->query("SELECT f.nome_completo, d.nome as departamento,
            SUM(CASE WHEN p.tipo='presenca' THEN 1 ELSE 0 END) as dias_trabalhados,
            SUM(CASE WHEN p.tipo LIKE 'falta%' THEN 1 ELSE 0 END) as faltas,
            SUM(p.horas_trabalhadas) as horas_trabalhadas
            FROM funcionarios f
            LEFT JOIN registros_ponto p ON f.id = p.funcionario_id AND YEAR(p.data) = YEAR(CURDATE())
            LEFT JOIN departamentos d ON f.departamento_id = d.id
            WHERE f.status = 'ativo'
            GROUP BY f.id ORDER BY f.nome_completo")->fetchAll();
        $headers = ['Funcionário', 'Departamento', 'Dias Trabalhados', 'Faltas', 'Horas Trabalhadas'];
        $data = array_map(fn($r) => [$r['nome_completo'], $r['departamento'], $r['dias_trabalhados'] ?? 0, $r['faltas'] ?? 0, $r['horas_trabalhadas'] ?? 0], $rows2);
        exportCsv('assiduidade.csv', $headers, $data);
    }
}
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

                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0 0 0.25rem 0;"><?php echo $pageTitle; ?></h2>
                <p style="color: var(--text-muted); margin: 0 0 1.5rem; font-size: 0.875rem;"><?php echo $pageSubtitle; ?></p>

                <?php if ($type === 'home'): ?>
                    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
                        <a href="?type=mapa_inss" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: var(--primary-soft); color: var(--primary); width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Mapa INSS / IRT</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Mapa mensal para INSS. Discrimina base, descontos e líquido por funcionário.</p>
                                </div>
                            </div>
                        </a>
                        <a href="?type=funcionarios" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: var(--success-soft); color: var(--success); width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Lista de Funcionários</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Quadro completo exportável com dados pessoais, cargo, salário e estado.</p>
                                </div>
                            </div>
                        </a>
                        <a href="?type=presenca" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: rgba(245, 158, 11, 0.12); color: #d97706; width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Mapa de Presença / Assiduidade</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Dias trabalhados, faltas e horas extras por funcionário, no ano corrente.</p>
                                </div>
                            </div>
                        </a>
                        <a href="?type=turnover" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: rgba(220, 38, 38, 0.12); color: #dc2626; width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-arrow-left-right"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Rotatividade (Turnover)</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Admissões e saídas por mês, com taxa de turnover calculada.</p>
                                </div>
                            </div>
                        </a>
                        <a href="?type=departamentos" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: rgba(8, 145, 178, 0.12); color: #0891b2; width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-diagram-3"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Efetivo por Departamento</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Distribuição do efetivo, custos salariais e responsabilidades.</p>
                                </div>
                            </div>
                        </a>
                        <a href="?type=vencidos" class="card" style="text-decoration: none; color: inherit; padding: 1.5rem; transition: var(--transition); display: block;">
                            <div style="display: flex; align-items: start; gap: 1rem;">
                                <div class="user-avatar-sm" style="background: rgba(217, 119, 6, 0.12); color: #d97706; width: 48px; height: 48px; font-size: 1.5rem;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem 0;">Documentos a Vencer</h4>
                                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Documentos com validade expirada ou a expirar nos próximos 30 dias.</p>
                                </div>
                            </div>
                        </a>
                    </div>

                <?php elseif ($type === 'mapa_inss'):
                    $mes = (int)($_GET['mes'] ?? date('n'));
                    $ano = (int)($_GET['ano'] ?? date('Y'));
                    $stmt = $db->prepare("SELECT f.nome_completo, f.bi, f.iban, fp.*, c.nome as cargo, d.nome as departamento
                        FROM folha_pagamento fp
                        JOIN funcionarios f ON fp.funcionario_id = f.id
                        LEFT JOIN cargos c ON f.cargo_id = c.id
                        LEFT JOIN departamentos d ON f.departamento_id = d.id
                        WHERE fp.mes = :m AND fp.ano = :a
                        ORDER BY f.nome_completo");
                    $stmt->execute([':m' => $mes, ':a' => $ano]);
                    $rows = $stmt->fetchAll();
                    $totalProv = array_sum(array_column($rows, 'total_proventos'));
                    $totalINSS = array_sum(array_column($rows, 'desconto_inss_trabalhador'));
                    $totalIRT = array_sum(array_column($rows, 'desconto_irt'));
                    $totalLiq = array_sum(array_column($rows, 'salario_liquido'));
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3" style="flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Mapa INSS / IRT</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Mês: <?php echo str_pad($mes, 2, '0', STR_PAD_LEFT); ?>/<?php echo $ano; ?></p>
                        </div>
                        <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>

                    <form method="GET" class="card mb-3" style="padding: 1rem;">
                        <input type="hidden" name="type" value="mapa_inss">
                        <div style="display: flex; gap: 0.5rem; align-items: end; flex-wrap: wrap;">
                            <div>
                                <label class="form-label">Mês</label>
                                <select name="mes" class="form-select">
                                    <?php for ($m=1; $m<=12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $mes==$m?'selected':''; ?>><?php echo str_pad($m,2,'0',STR_PAD_LEFT); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Ano</label>
                                <select name="ano" class="form-select">
                                    <?php for ($y=date('Y')-2; $y<=date('Y')+1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $ano==$y?'selected':''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Aplicar</button>
                            <a href="?export=mapa_inss&mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn btn-success"><i class="bi bi-download"></i> Exportar CSV</a>
                        </div>
                    </form>

                    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                            <div class="stat-value"><?php echo kz($totalProv); ?></div>
                            <div class="stat-label">Total Rendimentos</div>
                        </div>
                        <div class="stat-card stat-card-warning">
                            <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
                            <div class="stat-value"><?php echo kz($totalINSS); ?></div>
                            <div class="stat-label">INSS 3% (Trab.)</div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon"><i class="bi bi-percent"></i></div>
                            <div class="stat-value"><?php echo kz($totalIRT); ?></div>
                            <div class="stat-label">IRT Retido</div>
                        </div>
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon"><i class="bi bi-wallet2"></i></div>
                            <div class="stat-value"><?php echo kz($totalLiq); ?></div>
                            <div class="stat-label">Líquido Pago</div>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Funcionário</th>
                                        <th>BI</th>
                                        <th>Departamento</th>
                                        <th>Sal. Base</th>
                                        <th>Total Prov.</th>
                                        <th>INSS 3%</th>
                                        <th>IRT</th>
                                        <th>Líquido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">Sem folha processada para este período.</td></tr>
                                    <?php else: foreach ($rows as $i => $r): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($r['nome_completo']); ?></strong><div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($r['cargo'] ?? ''); ?></div></td>
                                            <td><?php echo Angola::formatarBI($r['bi']); ?></td>
                                            <td><?php echo htmlspecialchars($r['departamento'] ?? '—'); ?></td>
                                            <td><?php echo kz($r['salario_base']); ?></td>
                                            <td><?php echo kz($r['total_proventos']); ?></td>
                                            <td style="color: var(--warning);"><?php echo kz($r['desconto_inss_trabalhador']); ?></td>
                                            <td style="color: var(--danger);"><?php echo kz($r['desconto_irt']); ?></td>
                                            <td><strong style="color: var(--success);"><?php echo kz($r['salario_liquido']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($type === 'funcionarios'):
                    $rows = $db->query("SELECT f.*, c.nome as cargo, d.nome as departamento
                        FROM funcionarios f LEFT JOIN cargos c ON f.cargo_id = c.id LEFT JOIN departamentos d ON f.departamento_id = d.id
                        ORDER BY f.nome_completo")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Lista de Funcionários</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;"><?php echo count($rows); ?> funcionários</p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?export=funcionarios" class="btn btn-success"><i class="bi bi-download"></i> Exportar CSV</a>
                            <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Nome</th><th>BI</th><th>NIF</th><th>Cargo</th><th>Departamento</th>
                                        <th>Admissão</th><th>Tipo</th><th>Estado</th><th>Salário</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['nome_completo']); ?></strong></td>
                                            <td><?php echo Angola::formatarBI($r['bi']); ?></td>
                                            <td><?php echo htmlspecialchars($r['nif_angolano'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($r['cargo'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($r['departamento'] ?? '—'); ?></td>
                                            <td><?php echo $r['data_admissao'] ? date('d/m/Y', strtotime($r['data_admissao'])) : '—'; ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $r['tipo_contrato'])); ?></td>
                                            <td><span class="badge <?php echo $r['status']==='ativo'?'badge-success':'badge-neutral'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                            <td><?php echo $r['salario_atual'] ? kz($r['salario_atual']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($type === 'presenca'):
                    $rows = $db->query("SELECT f.nome_completo, d.nome as departamento,
                        SUM(CASE WHEN p.tipo='presenca' THEN 1 ELSE 0 END) as dias_trabalhados,
                        SUM(CASE WHEN p.tipo LIKE 'falta%' THEN 1 ELSE 0 END) as faltas,
                        COALESCE(SUM(p.horas_trabalhadas), 0) as horas_trabalhadas
                        FROM funcionarios f
                        LEFT JOIN registros_ponto p ON f.id = p.funcionario_id AND YEAR(p.data) = YEAR(CURDATE())
                        LEFT JOIN departamentos d ON f.departamento_id = d.id
                        WHERE f.status = 'ativo'
                        GROUP BY f.id ORDER BY f.nome_completo")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Assiduidade <?php echo date('Y'); ?></h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Totais do ano corrente</p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?export=presenca" class="btn btn-success"><i class="bi bi-download"></i> Exportar</a>
                            <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Funcionário</th><th>Departamento</th><th>Dias Trab.</th><th>Faltas</th><th>Horas Trab.</th></tr></thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['nome_completo']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($r['departamento'] ?? '—'); ?></td>
                                            <td><?php echo (int)$r['dias_trabalhados']; ?></td>
                                            <td style="color: var(--danger);"><?php echo (int)$r['faltas']; ?></td>
                                            <td><?php echo number_format($r['horas_trabalhadas'], 1); ?>h</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($type === 'turnover'):
                    $rows = $db->query("SELECT
                        YEAR(data_admissao) as ano, MONTH(data_admissao) as mes,
                        SUM(CASE WHEN status='ativo' THEN 1 ELSE 0 END) as ativos_fim,
                        SUM(CASE WHEN status='demitido' THEN 1 ELSE 0 END) as saidas,
                        COUNT(*) as admitidos
                        FROM funcionarios GROUP BY YEAR(data_admissao), MONTH(data_admissao) ORDER BY ano DESC, mes DESC LIMIT 24")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Rotatividade</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Últimos 24 meses</p>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="?export=turnover" class="btn btn-success"><i class="bi bi-download"></i> Exportar</a>
                            <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Período</th><th>Admitidos</th><th>Saídas</th><th>Saldo</th><th>Ativos no Mês</th><th>Taxa Turnover %</th></tr></thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><strong><?php echo str_pad($r['mes'],2,'0',STR_PAD_LEFT); ?>/<?php echo $r['ano']; ?></strong></td>
                                            <td><?php echo (int)$r['admitidos']; ?></td>
                                            <td style="color: var(--danger);"><?php echo (int)$r['saidas']; ?></td>
                                            <td><?php echo (int)($r['admitidos'] - $r['saidas']); ?></td>
                                            <td><?php echo (int)$r['ativos_fim']; ?></td>
                                            <td><?php echo $r['ativos_fim'] > 0 ? number_format($r['saidas'] / $r['ativos_fim'] * 100, 2) : '0.00'; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($type === 'departamentos'):
                    $rows = $db->query("SELECT d.nome, d.descricao, f.nome_completo as responsavel,
                        COUNT(fu.id) as efetivo,
                        COALESCE(SUM(fu.salario_atual), 0) as custo_total,
                        COALESCE(AVG(fu.salario_atual), 0) as custo_medio
                        FROM departamentos d
                        LEFT JOIN funcionarios f ON d.responsavel_id = f.id
                        LEFT JOIN funcionarios fu ON fu.departamento_id = d.id AND fu.status='ativo'
                        GROUP BY d.id ORDER BY d.nome")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Efetivo por Departamento</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Distribuição de pessoal e custos</p>
                        </div>
                        <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Departamento</th><th>Responsável</th><th>Efetivo</th><th>Custo Total</th><th>Salário Médio</th></tr></thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['nome']); ?></strong><div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($r['descricao'] ?? ''); ?></div></td>
                                            <td><?php echo htmlspecialchars($r['responsavel'] ?? '—'); ?></td>
                                            <td><span class="badge badge-primary"><?php echo (int)$r['efetivo']; ?></span></td>
                                            <td><?php echo kz($r['custo_total']); ?></td>
                                            <td><?php echo kz($r['custo_medio']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($type === 'vencidos'):
                    $rows = $db->query("SELECT d.*, f.nome_completo,
                        DATEDIFF(d.data_validade, CURDATE()) as dias
                        FROM documentos_funcionarios d
                        JOIN funcionarios f ON d.funcionario_id = f.id
                        WHERE d.ativo = 1 AND d.data_validade IS NOT NULL
                        AND d.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        ORDER BY d.data_validade ASC")->fetchAll();
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0;">Documentos a Vencer</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0; font-size: 0.875rem;">Vencidos ou a expirar em 30 dias</p>
                        </div>
                        <a href="relatorios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Funcionário</th><th>Tipo</th><th>Documento</th><th>Validade</th><th>Estado</th></tr></thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--success);"><i class="bi bi-check-circle"></i> Todos os documentos estão válidos!</td></tr>
                                    <?php else: foreach ($rows as $r):
                                        $cls = $r['dias'] < 0 ? 'danger' : 'warning';
                                        $st = $r['dias'] < 0 ? 'Vencido há ' . abs($r['dias']) . ' dias' : 'Vence em ' . $r['dias'] . ' dias';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($r['nome_completo']); ?></strong></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $r['tipo_documento'])); ?></td>
                                            <td><?php echo htmlspecialchars($r['nome_original']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($r['data_validade'])); ?></td>
                                            <td><span class="badge badge-<?php echo $cls; ?>"><span class="badge-dot"></span> <?php echo $st; ?></span></td>
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
