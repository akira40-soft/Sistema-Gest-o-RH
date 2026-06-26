<?php
/**
 * API - Time Clock com Geolocalização
 * POST /api/timeclock.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Models\TimeClockGeolocation;

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth();

$userId = $auth->getUserId();
$db = \App\Database\Database::getInstance();

try {
    // Recebe dados
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }

    $funcionarioId = $data['funcionario_id'] ?? null;
    $tipo = $data['tipo_evento'] ?? null;
    $latitude = floatval($data['latitude'] ?? 0);
    $longitude = floatval($data['longitude'] ?? 0);

    // Validações
    if (!$funcionarioId || !$tipo) {
        throw new Exception('Dados incompletos');
    }

    if (!in_array($tipo, ['entrada', 'saida', 'pausa', 'retorno'])) {
        throw new Exception('Tipo de evento inválido');
    }

    // Pega dados do funcionário
    $stmt = $db->prepare("
        SELECT f.*, 
               f.latitude_escritorio, f.longitude_escritorio,
               f.raio_permitido, f.tipo_presenca
        FROM funcionarios f
        WHERE f.id = ?
    ");
    $stmt->execute([$funcionarioId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        throw new Exception('Funcionário não encontrado');
    }

    // Validações críticas
    if (empty($employee['carteira_profissional'])) {
        throw new Exception('Carteira profissional não preenchida');
    }

    // Verifica consentimento
    $stmt = $db->prepare("
        SELECT * FROM conformidade_regulatoria 
        WHERE funcionario_id = ? AND status = 'aceito'
    ");
    $stmt->execute([$funcionarioId]);
    $consentimento = $stmt->fetch();

    if (!$consentimento) {
        throw new Exception('Consentimento de rastreamento não aceito');
    }

    // Calcula distância do escritório
    $officeLat = $employee['latitude_escritorio'] ?? -8.8383;
    $officeLon = $employee['longitude_escritorio'] ?? 13.2344;
    $distance = TimeClockGeolocation::calculateDistance($latitude, $longitude, $officeLat, $officeLon);
    
    // Valida se está dentro do raio
    $validation = TimeClockGeolocation::isWithinAllowedRadius(
        $latitude, $longitude, 
        $officeLat, $officeLon,
        $employee['tipo_presenca']
    );

    $status_validacao = $validation['within_radius'] ? 'validado' : 'rejeitado';
    $motivo_rejeicao = !$validation['within_radius'] ? 
        'Fora do raio permitido (' . $distance . 'm > ' . $validation['allowed_radius'] . 'm)' : 
        null;

    // Registra a batida
    $stmt = $db->prepare("
        INSERT INTO timeclock_logs 
        (funcionario_id, tipo_evento, latitude, longitude, 
         precisao_gps, ip_address, user_agent, dispositivo,
         status_validacao, motivo_rejeicao, distancia_escritorio, dentro_raio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $precisao = $_POST['accuracy'] ?? 10;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $dispositivo = (strpos($userAgent, 'Mobile') !== false) ? 'mobile' : 'desktop';

    $success = $stmt->execute([
        $funcionarioId,
        $tipo,
        $latitude,
        $longitude,
        $precisao,
        $ipAddress,
        $userAgent,
        $dispositivo,
        $status_validacao,
        $motivo_rejeicao,
        $distance,
        $validation['within_radius'] ? 1 : 0
    ]);

    if (!$success) {
        throw new Exception('Erro ao registar batida');
    }

    // Se foi rejeitado, cria alerta
    if (!$validation['within_radius']) {
        $stmt = $db->prepare("
            INSERT INTO alertas_timeclock 
            (funcionario_id, tipo_alerta, descricao, severidade)
            VALUES (?, ?, ?, ?)
        ");

        $descricao = "Tentativa de bater ponto fora do raio permitido: " . $distance . "m distante do escritório";
        $severidade = $distance > ($validation['allowed_radius'] * 2) ? 'alta' : 'media';

        $stmt->execute([
            $funcionarioId,
            'fora_do_raio',
            $descricao,
            $severidade
        ]);
    }

    // Log para auditoria
    $stmt = $db->prepare("
        INSERT INTO audit_logs_detailed 
        (usuario_id, acao, tipo, tabela, descricao)
        VALUES (?, ?, ?, ?, ?)
    ");

    $acao = strtoupper($tipo);
    $descricao = "Batida de $tipo registada - Status: $status_validacao - Distância: {$distance}m";
    
    $stmt->execute([
        $userId,
        $acao,
        'timeclock',
        'timeclock_logs',
        $descricao
    ]);

    // Resposta
    echo json_encode([
        'success' => true,
        'message' => $status_validacao === 'validado' ? 
            'Batida registada com sucesso' : 
            'Batida registada mas fora do raio permitido. Será revisada pelo RH.',
        'data' => [
            'tipo_evento' => $tipo,
            'status' => $status_validacao,
            'distancia' => $distance,
            'within_radius' => $validation['within_radius'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
