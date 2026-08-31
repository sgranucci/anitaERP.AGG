<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\Lsd\LsdBases04Support;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Traduce siapcon del formato SIAP 99 (Libro de sueldos digital Anita) a lsd_bases.
 */
class SueldosImportarSiapLsdAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-siap-lsd-anita
        {--formato=99 : Número de proceso SIAP en Anita}
        {--dry-run : Solo informa (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste lsd_bases en concepto_sueldos}';

    protected $description = 'Importa el armado LSD (siapcon formato 99) a flags lsd_bases de conceptos (dry-run por defecto).';

    public function handle(): int
    {
        if (! Schema::hasColumn('concepto_sueldos', 'lsd_bases')) {
            $this->error('Falta la columna concepto_sueldos.lsd_bases. Correr la migración 2026_08_30_150200_concepto_lsd_bases_sueldos.');

            return self::FAILURE;
        }

        $formato = (int) $this->option('formato');
        if ($formato <= 0) {
            $this->error('Formato SIAP inválido.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Modo análisis (no graba).' : 'Modo ejecución: persistirá lsd_bases.');
        $this->comment('Formato SIAP: '.$formato);

        $parsed = $this->listarSiapcon();
        if (isset($parsed['error'])) {
            $this->error($parsed['error']);

            return self::FAILURE;
        }

        $mapeo = LsdBases04Support::mapearDesdeSiapcon($parsed['filas'], $formato);
        if ($mapeo === []) {
            $this->warn('Anita no devolvió conceptos mapeables para el formato '.$formato.' (campos 22–47).');

            return self::SUCCESS;
        }

        $conceptos = Concepto_Sueldos::query()
            ->whereIn('codigo', array_keys($mapeo))
            ->get()
            ->keyBy(fn (Concepto_Sueldos $c) => (int) $c->codigo);

        $filasTabla = [];
        $crear = 0;
        $actualizar = 0;
        $igual = 0;
        $omitidos = 0;
        $aPersistir = [];

        foreach ($mapeo as $codigo => $flags) {
            $concepto = $conceptos->get($codigo);
            $nuevoTxt = LsdBases04Support::texto($flags);
            if (! $concepto) {
                $omitidos++;
                $filasTabla[] = [(string) $codigo, '(no está en ERP)', $nuevoTxt, 'omitir'];
                continue;
            }
            $actual = LsdBases04Support::normalizar(is_array($concepto->lsd_bases) ? $concepto->lsd_bases : null);
            if ($actual === $flags) {
                $igual++;
                $filasTabla[] = [(string) $codigo, (string) $concepto->descripcion, $nuevoTxt, 'igual'];
                continue;
            }
            if ($actual === []) {
                $crear++;
                $accion = 'crear';
            } else {
                $actualizar++;
                $accion = 'actualizar';
            }
            $filasTabla[] = [(string) $codigo, (string) $concepto->descripcion, $nuevoTxt, $accion];
            $aPersistir[] = [$concepto, $flags];
        }

        $this->table(['Código', 'Concepto ERP', 'lsd_bases (Anita 99)', 'Acción'], $filasTabla);
        $this->table(['En Anita', 'A crear', 'A actualizar', 'Igual', 'Sin concepto ERP'], [
            [count($mapeo), $crear, $actualizar, $igual, $omitidos],
        ]);

        if ($omitidos > 0) {
            $this->warn('Hay códigos SIAP sin concepto en el ERP: no se pueden traducir hasta importar el haber.');
        }

        if ($dryRun) {
            $this->comment('Nada persistido. Para grabar: php artisan sueldos:importar-siap-lsd-anita --formato='.$formato.' --ejecutar');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($aPersistir as [$concepto, $flags]) {
            $concepto->lsd_bases = $flags;
            $concepto->save();
            $n++;
        }
        $this->info('Persistidos: '.$n.' conceptos.');

        return self::SUCCESS;
    }

    /**
     * @return array{filas: list<object>}|array{error: string}
     */
    private function listarSiapcon(): array
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'siapcon',
            'campos' => 'siapcn_formato,siapcn_nro_campo,siapcn_concepto,siapcn_orden,siapcn_signo',
            'orderBy' => 'siapcn_formato,siapcn_nro_campo,siapcn_orden',
        ]));
        if (! empty($parsed['error_lectura'])) {
            return ['error' => (string) $parsed['error_lectura']];
        }

        return ['filas' => $parsed['filas'] ?? []];
    }
}
