<?php
/**
 * API - Upload de Foto de Perfil
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\AuthMiddleware;
use App\Models\UserPhoto;

header('Content-Type: application/json');

// Verificar autenticação
$middleware = new AuthMiddleware();
$middleware->requireAuth();

$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new \Exception('Método não permitido');
    }

    if (!isset($_FILES['foto']) || empty($_FILES['foto']['name'])) {
        throw new \Exception('Nenhum arquivo foi enviado');
    }

    $photoModel = new UserPhoto();
    $result = $photoModel->upload($usuario_id, $_FILES['foto']);

    if ($result) {
        // Log de auditoria
        error_log("✅ Foto de perfil enviada para usuário {$usuario_id}");

        echo json_encode([
            'success' => true,
            'message' => 'Foto enviada com sucesso!',
            'data' => $result
        ]);
    } else {
        throw new \Exception('Erro ao salvar foto');
    }

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
