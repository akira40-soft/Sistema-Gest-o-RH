<?php
/**
 * Criar e Editar RG - Formulário
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;
use App\Models\RG;
use App\Database\Database;

$middleware = new AuthMiddleware();
$middleware->requireAdmin();

$auth = $middleware->getAuth();
$rg_model = new RG();
$db = Database::getInstance();

// Verificar se é edição
$is_edit = false;
$rg_data = null;
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $is_edit = true;
    $rg_data = $rg_model->getById($id);
    if (!$rg_data) {
        header('Location: index.php');
        exit;
    }
}

// Processamento de POST
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'funcionario_id' => (int)($_POST['funcionario_id'] ?? 0),
        'numero_rg' => trim($_POST['numero_rg'] ?? ''),
        'orgao_expedidor' => trim($_POST['orgao_expedidor'] ?? ''),
        'uf_expedidor' => trim($_POST['uf_expedidor'] ?? ''),
        'data_expedicao' => $_POST['data_expedicao'] ?? null,
        'data_validade' => $_POST['data_validade'] ?? null,
        'mae_nome' => trim($_POST['mae_nome'] ?? ''),
        'data_nascimento' => $_POST['data_nascimento'] ?? null,
        'naturalidade' => trim($_POST['naturalidade'] ?? ''),
        'filiacao' => trim($_POST['filiacao'] ?? ''),
        'status' => $_POST['status'] ?? 'ativo',
        'observacoes' => trim($_POST['observacoes'] ?? '')
    ];

    if ($is_edit) {
        $result = $rg_model->update($id, $data);
    } else {
        $result = $rg_model->create($data);
    }

    if ($result['success']) {
        $success = $result['message'];
        if (!$is_edit) {
            header('Location: index.php?success=1');
            exit;
        }
        $rg_data = $rg_model->getById($id);
    } else {
        $error = $result['message'];
    }
}

// Buscar funcionários
try {
    $funcionarios = $db->select('funcionarios', [], false);
} catch (\Exception $e) {
    $funcionarios = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Editar' : 'Novo'; ?> RG | Farmácia Gingongo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style-2026.css">
    <style>
        body {
            background: #f8fafc;
            padding: 30px 20px;
        }
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h5 {
            color: #2563eb;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="d-flex justify-content-between align-items-center mb-30">
            <h4><?php echo $is_edit ? 'Editar RG' : 'Novo RG'; ?></h4>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Seção: Dados do RG -->
            <div class="form-section">
                <h5><i class="bi bi-card-text me-2"></i> Dados do RG</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Funcionário *</label>
                        <select name="funcionario_id" class="form-control" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                            <option value="">Selecione um funcionário</option>
                            <?php foreach ($funcionarios as $func): ?>
                                <option value="<?php echo $func['id']; ?>" <?php echo ($rg_data && $rg_data['funcionario_id'] == $func['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($func['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Número RG *</label>
                        <input type="text" name="numero_rg" class="form-control" 
                               value="<?php echo $rg_data ? htmlspecialchars($rg_data['numero_rg']) : ''; ?>" 
                               placeholder="Ex: 1.234.567-X" required <?php echo $is_edit ? 'readonly' : ''; ?>>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Órgão Expedidor</label>
                        <input type="text" name="orgao_expedidor" class="form-control" 
                               value="<?php echo $rg_data ? htmlspecialchars($rg_data['orgao_expedidor']) : ''; ?>" 
                               placeholder="Ex: SSP">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">UF Expedidor</label>
                        <input type="text" name="uf_expedidor" class="form-control" maxlength="2"
                               value="<?php echo $rg_data ? htmlspecialchars($rg_data['uf_expedidor']) : ''; ?>" 
                               placeholder="SP">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Data Expedição</label>
                        <input type="date" name="data_expedicao" class="form-control"
                               value="<?php echo $rg_data ? $rg_data['data_expedicao'] : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data de Validade</label>
                        <input type="date" name="data_validade" class="form-control"
                               value="<?php echo $rg_data ? $rg_data['data_validade'] : ''; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="ativo" <?php echo ($rg_data && $rg_data['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                            <option value="inativo" <?php echo ($rg_data && $rg_data['status'] == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seção: Dados Pessoais -->
            <div class="form-section">
                <h5><i class="bi bi-person me-2"></i> Dados Pessoais</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Data de Nascimento</label>
                        <input type="date" name="data_nascimento" class="form-control"
                               value="<?php echo $rg_data ? $rg_data['data_nascimento'] : ''; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Naturalidade</label>
                        <input type="text" name="naturalidade" class="form-control"
                               value="<?php echo $rg_data ? htmlspecialchars($rg_data['naturalidade']) : ''; ?>" 
                               placeholder="Cidade/Estado">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Nome da Mãe</label>
                        <input type="text" name="mae_nome" class="form-control"
                               value="<?php echo $rg_data ? htmlspecialchars($rg_data['mae_nome']) : ''; ?>" 
                               placeholder="Nome completo da mãe">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Filiação</label>
                        <textarea name="filiacao" class="form-control" rows="2" placeholder="Informações adicionais de filiação"><?php echo $rg_data ? htmlspecialchars($rg_data['filiacao']) : ''; ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Seção: Observações -->
            <div class="form-section">
                <h5><i class="bi bi-chat-left-text me-2"></i> Observações</h5>
                <textarea name="observacoes" class="form-control" rows="3" placeholder="Notas adicionais..."><?php echo $rg_data ? htmlspecialchars($rg_data['observacoes']) : ''; ?></textarea>
            </div>

            <!-- Botões -->
            <div class="d-flex gap-2 justify-content-end">
                <a href="index.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-2"></i><?php echo $is_edit ? 'Atualizar' : 'Cadastrar'; ?>
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
