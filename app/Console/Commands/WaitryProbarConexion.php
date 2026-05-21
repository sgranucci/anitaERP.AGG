<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\Waitry\WaitryAuthService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryHttpClient;
use Illuminate\Console\Command;

class WaitryProbarConexion extends Command
{
    protected $signature = 'waitry:probar-conexion
                            {--renovar : Fuerza renovación del token antes de probar}
                            {--empresa=1 : empresa_id para validar placeId y table}';

    protected $description = 'Prueba login OAuth Waitry y configuración placeId/table por empresa';

    public function handle(WaitryAuthService $auth, WaitryHttpClient $http): int
    {
        if (! config('waitry.habilitado', false)) {
            $this->warn('WAITRY_HABILITADO=false — active la integración en .env para producción.');

            return self::SUCCESS;
        }

        if ($this->option('renovar')) {
            $auth->invalidarToken();
            $auth->renovarTokenForzado();
            $this->info('Token renovado.');
        }

        $ctx = $auth->contextoAutenticado();
        if (! $ctx['ok']) {
            $this->error($ctx['error']);

            return self::FAILURE;
        }

        $this->info('Token OK (longitud '.strlen($ctx['access_token']).').');

        $empresaId = (int) $this->option('empresa');
        $map = config('waitry.place_id_por_empresa', []);
        $placeId = is_array($map) ? (int) ($map[$empresaId] ?? 0) : 0;
        if ($placeId <= 0) {
            $this->error('Sin placeId para empresa '.$empresaId);

            return self::FAILURE;
        }
        $this->info("placeId empresa {$empresaId}: {$placeId}");

        $tables = config('waitry.table_por_empresa', []);
        $table = is_array($tables) ? ($tables[$empresaId] ?? null) : null;
        if (! is_array($table) || $table === []) {
            $legacy = config('waitry.table', []);
            if (is_array($legacy) && $legacy !== []) {
                $this->warn('Usando WAITRY_TABLE_JSON legacy (sin table por empresa).');
                $table = $legacy;
            }
        }

        if (! is_array($table) || $table === []) {
            $this->error('Sin table para empresa '.$empresaId.' (WAITRY_TABLE_POR_EMPRESA)');

            return self::FAILURE;
        }

        $this->info('table empresa '.$empresaId.': '.json_encode($table, JSON_UNESCAPED_UNICODE));
        $this->line('Push Orders URL: '.config('waitry.push_order_url'));
        $this->line('Ejemplos JSON: docs/waitry/');

        return self::SUCCESS;
    }
}
