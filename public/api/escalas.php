<?php
require_once __DIR__ . '/../../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-t');

    $sql = "SELECT e.id, e.data, t.nome as turno, t.hora_inicio, t.hora_fim, t.tipo, f.nome_completo
            FROM escalas e
            JOIN turnos t ON e.turno_id = t.id
            JOIN funcionarios f ON e.funcionario_id = f.id
            WHERE e.data BETWEEN :start AND :end AND e.status != 'cancelado'";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    $escalas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = [];
    foreach ($escalas as $escala) {
        // Definir cor baseado no tipo
        $color = '#3788d8'; // default blue
        switch ($escala['tipo']) {
            case 'manha':
                $color = '#28a745';
                break; // green
            case 'tarde':
                $color = '#ffc107';
                break; // yellow/orange
            case 'noite':
                $color = '#6f42c1';
                break; // purple
        }

        $events[] = [
            'id' => $escala['id'],
            'title' => $escala['nome_completo'] . ' (' . $escala['turno'] . ')',
            'start' => $escala['data'] . 'T' . $escala['hora_inicio'],
            'end' => $escala['data'] . 'T' . $escala['hora_fim'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'allDay' => false // Turnos têm horário específico
        ];
    }

    echo json_encode($events);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
