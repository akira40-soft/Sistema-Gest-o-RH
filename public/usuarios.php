<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

// Apenas Admin e Gestor RH
if (!$auth->isHRStaff()) {
    die("Acesso negado.");
}

$db = Database::getInstance()->getConnection();

// Buscar Todos os Usuários
$stmt = $db->query("
    SELECT u.*, f.nome_completo as funcionario_nome 
    FROM usuarios u 
    LEFT JOIN funcionarios f ON f.usuario_id = u.id 
    ORDER BY u.id DESC
");
$usuarios = $stmt->fetchAll();

// Buscar Funcionários SEM Usuário (para o dropdown)
$stmtFunc = $db->query("SELECT id, nome_completo FROM funcionarios WHERE usuario_id IS NULL AND status = 'ativo' ORDER BY nome_completo");
$funcionariosSemUser = $stmtFunc->fetchAll();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Acessos | Farmácia Gingongo</title>
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
            $pageTitle = 'Contas de Acesso';
            $pageSubtitle = 'Utilizadores, permissões e estado das contas';
            include 'includes/topbar.php';
            ?>
            <div class="content-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="mb-1">Contas de Acesso</h2>
                        <p class="text-muted mb-0 small">Utilizadores, permissões e estado das contas</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
                        <i class="bi bi-plus-lg me-1"></i> Novo Usuário
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary-soft text-primary">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo count($usuarios); ?></span>
                                <span class="stat-label">Total de Acessos</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-success-soft text-success">
                                <i class="bi bi-check-circle fs-4"></i>
                            </div>
                            <div class="stat-content">
                                <?php $ativos = count(array_filter($usuarios, fn($u) => $u['ativo'])); ?>
                                <span class="stat-value"><?php echo $ativos; ?></span>
                                <span class="stat-label">Ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-danger-soft text-danger">
                                <i class="bi bi-x-circle fs-4"></i>
                            </div>
                            <div class="stat-content">
                                <?php $inativos = count($usuarios) - $ativos; ?>
                                <span class="stat-value"><?php echo $inativos; ?></span>
                                <span class="stat-label">Inativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-info-soft text-info">
                                <i class="bi bi-person-badge fs-4"></i>
                            </div>
                            <div class="stat-content">
                                <?php $vinculados = count(array_filter($usuarios, fn($u) => !empty($u['funcionario_nome']))); ?>
                                <span class="stat-value"><?php echo $vinculados; ?></span>
                                <span class="stat-label">Vinculados</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Perfil</th>
                                        <th>Funcionário</th>
                                        <th>Status</th>
                                        <th>Último Login</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td class="text-muted small">#<?php echo $u['id']; ?></td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($u['username']); ?></td>
                                        <td>
                                            <?php
$roleColors = [
    'super_admin' => 'badge-danger',
    'admin' => 'badge-danger',
    'gestor_rh' => 'badge-warning',
    'lider_farmaceutico' => 'badge-info',
    'funcionario' => 'badge-success',
    'funcionario_rh' => 'badge-info',
    'geral' => 'badge-secondary'
];
$roleClass = $roleColors[$u['tipo_acesso']] ?? 'badge-secondary';
?>
                                            <span class="badge-custom <?php echo $roleClass; ?>"><?php echo ucfirst(str_replace('_', ' ', $u['tipo_acesso'])); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($u['funcionario_nome']): ?>
                                                <span class="text-light">
                                                    <i class="bi bi-person-badge me-1 text-primary"></i>
                                                    <?php echo htmlspecialchars($u['funcionario_nome']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic small">Não vinculado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['ativo']): ?>
                                                <span class="badge-custom badge-success"><i class="bi bi-check-circle-fill me-1"></i>Ativo</span>
                                            <?php else: ?>
                                                <span class="badge-custom badge-danger"><i class="bi bi-x-circle-fill me-1"></i>Inativo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted"><?php echo $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '-'; ?></td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary btn-edit me-1" 
                                                    data-id="<?php echo $u['id']; ?>" 
                                                    data-username="<?php echo htmlspecialchars($u['username']); ?>"
                                                    data-role="<?php echo $u['tipo_acesso']; ?>"
                                                    title="Editar Usuário">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <button class="btn btn-sm <?php echo $u['ativo'] ? 'btn-outline-danger' : 'btn-outline-success'; ?> btn-toggle-status" 
                                                    data-id="<?php echo $u['id']; ?>" 
                                                    data-status="<?php echo $u['ativo'] ? 0 : 1; ?>"
                                                    title="<?php echo $u['ativo'] ? 'Desativar' : 'Ativar'; ?>">
                                                <i class="bi <?php echo $u['ativo'] ? 'bi-lock-fill' : 'bi-unlock-fill'; ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarUsuario">
                        <input type="hidden" name="id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Nome de Usuário</label>
                            <input type="text" name="username" id="editUsername" class="form-control" required minlength="3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Acesso</label>
                            <select name="role" id="editRole" class="form-select" required>
                                <option value="funcionario">Funcionário (Self-Service)</option>
                                <option value="funcionario_rh">Funcionário RH</option>
                                <option value="lider_farmaceutico">Líder Farmacêutico</option>
                                <option value="gestor_rh">Gestor de RH</option>
                                <?php if ($auth->isAdmin()): ?>
                                <option value="admin">Administrador TI</option>
                                <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nova Senha (deixe em branco para não alterar)</label>
                            <input type="password" name="password" class="form-control" minlength="6">
                            <div class="form-text text-muted small">Apenas preencha se desejar trocar a senha atual.</div>
                        </div>

                        <div id="msgErroEdit" class="alert alert-danger d-none py-2 small"></div>
                        <div id="msgSucessoEdit" class="alert alert-success d-none py-2 small"></div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="btnAtualizar">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Usuário -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Usuário do Sistema</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovoUsuario">
                        
                        <div class="mb-3">
                            <label class="form-label">Vincular a Funcionário</label>
                            <select name="funcionario_id" class="form-select" id="selectFuncionario">
                                <option value="">-- Apenas Usuário do Sistema (Sem vínculo) --</option>
                                <?php foreach ($funcionariosSemUser as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['nome_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted small">Ao selecionar, o nome de usuário será sugerido automaticamente.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nome de Usuário (Login)</label>
                            <input type="text" name="username" id="inputUsername" class="form-control" required minlength="3">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Senha Provisória</label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Acesso</label>
                                <select name="role" class="form-select" required>
                                    <option value="funcionario">Funcionário (Self-Service)</option>
                                    <option value="funcionario_rh">Funcionário RH</option>
                                    <option value="lider_farmaceutico">Líder Farmacêutico</option>
                                    <option value="gestor_rh">Gestor de RH</option>
                                    <?php if ($auth->isAdmin()): ?>
                                    <option value="admin">Administrador TI</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div id="msgErro" class="alert alert-danger d-none py-2 small"></div>
                        <div id="msgSucesso" class="alert alert-success d-none py-2 small"></div>

                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="submit" class="btn btn-primary" id="btnSalvar">Criar Acesso</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sugestão Automática de Username
        const selectFunc = document.getElementById('selectFuncionario');
        if (selectFunc) {
            selectFunc.addEventListener('change', function() {
                if (this.value) {
                    const text = this.options[this.selectedIndex].text;
                    const parts = text.toLowerCase().split(' ');
                    if (parts.length >= 1) {
                        const sugestao = parts[0] + '.' + (parts[parts.length - 1] || '');
                        document.getElementById('inputUsername').value = sugestao.replace(/[^a-z0-9.]/g, '');
                    }
                }
            });
        }

        // AJAX Submit Novo Usuário
        const formNovo = document.getElementById('formNovoUsuario');
        if (formNovo) {
            formNovo.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSalvar');
                const msgErro = document.getElementById('msgErro');
                const msgSucesso = document.getElementById('msgSucesso');
                
                btn.disabled = true;
                msgErro.classList.add('d-none');
                msgSucesso.classList.add('d-none');

                fetch('register_process.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success) {
                        msgSucesso.textContent = data.message;
                        msgSucesso.classList.remove('d-none');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        msgErro.textContent = data.message;
                        msgErro.classList.remove('d-none');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    msgErro.textContent = "Erro na requisição.";
                    msgErro.classList.remove('d-none');
                });
            });
        }

        // Configurar Modal de Edição
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editUserId').value = this.dataset.id;
                document.getElementById('editUsername').value = this.dataset.username;
                document.getElementById('editRole').value = this.dataset.role;
                new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
            });
        });

        // AJAX Submit Edição
        const formEdit = document.getElementById('formEditarUsuario');
        if (formEdit) {
            formEdit.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnAtualizar');
                const msgErro = document.getElementById('msgErroEdit');
                const msgSucesso = document.getElementById('msgSucessoEdit');
                
                btn.disabled = true;
                msgErro.classList.add('d-none');
                msgSucesso.classList.add('d-none');

                fetch('api/update_user_process.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    if (data.success) {
                        msgSucesso.textContent = data.message;
                        msgSucesso.classList.remove('d-none');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        msgErro.textContent = data.message;
                        msgErro.classList.remove('d-none');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    msgErro.textContent = "Erro na requisição.";
                    msgErro.classList.remove('d-none');
                });
            });
        }

        // AJAX Toggle Status
        document.querySelectorAll('.btn-toggle-status').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Deseja realmente alterar o status deste acesso?')) return;
                
                const formData = new FormData();
                formData.append('id', this.dataset.id);
                formData.append('ativo', this.dataset.status);

                fetch('api/update_user_process.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => alert("Erro na requisição de status."));
            });
        });
    </script>
    <script src="js/app-2026.js"></script>
</body>
</html>
