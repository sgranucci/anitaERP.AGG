<?php

namespace App\Services\Crm;

use App\Support\SuitecrmPermiso;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SuitecrmConfigService
{
    public function isHabilitado(): bool
    {
        return SuitecrmPermiso::integracionActiva();
    }

    /**
     * @return array{host: string, database: string, username: string, password: string, port: int}
     */
    public function getDbConfig(): array
    {
        $path = (string) config('suitecrm.legacy_config_path', '');
        if ($path === '' || ! is_readable($path)) {
            throw new RuntimeException('No se puede leer la configuración de SuiteCRM: '.$path);
        }

        $sugar_config = [];
        include $path;

        if (! is_array($sugar_config) || ! isset($sugar_config['dbconfig']) || ! is_array($sugar_config['dbconfig'])) {
            throw new RuntimeException('config.php de SuiteCRM sin dbconfig válido.');
        }

        $db = $sugar_config['dbconfig'];
        $host = (string) ($db['db_host_name'] ?? 'localhost');
        $port = (int) ($db['db_port'] ?? 3306);
        if ($port <= 0) {
            $port = 3306;
        }

        return [
            'host' => $host,
            'database' => (string) ($db['db_name'] ?? ''),
            'username' => (string) ($db['db_user_name'] ?? ''),
            'password' => (string) ($db['db_password'] ?? ''),
            'port' => $port,
        ];
    }

    public function ensureConnection(): void
    {
        if (Config::has('database.connections.suitecrm')) {
            return;
        }

        $db = $this->getDbConfig();
        if ($db['database'] === '') {
            throw new RuntimeException('SuiteCRM: nombre de base de datos vacío.');
        }

        Config::set('database.connections.suitecrm', [
            'driver' => 'mysql',
            'host' => $db['host'],
            'port' => $db['port'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
            'charset' => 'utf8mb3',
            'collation' => 'utf8mb3_general_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ]);

        DB::purge('suitecrm');
    }

    public function connection()
    {
        $this->ensureConnection();

        return DB::connection('suitecrm');
    }

    public function defaultUserId(): string
    {
        return (string) config('suitecrm.default_user_id', '1');
    }
}
