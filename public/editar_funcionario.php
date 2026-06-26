<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();
// Apenas Admin e Gestor
if (!$auth->isHRStaff()) {
    header("Location: dashboard.php");
    exit;
}

$db = Database::getInstance()->getConnection();
$msg = '';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: funcionarios.php");
    exit;
}

// Buscar dados
$stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
$stmt->execute([$id]);
$f = $stmt->fetch();

if (!$f) {
    die("Funcionário não encontrado.");
}

// Processar Atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE funcionarios SET 
                nome_completo = ?, email = ?, telefone = ?, bi = ?,
                data_nascimento = ?, sexo = ?, estado_civil = ?, nacionalidade = ?,
                departamento_id = ?, cargo_id = ?, data_admissao = ?, 
                tipo_contrato = ?, salario_atual = ?, status = ?,
                numero_crf = ?, validade_certificacao = ?, nivel_escolaridade = ?, formacao_especifica = ?
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $_POST['nome_completo'],
            $_POST['email'],
            $_POST['telefone'],
            $_POST['nif'],
            $_POST['data_nascimento'],
            $_POST['sexo'],
            $_POST['estado_civil'],
            $_POST['nacionalidade'],
            $_POST['departamento_id'],
            $_POST['cargo_id'],
            $_POST['data_admissao'],
            $_POST['tipo_vinculo'],
            $_POST['salario_atual'],
            $_POST['status'],
            $_POST['numero_crf'] ?? null,
            $_POST['validade_crf'] ?? null,
            $_POST['nivel_escolaridade'] ?? null,
            $_POST['formacao_especifica'] ?? null,
            $id
        ]);

        $msg = '<div class="alert alert-success">Dados atualizados com sucesso!</div>';

        // Recarregar dados
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE id = ?");
        $stmt->execute([$id]);
        $f = $stmt->fetch();

    }
    catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
    }
}

$departamentos = $db->query("SELECT * FROM departamentos")->fetchAll();
$cargos = $db->query("SELECT * FROM cargos")->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Funcionário | Farmácia Gingongo</title>
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
            $pageTitle = 'Editar Funcionário';
            include 'includes/topbar.php';
            ?>
            <div class="content-body">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i> Início</a></li>
                        <li class="breadcrumb-item"><a href="funcionarios.php">Funcionários</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>

                <div class="flex-actions" style="margin-bottom: 1rem;">
                    <a href="funcionarios.php" class="btn btn-ghost"><i class="bi bi-arrow-left"></i> Voltar</a>
                    <h2 class="page-title">✏️ Editar Funcionário</h2>
                </div>

                <div class="container-fluid p-0">
                <?php echo $msg; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            
                            <h6 class="text-primary mb-3">1. Dados Pessoais</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nome Completo *</label>
                                    <input type="text" name="nome_completo" class="form-control" required value="<?php echo htmlspecialchars($f['nome_completo']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Nascimento</label>
                                    <input type="date" name="data_nascimento" class="form-control" value="<?php echo $f['data_nascimento']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Gênero</label>
                                    <select name="sexo" class="form-select">
                                        <option value="M" <?php echo $f['sexo'] == 'M' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo $f['sexo'] == 'F' ? 'selected' : ''; ?>>Feminino</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Nacionalidade</label>
                                    <input type="text" name="nacionalidade" class="form-control" value="<?php echo htmlspecialchars($f['nacionalidade']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">BI</label>
                                    <input type="text" name="nif" class="form-control" value="<?php echo htmlspecialchars($f['bi']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Estado Civil</label>
                                    <select name="estado_civil" class="form-select">
                                        <option value="solteiro" <?php echo $f['estado_civil'] == 'solteiro' ? 'selected' : ''; ?>>Solteiro(a)</option>
                                        <option value="casado" <?php echo $f['estado_civil'] == 'casado' ? 'selected' : ''; ?>>Casado(a)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($f['email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Telefone</label>
                                    <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($f['telefone']); ?>">
                                </div>
                            </div>

                            <h6 class="text-primary mb-3">2. Dados Acadêmicos e Profissionais</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Nível de Escolaridade</label>
                                    <select name="nivel_escolaridade" class="form-select">
                                        <option value="">Selecione...</option>
                                        <?php
$niveis = ['medio', 'tecnico', 'superior', 'pos_graduacao', 'mestrado'];
foreach ($niveis as $n) {
    $sel = ($f['nivel_escolaridade'] ?? '') == $n ? 'selected' : '';
    echo "<option value='$n' $sel>" . ucfirst($n) . "</option>";
}
?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Formação Específica</label>
                                    <input type="text" name="formacao_especifica" class="form-control" value="<?php echo htmlspecialchars($f['formacao_especifica'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nº Ordem (CRF)</label>
                                    <input type="text" name="numero_crf" class="form-control" value="<?php echo htmlspecialchars($f['numero_crf'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Validade Carteira</label>
                                    <input type="date" name="validade_crf" class="form-control" value="<?php echo $f['validade_certificacao'] ?? ''; ?>">
                                </div>
                            </div>

                            <h6 class="text-primary mb-3">3. Dados Contratuais</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Departamento</label>
                                    <select name="departamento_id" class="form-select" required>
                                        <?php foreach ($departamentos as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" <?php echo $f['departamento_id'] == $d['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['nome']); ?>
                                            </option>
                                        <?php
endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cargo</label>
                                    <select name="cargo_id" class="form-select" required>
                                        <?php foreach ($cargos as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php echo $f['cargo_id'] == $c['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['nome']); ?>
                                            </option>
                                        <?php
endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Salário Atual (Kz)</label>
                                    <input type="number" name="salario_atual" class="form-control" step="0.01" value="<?php echo $f['salario_atual']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tipo de Contrato</label>
                                    <select name="tipo_contrato" class="form-select">
                                        <?php
$tipos = ['efetivo', 'determinado', 'estagio_curricular', 'estagio_profissional', 'voluntariado', 'prestacao_servicos'];
foreach ($tipos as $t) {
    $sel = ($f['tipo_contrato'] ?? '') == $t ? 'selected' : '';
    echo "<option value='$t' $sel>" . ucfirst($t) . "</option>";
}
?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Data de Admissão</label>
                                    <input type="date" name="data_admissao" class="form-control" value="<?php echo $f['data_admissao']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="ativo" <?php echo $f['status'] == 'ativo' ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="ferias" <?php echo $f['status'] == 'ferias' ? 'selected' : ''; ?>>Férias</option>
                                        <option value="afastado" <?php echo $f['status'] == 'afastado' ? 'selected' : ''; ?>>Afastado</option>
                                        <option value="demitido" <?php echo $f['status'] == 'demitido' ? 'selected' : ''; ?>>Demitido</option>
                                        <option value="suspenso" <?php echo $f['status'] == 'suspenso' ? 'selected' : ''; ?>>Suspenso</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="d-flex justify-content-end gap-2">
                                <a href="funcionarios.php" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/app-2026.js"></script>
</body>
</html>
