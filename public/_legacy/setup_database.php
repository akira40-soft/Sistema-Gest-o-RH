<?php
/**
 * Script de Execução do Schema SQL (VERSÃO SQLITE)
 * Executa o arquivo schema_sqlite.sql usando PDO
 * 
 * VANTAGENS DO SQLITE:
 * ✅ Não precisa de servidor MySQL rodando
 * ✅ Banco de dados em arquivo (portátil)
 * ✅ Zero configuração
 * ✅ Ideal para desenvolvimento
 * 
 * Como executar:
 * 1. Acesse via navegador: http://localhost:8080/setup_database.php
 * 2. Ou execute via CLI: php setup_database.php
 */

try {
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  SETUP DO BANCO DE DADOS - SQLite\n";
    echo "═══════════════════════════════════════════════════════════\n\n";

    // Definir caminho do banco SQLite
    $db_path = __DIR__ . '/../database/farmacia_valodia_rg.db';
    $db_dir = dirname($db_path);

    // Criar diretório se não existir
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
        echo "📁 Diretório 'database' criado\n";
    }

    // Conectar ao SQLite
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Conectado ao SQLite com sucesso!\n";
    echo "📍 Arquivo: $db_path\n\n";

    // Ler o arquivo SQL
    $sqlFile = __DIR__ . '/../database/schema_sqlite.sql';

    if (!file_exists($sqlFile)) {
        die("❌ Erro: Arquivo schema_sqlite.sql não encontrado\n");
    }

    $sql = file_get_contents($sqlFile);

    echo "📄 Lendo schema_sqlite.sql...\n";
    echo "📊 Tamanho: " . strlen($sql) . " bytes\n\n";

    // Dividir em comandos individuais
    $commands = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($cmd) {
        $cmd = trim($cmd);
        return !empty($cmd) &&
        !preg_match('/^--/', $cmd) &&
        $cmd !== 'SELECT \'Banco de dados SQLite criado com sucesso!\' AS mensagem';
    }
    );

    echo "🔧 Executando " . count($commands) . " comandos SQL...\n\n";

    $successCount = 0;
    $errorCount = 0;

    foreach ($commands as $index => $command) {
        try {
            $pdo->exec($command);
            $successCount++;

            if ($successCount % 5 === 0) {
                echo "  ✓ $successCount comandos executados...\n";
            }
        }
        catch (PDOException $e) {
            // Ignorar erros de "table already exists"
            if (strpos($e->getMessage(), 'already exists') === false) {
                $errorCount++;
                echo "  ⚠️ Aviso: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  RESUMO DA EXECUÇÃO\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  ✅ Comandos bem-sucedidos: $successCount\n";

    if ($errorCount > 0) {
        echo "  ⚠️ Avisos: $errorCount\n";
    }

    echo "═══════════════════════════════════════════════════════════\n\n";

    // Verificar tabelas criadas
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

    echo "✅✅✅ BANCO DE DADOS CRIADO COM SUCESSO! ✅✅✅\n\n";

    echo "📋 Tabelas criadas (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "   - $table\n";
    }

    echo "\n";

    // Mostrar contagem de dados seed
    echo "📊 Dados iniciais (seed):\n";
    foreach (['departamentos', 'cargos', 'usuarios', 'funcionarios'] as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "   - $table: $count registros\n";
    }

    echo "\n";
    echo "🎉 SISTEMA PRONTO PARA USO! 🎉\n";
    echo "\n";
    echo "👤 Usuários de teste criados:\n";
    echo "   - Username: admin          | Senha: senha123 | Tipo: Administrador\n";
    echo "   - Username: isaac.quarenta | Senha: senha123 | Tipo: Administrador\n";
    echo "   - Username: ilda.livenia   | Senha: senha123 | Tipo: Geral\n";
    echo "   - Username: jardel.banoyo  | Senha: senha123 | Tipo: Geral\n";
    echo "\n";
    echo "🌐 Acesse: http://localhost:8080/login.php\n";
    echo "💾 Banco: $db_path\n";
    echo "\n";

    // Tamanho do arquivo
    $fileSize = filesize($db_path);
    echo "📦 Tamanho do banco: " . number_format($fileSize / 1024, 2) . " KB\n";
    echo "\n";


}
catch (PDOException $e) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  ❌ ERRO\n";
    echo "═══════════════════════════════════════════════════════════\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "═══════════════════════════════════════════════════════════\n";
}

echo "\n";
?>
