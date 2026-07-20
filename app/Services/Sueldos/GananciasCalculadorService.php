<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Ganancia_Linea_Sueldos;
use App\Models\Sueldos\Ganancia_Resultado_Sueldos;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use App\Support\Sueldos\Ganancias\ContextoGanancias;
use App\Support\Sueldos\Ganancias\GananciasArt30Resolver;
use App\Support\Sueldos\Ganancias\GananciasArt94Resolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Motor de Ganancias 4ta categoria: recorre el plan de lineas (por formula)
 * mes a mes y produce la planilla anual + retencion del mes.
 *
 * Sin ifs por descripcion: toda la logica vive en ganancia_linea_sueldos.formula
 * y en las tablas Art.94 / Art.30 con vigencia.
 */
class GananciasCalculadorService
{
    private EvaluadorFormula $motor;

    private GananciasArt94Resolver $art94;

    private GananciasArt30Resolver $art30;

    public function __construct()
    {
        $this->motor = new EvaluadorFormula;
        $this->art94 = new GananciasArt94Resolver;
        $this->art30 = new GananciasArt30Resolver;
    }

    /**
     * Calcula la planilla de un empleado para un anio (hasta $hastaMes).
     *
     * @param  array<int, array<string, float>>  $entradasPorMes  mes => [CODIGO => valor]
     * @param  array<int, array<string, float>>  $cantidadesPorMes  mes => [CODIGO => cant]
     * @return array{
     *   matriz: array<int, array<string, float>>,
     *   lineas: list<array{codigo: string, descripcion: string, orden: int}>,
     *   retencion_mes: float,
     *   errores: list<string>
     * }
     */
    public function calcularAnio(
        int $anio,
        int $hastaMes = 12,
        array $entradasPorMes = [],
        array $cantidadesPorMes = []
    ): array {
        $lineas = Ganancia_Linea_Sueldos::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        $ctx = new ContextoGanancias($anio, $this->art94, $this->art30);
        $errores = [];

        for ($mes = 1; $mes <= $hastaMes; $mes++) {
            $ctx->setMes($mes);
            $ents = [];
            foreach ($entradasPorMes[$mes] ?? [] as $cod => $val) {
                $ents[strtoupper((string) $cod)] = (float) $val;
            }
            $cants = [];
            foreach ($cantidadesPorMes[$mes] ?? [] as $cod => $val) {
                $cants[strtoupper((string) $cod)] = (float) $val;
            }
            $ctx->setEntradasMes($mes, $ents, $cants);

            foreach ($lineas as $linea) {
                try {
                    $valor = $this->evaluarLinea($ctx, $linea);
                } catch (FormulaException $e) {
                    $errores[] = "{$linea->codigo} mes {$mes}: ".$e->getMessage();
                    $valor = 0.0;
                }
                $ctx->setLinea($linea->codigo, round($valor, 2));
            }
        }

        return [
            'matriz' => $ctx->matriz(),
            'lineas' => $lineas->map(fn ($l) => [
                'codigo' => $l->codigo,
                'descripcion' => $l->descripcion,
                'orden' => $l->orden,
            ])->all(),
            'retencion_mes' => $ctx->getLinea('RET_GANANCIAS', $hastaMes),
            'errores' => $errores,
        ];
    }

    /**
     * Calcula y persiste resultado para un empleado. Las entradas se leen de
     * ganancia_movimiento_sueldos (+ inyeccion opcional).
     *
     * @param  array<int, array<string, float>>  $entradasExtra
     * @param  array<int, array<string, float>>  $cantidadesExtra
     * @return array{retencion_mes: float, matriz: array, errores: list<string>}
     */
    public function calcularYPersistir(
        int $empleadoId,
        ?int $empresaId,
        int $anio,
        int $hastaMes = 12,
        array $entradasExtra = [],
        array $cantidadesExtra = [],
        ?int $liquidacionId = null
    ): array {
        $movs = $this->cargarMovimientos($empleadoId, $anio, $hastaMes);
        $entradas = $this->fusionar($movs['valores'], $entradasExtra);
        $cantidades = $this->fusionar($movs['cantidades'], $cantidadesExtra);

        $res = $this->calcularAnio($anio, $hastaMes, $entradas, $cantidades);

        DB::transaction(function () use ($empleadoId, $empresaId, $anio, $hastaMes, $res, $liquidacionId) {
            Ganancia_Resultado_Sueldos::query()
                ->where('empleado_id', $empleadoId)
                ->where('anio', $anio)
                ->where('mes', '<=', $hastaMes)
                ->delete();

            $ahora = Carbon::now();
            $filas = [];
            foreach ($res['matriz'] as $mes => $vals) {
                foreach ($vals as $codigo => $valor) {
                    $filas[] = [
                        'empresa_id' => $empresaId,
                        'empleado_id' => $empleadoId,
                        'anio' => $anio,
                        'mes' => $mes,
                        'linea_codigo' => $codigo,
                        'valor' => $valor,
                        'cantidad' => 0,
                        'liquidacion_id' => $liquidacionId,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }
            }
            foreach (array_chunk($filas, 500) as $lote) {
                Ganancia_Resultado_Sueldos::insert($lote);
            }
        });

        return $res;
    }

    private function evaluarLinea(ContextoGanancias $ctx, Ganancia_Linea_Sueldos $linea): float
    {
        if ($linea->origen === 'entrada') {
            return (float) $ctx->funcion('entrada', [$linea->codigo]);
        }

        $formula = trim((string) $linea->formula);
        if ($formula === '') {
            if ($linea->origen === 'deduccion_art30' && $linea->deduccion_codigo) {
                return -1 * $ctx->funcion('deduccion_art30', [$linea->deduccion_codigo]);
            }

            return 0.0;
        }

        return (float) $this->motor->evaluar($formula, $ctx);
    }

    /**
     * @return array{valores: array<int, array<string, float>>, cantidades: array<int, array<string, float>>}
     */
    private function cargarMovimientos(int $empleadoId, int $anio, int $hastaMes): array
    {
        $valores = [];
        $cantidades = [];
        try {
            $desde = $anio * 100 + 1;
            $hasta = $anio * 100 + $hastaMes;
            $rows = DB::table('ganancia_movimiento_sueldos')
                ->where('empleado_id', $empleadoId)
                ->whereBetween('periodo', [$desde, $hasta])
                ->get(['periodo', 'linea_codigo', 'valor', 'cantidad']);
            foreach ($rows as $r) {
                $mes = (int) $r->periodo % 100;
                $cod = strtoupper((string) $r->linea_codigo);
                $valores[$mes][$cod] = (float) $r->valor;
                $cantidades[$mes][$cod] = (float) $r->cantidad;
            }
        } catch (\Throwable $e) {
            // tabla aun no migrada
        }

        return ['valores' => $valores, 'cantidades' => $cantidades];
    }

    /**
     * @param  array<int, array<string, float>>  $base
     * @param  array<int, array<string, float>>  $extra
     * @return array<int, array<string, float>>
     */
    private function fusionar(array $base, array $extra): array
    {
        foreach ($extra as $mes => $vals) {
            foreach ($vals as $cod => $val) {
                $base[$mes][strtoupper((string) $cod)] = (float) $val;
            }
        }

        return $base;
    }
}
