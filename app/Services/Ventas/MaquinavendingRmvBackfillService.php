<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Ventas\Venta;
use App\Support\Contable\CierreRendicionMaquinavendingGrupoSupport;
use App\Support\Ventas\MaquinavendingRmvMontosSupport;
use App\Support\Ventas\MaquinavendingRmvTipoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Emite RMV faltantes para cierres contables vending ya grabados (sin venta_id).
 */
class MaquinavendingRmvBackfillService
{
    public function __construct(
        private readonly MaquinavendingRmvEmisionService $emisionService,
    ) {
    }

    /**
     * @return array{
     *   grupos_encontrados: int,
     *   emitidos: int,
     *   omitidos: int,
     *   errores: list<string>,
     *   detalle: list<array<string, mixed>>
     * }
     */
    public function ejecutar(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = false,
        ?int $empresaId = null,
    ): array {
        $rendiciones = $this->rendicionesCerradasSinRmv($fechaDesde, $fechaHasta, $empresaId);
        $grupos = $this->agruparPorAsiento($rendiciones);

        $emitidos = 0;
        $omitidos = 0;
        $errores = [];
        $detalle = [];

        foreach ($grupos as $grupo) {
            /** @var Collection<int, RendicionMaquinavendingCaja> $filas */
            $filas = $grupo['rendiciones'];
            $fechaDia = (string) $grupo['fecha_dia'];
            $montos = MaquinavendingRmvMontosSupport::desdeRendiciones($filas);

            $filaDetalle = [
                'empresa_id' => (int) $grupo['empresa_id'],
                'fecha_dia' => $fechaDia,
                'puntoventa_codigo' => (string) $grupo['puntoventa_codigo'],
                'asiento_id' => (int) $grupo['asiento_id'],
                'asiento_numero' => (string) $grupo['asiento_numero'],
                'rendicion_ids' => $filas->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'total' => $montos['total'],
                'gravado' => $montos['gravado'],
                'iva' => $montos['iva'],
                'exento' => $montos['exento'],
                'venta_codigo' => null,
                'venta_id' => null,
                'estado' => 'pendiente',
            ];

            if ($montos['total'] <= 0.0001) {
                $filaDetalle['estado'] = 'omitido_total_cero';
                $omitidos++;
                $detalle[] = $filaDetalle;

                continue;
            }

            if ($dryRun) {
                $filaDetalle['estado'] = 'dry_run';
                $emitidos++;
                $detalle[] = $filaDetalle;

                continue;
            }

            try {
                $resultado = DB::transaction(function () use ($filas, $fechaDia) {
                    $ids = $filas->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $bloqueadas = RendicionMaquinavendingCaja::query()
                        ->whereIn('id', $ids)
                        ->lockForUpdate()
                        ->with([
                            'maquinavendingRendicion.articulos.articulo',
                            'puntoventaCae',
                        ])
                        ->get();

                    foreach ($bloqueadas as $r) {
                        if ((int) ($r->venta_id ?? 0) > 0) {
                            throw new \InvalidArgumentException(
                                'Rendición #'.$r->id.' ya tiene venta_id='.$r->venta_id,
                            );
                        }
                        if ((int) ($r->asiento_id ?? 0) <= 0) {
                            throw new \InvalidArgumentException(
                                'Rendición #'.$r->id.' sin asiento; no aplica backfill RMV.',
                            );
                        }
                    }

                    $rmv = $this->emisionService->emitirParaGrupo($bloqueadas, $fechaDia);
                    foreach ($bloqueadas as $r) {
                        $r->update(['venta_id' => (int) $rmv['venta_id']]);
                    }

                    return $rmv;
                });

                $filaDetalle['estado'] = 'emitido';
                $filaDetalle['venta_id'] = (int) $resultado['venta_id'];
                $filaDetalle['venta_codigo'] = (string) $resultado['codigo'];
                $emitidos++;
            } catch (\Throwable $e) {
                $filaDetalle['estado'] = 'error';
                $errores[] = sprintf(
                    'Empresa %d PV %s %s asiento %s: %s',
                    (int) $grupo['empresa_id'],
                    (string) $grupo['puntoventa_codigo'],
                    $fechaDia,
                    (string) $grupo['asiento_numero'],
                    $e->getMessage(),
                );
            }

            $detalle[] = $filaDetalle;
        }

        return [
            'grupos_encontrados' => count($grupos),
            'emitidos' => $emitidos,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    /**
     * Recalcula impuestos de RMV ya emitidos (neto = total/1.21, cuadra contra asiento).
     *
     * @return array{
     *   encontrados: int,
     *   recalculados: int,
     *   errores: list<string>,
     *   detalle: list<array<string, mixed>>
     * }
     */
    public function recalcularExistentes(
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun = false,
        ?int $empresaId = null,
    ): array {
        $tipoId = MaquinavendingRmvTipoSupport::tipoId();
        $q = Venta::query()
            ->where('tipotransaccion_id', $tipoId)
            ->whereDate('fechajornada', '>=', $fechaDesde)
            ->whereDate('fechajornada', '<=', $fechaHasta)
            ->orderBy('fechajornada')
            ->orderBy('id');

        if ($empresaId !== null && $empresaId > 0) {
            $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
        }

        $ventas = $q->get();
        $recalculados = 0;
        $errores = [];
        $detalle = [];

        foreach ($ventas as $venta) {
            $rendiciones = RendicionMaquinavendingCaja::query()
                ->with(['maquinavendingRendicion.articulos.articulo'])
                ->where('venta_id', $venta->id)
                ->get();
            $montos = $rendiciones->isEmpty()
                ? MaquinavendingRmvMontosSupport::partirTotalConIva((float) $venta->total)
                : MaquinavendingRmvMontosSupport::desdeRendiciones($rendiciones);

            $fila = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'fecha_dia' => (string) $venta->fechajornada,
                'total' => $montos['total'],
                'gravado' => $montos['gravado'],
                'iva' => $montos['iva'],
                'exento' => $montos['exento'],
                'estado' => $dryRun ? 'dry_run' : 'pendiente',
            ];

            if ($dryRun) {
                $recalculados++;
                $detalle[] = $fila;

                continue;
            }

            try {
                $out = $this->emisionService->recalcularImpuestos($venta);
                $fila['estado'] = 'recalculado';
                $fila['gravado'] = $out['gravado'];
                $fila['iva'] = $out['iva'];
                $fila['exento'] = $out['exento'];
                $recalculados++;
            } catch (\Throwable $e) {
                $fila['estado'] = 'error';
                $errores[] = ($venta->codigo ?? '#'.$venta->id).': '.$e->getMessage();
            }

            $detalle[] = $fila;
        }

        return [
            'encontrados' => $ventas->count(),
            'recalculados' => $recalculados,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return Collection<int, RendicionMaquinavendingCaja>
     */
    private function rendicionesCerradasSinRmv(
        string $fechaDesde,
        string $fechaHasta,
        ?int $empresaId,
    ): Collection {
        $q = RendicionMaquinavendingCaja::query()
            ->with([
                'maquinavendingRendicion.articulos.articulo',
                'puntoventaCae:id,codigo,nombre',
                'asiento:id,numeroasiento,fecha',
            ])
            ->whereNotNull('asiento_id')
            ->whereNull('venta_id')
            ->where(function ($w) use ($fechaDesde, $fechaHasta) {
                $w->whereHas('maquinavendingRendicion', function ($mr) use ($fechaDesde, $fechaHasta) {
                    $mr->whereDate('fecha_jornada', '>=', $fechaDesde)
                        ->whereDate('fecha_jornada', '<=', $fechaHasta);
                })->orWhere(function ($q) use ($fechaDesde, $fechaHasta) {
                    $q->whereDoesntHave('maquinavendingRendicion')
                        ->whereDate('fecharendicion', '>=', $fechaDesde)
                        ->whereDate('fecharendicion', '<=', $fechaHasta);
                });
            })
            ->orderBy('empresa_id')
            ->orderBy('id');

        if ($empresaId !== null && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        return $q->get();
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return list<array{
     *   empresa_id: int,
     *   fecha_dia: string,
     *   puntoventa_codigo: string,
     *   asiento_id: int,
     *   asiento_numero: string,
     *   rendiciones: Collection<int, RendicionMaquinavendingCaja>
     * }>
     */
    private function agruparPorAsiento(Collection $rendiciones): array
    {
        /** @var array<string, array<string, mixed>> $grupos */
        $grupos = [];

        foreach ($rendiciones as $rendicion) {
            $asientoId = (int) ($rendicion->asiento_id ?? 0);
            if ($asientoId <= 0) {
                continue;
            }
            $fechaDia = CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($rendicion);
            $key = $asientoId.'|'.$fechaDia.'|'.(int) $rendicion->puntoventa_cae_id;

            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'empresa_id' => (int) $rendicion->empresa_id,
                    'fecha_dia' => $fechaDia,
                    'puntoventa_codigo' => (string) ($rendicion->puntoventaCae?->codigo ?? ''),
                    'asiento_id' => $asientoId,
                    'asiento_numero' => (string) ($rendicion->asiento?->numeroasiento ?? $asientoId),
                    'rendiciones' => collect(),
                ];
            }
            $grupos[$key]['rendiciones']->push($rendicion);
        }

        return array_values($grupos);
    }
}
