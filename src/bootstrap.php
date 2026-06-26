<?php
/**
 * Bootstrap do Sistema - Farmácia Gingongo RG
 * Versão 2026 - Limpa e Otimizada
 */

// Caminho raiz do projeto
define('ROOT_PATH', dirname(__DIR__));

// Carregar Autoloader (Composer ou manual)
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Autoloader manual PSR-4
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $base_dir = ROOT_PATH . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

// Timezone
date_default_timezone_set('Africa/Luanda');

// Configurar sessão
if (session_status() === PHP_SESSION_NONE) {
    $session_path = ROOT_PATH . '/tmp/sessions';
    if (!is_dir($session_path)) {
        @mkdir($session_path, 0755, true);
    }
    if (is_dir($session_path) && is_writable($session_path)) {
        ini_set('session.save_path', $session_path);
    }
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 86400);
    @session_start();
}

// Constantes do sistema
if (!defined('APP_NAME')) define('APP_NAME', 'SG Farmácia Gingongo');
if (!defined('APP_VERSION')) define('APP_VERSION', '2.0.2026');

// Função helper de debug
if (!function_exists('dd')) {
    function dd($data)
    {
        echo '<pre style="background:#0b1220;color:#10b981;padding:20px;border-radius:8px;border:1px solid #1e293b;font-family:monospace;font-size:13px;">';
        print_r($data);
        echo '</pre>';
        die();
    }
}

// Função helper para escapar
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Helper para formatar moeda Kwanza
if (!function_exists('kz')) {
    function kz($value) {
        return 'Kz ' . number_format((float)$value, 2, ',', '.');
    }
}

// Helper para formatar data
if (!function_exists('rg_date')) {
    function rg_date($date, $withTime = false) {
        if (empty($date)) return '—';
        $format = $withTime ? 'd/m/Y H:i' : 'd/m/Y';
        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return '—';
        }
    }
}
