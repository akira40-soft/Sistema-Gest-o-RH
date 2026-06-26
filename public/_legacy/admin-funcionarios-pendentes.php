<?php
/**
 * Admin - Funcionários Pendentes de Aprovação
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\AuthMiddleware;
use App\Models\EmployeeApproval;

// Verificar acesso de admin
AuthMiddleware::requireAdmin();

$db = \App\Database\Database::getInstance();
$approvalModel = new EmployeeApproval();

$acao = $_GET['acao'] ?? '';
$id_aprovacao = intval($_GET['id'] ?? 0);
$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $acao_post = $_POST['acao'] ?? '';
    
    if ($acao_post === 'aprovar') {
        $id = intval($_POST['id']);
        $observacoes = trim($_POST['observacoes'] ?? '');
        
        if ($approvalModel->approve($id, $usuario_id, $observacoes)) {
            $mensagem = '✅ Funcionário aprovado com sucesso!';
            $tipo_mensagem = 'sucesso';
        } else {
            $mensagem = '❌ Erro ao aprovar funcionário';
            $tipo_mensagem = 'erro';
        }
    } elseif ($acao_post === 'rejeitar') {
        $id = intval($_POST['id']);
        $motivo = trim($_POST['motivo'] ?? '');
        
        if (empty($motivo)) {
            $mensagem = '❌ Motivo da rejeição é obrigatório';
            $tipo_mensagem = 'erro';
        } else {
            if ($approvalModel->reject($id, $usuario_id, $motivo)) {
                $mensagem = '✅ Funcionário rejeitado';
                $tipo_mensagem = 'sucesso';
            } else {
                $mensagem = '❌ Erro ao rejeitar funcionário';
                $tipo_mensagem = 'erro';
            }
        }
    }
}

// Obter dados
$pendentes = $approvalModel->getPending();
$detalhes = null;
if ($id_aprovacao > 0) {
    $detalhes = $approvalModel->getById($id_aprovacao);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprovações Pendentes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/css/style-new.css" rel="stylesheet">
    <style>
        body {
            background: #f5f6fa;
        }

        .navbar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .main-content {
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            font-weight: 700;
        }

        .approval-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #ffc107;
            transition: all 0.3s ease;
        }

        .approval-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .approval-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .approval-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .approval-date {
            color: #666;
            font-size: 0.9rem;
        }

        .approval-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            border-left: 2px solid #667eea;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .info-value {
            color: #333;
            margin-top: 5px;
        }

        .approval-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-approve {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .modal-dialog {
            max-width: 600px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .alert {
            border: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-sucesso {
            background: #d4edda;
            color: #155724;
        }

        .alert-erro {
            background: #f8d7da;
            color: #721c24;
        }

        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .details-modal.active {
            display: flex;
        }

        .modal-content-custom {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/dashboard_admin_advanced.php">
                <i class="fas fa-check-circle"></i> Aprovações
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard_admin_advanced.php">
                            <i class="fas fa-arrow-left"></i> Voltar ao Dashboard
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid main-content">
        <div class="page-header">
            <h1><i class="fas fa-hourglass-half"></i> Funcionários Pendentes de Aprovação</h1>
            <p class="text-muted">Revise e aprove novos funcionários no sistema</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($pendentes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">✅</div>
                <h3>Nenhuma aprovação pendente!</h3>
                <p>Todos os funcionários já foram aprovados ou rejeitados.</p>
                <a href="/dashboard_admin_advanced.php" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Lista de Pendentes -->
                    <div>
                        <?php foreach ($pendentes as $approval): ?>
                            <div class="approval-card">
                                <div class="approval-header">
                                    <div>
                                        <div class="approval-name">
                                            👤 <?php echo htmlspecialchars($approval['nome_completo']); ?>
                                        </div>
                                        <div class="approval-date">
                                            Solicitado em: <?php echo date('d/m/Y H:i', strtotime($approval['criado_em'])); ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-warning">Pendente</span>
                                </div>

                                <div class="approval-info">
                                    <div class="info-item">
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?php echo htmlspecialchars($approval['email']); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">CPF</div>
                                        <div class="info-value"><?php echo htmlspecialchars($approval['cpf'] ?? '-'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Cargo</div>
                                        <div class="info-value"><?php echo htmlspecialchars($approval['cargo_nome'] ?? '-'); ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Departamento</div>
                                        <div class="info-value"><?php echo htmlspecialchars($approval['departamento_nome'] ?? '-'); ?></div>
                                    </div>
                                </div>

                                <div class="approval-actions">
                                    <button type="button" class="btn-approve" 
                                            onclick="abrirModalAprovacao(<?php echo $approval['id']; ?>)">
                                        ✓ Aprovar
                                    </button>
                                    <button type="button" class="btn-reject"
                                            onclick="abrirModalRejeicao(<?php echo $approval['id']; ?>)">
                                        ✗ Rejeitar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="abrirDetalhes(<?php echo $approval['id']; ?>)">
                                        <i class="fas fa-eye"></i> Detalhes
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Resumo -->
                    <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h5 class="mb-3">📊 Resumo</h5>
                        <div style="background: #ffc10720; padding: 15px; border-radius: 5px; border-left: 3px solid #ffc107;">
                            <div style="font-weight: 600; color: #333;">
                                <?php echo count($pendentes); ?> pendentes
                            </div>
                            <div style="font-size: 0.9rem; color: #666; margin-top: 5px;">
                                Aguardando revisão
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Aprovar -->
    <div class="details-modal" id="modalAprovacao">
        <div class="modal-content-custom">
            <button type="button" class="close-modal" onclick="fecharModalAprovacao()">×</button>
            <h4 class="mb-4">✓ Aprovar Funcionário</h4>
            <form method="POST" onsubmit="return confirmarAprovacao();">
                <input type="hidden" name="acao" value="aprovar">
                <input type="hidden" name="id" id="aprovacaoId">
                
                <div class="mb-3">
                    <label class="form-label">Observações (opcional)</label>
                    <textarea class="form-control" name="observacoes" rows="4" 
                              placeholder="Adicione observações sobre a aprovação..."></textarea>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> O funcionário receberá acesso ao sistema após a aprovação.
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalAprovacao()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirmar Aprovação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Rejeitar -->
    <div class="details-modal" id="modalRejeicao">
        <div class="modal-content-custom">
            <button type="button" class="close-modal" onclick="fecharModalRejeicao()">×</button>
            <h4 class="mb-4">✗ Rejeitar Funcionário</h4>
            <form method="POST" onsubmit="return confirmarRejeicao();">
                <input type="hidden" name="acao" value="rejeitar">
                <input type="hidden" name="id" id="rejeicaoId">
                
                <div class="mb-3">
                    <label class="form-label">Motivo da Rejeição *</label>
                    <textarea class="form-control" name="motivo" rows="4" required
                              placeholder="Explique por que está rejeitando..."></textarea>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-warning"></i> Esta ação não poderá ser desfeita.
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="fecharModalRejeicao()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Confirmar Rejeição
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Detalhes -->
    <div class="details-modal" id="modalDetalhes">
        <div class="modal-content-custom">
            <button type="button" class="close-modal" onclick="fecharModalDetalhes()">×</button>
            <h4 class="mb-4">👤 Detalhes do Funcionário</h4>
            <div id="detalhesConteudo">Carregando...</div>
            <div style="margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="fecharModalDetalhes()">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalAprovacao(id) {
            document.getElementById('aprovacaoId').value = id;
            document.getElementById('modalAprovacao').classList.add('active');
        }

        function fecharModalAprovacao() {
            document.getElementById('modalAprovacao').classList.remove('active');
        }

        function confirmarAprovacao() {
            if (confirm('Tem certeza que deseja aprovar este funcionário?')) {
                return true;
            }
            return false;
        }

        function abrirModalRejeicao(id) {
            document.getElementById('rejeicaoId').value = id;
            document.getElementById('modalRejeicao').classList.add('active');
        }

        function fecharModalRejeicao() {
            document.getElementById('modalRejeicao').classList.remove('active');
        }

        function confirmarRejeicao() {
            if (confirm('Tem certeza que deseja REJEITAR este funcionário?')) {
                return true;
            }
            return false;
        }

        function abrirDetalhes(id) {
            // Aqui você faria um fetch para obter os detalhes completos
            document.getElementById('detalhesConteudo').innerHTML = 'Funcionalidade em desenvolvimento...';
            document.getElementById('modalDetalhes').classList.add('active');
        }

        function fecharModalDetalhes() {
            document.getElementById('modalDetalhes').classList.remove('active');
        }

        // Fechar modal ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.id === 'modalAprovacao') fecharModalAprovacao();
            if (e.target.id === 'modalRejeicao') fecharModalRejeicao();
            if (e.target.id === 'modalDetalhes') fecharModalDetalhes();
        });
    </script>
</body>
</html>
