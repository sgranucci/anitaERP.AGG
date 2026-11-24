<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Support\Sueldos\VacacionPlanAnitaMapper;
use Illuminate\Support\Facades\DB;

/**
 * Trae el plan / gozadas de Anita (vacempl) al legajo del ERP, con fecha real de salida.
 *
 * Por defecto importa desde ANIO_DESDE (histórico). La migración vacliq solo
 * completa períodos sin filas vacempl (fechas sintéticas).
 */
class AnitaVacacionPlanImportService
{
    /** Año mínimo del histórico vacempl (fechas reales). */
    public const ANIO_DESDE = 2000;

    public const MARCA = 'Plan Anita vacempl';

    public const MARCA_VACLIQ = 'Migración Anita vacliq';

    /**
     * @return array{
     *   leidas:int,
     *   importadas:int,
     *   empleados:int,
     *   liquidadas:int,
     *   tablas:int,
     *   sin_empleado:int,
     *   vacliq_reemplazadas:int,
     *   huecos_vacliq:int,
     *   anio_desde:int,
     *   anio_hasta:int,
     *   dry_run:bool,
     *   errores:list<string>
     * }
     */
    public function importar(?int $anioDesde = null, ?int $anioHasta = null, bool $dryRun = false): array
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '-1');

        $anioDesde = $anioDesde ?? self::ANIO_DESDE;
        $anioHasta = $anioHasta ?? ((int) date('Y') + 2);
        if ($anioDesde < 1990) {
            $anioDesde = 1990;
        }
        if ($anioHasta < $anioDesde) {
            $anioHasta = $anioDesde;
        }

        $res = [
            'leidas' => 0,
            'importadas' => 0,
            'empleados' => 0,
            'liquidadas' => 0,
            'tablas' => 0,
            'sin_empleado' => 0,
            'vacliq_reemplazadas' => 0,
            'huecos_vacliq' => 0,
            'anio_desde' => $anioDesde,
            'anio_hasta' => $anioHasta,
            'dry_run' => $dryRun,
            'errores' => [],
        ];

        $tipo = Tipo_Ausencia_Sueldos::query()
            ->where('categoria', 'vacaciones')
            ->orderBy('codigo')
            ->first();
        if ($tipo === null) {
            $res['errores'][] = 'No existe un tipo de ausencia con categoría vacaciones.';

            return $res;
        }

        $empresaPorCodigo = [];
        foreach (DB::table('empresa')->select('id', 'codigo')->get() as $r) {
            $cod = $this->normCodigo($r->codigo);
            if ($cod !== null) {
                $empresaPorCodigo[$cod] = (int) $r->id;
            }
        }

        $vacacionPorCodigo = [];
        foreach (DB::table('vacacion_sueldos')->select('id', 'codigo')->get() as $r) {
            $vacacionPorCodigo[(int) $r->codigo] = (int) $r->id;
        }

        $api = new ApiAnita();
        $desdePeriodo = $anioDesde * 100;
        $hastaPeriodo = ($anioHasta + 1) * 100;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'vacempl',
            'campos' => 'vace_empresa, vace_legajo, vace_periodo, vace_sale_vac, vace_dias_tomados, vace_tabla_vac, vace_dias_atomar',
            'whereArmado' => ' WHERE vace_periodo >= '.$desdePeriodo.' AND vace_periodo < '.$hastaPeriodo,
            'orderBy' => 'vace_empresa, vace_legajo, vace_periodo, vace_sale_vac',
        ]));
        if (! empty($parsed['error_lectura'])) {
            $res['errores'][] = 'Lectura vacempl: '.$parsed['error_lectura'];

            return $res;
        }

        $liq = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'vacliq',
            'campos' => 'vacl_empresa, vacl_legajo, vacl_periodo, vacl_dias_tomados',
            'whereArmado' => ' WHERE vacl_periodo >= '.$desdePeriodo.' AND vacl_periodo < '.$hastaPeriodo,
        ]));
        if (! empty($liq['error_lectura'])) {
            $res['errores'][] = 'Lectura vacliq: '.$liq['error_lectura'];

            return $res;
        }

        $diasLiquidados = [];
        foreach ($liq['filas'] as $f) {
            $key = $this->clavePeriodo($empresaPorCodigo, $f->vacl_empresa ?? null, $f->vacl_legajo ?? null, $f->vacl_periodo ?? null);
            if ($key === null) {
                continue;
            }
            $diasLiquidados[$key] = ($diasLiquidados[$key] ?? 0) + (float) ($f->vacl_dias_tomados ?? 0);
        }

        $idPorKey = [];
        foreach (DB::table('empleado_sueldos')->select('id', 'empresa_id', 'legajo', 'vacacion_id')->get() as $e) {
            $idPorKey[$e->empresa_id.':'.$e->legajo] = [
                'id' => (int) $e->id,
                'vacacion_id' => $e->vacacion_id !== null ? (int) $e->vacacion_id : null,
            ];
        }

        // Una fila por salida. Si Anita repite el mismo tramo, queda el que tiene días tomados.
        $tramos = [];
        $tablaPorEmpleado = [];
        foreach ($parsed['filas'] as $f) {
            $res['leidas']++;
            $empresaCodigo = $this->normCodigo($f->vace_empresa ?? null);
            $empresaId = $empresaCodigo !== null ? ($empresaPorCodigo[$empresaCodigo] ?? null) : null;
            $legajo = (int) ($f->vace_legajo ?? 0);
            $periodo = (int) ($f->vace_periodo ?? 0);
            if ($empresaId === null || $legajo <= 0 || $periodo <= 0) {
                continue;
            }
            $empKey = $empresaId.':'.$legajo;
            if (! isset($idPorKey[$empKey])) {
                $res['sin_empleado']++;
                continue;
            }
            $tabla = (int) ($f->vace_tabla_vac ?? 0);
            if ($tabla > 0) {
                $tablaPorEmpleado[$empKey] = $tabla;
            }
            $sale = (int) ($f->vace_sale_vac ?? 0);
            $dedupe = $empKey.':'.$periodo.':'.$sale;
            $candidato = [
                'empleado_id' => $idPorKey[$empKey]['id'],
                'periodo_key' => $empKey.':'.$periodo,
                'periodo' => $periodo,
                'sale' => $sale,
                'tomados' => (float) ($f->vace_dias_tomados ?? 0),
                'atomar' => (float) ($f->vace_dias_atomar ?? 0),
            ];
            if (! isset($tramos[$dedupe]) || $this->prefiere($candidato, $tramos[$dedupe])) {
                $tramos[$dedupe] = $candidato;
            }
        }

        $porPeriodo = [];
        foreach ($tramos as $tramo) {
            $porPeriodo[$tramo['periodo_key']][] = $tramo;
        }

        $ahora = now();
        $filas = [];
        $empleadosConEvento = [];
        $recalcular = [];
        $periodosConVacempl = [];
        foreach ($porPeriodo as $periodoKey => $lista) {
            usort($lista, fn ($a, $b) => [$a['sale'], $a['periodo']] <=> [$b['sale'], $b['periodo']]);
            $restanteLiquidado = $diasLiquidados[$periodoKey] ?? 0.0;
            foreach ($lista as $tramo) {
                $diasFila = $tramo['atomar'] > 0 ? $tramo['atomar'] : $tramo['tomados'];
                $liquidada = $restanteLiquidado > 0 && $diasFila > 0;
                $evento = VacacionPlanAnitaMapper::evento(
                    $tramo['periodo'],
                    $tramo['sale'],
                    $tramo['tomados'],
                    $tramo['atomar'],
                    $liquidada
                );
                if ($evento === null) {
                    continue;
                }
                if ($liquidada) {
                    $restanteLiquidado = max(0, $restanteLiquidado - $evento['dias']);
                }
                $filas[] = [
                    'empleado_id' => $tramo['empleado_id'],
                    'tipo_ausencia_id' => (int) $tipo->id,
                    'anio_imputacion' => $evento['anio'],
                    'fecha_desde' => $evento['fecha_desde'],
                    'fecha_hasta' => $evento['fecha_hasta'],
                    'dias' => $evento['dias'],
                    'tipo_dias' => 'corridos',
                    'estado' => $evento['estado'],
                    'observacion' => self::MARCA.' '.$tramo['periodo'],
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
                $periodosConVacempl[$periodoKey] = true;
                $empleadosConEvento[$tramo['empleado_id']] = true;
                if (in_array($evento['estado'], Empleado_Ausencia_Sueldos::ESTADOS_CONSUMEN, true)) {
                    $recalcular[$tramo['empleado_id']] = true;
                }
                if ($evento['estado'] === 'liquidada') {
                    $res['liquidadas']++;
                }
            }
        }

        // Huecos: vacliq con días y sin ningún tramo vacempl → sintético (1/1 del año).
        $huecosVacliq = 0;
        foreach ($diasLiquidados as $periodoKey => $diasLiq) {
            if ($diasLiq <= 0 || isset($periodosConVacempl[$periodoKey])) {
                continue;
            }
            [$empresaId, $legajo, $periodo] = array_map('intval', explode(':', $periodoKey));
            $empKey = $empresaId.':'.$legajo;
            $empleadoId = $idPorKey[$empKey]['id'] ?? null;
            if ($empleadoId === null) {
                continue;
            }
            $anio = (int) substr((string) $periodo, 0, 4);
            if ($anio < $anioDesde || $anio > $anioHasta || $anio < 1990) {
                continue;
            }
            $desde = sprintf('%04d-01-01', $anio);
            $hasta = \Carbon\Carbon::parse($desde)->addDays(max(0, (int) ceil($diasLiq) - 1))->toDateString();
            $filas[] = [
                'empleado_id' => $empleadoId,
                'tipo_ausencia_id' => (int) $tipo->id,
                'anio_imputacion' => $anio,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'dias' => round($diasLiq, 2),
                'tipo_dias' => 'corridos',
                'estado' => 'liquidada',
                'observacion' => self::MARCA_VACLIQ.' (sin vacempl)',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
            $huecosVacliq++;
            $empleadosConEvento[$empleadoId] = true;
            $recalcular[$empleadoId] = true;
            $res['liquidadas']++;
        }
        $res['huecos_vacliq'] = $huecosVacliq;

        $res['importadas'] = count($filas);
        $res['empleados'] = count($empleadosConEvento);

        if ($dryRun) {
            $res['vacliq_reemplazadas'] = (int) DB::table('empleado_ausencia_sueldos')
                ->where(function ($q) {
                    $q->where('observacion', self::MARCA_VACLIQ)
                        ->orWhere('observacion', 'like', self::MARCA_VACLIQ.'%');
                })
                ->where('anio_imputacion', '>=', $anioDesde)
                ->where('anio_imputacion', '<=', $anioHasta)
                ->count();

            return $res;
        }

        DB::transaction(function () use ($filas, $anioDesde, $anioHasta, &$res) {
            // Quita sintéticos vacliq del rango: vacempl trae los tramos con fecha real.
            $res['vacliq_reemplazadas'] = (int) DB::table('empleado_ausencia_sueldos')
                ->where(function ($q) {
                    $q->where('observacion', self::MARCA_VACLIQ)
                        ->orWhere('observacion', 'like', self::MARCA_VACLIQ.'%');
                })
                ->where('anio_imputacion', '>=', $anioDesde)
                ->where('anio_imputacion', '<=', $anioHasta)
                ->delete();

            DB::table('empleado_ausencia_sueldos')
                ->where('observacion', 'like', self::MARCA.'%')
                ->where('anio_imputacion', '>=', $anioDesde)
                ->where('anio_imputacion', '<=', $anioHasta)
                ->delete();

            foreach (array_chunk($filas, 400) as $chunk) {
                DB::table('empleado_ausencia_sueldos')->insert($chunk);
            }
        });

        foreach ($tablaPorEmpleado as $empKey => $codigo) {
            $vacacionId = $vacacionPorCodigo[$codigo] ?? null;
            $actual = $idPorKey[$empKey] ?? null;
            if ($vacacionId === null || $actual === null || $actual['vacacion_id'] === $vacacionId) {
                continue;
            }
            DB::table('empleado_sueldos')->where('id', $actual['id'])->update([
                'vacacion_id' => $vacacionId,
                'updated_at' => $ahora,
            ]);
            $res['tablas']++;
        }

        if ($recalcular !== []) {
            $devengar = app(DevengamientoVacacionesService::class);
            foreach (Empleado_Sueldos::query()->whereIn('id', array_keys($recalcular))->cursor() as $empleado) {
                $devengar->recalcularEmpleado($empleado);
            }
        }

        return $res;
    }

    /**
     * @param  array{tomados:float, atomar:float}  $nuevo
     * @param  array{tomados:float, atomar:float}  $actual
     */
    private function prefiere(array $nuevo, array $actual): bool
    {
        if ($nuevo['tomados'] !== $actual['tomados']) {
            return $nuevo['tomados'] > $actual['tomados'];
        }

        return $nuevo['atomar'] > $actual['atomar'];
    }

    /**
     * @param  array<string,int>  $empresaPorCodigo
     */
    private function clavePeriodo(array $empresaPorCodigo, $empresa, $legajo, $periodo): ?string
    {
        $cod = $this->normCodigo($empresa);
        $empresaId = $cod !== null ? ($empresaPorCodigo[$cod] ?? null) : null;
        $legajoN = (int) $legajo;
        $periodoN = (int) $periodo;
        if ($empresaId === null || $legajoN <= 0 || $periodoN <= 0) {
            return null;
        }

        return $empresaId.':'.$legajoN.':'.$periodoN;
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
