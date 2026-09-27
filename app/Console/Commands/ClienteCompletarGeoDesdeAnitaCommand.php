<?php

namespace App\Console\Commands;

use App\Support\Ventas\ClienteAnitaGeoSupport;
use Illuminate\Console\Command;

/**
 * Completa localidad_id / provincia_id vacíos desde clim_* de Anita.
 * Default: dry-run. --ejecutar persiste.
 */
class ClienteCompletarGeoDesdeAnitaCommand extends Command
{
    protected $signature = 'cliente:completar-geo-desde-anita
                            {--codigo= : Limitar a un código de cliente}
                            {--ejecutar : Persiste (sin esto solo informa)}';

    protected $description = 'Completa localidad/provincia vacías en cliente desde Anita (clim_localidad/clim_provincia). No pisa valores ya cargados.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $codigo = is_string($this->option('codigo')) ? trim($this->option('codigo')) : '';

        $this->comment($ejecutar
            ? 'PERSISTIR: solo localidad_id / provincia_id vacíos.'
            : 'Dry-run: no se graba nada.');

        if ($codigo !== '') {
            $this->info("Filtro código={$codigo}");
        }

        $this->info('Leyendo climae en Anita y resolviendo geo…');

        try {
            $ret = ClienteAnitaGeoSupport::completarVaciosDesdeAnita(
                $ejecutar,
                $codigo !== '' ? $codigo : null
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Clientes Anita', $ret['en_anita']],
                ['ERP sin geo (loc o prov vacía)', $ret['erp_sin_geo']],
                ['Se completarían localidad', $ret['completar_localidad']],
                ['Se completarían provincia', $ret['completar_provincia']],
                ['Sin match posible', $ret['sin_match']],
                ['Anita sin cliente en ERP', $ret['sin_cliente']],
                ['Filas con cambio', count($ret['filas'])],
                ['Actualizados', $ret['actualizados']],
            ]
        );

        if ($ret['ejemplos'] !== []) {
            $this->newLine();
            $this->comment('Ejemplos');
            $this->table(
                ['Código', 'Nombre', 'Anita loc', 'CP', 'Anita prov', 'loc_id', 'prov_id', 'Estado'],
                array_map(static fn (array $r) => [
                    $r['codigo'],
                    mb_substr((string) $r['nombre'], 0, 28),
                    mb_substr((string) $r['anita_loc'], 0, 18),
                    $r['anita_cp'],
                    mb_substr((string) $r['anita_prov'], 0, 16),
                    $r['localidad_id'] ?? '',
                    $r['provincia_id'] ?? '',
                    $r['estado'],
                ], $ret['ejemplos'])
            );
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('Nada persistido. Para grabar: php artisan cliente:completar-geo-desde-anita --ejecutar');

            return self::SUCCESS;
        }

        $this->info("Listo. Actualizados: {$ret['actualizados']}.");

        return self::SUCCESS;
    }
}
