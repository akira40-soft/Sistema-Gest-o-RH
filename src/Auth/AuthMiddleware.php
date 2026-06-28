<?php
namespace App\Auth;

class AuthMiddleware
{
    private $auth;

    public function __construct()
    {
        $this->auth = new Auth();
    }

    public function requireAuth()
    {
        if (!$this->auth->isAuthenticated()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        return $this;
    }

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

    public function requireRoles($roles)
    {
        $this->requireAuth();
        if (!is_array($roles)) $roles = [$roles];
        $userRole = $this->auth->getUserRole();
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            header('Location: /acesso_negado.php');
            exit;
        }
        return $this;
    }

    public function getAuth()
    {
        return $this->auth;
    }

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

    public function check($condition, $redirectTo = '/acesso_negado.php')
    {
        if (!$condition) {
            http_response_code(403);
            header('Location: ' . $redirectTo);
            exit;
        }
        return $this;
    }

    public static function staticRequireAdmin()
    {
        $m = new self();
        $m->requireAdmin();
    }

    public static function staticRequireRoles($roles)
    {
        $m = new self();
        $m->requireRoles($roles);
    }
}
