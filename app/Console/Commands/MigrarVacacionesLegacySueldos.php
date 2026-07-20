<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Services\Sueldos\DevengamientoVacacionesService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra el consumo real de vacaciones desde Anita (vacliq) al ledger del ERP.
 *
 * Criterio confirmado: vacliq es la fuente de la verdad de los días efectivamente
 * tomados/liquidados (una fila por tramo/liquidación → soporta vacaciones fraccionadas).
 * El devengamiento (saldo teórico) se recalcula por antigüedad (LCT) en el mismo paso.
 *
 * Idempotente: borra las ausencias migradas previas (marca MIGRACION) antes de reinsertar.
 * Solo lectura contra Anita.
 */
class MigrarVacacionesLegacySueldos extends Command
{
    protected $signature = 'sueldos:migrar-vacaciones-legacy
        {--empresa= : Filtra por empresa_id del ERP}
        {--dry-run : No graba, solo informa}';

    protected $description = 'Migra vacaciones tomadas (Anita vacliq) al ledger de ausencias y recalcula saldos.';

    private const MARCA = 'Migración Anita vacliq';

    public function handle(DevengamientoVacacionesService $devengamiento): int
    {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        $dryRun = (bool) $this->option('dry-run');
        $empresaFiltro = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        $tipoVacaciones = Tipo_Ausencia_Sueldos::query()
            ->where('categoria', 'vacaciones')
            ->orderBy('codigo')
            ->first();
        if ($tipoVacaciones === null) {
            $this->error('No existe un tipo de ausencia con categoría "vacaciones". Corré la migración del ledger primero.');

            return self::FAILURE;
        }

        // Mapa código empresa Anita => empresa_id ERP.
        $empresaPorCodigo = [];
        foreach (DB::table('empresa')->select('id', 'codigo')->get() as $r) {
            $cod = $this->normCodigo($r->codigo);
            if ($cod !== null) {
                $empresaPorCodigo[$cod] = (int) $r->id;
            }
        }

        $this->info('Leyendo vacliq desde Anita…');
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'vacliq',
            'campos' => 'vacl_empresa, vacl_legajo, vacl_periodo, vacl_dias_tomados, vacl_cierre',
            'orderBy' => 'vacl_empresa, vacl_legajo, vacl_periodo',
        ]));
        if (! empty($parsed['error_lectura'])) {
            $this->error('Error leyendo vacliq: '.$parsed['error_lectura']);

            return self::FAILURE;
        }

        // Agrupa por (empresa_id, legajo) => lista de tramos (periodo, dias).
        $porEmpleado = [];
        $leidas = 0;
        foreach ($parsed['filas'] as $f) {
            $leidas++;
            $codEmp = $this->normCodigo($f->vacl_empresa ?? $f->VACL_EMPRESA ?? null);
            $empresaId = $codEmp !== null ? ($empresaPorCodigo[$codEmp] ?? null) : null;
            if ($empresaId === null) {
                continue;
            }
            if ($empresaFiltro !== null && $empresaId !== $empresaFiltro) {
                continue;
            }
            $legajo = (int) ($f->vacl_legajo ?? $f->VACL_LEGAJO ?? 0);
            $dias = (float) ($f->vacl_dias_tomados ?? $f->VACL_DIAS_TOMADOS ?? 0);
            $periodo = (int) ($f->vacl_periodo ?? $f->VACL_PERIODO ?? 0);
            if ($legajo <= 0 || $dias <= 0) {
                continue;
            }
            $porEmpleado[$empresaId.':'.$legajo][] = [
                'anio' => $this->anioDesdePeriodo($periodo),
                'dias' => $dias,
                'periodo' => $periodo,
            ];
        }

        $this->info(sprintf('vacliq leídas: %d — empleados con consumo: %d', $leidas, count($porEmpleado)));

        $empQuery = Empleado_Sueldos::query();
        if ($empresaFiltro !== null) {
            $empQuery->where('empresa_id', $empresaFiltro);
        }
        $empleados = $empQuery->get();

        $totEventos = 0;
        $totEmpleados = 0;
        $sinMatch = 0;

        foreach ($empleados as $empleado) {
            $key = $empleado->empresa_id.':'.$empleado->legajo;
            $tramos = $porEmpleado[$key] ?? [];

            if ($dryRun) {
                if ($tramos !== []) {
                    $totEmpleados++;
                    $totEventos += count($tramos);
                }
                continue;
            }

            DB::transaction(function () use ($empleado, $tramos, $tipoVacaciones, $devengamiento, &$totEventos, &$totEmpleados) {
                // Idempotencia: borra migraciones previas de este empleado.
                Empleado_Ausencia_Sueldos::query()
                    ->where('empleado_id', $empleado->id)
                    ->where('observacion', self::MARCA)
                    ->delete();

                foreach ($tramos as $tramo) {
                    $anio = $tramo['anio'] > 0 ? $tramo['anio'] : (int) Carbon::now()->year;
                    $desde = Carbon::create($anio, 1, 1);
                    $hasta = $desde->copy()->addDays(max(0, (int) ceil($tramo['dias']) - 1));

                    Empleado_Ausencia_Sueldos::create([
                        'empleado_id' => $empleado->id,
                        'tipo_ausencia_id' => $tipoVacaciones->id,
                        'anio_imputacion' => $anio,
                        'fecha_desde' => $desde->toDateString(),
                        'fecha_hasta' => $hasta->toDateString(),
                        'dias' => $tramo['dias'],
                        'tipo_dias' => 'corridos',
                        'estado' => 'liquidada',
                        'observacion' => self::MARCA,
                    ]);
                    $totEventos++;
                }

                $devengamiento->recalcularEmpleado($empleado);
                if ($tramos !== []) {
                    $totEmpleados++;
                }
            });
        }

        foreach (array_keys($porEmpleado) as $key) {
            $existe = $empleados->first(fn ($e) => $e->empresa_id.':'.$e->legajo === $key);
            if ($existe === null) {
                $sinMatch++;
            }
        }

        $this->info(sprintf(
            '%s Empleados migrados: %d — eventos: %d — vacliq sin empleado en ERP: %d',
            $dryRun ? '[DRY-RUN]' : 'OK',
            $totEmpleados,
            $totEventos,
            $sinMatch
        ));

        return self::SUCCESS;
    }

    private function anioDesdePeriodo(int $periodo): int
    {
        $s = (string) $periodo;
        if (strlen($s) >= 4) {
            $anio = (int) substr($s, 0, 4);
            if ($anio >= 1990 && $anio <= 2100) {
                return $anio;
            }
        }
        if ($periodo >= 1990 && $periodo <= 2100) {
            return $periodo;
        }

        return 0;
    }

    private function normCodigo($v): ?string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        if (is_numeric($s)) {
            return (string) (int) $s;
        }

        return mb_strtoupper($s);
    }
}
