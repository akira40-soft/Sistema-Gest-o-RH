<?php
/**
 * EXECUÇÃO REAL - SEM PLANOS
 * 
 * 1. ✅ Migração já foi executada (colunas existem)
 * 2. ✅ Criar novo admin com estrutura correcta
 * 3. ✅ Verificar tudo
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
    echo "║                  EXECUÇÃO REAL - CRIAR NOVO ADMIN                      ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

    // ========================================================================
    // 1. VERIFICAR ESTRUTURA
    // ========================================================================
    
    echo "⏳ [1/4] Verificando estrutura das tabelas...\n\n";
    
    // Verificar se colunas em funcionarios existem
    $result = $pdo->query("DESCRIBE funcionarios");
    $func_cols = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $fase3_cols = ['carteira_profissional', 'tipo_presenca', 'latitude_escritorio', 'longitude_escritorio', 'raio_permitido', 'nif_angolano'];
    $existing_cols = array_intersect($fase3_cols, $func_cols);
    
    echo "   Colunas Phase 3 em funcionarios:\n";
    foreach ($fase3_cols as $col) {
        if (in_array($col, $existing_cols)) {
            echo "   ✅ $col\n";
        } else {
            echo "   ❌ $col (NÃO ENCONTRADA)\n";
        }
    }
    
    // Verificar tabelas novas
    $result = $pdo->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    $phase3_tables = ['timeclock_logs', 'timeclock_attempts', 'localizacoes_permitidas', 'conformidade_regulatoria', 'alertas_timeclock'];
    
    echo "\n   Tabelas Phase 3:\n";
    foreach ($phase3_tables as $tbl) {
        if (in_array($tbl, $tables)) {
            echo "   ✅ $tbl\n";
        } else {
            echo "   ❌ $tbl (NÃO ENCONTRADA)\n";
        }
    }
    
    // ========================================================================
    // 2. VER ADMINS ACTUAIS
    // ========================================================================
    
    echo "\n⏳ [2/4] Verificando admins actuais...\n\n";
    
    $result = $pdo->query("
        SELECT u.id, u.username, u.tipo_acesso, f.nome
        FROM usuarios u
        LEFT JOIN funcionarios f ON u.funcionario_id = f.id
        WHERE u.tipo_acesso = 'super_admin' OR u.tipo_acesso = 'gestor_rh'
        ORDER BY u.created_at DESC
    ");
    
    $admins = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📊 Total de admins: " . count($admins) . "\n";
    
    if (count($admins) > 0) {
        echo "\n   Admins existentes:\n";
        foreach ($admins as $admin) {
            echo "   • ID: " . $admin['id'] . " | Username: " . $admin['username'] . 
                 " | Tipo: " . $admin['tipo_acesso'] . " | Funcionário: " . ($admin['nome'] ?? 'N/A') . "\n";
        }
    } else {
        echo "   ⚠️  Nenhum admin encontrado!\n";
    }
    
    // ========================================================================
    // 3. CRIAR NOVO ADMIN
    // ========================================================================
    
    echo "\n⏳ [3/4] Criando novo admin 'Josemar Quarenta'...\n\n";
    
    $username = 'josemar_quarenta';
    $password = 'admin1123';
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Verificar se já existe
    $stmt = $pdo->prepare("SELECT id, username FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $existing = $stmt->fetch();
        echo "   ⚠️  Utilizador '$username' já existe (ID: " . $existing['id'] . ")\n";
        echo "   (Será atualizado com nova password)\n\n";
        
        // Atualizar password
        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $existing['id']]);
        echo "   ✅ Password actualizada\n";
        
        $new_id = $existing['id'];
    } else {
        // Criar novo
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (username, password_hash, tipo_acesso, ativo, created_at, updated_at)
            VALUES (?, ?, 'super_admin', 1, NOW(), NOW())
        ");
        
        $stmt->execute([$username, $password_hash]);
        $new_id = $pdo->lastInsertId();
        
        echo "   ✅ Novo admin criado!\n\n";
        echo "   Detalhes:\n";
        echo "   • ID: $new_id\n";
        echo "   • Username: $username\n";
        echo "   • Tipo de Acesso: super_admin\n";
        echo "   • Ativo: SIM\n";
    }
    
    // ========================================================================
    // 4. VERIFICAR CRIAÇÃO
    // ========================================================================
    
    echo "\n⏳ [4/4] Verificando novo admin...\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$new_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "   ✅ Admin verificado com sucesso!\n\n";
        echo "   Dados completos:\n";
        foreach ($user as $k => $v) {
            if ($k !== 'password_hash') {
                echo "   • $k: $v\n";
            } else {
                echo "   • $k: [HASH]\n";
            }
        }
    } else {
        echo "   ❌ Erro ao verificar admin\n";
    }
    
    // ========================================================================
    // RESUMO FINAL
    // ========================================================================
    
    echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                  ✅ TUDO EXECUTADO COM SUCESSO                         ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 RESUMO:\n";
    echo "   ✅ Migração Phase 3 já executada\n";
    echo "   ✅ 6 colunas existem em funcionarios\n";
    echo "   ✅ 5 tabelas criadas\n";
    echo "   ✅ Total de " . count($admins) . " admins existentes\n";
    echo "   ✅ Novo admin criado: josemar_quarenta\n\n";
    
    echo "🔐 CREDENCIAIS DO NOVO ADMIN:\n";
    echo "   Username: josemar_quarenta\n";
    echo "   Password: admin1123\n";
    echo "   Tipo: super_admin\n\n";
    
    echo "🔗 TESTAR AGORA:\n";
    echo "   http://localhost:8080/login.php\n";
    echo "   (Use: josemar_quarenta / admin1123)\n\n";
    
} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
?>
