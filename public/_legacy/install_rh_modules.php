<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Database\Database;

try {
    $db = Database::getInstance()->getConnection();

    // Ler o arquivo SQL
    $sql = file_get_contents(__DIR__ . '/../database/modulos_rh_completo.sql');

    // Executar múltiplas queries
    $db->exec($sql);

    echo "<h1>Sucesso!</h1>";
    echo "<p>Todas as tabelas dos módulos de RH foram criadas com sucesso.</p>";
    echo "<a href='dashboard.php'>Voltar ao Dashboard</a>";


}
catch (PDOException $e) {
    echo "<h1>Erro</h1>";
    echo "<p>Erro ao executar SQL: " . $e->getMessage() . "</p>";
}
