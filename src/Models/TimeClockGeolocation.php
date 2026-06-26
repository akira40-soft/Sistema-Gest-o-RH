<?php
/**
 * PHASE 3 - ANGOLA CONTEXT + GEOLOCATION TIMECLOCK
 * 
 * Melhorias para contexto angolano:
 * 1. Carteira Profissional (número único validado)
 * 2. Time Clock com GPS (confirmação de presença)
 * 3. Validação de raio geográfico
 * 4. Conformidade com regulamentações angolanas
 * 
 * Referências de sistemas conhecidos:
 * - SAP SuccessFactors: GPS + Face recognition
 * - Workday: Geofence + Time tracking
 * - ADP Workforce: Location verification
 * - BambooHR: Time tracking com localização
 */

namespace App\Models;

class TimeClockGeolocation {
    
    /**
     * Angola Carteira Profissional validation
     * Formato: XXXXXXXXXX (10 dígitos)
     * Exemplo: 0001234567
     */
    public static function validateCarteiraAngolana($numero) {
        // Remove caracteres não numéricos
        $numero = preg_replace('/\D/', '', $numero);
        
        // Carteira profissional deve ter exatamente 10 dígitos
        if (strlen($numero) !== 10) {
            return ['valid' => false, 'error' => 'Carteira profissional deve ter 10 dígitos'];
        }
        
        // Verifica se não começa com zeros
        if (preg_match('/^0{10}$/', $numero)) {
            return ['valid' => false, 'error' => 'Carteira profissional inválida'];
        }
        
        // Formato válido
        return ['valid' => true, 'formatted' => $numero];
    }
    
    /**
     * Calcula distância entre duas coordenadas (Haversine)
     * @param lat1, lon1 - Localização do funcionário
     * @param lat2, lon2 - Localização do escritório
     * @return distance em metros
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // metros
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earth_radius * $c;
        
        return round($distance, 2);
    }
    
    /**
     * Valida se funcionário está dentro do raio permitido
     * Padrão: 500m para escritório, 2km para funcionários em campo
     */
    public static function isWithinAllowedRadius($userLat, $userLon, $officeLat, $officeLon, $employeeType = 'escritorio') {
        $distance = self::calculateDistance($userLat, $userLon, $officeLat, $officeLon);
        
        // Raios permitidos em metros
        $allowedRadius = [
            'escritorio' => 500,      // 500m para escritório
            'campo' => 2000,           // 2km para funcionários em campo
            'teletrabalho' => 999999   // Sem limite (marcado como home office)
        ];
        
        $radius = $allowedRadius[$employeeType] ?? 500;
        
        return [
            'within_radius' => $distance <= $radius,
            'distance' => $distance,
            'allowed_radius' => $radius,
            'status' => $distance <= $radius ? 'ACEITO' : 'REJEITADO - Fora do raio'
        ];
    }
    
    /**
     * Log de tentativa de bater ponto
     */
    public static function logTimeclockAttempt($db, $employeeId, $latitude, $longitude, $status, $reason = null) {
        $stmt = $db->prepare("
            INSERT INTO timeclock_attempts 
            (funcionario_id, latitude, longitude, status, reason, tentativa_em) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([$employeeId, $latitude, $longitude, $status, $reason]);
    }
}

/**
 * Melhores Práticas - Referências de Mercado:
 * 
 * 1. APPLE HR SYSTEMS (Como Implementam):
 *    - Face recognition + GPS
 *    - Múltiplas fotos durante o dia
 *    - AI detection de spoofing
 * 
 * 2. GOOGLE/FACEBOOK:
 *    - Geofence com buffer de 100-500m
 *    - IP tracking adicional (VPN detection)
 *    - WiFi network verification
 *    - Device fingerprinting
 * 
 * 3. EMPRESAS DE SEGURANÇA (Angola):
 *    - Confirmação via app + foto selfie
 *    - GPS + WiFi triangulation
 *    - Validação de sinal de rede
 *    - Timestamp sincronizado (NTP)
 * 
 * 4. REGULAMENTAÇÕES ANGOLANAS:
 *    - Lei 7/15 sobre Contrato Individual de Trabalho
 *    - Art. 90: Direito do empregador a controlar presença
 *    - Necessidade de avisar funcionários sobre rastreamento
 *    - Dados devem ser confidenciais
 */
?>
