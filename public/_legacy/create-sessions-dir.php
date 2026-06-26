<?php
// Criar diretório se não existe
$tmp_dir = __DIR__ . '/../tmp/sessions';
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, true);
    echo "✅ Diretório criado: $tmp_dir\n";
} else {
    echo "✅ Diretório já existe: $tmp_dir\n";
}

// Testar escrita
$test_file = $tmp_dir . '/test.txt';
if (file_put_contents($test_file, 'test')) {
    unlink($test_file);
    echo "✅ Permissões OK - pode escrever neste diretório\n";
} else {
    echo "❌ Sem permissão para escrever\n";
}
?>
