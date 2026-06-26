<?php
/**
 * Logout - Encerra a sessão do usuário
 */

require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

$auth = new Auth();
$auth->logout();

// Redirecionar para login
header('Location: /login.php?logged_out=1');
exit;
