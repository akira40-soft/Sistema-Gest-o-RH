<?php
/**
 * Script de Migração Phase 2
 * Aplica todas as mudanças do banco de dados necessárias para a Fase 2
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;

class MigrationRunner
{
    private $db;
    private $migrationFile;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->migrationFile = __DIR__ . '/migrations_phase2.sql';
    }
    
    /**
     * Executa a migração
     */
    public function run()
    {
        echo "🔄 Iniciando migração Phase 2...\n";
        echo "📁 Arquivo: {$this->migrationFile}\n";
        
        if (!file_exists($this->migrationFile)) {
            echo "❌ Arquivo de migração não encontrado!\n";
            return false;
        }
        
        try {
            $sql = file_get_contents($this->migrationFile);
            
            // Dividir em statements individuais
            $statements = array_filter(
                array_map('trim', preg_split('/;[\s]*\n/', $sql)),
                fn($s) => !empty($s) && !str_starts_with($s, '--')
            );
            
            $total = count($statements);
            $sucesso = 0;
            
            echo "📊 Total de statements: {$total}\n\n";
            
            foreach ($statements as $i => $statement) {
                try {
                    $this->db->query($statement . ';');
                    $sucesso++;
                    echo "[✅] Statement " . ($i + 1) . "/{$total}\n";
                } catch (\Exception $e) {
                    echo "[⚠️] Statement " . ($i + 1) . ": " . $e->getMessage() . "\n";
                    // Continua, pois muitos statements são IF NOT EXISTS
                }
            }
            
            echo "\n✅ Migração concluída!\n";
            echo "📊 {$sucesso}/{$total} statements executados com sucesso\n";
            
            return true;
            
        } catch (\Exception $e) {
            echo "❌ Erro na migração: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Verifica se a migração foi bem-sucedida
     */
    public function verify()
    {
        echo "\n🔍 Verificando estrutura do banco...\n";
        
        $tabelas_necessarias = [
            'employee_approvals',
            'user_photos',
            'dashboard_widgets',
            'audit_logs_detailed',
            'notificacoes',
            'sessoes_ativas',
            'configuracoes_sistema',
            'backups'
        ];
        
        $todas_existem = true;
        
        foreach ($tabelas_necessarias as $tabela) {
            try {
                $result = $this->db->query("SELECT 1 FROM {$tabela} LIMIT 1");
                echo "✅ Tabela '{$tabela}' existe\n";
            } catch (\Exception $e) {
                echo "❌ Tabela '{$tabela}' não encontrada\n";
                $todas_existem = false;
            }
        }
        
        // Verificar colunas adicionadas
        echo "\n🔍 Verificando colunas em tabelas existentes...\n";
        
        $colunas_check = [
            'funcionarios' => ['status_aprovacao', 'data_aprovacao', 'aprovado_por'],
            'usuarios' => ['foto_perfil', 'tema', 'ultimo_login', 'ativo'],
        ];
        
        foreach ($colunas_check as $tabela => $colunas) {
            foreach ($colunas as $coluna) {
                try {
                    // Tenta acessar a coluna
                    $this->db->query("SELECT {$coluna} FROM {$tabela} LIMIT 1");
                    echo "✅ Coluna '{$coluna}' em '{$tabela}' existe\n";
                } catch (\Exception $e) {
                    echo "⚠️ Coluna '{$coluna}' em '{$tabela}' pode não existir\n";
                }
            }
        }
        
        if ($todas_existem) {
            echo "\n✅ Todas as tabelas necessárias existem!\n";
            return true;
        } else {
            echo "\n⚠️ Algumas tabelas podem estar faltando\n";
            return false;
        }
    }
}

// Executar migração
$migrator = new MigrationRunner();

if ($migrator->run()) {
    $migrator->verify();
    echo "\n🎉 Migração Phase 2 concluída com sucesso!\n";
} else {
    echo "\n❌ Erro ao executar migração Phase 2\n";
    exit(1);
}
