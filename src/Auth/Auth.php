<?php
namespace App\Auth;

use App\Database\Database;

/**
 * Classe Auth - Versão JSON
 * Adaptada para funcionar com Database JSON
 */
class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Login
     */
    public function login($username, $password)
    {
        try {
            // Buscar usuário
            $user = $this->db->select('usuarios', ['username' => $username], true);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Credenciais inválidas.',
                    'user' => null
                ];
            }

            if (!$user['ativo']) {
                return [
                    'success' => false,
                    'message' => 'Usuário desativado. Contacte o administrador.',
                    'user' => null
                ];
            }

            // Verificar senha
            if (!$this->verifyPassword($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Credenciais inválidas.',
                    'user' => null
                ];
            }

            // Criar sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['tipo_acesso'] = $user['tipo_acesso'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();

            // Atualizar último login
            $this->db->update('usuarios',
            ['ultimo_login' => date('Y-m-d H:i:s')],
            ['id' => $user['id']]
            );

            return [
                'success' => true,
                'message' => 'Login realizado com sucesso!',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'tipo_acesso' => $user['tipo_acesso']
                ]
            ];

        }
        catch (\Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar login.',
                'user' => null
            ];
        }
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }

            session_destroy();
            return true;
        }
        return false;
    }

    public function isAuthenticated()
    {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function getUserRole()
    {
        return $_SESSION['tipo_acesso'] ?? null;
    }

    public function isAdmin()
    {
        $role = $this->getUserRole();
        return in_array($role, ['admin', 'gestor_rh', 'super_admin']);
    }

    public function isHRStaff()
    {
        $role = $this->getUserRole();
        return in_array($role, ['admin', 'gestor_rh', 'super_admin', 'funcionario_rh']);
    }

    public function isManager()
    {
        $role = $this->getUserRole();
        return in_array($role, ['admin', 'gestor_rh', 'super_admin', 'lider_farmaceutico']);
    }

    public function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function getUsername()
    {
        return $_SESSION['username'] ?? null;
    }

    public function requireAuth($requireAdmin = false)
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        if ($requireAdmin && !$this->isAdmin()) {
            header('Location: /acesso_negado.php');
            exit;
        }
    }

    public function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public function register($username, $password, $tipo_acesso = 'funcionario')
    {
        try {
            $validRoles = ['super_admin', 'admin', 'gestor_rh', 'funcionario_rh', 'lider_farmaceutico', 'funcionario'];
            if (!in_array($tipo_acesso, $validRoles)) {
                return ['success' => false, 'message' => 'Tipo de acesso inválido.'];
            }

            // Verificar se existe
            $exists = $this->db->select('usuarios', ['username' => $username], true);
            if ($exists) {
                return ['success' => false, 'message' => 'Usuário já existe.'];
            }

            // Inserir
            $id = $this->db->insert('usuarios', [
                'username' => $username,
                'password_hash' => $this->hashPassword($password),
                'tipo_acesso' => $tipo_acesso,
                'ativo' => 1
            ]);

            return [
                'success' => true,
                'message' => 'Usuário cadastrado!',
                'user_id' => $id
            ];

        }
        catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao cadastrar.'];
        }
    }

    /**
     * Valida a força da senha
     */
    public function validatePasswordStrength($password)
    {
        $strength = 0;
        $feedback = [];

        // Comprimento mínimo
        if (strlen($password) >= 8) {
            $strength++;
        }
        else {
            $feedback[] = "Mínimo 8 caracteres";
        }

        // Letras maiúsculas
        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        }
        else {
            $feedback[] = "Adicione letras maiúsculas";
        }

        // Letras minúsculas
        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        }
        else {
            $feedback[] = "Adicione letras minúsculas";
        }

        // Números
        if (preg_match('/[0-9]/', $password)) {
            $strength++;
        }
        else {
            $feedback[] = "Adicione números";
        }

        // Caracteres especiais
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $strength++;
        }
        else {
            $feedback[] = "Adicione caracteres especiais";
        }

        // Determinar nível
        $levels = ['muito-fraca', 'fraca', 'média', 'forte', 'muito-forte'];
        $level = $levels[min($strength, 4)];

        return [
            'valid' => $strength >= 3,
            'strength' => $level,
            'score' => $strength,
            'message' => implode(', ', $feedback) ?: 'Senha forte!'
        ];
    }

    /**
     * Atualiza dados de um usuário (Nome ou Senha)
     */
    public function updateUser($id, $data)
    {
        try {
            $updateData = [];

            if (isset($data['username'])) {
                // Verificar se o novo username já existe para outro ID
                $exists = $this->db->select('usuarios', ['username' => $data['username']], true);
                if ($exists && $exists['id'] != $id) {
                    return ['success' => false, 'message' => 'Este nome de usuário já está em uso.'];
                }
                $updateData['username'] = $data['username'];
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $strength = $this->validatePasswordStrength($data['password']);
                if (!$strength['valid']) {
                    return ['success' => false, 'message' => 'Senha fraca: ' . $strength['message']];
                }
                $updateData['password_hash'] = $this->hashPassword($data['password']);
            }

            if (isset($data['ativo'])) {
                $updateData['ativo'] = (int)$data['ativo'];
            }

            if (empty($updateData)) {
                return ['success' => false, 'message' => 'Nada para atualizar.'];
            }

            $success = $this->db->update('usuarios', $updateData, ['id' => $id]);

            if ($success) {
                // Se for o próprio usuário, atualizar a sessão
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
                    if (isset($updateData['username'])) {
                        $_SESSION['username'] = $updateData['username'];
                    }
                }
                return ['success' => true, 'message' => 'Usuário atualizado com sucesso!'];
            }

            return ['success' => false, 'message' => 'Erro ao atualizar no banco de dados.'];

        }
        catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro técnico: ' . $e->getMessage()];
        }
    }
}
