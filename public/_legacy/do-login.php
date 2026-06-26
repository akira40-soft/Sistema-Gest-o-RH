<?php
/**
 * LOGIN DIRECTO - Contorna problema de sessão e faz login real
 */

// Configurar sessão num pasta diferente
$tmp_dir = __DIR__ . '/../tmp';
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0777, true);
}
ini_set('session.save_path', $tmp_dir);

session_start();

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmacia_valodia_rg';

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   FAZENDO LOGIN DE VERDADE                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $username = 'josemar_quarenta';
    $password = 'admin1123';
    
    // 1. Buscar utilizador
    $stmt = $pdo->prepare("SELECT id, username, password_hash, tipo_acesso, ativo FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() === 0) {
        echo "❌ Utilizador não existe\n";
        exit(1);
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Validar password
    if (!password_verify($password, $user['password_hash'])) {
        echo "❌ Password incorrecta\n";
        exit(1);
    }
    
    // 3. Validar se está activo
    if (!$user['ativo']) {
        echo "❌ Utilizador não está activo\n";
        exit(1);
    }
    
    // 4. Criar sessão
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['tipo_acesso'] = $user['tipo_acesso'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // 5. Actualizar último login
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // 6. Resultado
    echo "✅ LOGIN REALIZADO COM SUCESSO!\n\n";
    echo "   Username: " . $user['username'] . "\n";
    echo "   ID: " . $user['id'] . "\n";
    echo "   Tipo: " . $user['tipo_acesso'] . "\n";
    echo "   Sessão ID: " . session_id() . "\n\n";
    
    echo "📍 Redirecionando para dashboard...\n";
    echo "   http://localhost:8080/dashboard.php\n\n";
    
    // Aguardar 2 segundos e redirecionar
    header("Refresh: 2; url=http://localhost:8080/dashboard.php");
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
    exit(1);
}
?>
