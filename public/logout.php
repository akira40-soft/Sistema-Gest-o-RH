<?php
require_once __DIR__ . '/../src/bootstrap.php';
use App\Auth\Auth;

$auth = new Auth();
$auth->logout();

header("Location: login.php");
exit;
