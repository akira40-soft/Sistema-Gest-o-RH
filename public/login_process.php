<?php
/**
 * Login Process - Backend para processar autenticação
 * Simplificado e corrigido para remover campos desnecessários
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond(array $payload, int $status = 200)
{
    global $isAjax;
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

// Redirecionar para HTML em caso de GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAjax) {
        respond(['success' => false, 'message' => 'Método GET não permitido.'], 405);
    }
    header('Location: /login.php');
    exit;
}

// Permitir apenas requisições POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        respond(['success' => false, 'message' => 'Método não permitido.'], 405);
    }
    header('Location: /login.php');
    exit;
}

// Obter dados do POST
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validações básicas
if (empty($username) || empty($password)) {
    $message = 'Por favor, preencha todos os campos.';
    if ($isAjax) {
        respond(['success' => false, 'message' => $message], 400);
    }
    $_SESSION['login_error'] = $message;
    header('Location: /login.php');
    exit;
}

// Validações de segurança
if (strlen($username) < 3 || strlen($username) > 50) {
    $message = 'Usuário inválido.';
    if ($isAjax) {
        respond(['success' => false, 'message' => $message], 400);
    }
    $_SESSION['login_error'] = $message;
    header('Location: /login.php');
    exit;
}

try {
    // Inicializar Auth
    $auth = new Auth();

    // Tentar login
    $result = $auth->login($username, $password);

    if (!$result['success']) {
        // Delay de 1 segundo para prevenir timing attacks
        sleep(1);
        $message = 'Credenciais inválidas.';
        if ($isAjax) {
            respond(['success' => false, 'message' => $message], 401);
        }
        $_SESSION['login_error'] = $message;
        header('Location: /login.php');
        exit;
    }

    // Se sucesso, redirecionar baseado no tipo de acesso
    $userType = $result['user']['tipo_acesso'];
    $redirect = in_array($userType, ['admin', 'gestor_rh', 'super_admin', 'funcionario_rh', 'lider_farmaceutico']) ? '/dashboard.php' : '/portal.php';

    if ($isAjax) {
        respond(['success' => true, 'redirect' => $redirect]);
    }

    header('Location: ' . $redirect);
    exit;

} catch (Exception $e) {
    error_log("Erro no login_process.php: " . $e->getMessage());
    $message = 'Erro ao processar login. Tente novamente.';
    if ($isAjax) {
        respond(['success' => false, 'message' => $message], 500);
    }
    $_SESSION['login_error'] = $message;
    header('Location: /login.php');
    exit;
}
