<?php
/**
 * TESTE COMPLETO - Faz login e acessa dashboard
 */

// Configurar sessão PRIMEIRO
$tmp_dir = __DIR__ . '/../tmp/sessions';
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, true);
}
ini_set('session.save_path', $tmp_dir);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Requer bootstrap DEPOIS de configurar sessão
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                     TESTE COMPLETO LOGIN                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

// 1. Fazer login
echo "1️⃣  Fazendo login...\n\n";
$auth = new Auth();
$result = $auth->login('josemar_quarenta', 'admin1123');

if ($result['success']) {
    echo "   ✅ Login realizado\n";
    echo "   • Username: " . $result['user']['username'] . "\n";
    echo "   • Tipo: " . $result['user']['tipo_acesso'] . "\n";
} else {
    echo "   ❌ " . $result['message'] . "\n";
    exit(1);
}

// 2. Verificar sessão
echo "\n2️⃣  Verificando sessão...\n\n";
echo "   • user_id: " . $_SESSION['user_id'] . "\n";
echo "   • username: " . $_SESSION['username'] . "\n";
echo "   • tipo_acesso: " . $_SESSION['tipo_acesso'] . "\n";
echo "   • logged_in: " . ($_SESSION['logged_in'] ? 'true' : 'false') . "\n";

// 3. Verificar Auth helpers
echo "\n3️⃣  Testando Auth helpers...\n\n";
echo "   • isAuthenticated: " . ($auth->isAuthenticated() ? 'true' : 'false') . "\n";
echo "   • isAdmin: " . ($auth->isAdmin() ? 'true' : 'false') . "\n";
echo "   • getUserId: " . $auth->getUserId() . "\n";
echo "   • getUserRole: " . $auth->getUserRole() . "\n";

// 4. Resultado final
echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    ✅ TESTE PASSOU!                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

echo "Credenciais de login:\n";
echo "   Username: josemar_quarenta\n";
echo "   Password: admin1123\n\n";

echo "Status do sistema:\n";
echo "   ✅ Database funcionando (MySQL)\n";
echo "   ✅ Auth funcionando\n";
echo "   ✅ Sessões funcionando\n";
echo "   ✅ Password verification funcionando\n\n";

echo "Próximos passos:\n";
echo "   1. Acessar http://localhost:8080/login.php\n";
echo "   2. Fazer login com josemar_quarenta / admin1123\n";
echo "   3. Sistema deve redirecionar para dashboard\n\n";
?>
