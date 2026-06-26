<?php
/**
 * Sidebar Reutilizável - Sistema de Gestão RG
 * Farmácia Gingongo - 2026
 */
if (!isset($user)) {
    if (isset($_SESSION['username'])) {
        $user = [
            'username' => $_SESSION['username'],
            'role' => $_SESSION['tipo_acesso'] ?? 'funcionario',
            'id' => $_SESSION['user_id'] ?? null
        ];
    } else {
        $user = [
            'username' => 'Visitante',
            'role' => 'funcionario',
            'id' => null
        ];
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$isAdmin = ($user['role'] !== 'funcionario');
$isHR = in_array($user['role'], ['super_admin', 'admin', 'gestor_rh', 'funcionario_rh']);
$isManager = in_array($user['role'], ['super_admin', 'admin', 'gestor_rh']);
$isLider = in_array($user['role'], ['super_admin', 'admin', 'gestor_rh', 'lider_farmaceutico']);

// Função helper para verificar se o item está ativo
function rg_is_active($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

function rg_is_sub_active($pages) {
    global $currentPage;
    return in_array($currentPage, (array)$pages);
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
<div class="logo-mark">
    <i class="bi bi-capsule-pill"></i>
</div>
        <div class="logo-text">
            <span class="brand">GINGONGO<span class="brand-accent">RG</span></span>
            <span class="tagline">Gestão de RH</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul style="list-style:none; padding:0; margin:0;">

            <?php if ($isAdmin): ?>
            <!-- ============== ADMIN MENU ============== -->
            <li class="nav-section">
                <span class="nav-label">Principal</span>
            </li>
            <li class="nav-item <?php echo rg_is_active('dashboard.php'); ?>">
                <a href="dashboard.php" class="nav-link">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-section">
                <span class="nav-label">RH & Pessoal</span>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['funcionarios.php', 'departamentos.php', 'cargos.php', 'usuarios.php', 'admissao.php', 'editar_funcionario.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-people-fill"></i>
                    <span class="nav-text">Colaboradores</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="funcionarios.php" class="nav-link <?php echo rg_is_active('funcionarios.php'); ?>"><i class="bi bi-person-badge"></i><span class="nav-text">Funcionários</span></a></li>
                    <li class="nav-item"><a href="departamentos.php" class="nav-link <?php echo rg_is_active('departamentos.php'); ?>"><i class="bi bi-diagram-3"></i><span class="nav-text">Departamentos</span></a></li>
                    <li class="nav-item"><a href="cargos.php" class="nav-link <?php echo rg_is_active('cargos.php'); ?>"><i class="bi bi-briefcase"></i><span class="nav-text">Cargos</span></a></li>
                    <li class="nav-item"><a href="usuarios.php" class="nav-link <?php echo rg_is_active('usuarios.php'); ?>"><i class="bi bi-shield-lock"></i><span class="nav-text">Contas de Acesso</span></a></li>
                </ul>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['escalas.php', 'pontos.php', 'licencas.php', 'ferias.php', 'advertencias.php', 'timeclock.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span class="nav-text">Operacional</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="escalas.php" class="nav-link <?php echo rg_is_active('escalas.php'); ?>"><i class="bi bi-calendar3"></i><span class="nav-text">Escalas de Turnos</span></a></li>
                    <li class="nav-item"><a href="pontos.php" class="nav-link <?php echo rg_is_active('pontos.php'); ?>"><i class="bi bi-clock-history"></i><span class="nav-text">Registros de Ponto</span></a></li>
                    <li class="nav-item"><a href="timeclock.php" class="nav-link <?php echo rg_is_active('timeclock.php'); ?>"><i class="bi bi-stopwatch"></i><span class="nav-text">TimeClock</span></a></li>
                    <li class="nav-item"><a href="licencas.php" class="nav-link <?php echo rg_is_active('licencas.php'); ?>"><i class="bi bi-file-medical"></i><span class="nav-text">Licenças</span></a></li>
                    <li class="nav-item"><a href="ferias.php" class="nav-link <?php echo rg_is_active('ferias.php'); ?>"><i class="bi bi-sun"></i><span class="nav-text">Férias</span></a></li>
                </ul>
            </li>

            <li class="nav-section">
                <span class="nav-label">Administrativo</span>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['folha.php', 'beneficios.php', 'recibo_salario.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-cash-stack"></i>
                    <span class="nav-text">Financeiro</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="folha.php" class="nav-link <?php echo rg_is_active('folha.php'); ?>"><i class="bi bi-calculator"></i><span class="nav-text">Folha de Pagamento</span></a></li>
                    <li class="nav-item"><a href="beneficios.php" class="nav-link <?php echo rg_is_active('beneficios.php'); ?>"><i class="bi bi-gift"></i><span class="nav-text">Benefícios</span></a></li>
                </ul>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['documentos.php', 'comunicados.php', 'uniformes.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span class="nav-text">Documentação</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="documentos.php" class="nav-link <?php echo rg_is_active('documentos.php'); ?>"><i class="bi bi-archive"></i><span class="nav-text">Arquivo Digital</span></a></li>
                    <li class="nav-item"><a href="comunicados.php" class="nav-link <?php echo rg_is_active('comunicados.php'); ?>"><i class="bi bi-megaphone"></i><span class="nav-text">Comunicados</span></a></li>
                    <li class="nav-item"><a href="uniformes.php" class="nav-link <?php echo rg_is_active('uniformes.php'); ?>"><i class="bi bi-shirt"></i><span class="nav-text">EPIs & Uniformes</span></a></li>
                </ul>
            </li>

            <li class="nav-section">
                <span class="nav-label">Desenvolvimento</span>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['avaliacoes.php', 'treinamentos.php', 'vagas.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-mortarboard-fill"></i>
                    <span class="nav-text">Talento</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="avaliacoes.php" class="nav-link <?php echo rg_is_active('avaliacoes.php'); ?>"><i class="bi bi-graph-up-arrow"></i><span class="nav-text">Avaliações 360°</span></a></li>
                    <li class="nav-item"><a href="treinamentos.php" class="nav-link <?php echo rg_is_active('treinamentos.php'); ?>"><i class="bi bi-book"></i><span class="nav-text">Treinamentos</span></a></li>
                    <li class="nav-item"><a href="vagas.php" class="nav-link <?php echo rg_is_active('vagas.php'); ?>"><i class="bi bi-person-plus"></i><span class="nav-text">Recrutamento</span></a></li>
                </ul>
            </li>

            <?php if ($isManager): ?>
            <li class="nav-section">
                <span class="nav-label">Sistema</span>
            </li>

            <li class="nav-item has-submenu <?php echo rg_is_sub_active(['config.php', 'relatorios.php', 'admin-usuarios.php', 'admin-logs.php']) ? 'expanded' : ''; ?>">
                <a href="#" class="nav-link">
                    <i class="bi bi-gear-fill"></i>
                    <span class="nav-text">Configurações</span>
                    <i class="bi bi-chevron-right nav-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="nav-item"><a href="relatorios.php" class="nav-link <?php echo rg_is_active('relatorios.php'); ?>"><i class="bi bi-file-earmark-bar-graph"></i><span class="nav-text">Relatórios</span></a></li>
                    <li class="nav-item"><a href="config.php" class="nav-link <?php echo rg_is_active('config.php'); ?>"><i class="bi bi-sliders"></i><span class="nav-text">Ajustes Gerais</span></a></li>
                    <li class="nav-item"><a href="advertencias.php" class="nav-link <?php echo rg_is_active('advertencias.php'); ?>"><i class="bi bi-exclamation-octagon"></i><span class="nav-text">Disciplinar</span></a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php else: ?>
            <!-- ============== FUNCIONÁRIO MENU ============== -->
            <li class="nav-section">
                <span class="nav-label">Minha Área</span>
            </li>
            <li class="nav-item <?php echo rg_is_active('portal.php'); ?>">
                <a href="portal.php" class="nav-link">
                    <i class="bi bi-house-door-fill"></i>
                    <span class="nav-text">Meu Portal</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('perfil.php'); ?>">
                <a href="perfil.php" class="nav-link">
                    <i class="bi bi-person-circle"></i>
                    <span class="nav-text">Meu Perfil</span>
                </a>
            </li>

            <li class="nav-section">
                <span class="nav-label">Operacional</span>
            </li>
            <li class="nav-item <?php echo rg_is_active('timeclock.php'); ?>">
                <a href="timeclock.php" class="nav-link">
                    <i class="bi bi-stopwatch"></i>
                    <span class="nav-text">Registrar Ponto</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('escalas.php'); ?>">
                <a href="escalas.php" class="nav-link">
                    <i class="bi bi-calendar3"></i>
                    <span class="nav-text">Minha Escala</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('licencas.php'); ?>">
                <a href="licencas.php" class="nav-link">
                    <i class="bi bi-calendar2-heart"></i>
                    <span class="nav-text">Licenças</span>
                </a>
            </li>

            <li class="nav-section">
                <span class="nav-label">Financeiro</span>
            </li>
            <li class="nav-item <?php echo rg_is_active('recibo_salario.php'); ?>">
                <a href="recibo_salario.php" class="nav-link">
                    <i class="bi bi-receipt"></i>
                    <span class="nav-text">Holerite</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('beneficios.php'); ?>">
                <a href="beneficios.php" class="nav-link">
                    <i class="bi bi-gift"></i>
                    <span class="nav-text">Benefícios</span>
                </a>
            </li>

            <li class="nav-section">
                <span class="nav-label">Documentação</span>
            </li>
            <li class="nav-item <?php echo rg_is_active('documentos.php'); ?>">
                <a href="documentos.php" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i>
                    <span class="nav-text">Documentos</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('comunicados.php'); ?>">
                <a href="comunicados.php" class="nav-link">
                    <i class="bi bi-megaphone"></i>
                    <span class="nav-text">Comunicados</span>
                </a>
            </li>
            <li class="nav-item <?php echo rg_is_active('notificacoes.php'); ?>">
                <a href="notificacoes.php" class="nav-link">
                    <i class="bi bi-bell"></i>
                    <span class="nav-text">Notificações</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <button id="themeToggle" class="theme-toggle" title="Alternar tema">
            <i class="bi bi-sun-fill"></i>
            <span class="theme-text">Tema Claro</span>
        </button>
        <div class="user-card">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars(ucfirst($user['username'])); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user['role']); ?></div>
            </div>
            <a href="logout.php" class="user-logout" title="Sair">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>
