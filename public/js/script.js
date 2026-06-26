/*
    VALÓDIA RG - INTERFACE SCRIPT
    Lógica de navegação, sidebar e interatividade.
*/

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const menuToggle = document.getElementById('menuToggle');

    // --- TEMA CLARO/ESCURO ---
    const body = document.body;
    const themeBtn = document.getElementById('themeToggle');
    
    // Função para aplicar o tema
    const applyTheme = (theme) => {
        if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
            if (themeBtn) themeBtn.innerHTML = '<i class="bi bi-moon-stars-fill"></i>';
        } else {
            document.documentElement.removeAttribute('data-theme');
            if (themeBtn) themeBtn.innerHTML = '<i class="bi bi-sun-fill"></i>';
        }
    };

    // Carregar preferência salva
    const savedTheme = localStorage.getItem('theme') || 'dark';
    applyTheme(savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
        });
    }

    // --- SIDEBAR TOGGLE ---
    if (sidebar && menuToggle) {
        // Restaurar estado do localStorage
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('expanded');
        }

        menuToggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            if (mainContent) mainContent.classList.toggle('expanded');

            // Persistir preferência
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // --- SUBMENUS (Click Activation) ---
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            const parent = this.parentElement;

            // Se a sidebar estiver colapsada, expande primeiro
            if (sidebar && sidebar.classList.contains('collapsed')) {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('expanded');
                localStorage.setItem('sidebarCollapsed', 'false');
            }

            // Fecha TODOS os outros submenus antes de abrir o atual
            document.querySelectorAll('.has-submenu').forEach(menu => {
                if (menu !== parent) {
                    menu.classList.remove('open');
                }
            });

            // Alterna o atual (abre ou fecha)
            parent.classList.toggle('open');
        });
    });


    // --- BUSCA GLOBAL ---
    const searchInput = document.getElementById('globalSearchInput');
    if (searchInput) {
        const resultsContainer = document.createElement('div');
        resultsContainer.className = 'search-results';
        searchInput.parentElement.appendChild(resultsContainer);

        searchInput.addEventListener('input', function () {
            const query = this.value.trim();
            if (query.length < 2) {
                resultsContainer.classList.remove('show');
                return;
            }

            fetch(`api/busca_global.php?q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.results && data.results.length > 0) {
                        resultsContainer.innerHTML = data.results.map(r => `
                            <a href="${r.url}" class="result-item">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-circle"></i>
                                    <div>
                                        <strong>${r.nome}</strong><br>
                                        <small>${r.tipo}</small>
                                    </div>
                                </div>
                            </a>
                        `).join('');
                        resultsContainer.classList.add('show');
                    } else {
                        resultsContainer.innerHTML = '<div class="p-3 text-muted">Nenhum resultado encontrado.</div>';
                        resultsContainer.classList.add('show');
                    }
                })
                .catch(err => console.error('Erro na busca:', err));
        });

        // Fechar busca ao clicar fora
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.classList.remove('show');
            }
        });
    }

    // --- RELÓGIO (Opcional se houver no dashboard) ---
    const clockEl = document.getElementById('currentClock');
    if (clockEl) {
        setInterval(() => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('pt-BR');
        }, 1000);
    }

    // --- LÓGICA DE LOGIN AJAX ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            const msgErro = document.getElementById('loginError'); // Assumindo que existe no login.php

            btn.disabled = true;
            btn.textContent = 'Autenticando...';
            if (msgErro) msgErro.classList.add('d-none');

            const formData = new FormData(this);

            fetch('login_process.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect || 'dashboard.php';
                    } else {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        if (msgErro) {
                            msgErro.textContent = data.message;
                            msgErro.classList.remove('d-none');
                        } else {
                            alert(data.message);
                        }
                    }
                })
                .catch(err => {
                    console.error('Erro no login:', err);
                    btn.disabled = false;
                    btn.textContent = originalText;
                    alert("Erro ao conectar com o servidor.");
                });
        });
    }
    
        // --- ANIMAÇÕES E TRANSIÇÕES GLOBAIS ---
        (function () {
            // Page transition overlay
            const overlayId = 'pageTransitionOverlay';
            let overlay = document.getElementById(overlayId);
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = overlayId;
                overlay.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:9999;transition:opacity 350ms ease;opacity:0;background:linear-gradient(135deg, rgba(13,110,253,0.06), rgba(32,201,151,0.04));backdrop-filter: blur(2px);';
                document.body.appendChild(overlay);
            }

            // Intercept internal link clicks for smooth page transitions
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a');
                if (!a) return;
                const href = a.getAttribute('href');
                if (!href || href.startsWith('#') || a.target === '_blank' || a.hasAttribute('download')) return;
                // Only handle same-origin relative links
                try {
                    const url = new URL(href, window.location.href);
                    if (url.origin !== window.location.origin) return;
                } catch (err) {
                    return;
                }

                e.preventDefault();
                overlay.style.pointerEvents = 'auto';
                overlay.style.opacity = '1';
                document.documentElement.classList.add('page-exit');
                setTimeout(() => {
                    window.location.href = href;
                }, 320);
            });

            // Reveal animations for dashboard elements using IntersectionObserver
            const elems = document.querySelectorAll('.stat-card, .chart-container-premium, .status-card-premium, .welcome-section');
            if (elems.length > 0 && 'IntersectionObserver' in window) {
                const obs = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('in-view');
                            obs.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });

                elems.forEach(el => obs.observe(el));
            } else {
                // Fallback: add in-view immediately
                elems.forEach(el => el.classList.add('in-view'));
            }
        })();

        // --- NOTIFICATIONS SYSTEM ---
        (function () {
            // Verifica se elementos de notificações existem na página
            const notificationsBtn = document.getElementById('notificationsBtn');
            if (!notificationsBtn) return; // Sai se não existir

            // Sample notifications (in production, fetch from API)
            const notificationsData = [
                {
                    id: 1,
                    title: 'Novo Funcionário Admitido',
                    message: 'João Silva foi admitido no departamento de RH',
                    time: '5 min atrás',
                    read: false,
                    icon: 'bi-person-plus-fill',
                    type: 'success'
                },
                {
                    id: 2,
                    title: 'Folha de Pagamento Processada',
                    message: 'A folha de maio foi processada com sucesso',
                    time: '2 horas atrás',
                    read: false,
                    icon: 'bi-cash-coin',
                    type: 'info'
                },
                {
                    id: 3,
                    title: 'Backup do Sistema Completo',
                    message: 'Backup automático realizado às 04:00',
                    time: '15 horas atrás',
                    read: true,
                    icon: 'bi-cloud-check-fill',
                    type: 'success'
                }
            ];

            const notificationsDropdown = document.getElementById('notificationsDropdown');
            const notificationsList = document.getElementById('notificationsList');
            const notificationBadge = document.getElementById('notificationBadge');
            const markAllReadBtn = document.getElementById('markAllRead');

            // Render notifications
            function renderNotifications() {
                if (!notificationsList) return;
                
                notificationsList.innerHTML = notificationsData.map(notif => `
                    <div class="notification-item ${!notif.read ? 'unread' : ''}" data-id="${notif.id}">
                        <div class="notification-icon" style="color: ${notif.type === 'success' ? '#20c997' : notif.type === 'info' ? '#0d6efd' : '#f08a5d'};">
                            <i class="bi ${notif.icon}"></i>
                        </div>
                        <div class="notification-content">
                            <p class="notification-title">${notif.title}</p>
                            <p class="notification-message">${notif.message}</p>
                            <span class="notification-time">${notif.time}</span>
                        </div>
                        ${!notif.read ? '<div class="notification-dot"></div>' : ''}
                    </div>
                `).join('');

                // Update badge count
                const unreadCount = notificationsData.filter(n => !n.read).length;
                if (notificationBadge) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.style.display = unreadCount > 0 ? 'block' : 'none';
                }
            }

            // Toggle dropdown
            if (notificationsBtn && notificationsDropdown) {
                notificationsBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notificationsDropdown.classList.toggle('show');
                });
            }

            // Close dropdown when clicking outside
            if (notificationsBtn && notificationsDropdown) {
                document.addEventListener('click', (e) => {
                    if (!notificationsBtn.contains(e.target)) {
                        notificationsDropdown.classList.remove('show');
                    }
                });
            }

            // Mark notification as read on click
            if (notificationsList) {
                document.addEventListener('click', (e) => {
                    const item = e.target.closest('.notification-item');
                    if (item && notificationsList.contains(item)) {
                        const id = parseInt(item.dataset.id);
                        const notif = notificationsData.find(n => n.id === id);
                        if (notif) {
                            notif.read = true;
                            renderNotifications();
                        }
                    }
                });
            }

            // Mark all as read
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    notificationsData.forEach(n => n.read = true);
                    renderNotifications();
                });
            }

            // Initial render
            renderNotifications();
        })();
});

