<?php
namespace App\Auth;

use App\Database\Database;

/**
 * Middleware de Autenticação
 * Verifica se o usuário está autenticado e tem permissão para acessar a página
 */
class AuthMiddleware
{
    private $auth;
    private $requiredRoles = [];
    private $requireAuth = true;

    public function __construct()
    {
        $this->auth = new Auth();
    }

    /**
     * Verifica autenticação básica
     */
    public function requireAuth()
    {
        if (!$this->auth->isAuthenticated()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        return $this;
    }

    /**
     * Verifica se o usuário é admin
     */
    public function requireAdmin()
    {
        $this->requireAuth();
        
        if (!$this->auth->isAdmin()) {
            http_response_code(403);
            header('Location: /acesso_negado.php');
            exit;
        }
        return $this;
    }

    /**
     * Verifica múltiplos roles
     */
    public function requireRoles($roles)
    {
        $this->requireAuth();
        
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        $userRole = $this->auth->getUserRole();
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            header('Location: /acesso_negado.php');
            exit;
        }
        return $this;
    }

    /**
     * Retorna a instância de Auth
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * Verifica se é usuário específico (por ID)
     */
    public function requireUser($userId)
    {
        $this->requireAuth();
        
        if ($this->auth->getUserId() != $userId && !$this->auth->isAdmin()) {
            http_response_code(403);
            header('Location: /acesso_negado.php');
            exit;
        }
        return $this;
    }

    /**
     * Verifica permissão genérica
     */
    public function check($condition, $redirectTo = '/acesso_negado.php')
    {
        if (!$condition) {
            http_response_code(403);
            header('Location: ' . $redirectTo);
            exit;
        }
        return $this;
    }
}
