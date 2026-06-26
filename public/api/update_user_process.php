<?php
require_once __DIR__ . '/../../src/bootstrap.php';
use App\Auth\Auth;

header('Content-Type: application/json');

$auth = new Auth();

// Segurança: Apenas logados
if (!$auth->isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Sessão expirada.']);
    exit;
}

$currentUserId = $auth->getUserId();
$currentUserRole = $auth->getUserRole();

// Obter dados
$id = $_POST['id'] ?? null;
$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID do usuário não fornecido.']);
    exit;
}

// Lógica de Permissão
// 1. Um Admin pode mudar qualquer coisa de qualquer um.
// 2. Um usuário pode mudar APENAS o seu próprio nome/senha (se permitido no futuro, mas aqui focamos no Admin conforme pedido).
// 3. Conforme o pedido: "Apenas eles (Admin) podem fazer isso, tanto a dele como a de outros".

if ($currentUserRole !== 'admin' && $currentUserRole !== 'gestor_rh' && $currentUserId != $id) {
    echo json_encode(['success' => false, 'message' => 'Permissão negada para esta operação.']);
    exit;
}

$updateData = [];
if ($username)
    $updateData['username'] = $username;
if ($password)
    $updateData['password'] = $password;
if ($ativo !== null)
    $updateData['ativo'] = $ativo;

$result = $auth->updateUser($id, $updateData);

echo json_encode($result);
