<?php
/**
 * Script de Migração: JSON → MySQL
 * 
 * Migra os dados do sistema temporário JSON para MySQL profissional
 * 
 * Uso: php scripts/migrate_json_to_mysql.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

echo "═══════════════════════════════════════════════════════════\n";
echo "  MIGRAÇÃO: JSON → MySQL\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Verificar se MySQL está configurado
$config_file = __DIR__ . '/../src/Database/Database.php';
$config_content = file_get_contents($config_file);

if (strpos($config_content, 'sqlite') !== false || strpos($config_content, 'json') !== false) {
    echo "❌ ERRO: Database.php ainda não está configurado para MySQL!\n";
    echo "\n📝 Para migrar, você precisa primeiro:\n";
    echo "1. Instalar XAMPP\n";
    echo "2. Criar o banco farmacia_valodia_rg\n";
    echo "3. Atualizar src/Database/Database.php para usar MySQL\n\n";
    exit(1);
}

// Caminhos dos arquivos JSON
$json_dir = __DIR__ . '/../database/json/';
$json_files = [
    'usuarios' => $json_dir . 'usuarios.json',
    'funcionarios' => $json_dir . 'funcionarios.json'
];

// Verificar se arquivos JSON existem
foreach ($json_files as $table => $file) {
    if (!file_exists($file)) {
        echo "⚠️  Arquivo não encontrado: $file\n";
        echo "   Pulando tabela: $table\n\n";
        unset($json_files[$table]);
    }
}

if (empty($json_files)) {
    echo "❌ Nenhum arquivo JSON encontrado para migrar!\n";
    exit(1);
}

try {
    // Conectar ao MySQL
    echo "🔌 Conectando ao MySQL...\n";
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "✅ Conexão estabelecida!\n\n";

    // Desabilitar checks temporariamente
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $total_migrated = 0;

    // Migrar cada tabela
    foreach ($json_files as $table => $file) {
        echo "📄 Migrando tabela: $table\n";

        // Ler JSON
        $json = file_get_contents($file);
        $data = json_decode($json, true);

        if (!$data || !is_array($data)) {
            echo "   ⚠️  JSON inválido, pulando...\n\n";
            continue;
        }

        $count = 0;

        foreach ($data as $record) {
            try {
                // Preparar campos e valores
                $fields = array_keys($record);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = sprintf(
                    "INSERT INTO %s (%s) VALUES (%s)",
                    $table,
                    implode(', ', $fields),
                    implode(', ', $placeholders)
                );

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($record));
                $count++;

            }
            catch (PDOException $e) {
                // Ignorar duplicatas
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "   ⚠️  Erro ao inserir registro: " . $e->getMessage() . "\n";
                }
            }
        }

        echo "   ✅ $count registros migrados\n\n";
        $total_migrated += $count;
    }

    // Reabilitar checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "═══════════════════════════════════════════════════════════\n";
    echo "  ✅ MIGRAÇÃO CONCLUÍDA!\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Total de registros migrados: $total_migrated\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    // Estatísticas
    echo "📊 Estatísticas do banco:\n\n";

    $tables = ['departamentos', 'cargos', 'usuarios', 'funcionarios'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "   - $table: $count registros\n";
    }

    echo "\n";
    echo "🎉 Sistema agora está rodando em MySQL!\n";
    echo "🌐 Acesse: http://localhost:8080/login.php\n\n";


}
catch (PDOException $e) {
    echo "\n═══════════════════════════════════════════════════════════\n";
    echo "  ❌ ERRO DE CONEXÃO\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Mensagem: " . $e->getMessage() . "\n\n";
    echo "Verifique:\n";
    echo "1. XAMPP MySQL está rodando?\n";
    echo "2. Banco 'farmacia_valodia_rg' foi criado?\n";
    echo "3. Usuário 'rg_admin' tem permissões?\n";
    echo "4. Database.php está configurado corretamente?\n\n";
    exit(1);
}
