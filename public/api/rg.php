<?php
/**
 * API para gerenciar RGs
 * Endpoints: /api/rg.php
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\AuthMiddleware;
use App\Models\RG;

// Headers JSON
header('Content-Type: application/json; charset=utf-8');

// Middleware de autenticação
$middleware = new AuthMiddleware();
$middleware->requireAuth();

// Método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

try {
    $rg = new RG();

    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);
                $results = $rg->getAll($limit, $offset);
                echo json_encode(['success' => true, 'data' => $results]);

            } elseif ($action === 'get') {
                $id = (int)($_GET['id'] ?? 0);
                $result = $rg->getById($id);
                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'RG não encontrado']);
                }

            } elseif ($action === 'by-funcionario') {
                $funcionario_id = (int)($_GET['funcionario_id'] ?? 0);
                if (!$funcionario_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID do funcionário obrigatório']);
                    exit;
                }
                $results = $rg->getByFuncionario($funcionario_id);
                echo json_encode(['success' => true, 'data' => $results]);

            } elseif ($action === 'search') {
                $query = $_GET['q'] ?? '';
                if (strlen($query) < 2) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Query deve ter no mínimo 2 caracteres']);
                    exit;
                }
                $results = $rg->search($query);
                echo json_encode(['success' => true, 'data' => $results]);

            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            }
            break;

        case 'POST':
            if ($action === 'create') {
                // Verificar permissão
                $middleware->requireAdmin();

                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                    exit;
                }

                $result = $rg->create($data);
                echo json_encode($result);

            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            }
            break;

        case 'PUT':
            if ($action === 'update') {
                // Verificar permissão
                $middleware->requireAdmin();

                $id = (int)($_GET['id'] ?? 0);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID do RG obrigatório']);
                    exit;
                }

                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                    exit;
                }

                $result = $rg->update($id, $data);
                echo json_encode($result);

            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            }
            break;

        case 'DELETE':
            if ($action === 'delete') {
                // Verificar permissão
                $middleware->requireAdmin();

                $id = (int)($_GET['id'] ?? 0);
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'ID do RG obrigatório']);
                    exit;
                }

                $result = $rg->delete($id);
                echo json_encode($result);

            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }

} catch (\Exception $e) {
    error_log("Erro na API RG: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
