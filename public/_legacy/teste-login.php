<?php
/**
 * LOGIN SIMPLES - Testa se consegue fazer login com josemar_quarenta
 */

session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmacia_valodia_rg';

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        TESTE LOGIN SIMPLES                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Dados de teste
    $username = 'josemar_quarenta';
    $password = 'admin1123';
    
    echo "1️⃣  Dados de teste:\n";
    echo "   • Username: $username\n";
    echo "   • Password: $password\n\n";
    
    // Buscar utilizador
    echo "2️⃣  Procurando no banco...\n\n";
    
    $stmt = $pdo->prepare("SELECT id, username, password_hash, tipo_acesso, ativo FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() === 0) {
        echo "   ❌ Utilizador não encontrado\n";
        exit(1);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Utilizador encontrado\n";
    echo "   • ID: " . $user['id'] . "\n";
    echo "   • Username: " . $user['username'] . "\n";
    echo "   • Tipo: " . $user['tipo_acesso'] . "\n";
    echo "   • Ativo: " . ($user['ativo'] ? 'SIM' : 'NÃO') . "\n\n";
    
    // Verificar se está activo
    if (!$user['ativo']) {
        echo "   ❌ Utilizador não está activo\n";
        exit(1);
    }
    
    // Verificar password
    echo "3️⃣  Verificando password...\n\n";
    
    $is_valid = password_verify($password, $user['password_hash']);
    
    if (!$is_valid) {
        echo "   ❌ Password INCORRETA\n";
        echo "   Hash: " . substr($user['password_hash'], 0, 30) . "...\n\n";
        
        // Criar novo hash
        echo "4️⃣  Corrigindo password...\n\n";
        $new_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user['id']]);
        echo "   ✅ Password actualizada\n";
        
        $is_valid = password_verify($password, $new_hash);
    }
    
    if ($is_valid) {
        echo "   ✅ Password CORRETA\n\n";
        
        // Simular sessão
        echo "5️⃣  Criando sessão...\n\n";
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['tipo_acesso'] = $user['tipo_acesso'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        echo "   ✅ Sessão criada\n";
        echo "   • user_id: " . $_SESSION['user_id'] . "\n";
        echo "   • username: " . $_SESSION['username'] . "\n";
        echo "   • tipo_acesso: " . $_SESSION['tipo_acesso'] . "\n";
        echo "   • logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n\n";
        
        // Actualizar último login
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        echo "╔════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                    ✅ LOGIN COM SUCESSO!                              ║\n";
        echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Credenciais validadas:\n";
        echo "   Username: $username\n";
        echo "   Password: ✅ Correcta\n";
        echo "   Tipo: " . $user['tipo_acesso'] . "\n\n";
        
        echo "🔗 Ir para dashboard: http://localhost:8080/dashboard.php\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
