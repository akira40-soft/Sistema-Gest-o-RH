<?php
/**
 * ADMIN - REGISTAR CARTEIRAS PROFISSIONAIS
 * 
 * Página prática de admin para:
 * 1. Registar/validar carteiras dos funcionários
 * 2. Ver quem tem e quem não tem
 * 3. Ver tentativas de bater ponto
 * 4. Configurar localizações e raios
 */

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Models/CarteiraAngolana.php';

use App\Models\CarteiraAngolana;

// Autenticação
$auth = new Auth();
if (!$auth->isAdmin()) {
    die("❌ Acesso negado. Admin apenas.");
}

$db = Database::getInstance();
$carteira = new CarteiraAngolana($db);

// Processar ações
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'registar_carteira') {
        $funcionario_id = (int)$_POST['funcionario_id'];
        $numero_carteira = trim($_POST['carteira_profissional']);
        $tipo_presenca = $_POST['tipo_presenca'] ?? 'escritorio';

        $resultado = $carteira->registarCarteira($funcionario_id, $numero_carteira, $tipo_presenca);
        
        if ($resultado['valido'] ?? false) {
            $mensagem = "✅ Carteira " . $resultado['carteira'] . " registada para funcionário!";
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "❌ Erro: " . ($resultado['erro'] ?? 'Desconhecido');
            $tipo_mensagem = 'error';
        }
    }
    elseif ($acao === 'registar_localizacao') {
        $nome = trim($_POST['nome']);
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        $raio = (int)$_POST['raio'];
        $tipo = $_POST['tipo'] ?? 'escritorio';

        try {
            $stmt = $db->prepare("
                INSERT INTO localizacoes_permitidas 
                (nome, latitude, longitude, raio_metros, tipo, ativa)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([$nome, $latitude, $longitude, $raio, $tipo]);
            $mensagem = "✅ Localização " . $nome . " adicionada!";
            $tipo_mensagem = 'success';
        } catch (Exception $e) {
            $mensagem = "❌ Erro: " . $e->getMessage();
            $tipo_mensagem = 'error';
        }
    }
    elseif ($acao === 'aceitar_conformidade') {
        $funcionario_id = (int)$_POST['funcionario_id'];
        $resultado = $carteira->confinarComCarteira($funcionario_id);
        
        if ($resultado['conforme'] ?? false) {
            $mensagem = "✅ Conformidade Lei 7/15 aceita!";
            $tipo_mensagem = 'success';
        } else {
            $mensagem = "❌ Erro: " . ($resultado['erro'] ?? 'Desconhecido');
            $tipo_mensagem = 'error';
        }
    }
}

// Obter dados
$relatorio = $carteira->relatoriCarteiras();
$localizacoes = $db->query("SELECT * FROM localizacoes_permitidas WHERE ativa = 1 ORDER BY nome")->fetchAll();
$funcionarios_sem_carteira = $db->query("
    SELECT id, nome, email, data_admissao 
    FROM funcionarios 
    WHERE carteira_profissional IS NULL 
    ORDER BY nome
")->fetchAll();

$tentativas_invalidas = $db->query("
    SELECT 
        ta.id,
        f.nome,
        ta.reason,
        ta.tentativa_em,
        COUNT(tl.id) as total_batidas
    FROM timeclock_attempts ta
    JOIN funcionarios f ON ta.funcionario_id = f.id
    LEFT JOIN timeclock_logs tl ON ta.funcionario_id = tl.funcionario_id
    WHERE ta.status = 'REJEITADO'
    GROUP BY ta.id
    ORDER BY ta.tentativa_em DESC
    LIMIT 20
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Carteiras Profissionais - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .badge-carteira { background: #28a745; }
        .badge-sem-carteira { background: #dc3545; }
        .alert { border-radius: 8px; }
    </style>
</head>
<body style="background-color: #f8f9fa;">

    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin-dashboard.php">
                <i class="bi bi-person-badge"></i> Gestão de Carteiras Profissionais
            </a>
        </div>
    </nav>

    <div class="container">
        
        <!-- Mensagem de Resultado -->
        <?php if ($mensagem): ?>
        <div class="alert alert-<?= $tipo_mensagem === 'success' ? 'success' : 'danger' ?>" role="alert">
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 📊 ESTATÍSTICAS -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Com Carteira</h5>
                        <h2 class="text-success">
                            <?= count(array_filter($relatorio, fn($r) => !empty($r['carteira_profissional']))) ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Sem Carteira</h5>
                        <h2 class="text-danger"><?= count($funcionarios_sem_carteira) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Batidas</h5>
                        <h2 class="text-primary">
                            <?= array_sum(array_column($relatorio, 'total_batidas')) ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Localizações</h5>
                        <h2 class="text-info"><?= count($localizacoes) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔧 REGISTAR NOVA CARTEIRA -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-plus-circle"></i> Registar Nova Carteira Profissional</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="acao" value="registar_carteira">
                    
                    <div class="col-md-4">
                        <label for="funcionario_id" class="form-label">Funcionário *</label>
                        <select name="funcionario_id" id="funcionario_id" class="form-select" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($funcionarios_sem_carteira as $func): ?>
                            <option value="<?= $func['id'] ?>">
                                <?= htmlspecialchars($func['nome']) ?> (ID: <?= $func['id'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="carteira_profissional" class="form-label">Nº Carteira Profissional *</label>
                        <input type="text" name="carteira_profissional" id="carteira_profissional" 
                               class="form-control" placeholder="1234567890" maxlength="10" required>
                        <small class="text-muted">Formato: 10 dígitos (ex: 0001234567)</small>
                    </div>

                    <div class="col-md-4">
                        <label for="tipo_presenca" class="form-label">Tipo de Presença *</label>
                        <select name="tipo_presenca" id="tipo_presenca" class="form-select">
                            <option value="escritorio">🏢 Escritório (500m)</option>
                            <option value="campo">🚗 Campo (2km)</option>
                            <option value="teletrabalho">🏠 Teletrabalho (ilimitado)</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Registar Carteira
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 📍 REGISTAR LOCALIZAÇÃO -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5><i class="bi bi-geo-alt"></i> Adicionar Localização Permitida</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="acao" value="registar_localizacao">
                    
                    <div class="col-md-3">
                        <label for="nome_loc" class="form-label">Nome da Localização *</label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Sede Luanda" required>
                    </div>

                    <div class="col-md-2">
                        <label for="latitude" class="form-label">Latitude *</label>
                        <input type="number" name="latitude" step="0.0000001" class="form-control" required>
                    </div>

                    <div class="col-md-2">
                        <label for="longitude" class="form-label">Longitude *</label>
                        <input type="number" name="longitude" step="0.0000001" class="form-control" required>
                    </div>

                    <div class="col-md-2">
                        <label for="raio" class="form-label">Raio (metros) *</label>
                        <input type="number" name="raio" value="500" class="form-control" min="100" required>
                    </div>

                    <div class="col-md-2">
                        <label for="tipo_loc" class="form-label">Tipo *</label>
                        <select name="tipo" class="form-select">
                            <option>escritorio</option>
                            <option>campo</option>
                            <option>teletrabalho</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-info">
                            <i class="bi bi-geo-fill"></i> Adicionar Localização
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 📋 RELATÓRIO DE CARTEIRAS E BATIDAS -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5><i class="bi bi-table"></i> Relatório de Carteiras e Batidas</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Funcionário</th>
                            <th>Carteira Profissional</th>
                            <th>Tipo Presença</th>
                            <th>Total Batidas</th>
                            <th>Válidas</th>
                            <th>Rejeitadas</th>
                            <th>Última Batida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($relatorio as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td>
                                <?php if ($item['carteira_profissional']): ?>
                                    <span class="badge badge-carteira">
                                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($item['carteira_profissional']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-sem-carteira">
                                        <i class="bi bi-exclamation-circle"></i> Sem registar
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $tipos = ['escritorio' => '🏢', 'campo' => '🚗', 'teletrabalho' => '🏠'];
                                echo ($tipos[$item['tipo_presenca']] ?? '') . ' ' . htmlspecialchars($item['tipo_presenca']);
                                ?>
                            </td>
                            <td><?= $item['total_batidas'] ?? 0 ?></td>
                            <td><span class="badge bg-success"><?= $item['batidas_validas'] ?? 0 ?></span></td>
                            <td><span class="badge bg-danger"><?= $item['batidas_rejeitadas'] ?? 0 ?></span></td>
                            <td><?= $item['ultima_batida'] ? date('d/m H:i', strtotime($item['ultima_batida'])) : 'Nunca' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 📍 LOCALIZAÇÕES CADASTRADAS -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5><i class="bi bi-map"></i> Localizações Permitidas</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Raio (m)</th>
                            <th>Tipo</th>
                            <th>Criado em</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($localizacoes as $loc): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($loc['nome']) ?></strong></td>
                            <td><?= number_format($loc['latitude'], 4) ?></td>
                            <td><?= number_format($loc['longitude'], 4) ?></td>
                            <td><span class="badge bg-info"><?= $loc['raio_metros'] ?>m</span></td>
                            <td><?= htmlspecialchars($loc['tipo']) ?></td>
                            <td><?= date('d/m/Y', strtotime($loc['criado_em'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ⚠️ TENTATIVAS INVÁLIDAS -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5><i class="bi bi-exclamation-triangle"></i> Tentativas de Bater Ponto Fora do Raio</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Funcionário</th>
                            <th>Motivo</th>
                            <th>Data/Hora</th>
                            <th>Total Batidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tentativas_invalidas)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Nenhuma tentativa inválida registada</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($tentativas_invalidas as $tent): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tent['nome']) ?></strong></td>
                                <td><?= htmlspecialchars($tent['reason']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($tent['tentativa_em'])) ?></td>
                                <td><span class="badge bg-secondary"><?= $tent['total_batidas'] ?? 0 ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
