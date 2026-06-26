<?php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

header('Content-Type: application/json; charset=utf-8');

// Validar autenticação
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Buscar query
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Digite pelo menos 2 caracteres']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $searchTerm = "%$query%";
    $results = [];

    // Buscar funcionários
    $stmt = $db->prepare("
        SELECT id, nome_completo as nome, 'Funcionário' as tipo,
               CONCAT('funcionarios.php?action=view&id=', id) as url
        FROM funcionarios
        WHERE (nome_completo LIKE :q1 OR cpf LIKE :q2 OR email LIKE :q3)
          AND status = 'ativo'
        LIMIT 5
    ");
    $stmt->execute([':q1' => $searchTerm, ':q2' => $searchTerm, ':q3' => $searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    // Buscar departamentos
    $stmt = $db->prepare("
        SELECT id, nome, 'Departamento' as tipo,
               CONCAT('departamentos.php?id=', id) as url
        FROM departamentos
        WHERE nome LIKE :q1 AND ativo = 1
        LIMIT 3
    ");
    $stmt->execute([':q1' => $searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    // Buscar cargos
    $stmt = $db->prepare("
        SELECT id, nome, 'Cargo' as tipo,
               CONCAT('cargos.php?id=', id) as url
        FROM cargos
        WHERE nome LIKE :q1 AND ativo = 1
        LIMIT 3
    ");
    $stmt->execute([':q1' => $searchTerm]);
    $results = array_merge($results, $stmt->fetchAll());

    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results)
    ]);

}
catch (Exception $e) {
    error_log("Erro na busca global: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar',
        'results' => []
    ]);
}
