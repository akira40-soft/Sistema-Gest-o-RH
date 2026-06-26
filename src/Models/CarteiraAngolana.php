<?php
/**
 * CARTEIRA PROFISSIONAL - UTILIDADE PRÁTICA NO SISTEMA
 * 
 * A carteira profissional é usada para:
 * 1. Identificação única do funcionário em Angola
 * 2. Validação de presença (time clock)
 * 3. Conformidade regulatória (Lei 7/15)
 * 4. Rastreamento de tentativas de bater ponto
 * 5. Relatórios de RH e folha de pagamento
 */

namespace App\Models;

use PDO;

class CarteiraAngolana {
    
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * 1. VALIDAR FORMATO DE CARTEIRA PROFISSIONAL
     * 
     * Formato legal em Angola:
     * - Exatamente 10 dígitos
     * - Não pode ser 0000000000
     * - Exemplo: 0001234567 (OK), 1234567890 (OK)
     */
    public function validarFormato($numero) {
        // Remove tudo que não é número
        $numero = preg_replace('/\D/', '', $numero);
        
        if (strlen($numero) !== 10) {
            return [
                'valido' => false,
                'erro' => 'Carteira deve ter exatamente 10 dígitos',
                'recebido' => $numero
            ];
        }
        
        if (preg_match('/^0{10}$/', $numero)) {
            return [
                'valido' => false,
                'erro' => 'Carteira 0000000000 é inválida',
                'recebido' => $numero
            ];
        }
        
        return ['valido' => true, 'numero' => $numero];
    }

    /**
     * 2. VERIFICAR SE CARTEIRA JÁ EXISTE NO SISTEMA
     */
    public function verificarDuplicata($carteira, $excluir_funcionario_id = null) {
        $query = "SELECT id, nome FROM funcionarios WHERE carteira_profissional = ?";
        $params = [$carteira];
        
        if ($excluir_funcionario_id) {
            $query .= " AND id != ?";
            $params[] = $excluir_funcionario_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'existe' => true,
                'funcionario_id' => $existing['id'],
                'nome' => $existing['nome']
            ];
        }
        
        return ['existe' => false];
    }

    /**
     * 3. REGISTAR CARTEIRA AO FUNCIONÁRIO
     * 
     * Utilidade prática:
     * - Funcionário PRECISA de carteira para bater ponto
     * - Sistema valida se tem carteira antes de permitir time clock
     * - Cada funcionário = 1 carteira única
     */
    public function registarCarteira($funcionario_id, $carteira, $tipo_presenca = 'escritorio') {
        // Validar formato
        $validacao = $this->validarFormato($carteira);
        if (!$validacao['valido']) {
            return $validacao;
        }

        // Verificar duplicata
        $duplicata = $this->verificarDuplicata($validacao['numero'], $funcionario_id);
        if ($duplicata['existe']) {
            return [
                'valido' => false,
                'erro' => 'Carteira já registada para ' . $duplicata['nome'],
                'existente_em' => $duplicata['funcionario_id']
            ];
        }

        // Atualizar funcionário
        try {
            $stmt = $this->db->prepare("
                UPDATE funcionarios 
                SET carteira_profissional = ?, tipo_presenca = ?, atualizado_em = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$validacao['numero'], $tipo_presenca, $funcionario_id]);
            
            return [
                'valido' => true,
                'mensagem' => 'Carteira registada com sucesso',
                'carteira' => $validacao['numero'],
                'tipo_presenca' => $tipo_presenca
            ];
        } catch (\Exception $e) {
            return [
                'valido' => false,
                'erro' => 'Erro ao registar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 4. UTILIDADE: PERMITIR BATER PONTO APENAS COM CARTEIRA
     * 
     * Esta função bloqueia time clock se funcionário não tiver carteira
     */
    public function podeBarPonto($funcionario_id) {
        $stmt = $this->db->prepare("
            SELECT 
                id, 
                nome, 
                carteira_profissional,
                tipo_presenca,
                latitude_escritorio,
                longitude_escritorio,
                raio_permitido
            FROM funcionarios 
            WHERE id = ?
        ");
        
        $stmt->execute([$funcionario_id]);
        
        if ($stmt->rowCount() === 0) {
            return [
                'pode_bater' => false,
                'erro' => 'Funcionário não encontrado'
            ];
        }

        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$funcionario['carteira_profissional']) {
            return [
                'pode_bater' => false,
                'erro' => 'Funcionário não tem carteira profissional registada',
                'acao' => 'Admin deve registar carteira antes'
            ];
        }

        if (!$funcionario['latitude_escritorio'] || !$funcionario['longitude_escritorio']) {
            return [
                'pode_bater' => false,
                'erro' => 'Localização do escritório não configurada',
                'acao' => 'Admin deve configurar localização'
            ];
        }

        return [
            'pode_bater' => true,
            'funcionario' => [
                'id' => $funcionario['id'],
                'nome' => $funcionario['nome'],
                'carteira' => $funcionario['carteira_profissional'],
                'tipo_presenca' => $funcionario['tipo_presenca'],
                'latitude' => $funcionario['latitude_escritorio'],
                'longitude' => $funcionario['longitude_escritorio'],
                'raio' => $funcionario['raio_permitido']
            ]
        ];
    }

    /**
     * 5. UTILIDADE: GERAR RELATÓRIO DE CARTEIRAS
     * 
     * Para admin ver:
     * - Quem tem carteira
     * - Quem não tem
     * - Tentativas de bater ponto por carteira
     */
    public function relatoriCarteiras() {
        $stmt = $this->db->query("
            SELECT 
                f.id,
                f.nome,
                f.carteira_profissional,
                f.tipo_presenca,
                COUNT(tl.id) as total_batidas,
                COUNT(CASE WHEN tl.dentro_raio = 1 THEN 1 END) as batidas_validas,
                COUNT(CASE WHEN tl.dentro_raio = 0 THEN 1 END) as batidas_rejeitadas,
                MAX(tl.criado_em) as ultima_batida
            FROM funcionarios f
            LEFT JOIN timeclock_logs tl ON f.id = tl.funcionario_id
            GROUP BY f.id
            ORDER BY f.nome
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 6. UTILIDADE: ALERTAR QUANDO FUNCIONÁRIO TENTA BATER PONTO SEM CARTEIRA
     */
    public function registarTentativaInvalida($funcionario_id, $motivo) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO timeclock_attempts 
                (funcionario_id, status, reason) 
                VALUES (?, 'REJEITADO', ?)
            ");
            
            $stmt->execute([$funcionario_id, $motivo]);

            // Registar alerta
            $this->db->prepare("
                INSERT INTO alertas_timeclock 
                (funcionario_id, tipo_alerta, descricao, severidade) 
                VALUES (?, 'sem_carteira', ?, 'alta')
            ")->execute([$funcionario_id, 'Tentativa de bater ponto sem carteira registada']);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 7. UTILIDADE: INTEGRAÇÃO COM FOLHA DE PAGAMENTO
     * 
     * Função auxiliar: obter batidas válidas para calcular folha
     */
    public function obterBatidasParaFolha($funcionario_id, $mes, $ano) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(criado_em) as data,
                tipo_evento,
                COUNT(*) as total,
                SUM(dentro_raio) as validas
            FROM timeclock_logs
            WHERE funcionario_id = ?
            AND MONTH(criado_em) = ?
            AND YEAR(criado_em) = ?
            GROUP BY DATE(criado_em), tipo_evento
            ORDER BY DATE(criado_em)
        ");

        $stmt->execute([$funcionario_id, $mes, $ano]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 8. CONFORMIDADE LEI 7/15 - CARTEIRA OBRIGATÓRIA
     */
    public function confinarComCarteira($funcionario_id) {
        // Verificar se tem carteira
        $stmt = $this->db->prepare("
            SELECT carteira_profissional FROM funcionarios WHERE id = ?
        ");
        $stmt->execute([$funcionario_id]);
        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$funcionario['carteira_profissional']) {
            return [
                'conforme' => false,
                'erro' => 'Funcionário não tem carteira profissional - não pode rastrear'
            ];
        }

        // Registar conformidade
        try {
            $stmt = $this->db->prepare("
                INSERT INTO conformidade_regulatoria 
                (funcionario_id, lei_ref, consentimento_rastreamento, data_consentimento, status)
                VALUES (?, 'Lei 7/15', 1, NOW(), 'aceito')
                ON DUPLICATE KEY UPDATE 
                    consentimento_rastreamento = 1, 
                    data_consentimento = NOW(),
                    status = 'aceito'
            ");
            
            $stmt->execute([$funcionario_id]);

            return [
                'conforme' => true,
                'mensagem' => 'Carteira validada para Lei 7/15'
            ];
        } catch (\Exception $e) {
            return [
                'conforme' => false,
                'erro' => $e->getMessage()
            ];
        }
    }
}
?>
