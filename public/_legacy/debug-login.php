<?php
/**
 * DEBUG LOGIN - Ver por que não está a entrar
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
    echo "║                      DEBUG LOGIN                                       ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
    
    // 1. Ver se utilizador existe
    echo "1️⃣  Procurando utilizador 'josemar_quarenta'...\n\n";
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute(['josemar_quarenta']);
    
    if ($stmt->rowCount() === 0) {
        echo "   ❌ Utilizador NÃO existe!\n";
    } else {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✅ Utilizador encontrado!\n\n";
        
        echo "   Dados:\n";
        foreach ($user as $key => $value) {
            if ($key === 'password_hash') {
                echo "   • $key: [HASH]\n";
            } else {
                echo "   • $key: $value\n";
            }
        }
    }
    
    // 2. Testar hash de password
    echo "\n2️⃣  Testando password...\n\n";
    
    $test_password = 'admin1123';
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $is_valid = password_verify($test_password, $user['password_hash']);
        
        if ($is_valid) {
            echo "   ✅ Password CORRETA!\n";
        } else {
            echo "   ❌ Password INCORRETA!\n";
            echo "   Hash armazenado: " . substr($user['password_hash'], 0, 20) . "...\n";
            
            // Tentar com diferentes hashes
            echo "\n   Gerando novo hash para testar...\n";
            $new_hash = password_hash($test_password, PASSWORD_BCRYPT);
            echo "   Novo hash: " . substr($new_hash, 0, 20) . "...\n";
            
            if (password_verify($test_password, $new_hash)) {
                echo "   ✅ Novo hash seria válido\n";
            }
        }
    }
    
    // 3. Ver estrutura do login
    echo "\n3️⃣  Verificando arquivo de login...\n\n";
    
    $login_file = __DIR__ . '/login.php';
    if (file_exists($login_file)) {
        $content = file_get_contents($login_file);
        
        if (strpos($content, 'password_verify') !== false) {
            echo "   ✅ Login usa password_verify() (correcto)\n";
        } else {
            echo "   ⚠️  Login pode não estar usando password_verify()\n";
        }
        
        if (strpos($content, 'tipo_acesso') !== false || strpos($content, 'tipo_usuario') !== false) {
            echo "   ✅ Login valida tipo de utilizador\n";
        } else {
            echo "   ⚠️  Login pode não validar tipo\n";
        }
    }
    
    // 4. Sugerir solução
    echo "\n4️⃣  SOLUÇÃO:\n\n";
    
    if ($stmt->rowCount() > 0 && !$is_valid) {
        echo "   Problema: Hash de password está inválido\n\n";
        echo "   Solução: Actualizar hash...\n\n";
        
        $new_hash = password_hash($test_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE username = ?");
        $stmt->execute([$new_hash, 'josemar_quarenta']);
        
        echo "   ✅ Password actualizada!\n";
        echo "   Novo hash: " . substr($new_hash, 0, 30) . "...\n";
    } elseif ($stmt->rowCount() === 0) {
        echo "   Problema: Utilizador não existe\n\n";
        echo "   Solução: Criando novo utilizador...\n\n";
        
        $password_hash = password_hash('admin1123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios 
            (username, password_hash, tipo_acesso, ativo, created_at, updated_at)
            VALUES (?, ?, 'super_admin', 1, NOW(), NOW())
        ");
        
        $stmt->execute(['josemar_quarenta', $password_hash]);
        echo "   ✅ Utilizador criado!\n";
    }
    
    // 5. Teste final
    echo "\n5️⃣  TESTE FINAL:\n\n";
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM usuarios WHERE username = ?");
    $stmt->execute(['josemar_quarenta']);
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $is_valid = password_verify('admin1123', $user['password_hash']);
        
        if ($is_valid) {
            echo "   ✅ PRONTO PARA LOGIN!\n";
            echo "   Username: josemar_quarenta\n";
            echo "   Password: admin1123\n";
            echo "   Status: Ativo\n\n";
            echo "   Teste em: http://localhost:8080/login.php\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
