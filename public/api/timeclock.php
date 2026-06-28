<?php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\Auth;
use App\Utils\Audit;

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth();

$userId = $auth->getUserId();
$db = \App\Database\Database::getInstance()->getConnection();

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Dados inválidos');
    }

    $funcionarioId = $data['funcionario_id'] ?? null;
    $tipo = $data['tipo'] ?? $data['tipo_evento'] ?? null;
    $lat = !empty($data['latitude']) ? (float)$data['latitude'] : null;
    $lon = !empty($data['longitude']) ? (float)$data['longitude'] : null;

    if (!$funcionarioId || !$tipo) {
        throw new Exception('Dados incompletos');
    }

    if (!in_array($tipo, ['entrada', 'saida'])) {
        throw new Exception('Tipo inválido');
    }

    $stmt = $db->prepare("SELECT id, nome_completo FROM funcionarios WHERE id = ?");
    $stmt->execute([$funcionarioId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        throw new Exception('Funcionário não encontrado');
    }

    $today = date('Y-m-d');
    $now = date('H:i:s');

    $stmt = $db->prepare("SELECT * FROM registros_ponto WHERE funcionario_id = ? AND data = ?");
    $stmt->execute([$funcionarioId, $today]);
    $existing = $stmt->fetch();

    if ($tipo === 'entrada') {
        if ($existing && $existing['hora_entrada']) {
            throw new Exception('Entrada já registada hoje');
        }

        if ($existing) {
            $stmt = $db->prepare("UPDATE registros_ponto SET hora_entrada = ?, tipo = 'presenca', metodo_registro = 'web', gps_latitude = ?, gps_longitude = ? WHERE id = ?");
            $stmt->execute([$now, $lat, $lon, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO registros_ponto (funcionario_id, data, hora_entrada, tipo, metodo_registro, gps_latitude, gps_longitude) VALUES (?, ?, ?, 'presenca', 'web', ?, ?)");
            $stmt->execute([$funcionarioId, $today, $now, $lat, $lon]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Entrada registada com sucesso',
            'data' => ['tipo' => 'entrada', 'hora' => $now, 'timestamp' => date('Y-m-d H:i:s')]
        ]);

    } elseif ($tipo === 'saida') {
        if (!$existing || !$existing['hora_entrada']) {
            throw new Exception('Sem entrada registada hoje');
        }
        if ($existing['hora_saida']) {
            throw new Exception('Saída já registada hoje');
        }

        $stmt = $db->prepare("UPDATE registros_ponto SET hora_saida = ? WHERE id = ?");
        $stmt->execute([$now, $existing['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Saída registada com sucesso',
            'data' => ['tipo' => 'saida', 'hora' => $now, 'timestamp' => date('Y-m-d H:i:s')]
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
