<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Sueldos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa actividad_sijp y localidad_afip (zona geográfica Anita) desde empleado Anita.
 */
class SueldosImportarSijpLsdAnitaCommand extends Command
{
    protected $signature = 'sueldos:importar-sijp-lsd-anita
        {--dry-run : Solo informa (default si no se pasa --ejecutar)}
        {--ejecutar : Persiste actividad_sijp y localidad_afip}';

    protected $description = 'Importa actividad SIJP y zona geográfica AFIP desde Anita (dry-run por defecto).';

    public function handle(): int
    {
        if (! Schema::hasColumn('empleado_sueldos', 'actividad_sijp')
            || ! Schema::hasColumn('empleado_sueldos', 'localidad_afip')) {
            $this->error('Faltan columnas actividad_sijp / localidad_afip.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        $this->info($dryRun ? 'Modo análisis (no graba).' : 'Modo ejecución: persistirá SIJP LSD.');

        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'empleado',
            'campos' => 'emp_empresa,emp_legajo,emp_actividad,emp_zonageo',
            'orderBy' => 'emp_empresa,emp_legajo',
        ]));
        if (! empty($parsed['error_lectura'])) {
            $this->error((string) $parsed['error_lectura']);

            return self::FAILURE;
        }

        $empresaPorCodigo = [];
        foreach (DB::table('empresa')->select('id', 'codigo')->get() as $e) {
            $k = trim((string) $e->codigo);
            if ($k !== '' && is_numeric($k)) {
                $empresaPorCodigo[(string) (int) $k] = (int) $e->id;
            }
        }

        $empleados = Empleado_Sueldos::query()
            ->get(['id', 'empresa_id', 'legajo', 'actividad_sijp', 'localidad_afip'])
            ->keyBy(fn (Empleado_Sueldos $e) => $e->empresa_id.':'.$e->legajo);

        $crear = 0;
        $actualizar = 0;
        $igual = 0;
        $omitidos = 0;
        $muestra = [];
        $aPersistir = [];

        foreach ($parsed['filas'] ?? [] as $f) {
            $codEmp = trim((string) ($f->emp_empresa ?? ''));
            $empresaId = is_numeric($codEmp) ? ($empresaPorCodigo[(string) (int) $codEmp] ?? null) : null;
            $legajo = (int) ($f->emp_legajo ?? 0);
            if ($empresaId === null || $legajo <= 0) {
                $omitidos++;
                continue;
            }
            $emp = $empleados->get($empresaId.':'.$legajo);
            if (! $emp) {
                $omitidos++;
                continue;
            }
            $act = self::padActividad($f->emp_actividad ?? null);
            $zona = self::padZona($f->emp_zonageo ?? null);
            $actActual = self::padActividad($emp->actividad_sijp);
            $zonaActual = self::padZona($emp->localidad_afip);
            if ($actActual === $act && $zonaActual === $zona) {
                $igual++;
                continue;
            }
            if ($actActual === '000' && $zonaActual === '00') {
                $crear++;
            } else {
                $actualizar++;
            }
            if (count($muestra) < 15) {
                $muestra[] = [(string) $emp->legajo, $actActual.'/'.$zonaActual, $act.'/'.$zona];
            }
            $aPersistir[] = [$emp, $act, $zona];
        }

        if ($muestra !== []) {
            $this->table(['Legajo', 'ERP actual (act/zona)', 'Anita'], $muestra);
        }
        $this->table(['En Anita', 'A crear', 'A actualizar', 'Igual', 'Sin match ERP'], [
            [count($parsed['filas'] ?? []), $crear, $actualizar, $igual, $omitidos],
        ]);

        if ($dryRun) {
            $this->comment('Nada persistido. Para grabar: php artisan sueldos:importar-sijp-lsd-anita --ejecutar');

            return self::SUCCESS;
        }

        $n = 0;
        foreach ($aPersistir as [$emp, $act, $zona]) {
            $emp->actividad_sijp = $act;
            $emp->localidad_afip = $zona;
            $emp->save();
            $n++;
        }
        $this->info('Persistidos: '.$n.' empleados.');

        return self::SUCCESS;
    }

    public static function padActividad(mixed $v): string
    {
        $n = (int) preg_replace('/\D+/', '', (string) $v);

        return str_pad((string) max(0, $n), 3, '0', STR_PAD_LEFT);
    }

    public static function padZona(mixed $v): string
    {
        $n = (int) preg_replace('/\D+/', '', (string) $v);

        return str_pad((string) max(0, min(99, $n)), 2, '0', STR_PAD_LEFT);
    }
}
