<?php
/**
 * Register Process - Backend para processar cadastro de novos usuários (RESTRICTED)
 * Apenas Admin e Gestor RH podem criar usuários.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;
use App\Utils\Notification;

header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();

// 1. BLOQUEIO DE SEGURANÇA: Exigir Login
if (!$auth->isAuthenticated()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Faça login.']);
    exit;
}

// 2. BLOQUEIO DE PERMISSÃO: Apenas Admin e Gestor RH
$userRole = $auth->getUserRole();
if (!in_array($userRole, ['admin', 'gestor_rh', 'super_admin', 'funcionario_rh'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para criar usuários.']);
    exit;
}

// Permitir apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Obter dados do POST
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$tipo_acesso = $_POST['role'] ?? 'funcionario';

$funcionario_id = $_POST['funcionario_id'] ?? null;

// Validações básicas
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Preencha usuário e senha.']);
    exit;
}

// Regras de Negócio de Permissão
// Gestor RH não pode criar Admin
if ($userRole === 'gestor_rh' && $tipo_acesso === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Gestores de RH não podem criar Administradores.']);
    exit;
}

try {
    // Validar força da senha
    $strengthCheck = $auth->validatePasswordStrength($password);
    if (!$strengthCheck['valid']) {
        echo json_encode(['success' => false, 'message' => 'Senha fraca! ' . $strengthCheck['message']]);
        exit;
    }

    // Tentar registrar (Auth->register agora retorna array com user_id)
    $result = $auth->register($username, $password, $tipo_acesso);

    if ($result['success']) {
        $newUserId = $result['user_id'];

        // Vincular ao funcionário (se fornecido)
        if (!empty($funcionario_id)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE funcionarios SET usuario_id = :uid WHERE id = :fid");
            $stmt->execute([':uid' => $newUserId, ':fid' => $funcionario_id]);
        }

        // Notificar o novo utilizador
        Notification::send(
            $newUserId,
            'Conta criada',
            'A sua conta foi criada com sucesso. Username: ' . $username . '. Faça login para começar.',
            'success',
            '/login.php'
        );

        // Audit log
        try {
            $db = $db ?? Database::getInstance()->getConnection();
            $adminName = $auth->getUsername() ?? 'admin';
            $db->prepare("INSERT INTO audit_logs (acao, tabela, registro_id, detalhes, usuario_id, criado_em) VALUES ('create', 'usuarios', :rid, :d, :uid, NOW())")
                ->execute([':rid' => $newUserId, ':d' => "Conta criada: $username (cargo: $tipo_acesso) por $adminName", ':uid' => $auth->getUserId()]);
        } catch (\Throwable $e) {}
    }

    echo json_encode($result);

}
catch (Exception $e) {
    error_log("Erro no register_process.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
