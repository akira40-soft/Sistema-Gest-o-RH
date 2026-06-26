<?php
// Garantir que a variável $user existe para a sidebar
if (!isset($user)) {
    if (isset($_SESSION['username'])) {
        $user = [
            'username' => $_SESSION['username'],
            'role' => $_SESSION['tipo_acesso'] ?? 'Geral',
            'id' => $_SESSION['user_id'] ?? null
        ];
    }
    else {
        // Fallback seguro caso não haja sessão
        $user = [
            'username' => 'Visitante',
            'role' => 'Geral',
            'id' => null
        ];
    }
}
?>
<!-- Sidebar Reutilizável - Sistema de Gestão RG -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="bi bi-capsule-pill"></i>
        </div>
        <div class="logo-text">
            <h3>GINGONGO <span>RG</span></h3>
            <small>Gestão de Farmácia</small>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <?php if (isset($user['role']) && $user['role'] !== 'funcionario'): ?>
            <li class="nav-label">Principal</li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="dashboard.php" title="Dashboard">
                    <div class="nav-link-content">
                        <i class="bi bi-grid-1x2-fill"></i> 
                        <span>Dashboard</span>
                    </div>
                </a>
            </li>
            
            <li class="nav-label">RH & Pessoal</li>
            <li class="has-submenu">
                <a class="submenu-toggle" title="Colaboradores">
                    <div class="nav-link-content">
                        <i class="bi bi-people-fill"></i> 
                        <span>Colaboradores</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="funcionarios.php"><i class="bi bi-person-badge"></i> Lista de Funcionários</a></li>
                    <li><a href="departamentos.php"><i class="bi bi-diagram-3"></i> Departamentos</a></li>
                    <li><a href="cargos.php"><i class="bi bi-briefcase"></i> Cargos & Funções</a></li>
                    <li><a href="usuarios.php"><i class="bi bi-shield-lock"></i> Contas de Acesso</a></li>
                </ul>
            </li>

            <li class="has-submenu">
                <a class="submenu-toggle" title="Presença & Escalas">
                    <div class="nav-link-content">
                        <i class="bi bi-calendar-check-fill"></i> 
                        <span>Operacional</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="escalas.php"><i class="bi bi-calendar3"></i> Escalas de Turnos</a></li>
                    <li><a href="pontos.php"><i class="bi bi-clock-history"></i> Registros de Ponto</a></li>
                    <li><a href="licencas.php"><i class="bi bi-file-medical"></i> Licenças & Atestados</a></li>
                    <li><a href="ferias.php"><i class="bi bi-sun"></i> Férias & Ausências</a></li>
                </ul>
            </li>

            <li class="nav-label">Administrativo</li>
            <li class="has-submenu">
                <a class="submenu-toggle" title="Financeiro">
                    <div class="nav-link-content">
                        <i class="bi bi-cash-stack"></i> 
                        <span>Financeiro</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="folha.php"><i class="bi bi-calculator"></i> Folha de Pagamento</a></li>
                    <li><a href="beneficios.php"><i class="bi bi-gift"></i> Gestão de Benefícios</a></li>
                </ul>
            </li>

            <li class="has-submenu">
                <a class="submenu-toggle" title="Documentação">
                    <div class="nav-link-content">
                        <i class="bi bi-file-earmark-pdf-fill"></i> 
                        <span>Documentação</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="documentos.php"><i class="bi bi-archive"></i> Arquivo Digital</a></li>
                    <li><a href="comunicados.php"><i class="bi bi-megaphone"></i> Comunicados Internos</a></li>
                </ul>
            </li>

            <li class="nav-label">Desenvolvimento</li>
            <li class="has-submenu">
                <a class="submenu-toggle" title="Talento">
                    <div class="nav-link-content">
                        <i class="bi bi-star-fill"></i> 
                        <span>Talento & Carreira</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="avaliacoes.php"><i class="bi bi-graph-up-arrow"></i> Avaliações 360°</a></li>
                    <li><a href="treinamentos.php"><i class="bi bi-mortarboard"></i> Treinamentos</a></li>
                    <li><a href="vagas.php"><i class="bi bi-person-plus"></i> Recrutamento</a></li>
                </ul>
            </li>

            <li class="has-submenu">
                <a class="submenu-toggle" title="Configurações">
                    <div class="nav-link-content">
                        <i class="bi bi-gear-fill"></i> 
                        <span>Sistema</span>
                    </div> 
                    <i class="bi bi-chevron-right submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li><a href="config.php"><i class="bi bi-sliders"></i> Ajustes Gerais</a></li>
                    <li><a href="advertencias.php"><i class="bi bi-exclamation-octagon"></i> Gestão Disciplinar</a></li>
                    <li><a href="uniformes.php"><i class="bi bi-shirt"></i> EPIs & Uniformes</a></li>
                </ul>
            </li>
            <?php else: ?>
            <!-- Menu do Funcionário (Portal) -->
            <li class="nav-label">Minha Área</li>
            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'portal.php' ? 'active' : ''; ?>">
                <a href="portal.php" title="Início">
                    <div class="nav-link-content">
                        <i class="bi bi-house-door-fill"></i> 
                        <span>Meu Portal</span>
                    </div>
                </a>
            </li>
            <li>
                <a href="recibo_salario.php" title="Meus Recibos">
                    <div class="nav-link-content">
                        <i class="bi bi-receipt"></i> 
                        <span>Meus Recibos</span>
                    </div>
                </a>
            </li>
            <li>
                <a href="licencas.php?meus=true" title="Minhas Solicitações">
                    <div class="nav-link-content">
                        <i class="bi bi-calendar2-heart"></i> 
                        <span>Minhas Férias</span>
                    </div>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="theme-switcher mb-3 px-3">
            <button id="themeToggle" class="btn btn-outline-light w-100 d-flex align-items-center justify-content-center gap-2" style="border-radius: 12px; border: 1px solid var(--glass-border); padding: 10px; color: var(--text-white);">
                <i class="bi bi-sun-fill"></i> Alterar Tema
            </button>
        </div>
        <div class="user-card-mini">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&background=39FF14&color=000&bold=true" alt="Avatar">
            <div class="user-details">
                <p><?php echo htmlspecialchars(ucfirst($user['username'])); ?></p>
                <span><?php echo htmlspecialchars($user['role'] ?? 'Geral'); ?></span>
            </div>
            <a href="logout.php" class="btn-logout-mini" title="Sair">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>
