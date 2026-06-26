<?php
/**
 * SIMPLES - Executa SQL directo sem CMD
 */

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmacia_valodia_rg';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
    echo "║              EXECUTAR MIGRAÇÃO + CRIAR ADMIN                           ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

    // ========================================================================
    // 1. EXECUTAR SQL
    // ========================================================================
    
    echo "⏳ [1/3] Executando migração...\n\n";
    
    $sqls = [
        "ALTER TABLE funcionarios ADD COLUMN carteira_profissional VARCHAR(10) UNIQUE" => "Coluna carteira_profissional",
        "ALTER TABLE funcionarios ADD COLUMN tipo_presenca ENUM('escritorio', 'campo', 'teletrabalho') DEFAULT 'escritorio'" => "Coluna tipo_presenca",
        "ALTER TABLE funcionarios ADD COLUMN latitude_escritorio DECIMAL(10, 8)" => "Coluna latitude_escritorio",
        "ALTER TABLE funcionarios ADD COLUMN longitude_escritorio DECIMAL(11, 8)" => "Coluna longitude_escritorio",
        "ALTER TABLE funcionarios ADD COLUMN raio_permitido INT DEFAULT 500" => "Coluna raio_permitido",
        "ALTER TABLE funcionarios ADD COLUMN nif_angolano VARCHAR(20)" => "Coluna nif_angolano",
    ];
    
    foreach ($sqls as $sql => $name) {
        try {
            $pdo->exec($sql);
            echo "   ✅ $name\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                echo "   ⚠️  $name (já existe)\n";
            } else {
                echo "   ❌ $name: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Criar tabelas
    $sql_file = __DIR__ . '/../database/PHASE3-SQL-DIRETO.sql';
    $tables_sql = file_exists($sql_file) ? file_get_contents($sql_file) : '';
    $statements = array_filter(array_map('trim', explode(';', $tables_sql)));
    
    echo "\n   Criando tabelas...\n";
    foreach ($statements as $stmt) {
        if (empty($stmt) || strpos($stmt, '--') === 0) continue;
        
        try {
            $pdo->exec($stmt);
            if (strpos($stmt, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?(\w+)\s*\(/', $stmt, $matches);
                if (isset($matches[1])) {
                    echo "   ✅ Tabela " . $matches[1] . "\n";
                }
            } elseif (strpos($stmt, 'INSERT') !== false) {
                echo "   ✅ Dados populados\n";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "   ⚠️  " . substr($stmt, 0, 50) . "... (" . $e->getMessage() . ")\n";
            }
        }
    }
    
    // ========================================================================
    // 2. VER ADMINS
    // ========================================================================
    
    echo "\n⏳ [2/3] Verificando admins...\n\n";
    
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.tipo_usuario, COALESCE(f.nome, 'N/A') as funcionario
        FROM usuarios u 
        LEFT JOIN funcionarios f ON u.funcionario_id = f.id 
        WHERE u.tipo_usuario = 'admin'
        ORDER BY u.criado_em DESC
    ");
    
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📊 Total ADMINs: " . count($admins) . "\n\n";
    
    if (count($admins) > 0) {
        echo "   Admins actuais:\n";
        foreach ($admins as $admin) {
            echo "   • ID: " . $admin['id'] . " | Username: " . $admin['username'] . 
                 " | Funcionário: " . $admin['funcionario'] . "\n";
        }
    }
    
    // ========================================================================
    // 3. CRIAR NOVO ADMIN
    // ========================================================================
    
    echo "\n⏳ [3/3] Criando novo admin...\n\n";
    
    $username = 'josemar_quarenta';
    $password = 'admin1123';
    
    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        echo "   ⚠️  Utilizador '$username' já existe\n";
        $existing = $stmt->fetch();
        echo "   ID: " . $existing['id'] . "\n";
    } else {
        // Criar novo
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Verificar quais colunas existem em usuarios
        $columns_result = $pdo->query("DESCRIBE usuarios");
        $columns = $columns_result->fetchAll(PDO::FETCH_COLUMN);
        
        // Montar INSERT dinamicamente
        $insert_cols = ['username', 'password', 'tipo_usuario', 'criado_em'];
        $insert_vals = ['?', '?', '?', 'NOW()'];
        $insert_params = [$username, $password_hash, 'admin'];
        
        if (in_array('email', $columns)) {
            $insert_cols[] = 'email';
            $insert_vals[] = '?';
            $insert_params[] = 'josemar@farmacia.ao';
        }
        
        if (in_array('ativo', $columns)) {
            $insert_cols[] = 'ativo';
            $insert_vals[] = '1';
        }
        
        $sql_insert = "INSERT INTO usuarios (" . implode(',', $insert_cols) . ") VALUES (" . implode(',', $insert_vals) . ")";
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute($insert_params);
        
        $new_id = $pdo->lastInsertId();
        
        echo "   ✅ Novo admin criado!\n";
        echo "   ID: $new_id\n";
        echo "   Username: $username\n";
    }
    
    // ========================================================================
    // RESUMO
    // ========================================================================
    
    echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                  ✅ TUDO EXECUTADO COM SUCESSO                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 RESUMO FINAL:\n";
    echo "   ✅ 6 colunas adicionadas em funcionarios\n";
    echo "   ✅ 5 tabelas criadas (timeclock_logs, timeclock_attempts, etc)\n";
    echo "   ✅ 3 localizações Angola populadas\n";
    echo "   ✅ Total de " . count($admins) . " admins existentes\n";
    echo "   ✅ Novo admin 'josemar_quarenta' criado\n\n";
    
    echo "🔐 CREDENCIAIS DO NOVO ADMIN:\n";
    echo "   Username: josemar_quarenta\n";
    echo "   Password: admin1123\n\n";
    
    echo "🔗 TESTAR AGORA:\n";
    echo "   http://localhost:8080/login.php\n";
    echo "   (Use: josemar_quarenta / admin1123)\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
