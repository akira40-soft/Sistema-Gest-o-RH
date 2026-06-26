<?php
/**
 * DEBUG LOGIN - Mostra exatamente o que acontece
 */

// Configurar sessão
$tmp_dir = __DIR__ . '/../tmp/sessions';
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, true);
}
ini_set('session.save_path', $tmp_dir);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'josemar_quarenta';
$_POST['password'] = 'admin1123';

// DEBUG: Mostrar que o POST foi definido
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                      DEBUG LOGIN PROCESS                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

echo "1️⃣  Dados recebidos:\n";
echo "   • Username: " . $_POST['username'] . "\n";
echo "   • Password: " . $_POST['password'] . "\n";
echo "   • Method: " . $_SERVER['REQUEST_METHOD'] . "\n\n";

// Inicializar sessão
session_start();

echo "2️⃣  Testando Auth class...\n\n";

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

try {
    $auth = new Auth();
    $result = $auth->login('josemar_quarenta', 'admin1123');
    
    echo "   Auth result:\n";
    echo "   • Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "   • Message: " . $result['message'] . "\n";
    
    if ($result['success']) {
        echo "   • User ID: " . $result['user']['id'] . "\n";
        echo "   • Username: " . $result['user']['username'] . "\n";
        echo "   • Tipo: " . $result['user']['tipo_acesso'] . "\n";
        
        echo "\n3️⃣  Sessão criada:\n";
        echo "   • user_id: " . ($_SESSION['user_id'] ?? 'não definido') . "\n";
        echo "   • username: " . ($_SESSION['username'] ?? 'não definido') . "\n";
        echo "   • tipo_acesso: " . ($_SESSION['tipo_acesso'] ?? 'não definido') . "\n";
        echo "   • logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n";
        
        echo "\n✅ LOGIN FUNCIONANDO!\n";
        echo "   Redirecionaria para: /dashboard.php\n";
    } else {
        echo "\n❌ Login failed: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Exceção: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
?>
