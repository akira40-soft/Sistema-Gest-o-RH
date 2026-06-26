<?php
/*
 * Entry point do sistema - redireciona conforme sessão
 */

// Se já tem sessão, vai direto para o dashboard/portal
require_once __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;

$auth = new Auth();

if ($auth->isAuthenticated()) {
    $role = $auth->getUserRole();
    if (in_array($role, ['admin', 'gestor_rh', 'super_admin'])) {
        header('Location: dashboard.php');
    } else {
        header('Location: portal.php');
    }
    exit;
}

// Não autenticado → login
header('Location: login.php');
exit;
