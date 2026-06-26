/* =====================================================================
   SISTEMA DE GESTÃO RG - FARMÁCIA GINGONGO
   Script Principal 2026 - Interatividade Avançada
   ===================================================================== */

(function () {
    'use strict';

    const App = {
        sidebar: null,
        mainArea: null,
        menuToggle: null,
        themeBtn: null,
        searchInput: null,
        searchResults: null,
        notificationsBtn: null,
        profileBtn: null,

        init() {
            this.cacheElements();
            this.initTheme();
            this.initSidebar();
            this.initSearch();
            this.initNotifications();
            this.initProfileMenu();
            this.initAnimations();
            this.initPageTransitions();
            this.initKeyboardShortcuts();
            this.initMobileMenu();
            this.initTimeUpdate();
        },

        cacheElements() {
            this.sidebar = document.getElementById('sidebar');
            this.mainArea = document.getElementById('mainArea');
            this.menuToggle = document.getElementById('menuToggle');
            this.themeBtn = document.getElementById('themeToggle');
            this.searchInput = document.getElementById('globalSearchInput');
            this.notificationsBtn = document.getElementById('notificationsBtn');
            this.profileBtn = document.getElementById('profileBtn');
        },

        // ============== TEMA DARK/LIGHT ==============
        initTheme() {
            const saved = localStorage.getItem('rg_theme') || 'dark';
            this.applyTheme(saved);

            if (this.themeBtn) {
                this.themeBtn.addEventListener('click', () => {
                    const current = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
                    const next = current === 'light' ? 'dark' : 'light';
                    this.applyTheme(next);
                    localStorage.setItem('rg_theme', next);
                    this.toast(next === 'light' ? '☀️ Tema claro ativado' : '🌙 Tema escuro ativado', 'info', 1500);
                });
            }
        },

        applyTheme(theme) {
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            if (this.themeBtn) {
                const icon = this.themeBtn.querySelector('i');
                const text = this.themeBtn.querySelector('.theme-text');
                if (theme === 'light') {
                    if (icon) icon.className = 'bi bi-moon-stars-fill';
                    if (text) text.textContent = 'Tema Escuro';
                } else {
                    if (icon) icon.className = 'bi bi-sun-fill';
                    if (text) text.textContent = 'Tema Claro';
                }
            }
        },

        // ============== SIDEBAR ==============
        initSidebar() {
            if (!this.sidebar) return;

            // Restaurar estado do localStorage
            const collapsed = localStorage.getItem('rg_sidebar_collapsed') === 'true';
            if (collapsed) this.setSidebarCollapsed(true);

            if (this.menuToggle) {
                this.menuToggle.addEventListener('click', () => {
                    if (window.innerWidth <= 1024) {
                        this.sidebar.classList.toggle('mobile-open');
                    } else {
                        const newState = !this.sidebar.classList.contains('collapsed');
                        this.setSidebarCollapsed(newState);
                        localStorage.setItem('rg_sidebar_collapsed', newState);
                    }
                });
            }

            // Submenus
            document.querySelectorAll('.nav-item.has-submenu > .nav-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    const item = this.parentElement;
                    const isCollapsed = App.sidebar && App.sidebar.classList.contains('collapsed');

                    if (isCollapsed) {
                        e.preventDefault();
                        App.setSidebarCollapsed(false);
                        localStorage.setItem('rg_sidebar_collapsed', 'false');
                        setTimeout(() => item.classList.add('expanded'), 150);
                        return;
                    }

                    e.preventDefault();
                    // Fecha outros
                    document.querySelectorAll('.nav-item.has-submenu.expanded').forEach(other => {
                        if (other !== item) other.classList.remove('expanded');
                    });
                    item.classList.toggle('expanded');
                });
            });

            // Abrir submenu se item ativo está dentro
            const activeSubItem = document.querySelector('.submenu .nav-link.active');
            if (activeSubItem) {
                activeSubItem.closest('.nav-item.has-submenu')?.classList.add('expanded');
            }
        },

        setSidebarCollapsed(collapsed) {
            if (!this.sidebar) return;
            this.sidebar.classList.toggle('collapsed', collapsed);
            if (this.mainArea) this.mainArea.classList.toggle('expanded', collapsed);
        },

        // ============== BUSCA GLOBAL ==============
        initSearch() {
            if (!this.searchInput) return;

            // Criar container de resultados se não existir
            this.searchResults = this.searchInput.parentElement.querySelector('.search-results');
            if (!this.searchResults) {
                this.searchResults = document.createElement('div');
                this.searchResults.className = 'search-results';
                this.searchInput.parentElement.appendChild(this.searchResults);
            }

            let debounceTimer = null;
            let activeIndex = -1;
            let currentResults = [];

            this.searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                clearTimeout(debounceTimer);

                if (query.length < 2) {
                    this.hideSearchResults();
                    return;
                }

                debounceTimer = setTimeout(() => this.performSearch(query), 200);
            });

            this.searchInput.addEventListener('keydown', (e) => {
                if (!this.searchResults.classList.contains('show')) return;
                const items = this.searchResults.querySelectorAll('.search-result-item');

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, items.length - 1);
                    this.updateActiveItem(items, activeIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    this.updateActiveItem(items, activeIndex);
                } else if (e.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                    e.preventDefault();
                    items[activeIndex].click();
                } else if (e.key === 'Escape') {
                    this.hideSearchResults();
                    this.searchInput.blur();
                }
            });

            this.searchInput.addEventListener('focus', () => {
                if (this.searchInput.value.trim().length >= 2) {
                    this.performSearch(this.searchInput.value.trim());
                }
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.global-search')) {
                    this.hideSearchResults();
                }
            });
        },

        updateActiveItem(items, index) {
            items.forEach((it, i) => it.classList.toggle('active', i === index));
            if (items[index]) {
                items[index].scrollIntoView({ block: 'nearest' });
            }
        },

        async performSearch(query) {
            try {
                const response = await fetch(`api/busca_global.php?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                if (data.success && data.results && data.results.length > 0) {
                    this.renderSearchResults(query, data.results);
                } else {
                    this.renderNoResults(query);
                }
            } catch (err) {
                console.error('Erro na busca:', err);
                this.renderNoResults(query);
            }
        },

        renderSearchResults(query, results) {
            const groups = this.groupBy(results, 'tipo');
            const iconMap = {
                'Funcionário': 'bi-person-circle',
                'Departamento': 'bi-diagram-3',
                'Cargo': 'bi-briefcase',
                'Vaga': 'bi-person-plus',
                'Documento': 'bi-file-earmark',
                'default': 'bi-search'
            };

            let html = `<div class="search-results-header">
                <span>${results.length} resultado(s) para "${this.escapeHtml(query)}"</span>
                <span><kbd>↑↓</kbd> navegar <kbd>↵</kbd> abrir</span>
            </div><div class="search-results-list">`;

            Object.keys(groups).forEach(tipo => {
                html += groups[tipo].map(r => `
                    <a href="${this.escapeHtml(r.url)}" class="search-result-item">
                        <div class="search-result-icon">
                            <i class="bi ${iconMap[tipo] || iconMap.default}"></i>
                        </div>
                        <div class="search-result-content">
                            <div class="search-result-title">${this.highlightText(this.escapeHtml(r.nome), query)}</div>
                            <div class="search-result-subtitle">${this.escapeHtml(r.subtitulo || '')}</div>
                        </div>
                        <span class="search-result-type">${this.escapeHtml(tipo)}</span>
                    </a>
                `).join('');
            });

            html += '</div>';
            this.searchResults.innerHTML = html;
            this.searchResults.classList.add('show');
        },

        renderNoResults(query) {
            this.searchResults.innerHTML = `
                <div class="search-no-results">
                    <i class="bi bi-search"></i>
                    <strong>Nenhum resultado encontrado</strong>
                    <p>Não encontramos nada para "<em>${this.escapeHtml(query)}</em>"</p>
                </div>
            `;
            this.searchResults.classList.add('show');
        },

        hideSearchResults() {
            if (this.searchResults) this.searchResults.classList.remove('show');
        },

        // ============== NOTIFICAÇÕES ==============
        initNotifications() {
            if (!this.notificationsBtn) return;

            this.notificationsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = this.notificationsBtn.querySelector('.notifications-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                }
                // Fechar profile dropdown se aberto
                const profile = document.querySelector('.profile-dropdown');
                if (profile) profile.classList.remove('show');
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('#notificationsBtn')) {
                    document.querySelectorAll('.notifications-dropdown').forEach(d => d.classList.remove('show'));
                }
            });

            // Marcar todas como lidas
            const markAllBtn = document.getElementById('markAllRead');
            if (markAllBtn) {
                markAllBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                    const badge = this.notificationsBtn.querySelector('.action-badge');
                    if (badge) badge.style.display = 'none';
                    this.toast('Notificações marcadas como lidas', 'success', 2000);
                });
            }
        },

        // ============== MENU DE PERFIL ==============
        initProfileMenu() {
            if (!this.profileBtn) return;

            this.profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = this.profileBtn.querySelector('.profile-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                }
                // Fechar notifications se aberto
                document.querySelectorAll('.notifications-dropdown').forEach(d => d.classList.remove('show'));
            });

            document.addEventListener('click', (e) => {
                if (!e.target.closest('#profileBtn')) {
                    document.querySelectorAll('.profile-dropdown').forEach(d => d.classList.remove('show'));
                }
            });
        },

        // ============== ANIMAÇÕES DE ENTRADA ==============
        initAnimations() {
            if (!('IntersectionObserver' in window)) {
                document.querySelectorAll('.stat-card, .card, .welcome-banner').forEach(el => el.classList.add('in-view'));
                return;
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

            document.querySelectorAll('.stat-card, .card, .welcome-banner, .data-table tbody tr').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(15px)';
                el.style.transition = `opacity 0.4s ease ${i * 0.04}s, transform 0.4s ease ${i * 0.04}s`;
                observer.observe(el);
            });
        },

        // ============== TRANSIÇÕES DE PÁGINA ==============
        initPageTransitions() {
            // Criar overlay uma vez
            let overlay = document.getElementById('pageTransitionOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'page-loader';
                overlay.id = 'pageTransitionOverlay';
                overlay.innerHTML = '<div class="spinner"></div>';
                document.body.appendChild(overlay);
            }

            // Scroll-to-top + spinner leve (NÃO bloqueia navegação)
            let navTimer = null;
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') ||
                    href.startsWith('mailto:') || href.startsWith('tel:') ||
                    link.target === '_blank' || link.hasAttribute('download') ||
                    link.classList.contains('no-transition')) return;

                try {
                    const url = new URL(href, window.location.href);
                    if (url.origin !== window.location.origin) return;
                    if (url.pathname === window.location.pathname && url.search === window.location.search && !url.hash) {
                        return;
                    }
                } catch (err) {
                    return;
                }

                // Scroll para o topo suavemente
                window.scrollTo({ top: 0, behavior: 'smooth' });

                // Mostrar spinner curto (180ms) para feedback visual
                overlay.classList.add('show');
                clearTimeout(navTimer);
                navTimer = setTimeout(() => overlay.classList.remove('show'), 1200);

                // Fallback de segurança: se a navegação falhar/cancelar, remove overlay após 5s
                setTimeout(() => overlay.classList.remove('show'), 5000);

                // NÃO chamar e.preventDefault() — deixa o navegador navegar normalmente
            });

            // Ao carregar a página, garante que o overlay não fica preso
            window.addEventListener('pageshow', () => overlay.classList.remove('show'));
            window.addEventListener('beforeunload', () => overlay.classList.add('show'));
        },

        // ============== ATALHOS DE TECLADO ==============
        initKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + K = Foco na busca
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    if (this.searchInput) this.searchInput.focus();
                }
                // "/" = Foco na busca (se não estiver em input)
                if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                    e.preventDefault();
                    if (this.searchInput) this.searchInput.focus();
                }
                // Esc = Fechar modais/dropdowns
                if (e.key === 'Escape') {
                    document.querySelectorAll('.notifications-dropdown.show, .profile-dropdown.show, .search-results.show').forEach(el => {
                        el.classList.remove('show');
                    });
                    document.querySelectorAll('.modal-overlay.show').forEach(el => {
                        el.classList.remove('show');
                    });
                }
            });
        },

        // ============== MOBILE MENU ==============
        initMobileMenu() {
            // Fechar menu ao clicar fora
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1024) {
                    if (this.sidebar && this.sidebar.classList.contains('mobile-open')) {
                        if (!e.target.closest('.sidebar') && !e.target.closest('#menuToggle')) {
                            this.sidebar.classList.remove('mobile-open');
                        }
                    }
                }
            });
        },

        // ============== RELÓGIO / DATA ==============
        initTimeUpdate() {
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');

            const updateDate = () => {
                const now = new Date();
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                if (dateEl) {
                    dateEl.textContent = now.toLocaleDateString('pt-BR', options);
                }
                if (timeEl) {
                    timeEl.textContent = now.toLocaleTimeString('pt-BR');
                }
            };

            if (dateEl || timeEl) {
                updateDate();
                setInterval(updateDate, 1000);
            }
        },

        // ============== UTILITÁRIOS ==============
        escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        },

        highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return text.replace(regex, '<mark style="background:var(--primary-soft);color:var(--primary-light);padding:0 2px;border-radius:3px;">$1</mark>');
        },

        groupBy(arr, key) {
            return arr.reduce((acc, item) => {
                const k = item[key] || 'Outros';
                (acc[k] = acc[k] || []).push(item);
                return acc;
            }, {});
        },

        // ============== SISTEMA DE TOAST ==============
        toast(message, type = 'info', duration = 3000) {
            let stack = document.querySelector('.toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.className = 'toast-stack';
                document.body.appendChild(stack);
            }

            const icons = {
                success: 'bi-check-circle-fill',
                danger: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <i class="bi ${icons[type] || icons.info}"></i>
                <div class="toast-content">${this.escapeHtml(message)}</div>
            `;
            stack.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'toastOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        },

        // ============== MODAL HELPER ==============
        modal(options) {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML = `
                <div class="modal" style="max-width: ${options.width || '540px'}">
                    <div class="modal-header">
                        <h3 class="modal-title">${this.escapeHtml(options.title || '')}</h3>
                        <button class="modal-close" aria-label="Fechar">&times;</button>
                    </div>
                    <div class="modal-body">${options.body || ''}</div>
                    ${options.footer ? `<div class="modal-footer">${options.footer}</div>` : ''}
                </div>
            `;
            document.body.appendChild(overlay);
            setTimeout(() => overlay.classList.add('show'), 10);

            const close = () => {
                overlay.classList.remove('show');
                setTimeout(() => overlay.remove(), 250);
            };

            overlay.querySelector('.modal-close').addEventListener('click', close);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close();
            });

            return { close, element: overlay };
        },

        // ============== CONFIRMAÇÃO ==============
        confirm(message, title = 'Confirmação') {
            return new Promise((resolve) => {
                const m = this.modal({
                    title,
                    body: `<p>${this.escapeHtml(message)}</p>`,
                    footer: `
                        <button class="btn btn-ghost" data-action="cancel">Cancelar</button>
                        <button class="btn btn-danger" data-action="confirm">Confirmar</button>
                    `
                });
                m.element.querySelector('[data-action="cancel"]').addEventListener('click', () => { m.close(); resolve(false); });
                m.element.querySelector('[data-action="confirm"]').addEventListener('click', () => { m.close(); resolve(true); });
            });
        }
    };

    // Inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => App.init());
    } else {
        App.init();
    }

    // Expor globalmente
    window.App = App;
})();
