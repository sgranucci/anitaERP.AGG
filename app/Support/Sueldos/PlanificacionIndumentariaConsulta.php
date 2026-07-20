<?php

namespace App\Support\Sueldos;

use App\Models\Stock\Articulo_Saldo_Deposito;
use App\Models\Sueldos\Configuracion_Indumentaria_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Planificación de compra de indumentaria: cruza la dotación de los empleados activos
 * (agrupamiento × sexo) con lo entregado (vigente para EPP con vida útil, o del año
 * para prendas anuales) y el stock del depósito de origen, y sugiere la compra.
 *
 *   pendiente = max(0, cupo_total − entregado)
 *   sugerido  = max(0, ceil(pendiente × (1 + %pedido/100)) − stock)
 */
class PlanificacionIndumentariaConsulta
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function filas(array $filtros, EmpresaRepositoryInterface $empresaRepository): array
    {
        $anio = (int) date('Y');
        $hoy = Carbon::today()->toDateString();
        $config = Configuracion_Indumentaria_Sueldos::actual();
        $depositoId = (int) ($config->deposito_id ?? 0);

        // 1) Empleados elegibles (activos con agrupamiento) según filtros.
        $empModel = Empleado_Sueldos::query()
            ->where('empleado_sueldos.estado', EmpleadoEstados::ACTIVO)
            ->whereNotNull('empleado_sueldos.agrupamiento_id')
            ->where('empleado_sueldos.agrupamiento_id', '>', 0);

        $empresaRepository->aplicarFiltroEmpresasAsignadas($empModel, 'empleado_sueldos.empresa_id');

        if (($filtros['empresa_scope'] ?? 'una') !== 'todas' && ! empty($filtros['empresa_id'])) {
            $empModel->where('empleado_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $empModel->where('empleado_sueldos.agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }

        $empleados = $empModel->get(['empleado_sueldos.id', 'empleado_sueldos.agrupamiento_id', 'empleado_sueldos.sexo']);
        if ($empleados->isEmpty()) {
            return [];
        }

        $sexoFiltro = $filtros['sexo'] ?? null;
        $agrupIds = $empleados->pluck('agrupamiento_id')->unique()->values()->all();

        // 2) Dotación por (agrupamiento, sexo, prenda).
        $dotQ = Prenda_Agrupamiento_Sueldos::query()
            ->selectRaw('agrupamiento_id, sexo, prenda_id, SUM(limite_anual) as limite')
            ->whereIn('agrupamiento_id', $agrupIds)
            ->groupBy('agrupamiento_id', 'sexo', 'prenda_id');
        if (! empty($filtros['prenda_id'])) {
            $dotQ->where('prenda_id', (int) $filtros['prenda_id']);
        }
        $dotIndex = [];
        foreach ($dotQ->get() as $d) {
            $dotIndex[$d->agrupamiento_id.'|'.$d->sexo][(int) $d->prenda_id] = (float) $d->limite;
        }
        if ($dotIndex === []) {
            return [];
        }

        // 3) Cupo total y empleados con derecho por prenda.
        $cupo = [];
        $empleadosPorPrenda = [];
        $empIds = [];
        foreach ($empleados as $e) {
            $sexo = Prenda_Agrupamiento_Sueldos::sexoDesdeEmpleado($e->sexo);
            if ($sexoFiltro !== null && $sexo !== $sexoFiltro) {
                continue;
            }
            $key = $e->agrupamiento_id.'|'.$sexo;
            if (! isset($dotIndex[$key])) {
                continue;
            }
            $empIds[(int) $e->id] = true;
            foreach ($dotIndex[$key] as $prendaId => $limite) {
                $cupo[$prendaId] = ($cupo[$prendaId] ?? 0) + $limite;
                $empleadosPorPrenda[$prendaId][(int) $e->id] = true;
            }
        }
        if ($cupo === []) {
            return [];
        }

        $prendaIds = array_keys($cupo);
        $empIds = array_keys($empIds);

        // 4) Entregado sobre empleados elegibles (anual y vigente).
        $entregadoAnual = [];
        $entregadoVigente = [];
        if ($empIds !== []) {
            $base = DB::table('entrega_prenda_articulo_sueldos as l')
                ->join('entrega_prenda_sueldos as e', 'e.id', '=', 'l.entrega_id')
                ->whereIn('e.empleado_id', $empIds)
                ->whereIn('l.prenda_id', $prendaIds);

            $entregadoAnual = (clone $base)
                ->where('e.anio', $anio)
                ->groupBy('l.prenda_id')
                ->selectRaw('l.prenda_id as pid, SUM(l.cantidad) as total')
                ->pluck('total', 'pid')
                ->map(fn ($v) => (float) $v)->all();

            $entregadoVigente = (clone $base)
                ->where(function ($q) use ($hoy) {
                    $q->whereNull('l.vence_el')->orWhere('l.vence_el', '>=', $hoy);
                })
                ->groupBy('l.prenda_id')
                ->selectRaw('l.prenda_id as pid, SUM(l.cantidad) as total')
                ->pluck('total', 'pid')
                ->map(fn ($v) => (float) $v)->all();
        }

        // 5) Stock del depósito de origen por prenda (suma de sus variantes/SKU).
        $stockPorPrenda = [];
        if ($depositoId > 0) {
            $variantes = Prenda_Articulo_Sueldos::query()
                ->whereIn('prenda_id', $prendaIds)
                ->whereNotNull('articulo_id')
                ->get(['prenda_id', 'articulo_id']);
            $artByPrenda = [];
            $allArt = [];
            foreach ($variantes as $v) {
                $artByPrenda[(int) $v->prenda_id][] = (int) $v->articulo_id;
                $allArt[(int) $v->articulo_id] = true;
            }
            $saldos = [];
            if ($allArt !== []) {
                $saldos = Articulo_Saldo_Deposito::query()
                    ->where('deposito_id', $depositoId)
                    ->whereIn('articulo_id', array_keys($allArt))
                    ->pluck('cantidad', 'articulo_id')
                    ->map(fn ($v) => (float) $v)->all();
            }
            foreach ($artByPrenda as $pid => $arts) {
                $s = 0.0;
                foreach (array_unique($arts) as $aid) {
                    $s += (float) ($saldos[$aid] ?? 0);
                }
                $stockPorPrenda[$pid] = $s;
            }
        }

        // 6) Meta de las prendas.
        $metas = Prenda_Sueldos::query()
            ->whereIn('id', $prendaIds)
            ->get(['id', 'codigo', 'descripcion', 'es_seguridad', 'vida_util_meses', 'norma', 'porcentaje_pedido'])
            ->keyBy('id');

        // 7) Armar filas.
        $filas = [];
        foreach ($prendaIds as $pid) {
            $meta = $metas->get($pid);
            if ($meta === null) {
                continue;
            }
            $vidaUtil = (int) ($meta->vida_util_meses ?? 0);
            $esEpp = (bool) $meta->es_seguridad;
            if (! empty($filtros['solo_epp']) && ! $esEpp) {
                continue;
            }

            $cupoP = (float) $cupo[$pid];
            $entregado = $vidaUtil > 0 ? (float) ($entregadoVigente[$pid] ?? 0) : (float) ($entregadoAnual[$pid] ?? 0);
            $pendiente = max(0.0, round($cupoP - $entregado, 3));
            $stock = (float) ($stockPorPrenda[$pid] ?? 0);
            $porc = (float) ($meta->porcentaje_pedido ?? 0);
            $requerido = $pendiente * (1 + $porc / 100);
            $sugerido = max(0, (int) ceil($requerido - $stock));

            $fila = [
                'prenda_id' => (int) $pid,
                'codigo' => (int) $meta->codigo,
                'descripcion' => (string) $meta->descripcion,
                'es_seguridad' => $esEpp,
                'norma' => $meta->norma,
                'modo' => $vidaUtil > 0 ? 'vencimiento' : 'anual',
                'vida_util_meses' => $vidaUtil,
                'empleados' => count($empleadosPorPrenda[$pid] ?? []),
                'cupo' => round($cupoP, 2),
                'entregado' => round($entregado, 2),
                'pendiente' => round($pendiente, 2),
                'stock' => round($stock, 2),
                'porcentaje_pedido' => $porc,
                'sugerido' => $sugerido,
            ];

            if (! empty($filtros['solo_sugerido']) && $sugerido <= 0) {
                continue;
            }

            $filas[] = $fila;
        }

        usort($filas, fn ($a, $b) => [$b['sugerido'], $a['descripcion']] <=> [$a['sugerido'], $b['descripcion']]);

        return $filas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     * @return array{prendas:int, cupo:float, entregado:float, pendiente:float, stock:float, sugerido:int}
     */
    public static function totales(array $filas): array
    {
        return [
            'prendas' => count($filas),
            'cupo' => round(array_sum(array_column($filas, 'cupo')), 2),
            'entregado' => round(array_sum(array_column($filas, 'entregado')), 2),
            'pendiente' => round(array_sum(array_column($filas, 'pendiente')), 2),
            'stock' => round(array_sum(array_column($filas, 'stock')), 2),
            'sugerido' => (int) array_sum(array_column($filas, 'sugerido')),
        ];
    }
}
