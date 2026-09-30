<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\DevolucionHistorial;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\MotivoDevolucion;
use App\Models\Ventas\Venta;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\DevolucionHistorialListadoFiltros;
use App\Support\Ventas\FacturacionLocal\DevolucionHistorialOrigenSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class DevolucionHistorialService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeListado(array $filtros, bool $paginar = true)
    {
        $query = $this->queryListado();
        DevolucionHistorialListadoFiltros::aplicar($query, $filtros);
        $query->orderByDesc('devolucion_historial.fecha')->orderByDesc('devolucion_historial.id');

        return $paginar ? $query->paginate(15) : $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{cantidad:int,importe:float,sin_stock:int}
     */
    public function totales(array $filtros): array
    {
        $query = DevolucionHistorial::query();
        DevolucionHistorialListadoFiltros::aplicar($query, $filtros);

        $cantidad = (int) (clone $query)->count();
        $importe = round((float) (clone $query)->sum('importe'), 2);
        $sinStock = (int) (clone $query)->where('vuelve_stock', false)->count();

        return [
            'cantidad' => $cantidad,
            'importe' => $importe,
            'sin_stock' => $sinStock,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function registrarPos(LocalVenta $local, Venta $ventaNc, ?Venta $ventaFac, array $lineas): void
    {
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $this->insertar($this->filaDesdeLinea($linea, [
                'origen' => DevolucionHistorialOrigenSupport::POS,
                'local_venta_id' => (int) $local->id,
                'empresa_id' => (int) ($local->empresa_id ?: 0) ?: null,
                'venta_id' => (int) $ventaNc->id,
                'venta_origen_id' => $ventaFac ? (int) $ventaFac->id : null,
                'fecha' => $ventaNc->fecha?->format('Y-m-d') ?: now()->toDateString(),
            ]));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public function registrarNotaCredito(
        LocalVenta $local,
        Venta $ventaNc,
        Venta $ventaOrigen,
        MotivoDevolucion $motivo,
        array $lineas,
    ): void {
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $linea['motivo_devolucion_id'] = (int) $motivo->id;
            $linea['motivo_codigo'] = (string) $motivo->codigo;
            $linea['motivo_nombre'] = (string) $motivo->nombre;
            $linea['vuelve_stock'] = (bool) $motivo->vuelve_stock;
            $this->insertar($this->filaDesdeLinea($linea, [
                'origen' => DevolucionHistorialOrigenSupport::NOTA_CREDITO,
                'local_venta_id' => (int) $local->id,
                'empresa_id' => (int) ($local->empresa_id ?: $ventaNc->empresa_id ?: 0) ?: null,
                'venta_id' => (int) $ventaNc->id,
                'venta_origen_id' => (int) $ventaOrigen->id,
                'fecha' => $ventaNc->fecha?->format('Y-m-d') ?: now()->toDateString(),
            ]));
        }
    }

    public function registrarTiendanube(CambioDevolucionMarketplace $cambio, Venta $ventaNc): void
    {
        $motivo = MotivoDevolucionSupport::resolverDeCambio($cambio);
        if (! $motivo) {
            return;
        }

        $cambio->loadMissing('lineas.articulo');
        $lineas = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_DEVOLVER)
            ->values();
        if ($lineas->isEmpty()) {
            $lineas = $cambio->lineas;
        }

        foreach ($lineas as $linea) {
            $this->insertar($this->filaDesdeLinea([
                'articulo_id' => (int) $linea->articulo_id,
                'sku' => (string) ($linea->articulo->sku ?? ''),
                'descripcion' => (string) ($linea->descripcion ?: ($linea->articulo->descripcion ?? '')),
                'combinacion_id' => (int) ($linea->combinacion_id ?: 0),
                'talle_id' => (int) ($linea->talle_id ?: 0),
                'color_id' => (int) ($linea->color_id ?: 0),
                'cantidad' => (float) $linea->cantidad,
                'precio' => (float) $linea->precio_unitario,
                'motivo_devolucion_id' => (int) $motivo->id,
                'motivo_codigo' => (string) $motivo->codigo,
                'motivo_nombre' => (string) $motivo->nombre,
                'vuelve_stock' => (bool) $motivo->vuelve_stock,
            ], [
                'origen' => DevolucionHistorialOrigenSupport::TIENDANUBE,
                'local_venta_id' => (int) $cambio->local_venta_id,
                'empresa_id' => (int) ($cambio->empresa_id ?: 0) ?: null,
                'venta_id' => (int) $ventaNc->id,
                'venta_origen_id' => (int) $cambio->venta_original_id,
                'cambio_devolucion_id' => (int) $cambio->id,
                'fecha' => $ventaNc->fecha?->format('Y-m-d') ?: now()->toDateString(),
            ]));
        }
    }

    public function queryListado(): Builder
    {
        return DevolucionHistorial::query()
            ->select([
                'devolucion_historial.*',
                'local_venta.codigo as local_codigo',
                'local_venta.nombre as local_nombre',
                'empresa.nombre as nombreempresa',
                'usuario.nombre as usuario_nombre',
                'venta.codigo as venta_codigo',
                'cambio_devolucion_marketplace.numero as cambio_numero',
            ])
            ->leftJoin('local_venta', 'local_venta.id', '=', 'devolucion_historial.local_venta_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'devolucion_historial.empresa_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'devolucion_historial.usuario_id')
            ->leftJoin('venta', 'venta.id', '=', 'devolucion_historial.venta_id')
            ->leftJoin('cambio_devolucion_marketplace', 'cambio_devolucion_marketplace.id', '=', 'devolucion_historial.cambio_devolucion_id');
    }

    /**
     * @param  array<string, mixed>  $linea
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function filaDesdeLinea(array $linea, array $ctx): array
    {
        $articuloId = (int) ($linea['articulo_id'] ?? 0);
        $sku = trim((string) ($linea['sku'] ?? ''));
        $descripcion = trim((string) ($linea['descripcion'] ?? ''));
        if ($articuloId > 0 && ($sku === '' || $descripcion === '')) {
            $art = Articulo::query()->find($articuloId, ['id', 'sku', 'descripcion']);
            if ($art) {
                $sku = $sku !== '' ? $sku : (string) $art->sku;
                $descripcion = $descripcion !== '' ? $descripcion : (string) $art->descripcion;
            }
        }

        $cantidad = abs((float) ($linea['cantidad'] ?? 0));
        $precio = (float) ($linea['precio'] ?? 0);
        $dto = (float) ($linea['descuento'] ?? $linea['descuentolinea'] ?? 0);
        $importe = round($cantidad * $precio * (1 - $dto / 100), 2);

        $motivoId = (int) ($linea['motivo_devolucion_id'] ?? 0);
        $vuelve = array_key_exists('vuelve_stock', $linea)
            ? (bool) $linea['vuelve_stock']
            : true;

        return [
            'fecha' => $ctx['fecha'] ?? now()->toDateString(),
            'origen' => (string) ($ctx['origen'] ?? DevolucionHistorialOrigenSupport::POS),
            'motivo_devolucion_id' => $motivoId,
            'motivo_codigo' => (string) ($linea['motivo_codigo'] ?? ''),
            'motivo_nombre' => (string) ($linea['motivo_nombre'] ?? ''),
            'vuelve_stock' => $vuelve,
            'articulo_id' => $articuloId > 0 ? $articuloId : null,
            'sku' => $sku !== '' ? mb_substr($sku, 0, 40) : null,
            'descripcion' => $descripcion !== '' ? mb_substr($descripcion, 0, 255) : null,
            'combinacion_id' => ((int) ($linea['combinacion_id'] ?? 0)) ?: null,
            'talle_id' => ((int) ($linea['talle_id'] ?? 0)) ?: null,
            'color_id' => ((int) ($linea['color_id'] ?? 0)) ?: null,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'importe' => $importe,
            'local_venta_id' => ((int) ($ctx['local_venta_id'] ?? 0)) ?: null,
            'empresa_id' => ((int) ($ctx['empresa_id'] ?? 0)) ?: null,
            'venta_id' => ((int) ($ctx['venta_id'] ?? 0)) ?: null,
            'venta_origen_id' => ((int) ($ctx['venta_origen_id'] ?? 0)) ?: null,
            'cambio_devolucion_id' => ((int) ($ctx['cambio_devolucion_id'] ?? 0)) ?: null,
            'usuario_id' => ((int) (Auth::id() ?: 0)) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function insertar(array $fila): void
    {
        if ((int) ($fila['motivo_devolucion_id'] ?? 0) <= 0) {
            return;
        }
        DevolucionHistorial::query()->create($fila);
    }
}
