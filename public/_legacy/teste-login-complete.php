<?php
/**
 * TESTE LOGIN COMPLETO - Simula um login real do formulário
 */

// Configurar sessão
$tmp_dir = __DIR__ . '/../tmp/sessions';
if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0755, true);
}
ini_set('session.save_path', $tmp_dir);
session_start();

// Simular POST de login
$_POST['username'] = 'josemar_quarenta';
$_POST['password'] = 'admin1123';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Executar login
require_once __DIR__ . '/login_process.php';
?>
