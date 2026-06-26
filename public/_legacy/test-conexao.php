<?php
/**
 * Teste de Conexão - Verifica qual banco está sendo usado
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;

echo "\n╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║                      TESTE DE CONEXÃO                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";

try {
    $db = Database::getInstance();
    
    echo "1️⃣  Testando SELECT...\n\n";
    $users = $db->select('usuarios', ['username' => 'josemar_quarenta'], true);
    
    if ($users) {
        echo "   ✅ Utilizador encontrado\n";
        echo "   • ID: " . $users['id'] . "\n";
        echo "   • Username: " . $users['username'] . "\n";
        echo "   • Tipo: " . $users['tipo_acesso'] . "\n";
    } else {
        echo "   ❌ Utilizador não encontrado\n";
    }
    
    echo "\n2️⃣  Testando PASSWORD_VERIFY...\n\n";
    $is_valid = password_verify('admin1123', $users['password_hash']);
    echo "   Password: " . ($is_valid ? '✅ VÁLIDA' : '❌ INVÁLIDA') . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
