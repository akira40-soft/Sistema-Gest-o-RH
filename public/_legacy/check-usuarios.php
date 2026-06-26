<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmacia_valodia_rg';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "╔════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                 ESTRUTURA DA TABELA usuarios                           ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
    
    // Estrutura usuarios
    $result = $pdo->query("DESCRIBE usuarios");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colunas em usuarios:\n";
    foreach ($columns as $col) {
        echo "  • " . $col['Field'] . " (" . $col['Type'] . ") - " . 
             ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . 
             ($col['Key'] !== '' ? ' [' . $col['Key'] . ']' : '') . "\n";
    }
    
    echo "\n════════════════════════════════════════════════════════════════════════\n";
    echo "║                       TODOS OS USUARIOS                               ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════╝\n\n";
    
    // Todos os usuarios
    $result = $pdo->query("SELECT * FROM usuarios LIMIT 20");
    $users = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de utilizadores: " . count($users) . "\n\n";
    
    foreach ($users as $user) {
        echo "ID: " . $user['id'] . " | Username: " . $user['username'] . "\n";
        foreach ($user as $k => $v) {
            if ($k !== 'id' && $k !== 'username') {
                echo "  $k: $v\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
