/**
 * Login Script - Validações e comportamentos
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');

    // Verificar se elementos existem antes de usar
    if (!loginForm || !usernameInput || !passwordInput) {
        console.warn('Elementos de login não encontrados');
        return;
    }

    // Fechar mensagem de erro ao clicar (se existir)
    if (loginError) {
        loginError.addEventListener('click', function() {
            this.classList.add('d-none');
        });
    }

    // Limpar erro ao começar a digitar
    usernameInput.addEventListener('input', clearError);
    passwordInput.addEventListener('input', clearError);

    // Submit do formulário
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Limpar erro anterior
        clearError();
        
        // Validações básicas
        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        if (!username) {
            showError('Digite seu usuário');
            return;
        }

        if (username.length < 3) {
            showError('Usuário deve ter no mínimo 3 caracteres');
            return;
        }

        if (!password) {
            showError('Digite sua senha');
            return;
        }

        if (password.length < 6) {
            showError('Senha deve ter no mínimo 6 caracteres');
            return;
        }

        // Desabilitar botão durante envio
        const btn = loginForm.querySelector('.btn-login');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

        // Enviar formulário
        loginForm.submit();
    });

    function showError(message) {
        if (loginError) {
            loginError.textContent = message;
            loginError.classList.remove('d-none');
            loginError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            alert(message);
        }
    }

    function clearError() {
        if (loginError) {
            loginError.classList.add('d-none');
            loginError.textContent = '';
        }
    }

    // Verificar se há erro na sessão (vindo do server)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('error')) {
        showError('Ocorreu um erro ao fazer login. Tente novamente.');
    }

    // Focar no campo de usuário automaticamente
    usernameInput.focus();
});
