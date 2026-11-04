<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Ausencia_Sueldos;
use App\Support\Sueldos\VacacionPlanAnitaMapper;
use Illuminate\Support\Facades\DB;

/**
 * Trae el plan de vacaciones cargado en Anita (vacempl) al legajo del ERP.
 * Cubre los períodos desde ANIO_DESDE: la migración de vacliq no los trae
 * y, cuando ya están liquidados, usa la fecha real de salida.
 */
class AnitaVacacionPlanImportService
{
    public const ANIO_DESDE = 2026;

    public const MARCA = 'Plan Anita vacempl';

    /**
     * @return array{leidas:int, importadas:int, empleados:int, liquidadas:int, tablas:int, sin_empleado:int, errores:list<string>}
     */
    public function importar(): array
    {
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '512M');

        $res = [
            'leidas' => 0,
            'importadas' => 0,
            'empleados' => 0,
            'liquidadas' => 0,
            'tablas' => 0,
            'sin_empleado' => 0,
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
        $desde = self::ANIO_DESDE * 100;
        $hasta = (self::ANIO_DESDE + 5) * 100;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'vacempl',
            'campos' => 'vace_empresa, vace_legajo, vace_periodo, vace_sale_vac, vace_dias_tomados, vace_tabla_vac, vace_dias_atomar',
            'whereArmado' => ' WHERE vace_periodo >= '.$desde.' AND vace_periodo < '.$hasta,
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
            'whereArmado' => ' WHERE vacl_periodo >= '.$desde.' AND vacl_periodo < '.$hasta,
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
                $empleadosConEvento[$tramo['empleado_id']] = true;
                if (in_array($evento['estado'], Empleado_Ausencia_Sueldos::ESTADOS_CONSUMEN, true)) {
                    $recalcular[$tramo['empleado_id']] = true;
                }
                if ($evento['estado'] === 'liquidada') {
                    $res['liquidadas']++;
                }
            }
        }

        DB::transaction(function () use ($filas) {
            DB::table('empleado_ausencia_sueldos')
                ->where('observacion', 'like', self::MARCA.'%')
                ->where('anio_imputacion', '>=', self::ANIO_DESDE)
                ->delete();
            foreach (array_chunk($filas, 400) as $chunk) {
                DB::table('empleado_ausencia_sueldos')->insert($chunk);
            }
        });

        $res['importadas'] = count($filas);
        $res['empleados'] = count($empleadosConEvento);

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
            foreach (Empleado_Sueldos::query()->whereIn('id', array_keys($recalcular))->get() as $empleado) {
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
