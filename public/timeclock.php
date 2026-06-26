<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Database\Database;

$auth = new Auth();
$auth->requireAuth();

$userId = $auth->getUserId();
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT f.*, u.id as user_id, u.tipo_acesso FROM funcionarios f JOIN usuarios u ON f.usuario_id = u.id WHERE u.id = ?");
$stmt->execute([$userId]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: perfil.php?erro=' . urlencode('Página requer funcionário vinculado. Contacte o RH.'));
    exit;
}

$validation_errors = [];
if (empty($employee['carteira_profissional'])) {
    $validation_errors[] = "Carteira profissional não preenchida. Contacte o RH.";
}

$stmt = $db->prepare("SELECT * FROM conformidade_regulatoria WHERE funcionario_id = ? AND status = 'aceito'");
$stmt->execute([$employee['id']]);
$consentimento = $stmt->fetch();
if (!$consentimento) {
    $validation_errors[] = "Deve aceitar o consentimento de rastreamento para bater ponto.";
}

$stmt = $db->prepare("SELECT * FROM timeclock_logs WHERE funcionario_id = ? ORDER BY criado_em DESC LIMIT 5");
$stmt->execute([$employee['id']]);
$recent_logs = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM alertas_timeclock WHERE funcionario_id = ? AND resolvido = 0 ORDER BY criado_em DESC");
$stmt->execute([$employee['id']]);
$alertas = $stmt->fetchAll();

$pageTitle = 'TimeClock';
$pageSubtitle = 'Bater Ponto com Geolocalização';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | SG Farmácia Gingongo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style-2026.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <style>
        .tc-container { max-width: 600px; margin: 2rem auto; }
        .tc-gps { padding: 1.25rem; border-radius: var(--radius); text-align: center; font-weight: 600; margin-bottom: 1rem; }
        .tc-gps.active { background: rgba(16,185,129,0.1); color: var(--success); border: 2px solid rgba(16,185,129,0.3); }
        .tc-gps.inactive { background: rgba(239,68,68,0.1); color: var(--danger); border: 2px solid rgba(239,68,68,0.3); }
        .tc-gps.loading { background: rgba(245,158,11,0.1); color: var(--warning); border: 2px solid rgba(245,158,11,0.3); }
        .tc-map { width: 100%; height: 280px; border-radius: var(--radius); border: 1px solid var(--border-color); margin-bottom: 1rem; }
        .tc-btn { width: 100%; padding: 1rem; font-size: 1rem; font-weight: 700; border: none; border-radius: var(--radius); cursor: pointer; transition: var(--transition); margin-bottom: 0.5rem; }
        .tc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .tc-btn.entrada { background: var(--success); color: #fff; }
        .tc-btn.saida { background: var(--danger); color: #fff; }
        .tc-log { padding: 0.75rem; margin-bottom: 0.5rem; background: var(--bg-body); border-left: 4px solid var(--primary); border-radius: var(--radius-sm); font-size: 0.85rem; }
    </style>
</head>
<body class="dashboard-body">
    <div class="app-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-area" id="mainArea">
            <?php include 'includes/topbar.php'; ?>
            <div class="content-body">
                <div class="tc-container">

                    <?php if (!empty($validation_errors)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div class="alert-content">
                                <strong>Não é possível bater ponto:</strong>
                                <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem;">
                                    <?php foreach ($validation_errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <a href="perfil.php" class="btn btn-primary btn-sm" style="margin-top: 0.75rem;">
                                    <i class="bi bi-person"></i> Ir para Perfil
                                </a>
                            </div>
                        </div>
                    <?php else: ?>

                        <div class="card mb-3">
                            <div class="card-body">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <div class="user-avatar-sm"><?php echo strtoupper(substr($employee['nome_completo'], 0, 1)); ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($employee['nome_completo']); ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Carteira: <?php echo htmlspecialchars($employee['carteira_profissional']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <div class="alert-content">
                                <strong>Notificação Legal (Lei 7/15):</strong> A sua localização será registada por conformidade regulamentar. Dados confidenciais.
                            </div>
                        </div>

                        <?php if (!empty($alertas)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div class="alert-content">
                                    <strong>Alertas Pendentes:</strong>
                                    <?php foreach ($alertas as $alerta): ?>
                                        <div style="margin-top: 0.5rem; padding: 0.5rem; background: var(--bg-card); border-radius: var(--radius-sm); font-size: 0.85rem;">
                                            <strong><?php echo htmlspecialchars($alerta['tipo_alerta']); ?></strong> — <?php echo htmlspecialchars($alerta['descricao']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="tc-gps loading" id="gpsStatus">
                            <i class="bi bi-geo-alt"></i> Ativando GPS...
                        </div>

                        <div id="map" class="tc-map"></div>

                        <div class="card mb-3" id="distanceInfo" style="display: none;">
                            <div class="card-body" style="font-size: 0.85rem;">
                                <strong>Distância:</strong> <span id="distanceValue">--</span>m &nbsp;|&nbsp;
                                <strong>Raio:</strong> <span id="radiusValue">--</span>m &nbsp;|&nbsp;
                                <strong>Status:</strong> <span id="statusValue" style="font-weight: 700;">--</span>
                            </div>
                        </div>

                        <button class="tc-btn entrada" id="btnEntrada" disabled>
                            <i class="bi bi-box-arrow-in-right"></i> ENTRADA
                        </button>
                        <button class="tc-btn saida" id="btnSaida" disabled>
                            <i class="bi bi-box-arrow-right"></i> SAÍDA
                        </button>

                        <?php if (!empty($recent_logs)): ?>
                            <h4 style="font-size: 0.9rem; font-weight: 700; margin: 1.5rem 0 0.75rem;"><i class="bi bi-clock-history"></i> Últimas Batidas</h4>
                            <?php foreach ($recent_logs as $log): ?>
                                <div class="tc-log">
                                    <strong><?php echo ucfirst($log['tipo_evento']); ?></strong>
                                    <span style="color: var(--text-muted);"> — <?php echo date('d/m/Y H:i', strtotime($log['criado_em'])); ?></span>
                                    <span style="margin-left: 0.5rem;">
                                        <?php if ($log['dentro_raio']): ?>
                                            <span class="badge badge-success">Dentro do raio</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Fora do raio</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="js/app-2026.js"></script>
    <script>
    const EMPLOYEE_ID = <?php echo $employee['id']; ?>;
    const OFFICE_LAT = <?php echo $employee['latitude_escritorio'] ?? '-8.8383'; ?>;
    const OFFICE_LON = <?php echo $employee['longitude_escritorio'] ?? '13.2344'; ?>;
    const ALLOWED_RADIUS = <?php echo $employee['raio_permitido'] ?? '500'; ?>;

    let map, userMarker, radiusCircle, currentLat, currentLon;

    function initMap() {
        map = L.map('map').setView([OFFICE_LAT, OFFICE_LON], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        L.marker([OFFICE_LAT, OFFICE_LON]).addTo(map).bindPopup('Local de Trabalho');
        radiusCircle = L.circle([OFFICE_LAT, OFFICE_LON], {
            radius: ALLOWED_RADIUS, color: '#3b82f6', fillColor: '#3b82f6', fillOpacity: 0.1, dashArray: '5,5'
        }).addTo(map);
    }

    function startGPS() {
        if (!navigator.geolocation) return;
        navigator.geolocation.watchPosition(pos => {
            currentLat = pos.coords.latitude;
            currentLon = pos.coords.longitude;
            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.marker([currentLat, currentLon]).addTo(map).bindPopup('Sua Localização');
            map.setView([currentLat, currentLon], 16);
            const dist = haversine(currentLat, currentLon, OFFICE_LAT, OFFICE_LON);
            const ok = dist <= ALLOWED_RADIUS;
            document.getElementById('distanceInfo').style.display = 'block';
            document.getElementById('distanceValue').textContent = dist;
            document.getElementById('radiusValue').textContent = ALLOWED_RADIUS;
            const sv = document.getElementById('statusValue');
            sv.textContent = ok ? '✓ Dentro do raio' : '✗ Fora do raio';
            sv.style.color = ok ? 'var(--success)' : 'var(--danger)';
            document.getElementById('btnEntrada').disabled = !ok;
            document.getElementById('btnSaida').disabled = !ok;
            const gs = document.getElementById('gpsStatus');
            gs.innerHTML = '<i class="bi bi-geo-alt"></i> GPS Ativo (' + pos.coords.accuracy.toFixed(0) + 'm)';
            gs.className = 'tc-gps active';
        }, err => {
            const gs = document.getElementById('gpsStatus');
            gs.innerHTML = '<i class="bi bi-x-circle"></i> Erro: ' + err.message;
            gs.className = 'tc-gps inactive';
        }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
    }

    function haversine(lat1, lon1, lat2, lon2) {
        const R = 6371000, toRad = x => x * Math.PI / 180;
        const dLat = toRad(lat2 - lat1), dLon = toRad(lon2 - lon1);
        const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2)**2;
        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
    }

    async function baterPonto(tipo) {
        if (!currentLat || !currentLon) { alert('GPS não disponível.'); return; }
        const r = await fetch('/api/timeclock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ funcionario_id: EMPLOYEE_ID, tipo_evento: tipo, latitude: currentLat, longitude: currentLon })
        });
        const result = await r.json();
        if (result.success) { location.reload(); } else { alert('Erro: ' + result.message); }
    }

    document.getElementById('btnEntrada')?.addEventListener('click', () => baterPonto('entrada'));
    document.getElementById('btnSaida')?.addEventListener('click', () => baterPonto('saida'));

    window.addEventListener('load', () => { initMap(); startGPS(); });
    </script>
</body>
</html>
