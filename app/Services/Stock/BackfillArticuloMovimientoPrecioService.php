<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Venta_Emision;
use App\Support\Stock\ArticuloMovimientoPrecioHistoricoSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;

final class BackfillArticuloMovimientoPrecioService
{
    private bool $dryRun = false;

    private int $chunkSize = 500;

    public function dryRun(bool $dryRun = true): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function chunkSize(int $size): self
    {
        $this->chunkSize = max(50, $size);

        return $this;
    }

    /**
     * @return array{ventas: int, insumos: int, movimientos: int}
     */
    public function ejecutar(string $solo = 'todos'): array
    {
        $contadores = ['ventas' => 0, 'insumos' => 0, 'movimientos' => 0];

        if ($solo === 'todos' || $solo === 'ventas') {
            $contadores['ventas'] = $this->backfillVentas();
        }
        if ($solo === 'todos' || $solo === 'insumos') {
            $contadores['insumos'] = $this->backfillInsumos();
        }
        if ($solo === 'todos' || $solo === 'movimientos') {
            $contadores['movimientos'] = $this->backfillMovimientosSinPrecio();
        }

        return $contadores;
    }

    public function backfillVentas(): int
    {
        $actualizados = 0;

        Articulo_Movimiento::query()
            ->whereNull('deleted_at')
            ->whereNotNull('venta_id')
            ->where('precio', 0)
            ->tap(static fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoNoEsInsumo($q))
            ->orderBy('id')
            ->chunkById($this->chunkSize, function ($movimientos) use (&$actualizados) {
                foreach ($movimientos as $movimiento) {
                    $emision = $this->resolverVentaEmision($movimiento);
                    if ($emision === null) {
                        continue;
                    }

                    $descuento = (float) ($emision->descuento ?? 0);
                    $precioNet = round((float) $emision->precio * (1 - $descuento / 100.), 6);
                    if ($precioNet <= 0) {
                        continue;
                    }

                    if (! $this->dryRun) {
                        $movimiento->update(['precio' => $precioNet, 'costo' => 0]);
                    }
                    $actualizados++;
                }
            });

        return $actualizados;
    }

    public function backfillInsumos(): int
    {
        $actualizados = 0;

        Articulo_Movimiento::query()
            ->whereNull('deleted_at')
            ->where('costo', 0)
            ->where('precio', 0)
            ->tap(static fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoEsInsumo($q))
            ->orderBy('id')
            ->chunkById($this->chunkSize, function ($movimientos) use (&$actualizados) {
                $ids = $movimientos->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
                $precios = ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraInsumoPorArticuloIds($ids);

                foreach ($movimientos as $movimiento) {
                    $articuloId = (int) $movimiento->articulo_id;
                    $dato = $precios[$articuloId] ?? null;
                    if ($dato === null || (float) ($dato['costo'] ?? 0) <= 0) {
                        continue;
                    }

                    if (! $this->dryRun) {
                        $update = [
                            'precio' => 0,
                            'costo' => $dato['costo'],
                        ];
                        if (! empty($dato['moneda_id']) && empty($movimiento->moneda_id)) {
                            $update['moneda_id'] = $dato['moneda_id'];
                        }
                        $movimiento->update($update);
                    }
                    $actualizados++;
                }
            });

        return $actualizados;
    }

    public function backfillMovimientosSinPrecio(): int
    {
        $actualizados = 0;

        Articulo_Movimiento::query()
            ->whereNull('deleted_at')
            ->whereNull('venta_id')
            ->where('precio', 0)
            ->where('costo', 0)
            ->orderBy('id')
            ->chunkById($this->chunkSize, function ($movimientos) use (&$actualizados) {
                $ids = $movimientos->pluck('articulo_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
                $precios = ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraMovimientoPorArticuloIds($ids);

                foreach ($movimientos as $movimiento) {
                    $articuloId = (int) $movimiento->articulo_id;
                    $dato = $precios[$articuloId] ?? null;
                    if ($dato === null || (float) ($dato['costo'] ?? 0) <= 0) {
                        continue;
                    }

                    if (! $this->dryRun) {
                        $update = [
                            'precio' => $dato['precio'],
                            'costo' => $dato['costo'],
                        ];
                        if (! empty($dato['moneda_id']) && empty($movimiento->moneda_id)) {
                            $update['moneda_id'] = $dato['moneda_id'];
                        }
                        $movimiento->update($update);
                    }
                    $actualizados++;
                }
            });

        return $actualizados;
    }

    /**
     * Estadísticas rápidas sin modificar datos.
     *
     * @return array{ventas_pendientes: int, insumos_pendientes: int, movimientos_pendientes: int}
     */
    public function contarPendientes(): array
    {
        return [
            'ventas_pendientes' => (int) Articulo_Movimiento::query()
                ->whereNull('deleted_at')
                ->whereNotNull('venta_id')
                ->where('precio', 0)
                ->tap(static fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoNoEsInsumo($q))
                ->count(),
            'insumos_pendientes' => (int) Articulo_Movimiento::query()
                ->whereNull('deleted_at')
                ->where('costo', 0)
                ->where('precio', 0)
                ->tap(static fn ($q) => GastronomiaVentaDetalleSupport::aplicarWhereConceptoEsInsumo($q))
                ->count(),
            'movimientos_pendientes' => (int) Articulo_Movimiento::query()
                ->whereNull('deleted_at')
                ->whereNull('venta_id')
                ->where('precio', 0)
                ->where('costo', 0)
                ->count(),
        ];
    }

    private function resolverVentaEmision(Articulo_Movimiento $movimiento): ?Venta_Emision
    {
        $query = Venta_Emision::query()
            ->where('venta_id', (int) $movimiento->venta_id)
            ->where('articulo_id', (int) $movimiento->articulo_id);

        $ventaEmisionId = (int) ($movimiento->venta_emision_id ?? 0);
        if ($ventaEmisionId > 0) {
            $query->where('id', $ventaEmisionId);
        }

        $emision = $query->orderByDesc('id')->first();
        if ($emision !== null) {
            return $emision;
        }

        if ($ventaEmisionId > 0) {
            return null;
        }

        return Venta_Emision::query()
            ->where('venta_id', (int) $movimiento->venta_id)
            ->where('articulo_id', (int) $movimiento->articulo_id)
            ->orderByDesc('id')
            ->first();
    }
}
