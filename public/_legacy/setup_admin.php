<?php
/**
 * Setup Admin User - Cria/Reseta usuário admin
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Database\Database;
use App\Auth\Auth;

$db = Database::getInstance()->getConnection();
$auth = new Auth();

try {
    // Dados do admin
    $username = 'augusto';
    $password = 'Augusto@Gingongo2026';
    $password_hash = $auth->hashPassword($password);

    // Verificar se usuário existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Atualizar senha
        $stmt = $db->prepare("UPDATE usuarios SET password_hash = ?, ativo = 1, tipo_acesso = 'admin' WHERE username = ?");
        $stmt->execute([$password_hash, $username]);
        echo "✅ Usuário $username atualizado com sucesso!<br>";
    } else {
        // Criar novo usuário (sem vincular a funcionário por enquanto)
        $stmt = $db->prepare("INSERT INTO usuarios (username, password_hash, tipo_acesso, ativo) VALUES (?, ?, ?, 1)");
        $stmt->execute([$username, $password_hash, 'admin']);
        echo "✅ Usuário $username criado com sucesso!<br>";
    }

    echo "Credenciais:<br>";
    echo "Usuário: <strong>$username</strong><br>";
    echo "Senha: <strong>$password</strong><br>";
    echo "<br><a href='login.php'>Ir para Login</a>";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
