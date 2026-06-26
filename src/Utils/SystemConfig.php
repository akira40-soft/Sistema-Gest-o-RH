<?php
namespace App\Utils;

use App\Database\Database;
use PDO;

class SystemConfig
{
    private static $settings = [];

    public static function init()
    {
        self::load();
        self::apply();
    }

    private static function load()
    {
        // Se já carregado nesta execução ou se estiver na sessão, evita query
        if (!empty(self::$settings))
            return;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['sys_settings']) && !isset($_GET['clear_cache'])) {
            self::$settings = $_SESSION['sys_settings'];
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT chave, valor FROM configuracoes");
            while ($row = $stmt->fetch()) {
                self::$settings[$row['chave']] = $row['valor'];
            }
            // Salva na sessão para as próximas páginas
            $_SESSION['sys_settings'] = self::$settings;
        }
        catch (\Exception $e) {
            error_log("Erro ao carregar configurações: " . $e->getMessage());
        }
    }

    private static function apply()
    {
        // Timezone
        $timezone = self::$settings['timezone'] ?? 'Africa/Luanda';
        date_default_timezone_set($timezone);
    }

    public static function get($key, $default = null)
    {
        return self::$settings[$key] ?? $default;
    }

    public static function getAll()
    {
        return self::$settings;
    }
}
