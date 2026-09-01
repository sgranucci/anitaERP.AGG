<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use App\Support\Stock\ArticuloPrecioPromedioCompraSupport;
use App\Support\Stock\TransferenciaMercaderiaCostoSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use App\Support\Stock\TransferenciaMercaderiaTitoRecalculoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula TRA TITO del mes en curso (misma empresa) tras un cambio de cotización de COM.
 */
class TransferenciaMercaderiaTitoRecalculoService
{
    public function __construct(
        private readonly TransferenciaMercaderiaAsientoService $asientoService,
    ) {
    }

    /**
     * @return list<Articulo>
     */
    public function articulosTito(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos']);

        $out = [];
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $art = $linea->articulos;
            if (! $art instanceof Articulo) {
                continue;
            }
            if (! (bool) ($art->fl_precio_promedio_transferencia ?? false)) {
                continue;
            }
            $out[(int) $art->id] = $art;
        }

        return array_values($out);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contextoSiHayTito(Recepcion_Proveedor $recepcion): ?array
    {
        $articulos = $this->articulosTito($recepcion);
        if ($articulos === []) {
            return null;
        }

        $rango = TransferenciaMercaderiaTitoRecalculoSupport::rangoMesEnCurso();
        $recepcion->loadMissing(['empresas']);

        return [
            'recepcion_id' => (int) $recepcion->id,
            'numero' => (string) ($recepcion->numerorecepcion ?? ''),
            'empresa_id' => (int) $recepcion->empresa_id,
            'empresa_nombre' => (string) (optional($recepcion->empresas)->nombre ?? ''),
            'fecha_desde' => $rango['desde'],
            'fecha_hasta' => $rango['hasta'],
            'articulos' => array_map(static fn (Articulo $a): array => [
                'id' => (int) $a->id,
                'sku' => (string) ($a->sku ?? ''),
            ], $articulos),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(int $recepcionId): array
    {
        [$recepcion, $articulos, $rango] = $this->cargarContexto($recepcionId);
        $preciosNuevos = ArticuloPrecioPromedioCompraSupport::resolverPorArticulos($articulos);
        $lineas = $this->buscarLineas($recepcion, $articulos, $rango);

        $filas = [];
        $conCambio = 0;
        foreach ($lineas as $linea) {
            $fila = $this->armarFila($linea, $preciosNuevos);
            if ($fila['requiere_cambio']) {
                $conCambio++;
            }
            $filas[] = $fila;
        }

        return [
            'recepcion_id' => (int) $recepcion->id,
            'numero' => (string) ($recepcion->numerorecepcion ?? ''),
            'empresa_id' => (int) $recepcion->empresa_id,
            'empresa_nombre' => (string) (optional($recepcion->empresas)->nombre ?? ''),
            'fecha_desde' => $rango['desde'],
            'fecha_hasta' => $rango['hasta'],
            'filas' => $filas,
            'total' => count($filas),
            'con_cambio' => $conCambio,
        ];
    }

    /**
     * @param  list<int>  $lineaIds
     * @return array<string, mixed>
     */
    public function aplicar(int $recepcionId, array $lineaIds): array
    {
        [$recepcion, $articulos, $rango] = $this->cargarContexto($recepcionId);
        $lineaIds = array_values(array_unique(array_filter(array_map('intval', $lineaIds), static fn ($id) => $id > 0)));
        if ($lineaIds === []) {
            throw new \RuntimeException('Seleccione al menos una línea de transferencia.');
        }

        $preciosNuevos = ArticuloPrecioPromedioCompraSupport::resolverPorArticulos($articulos);
        $lineas = $this->buscarLineas($recepcion, $articulos, $rango)
            ->filter(static fn (Transferencia_Mercaderia_Articulo $l) => in_array((int) $l->id, $lineaIds, true))
            ->values();

        if ($lineas->count() !== count($lineaIds)) {
            throw new \RuntimeException('Hay líneas que no pertenecen a TRA TITO del mes en curso de esta empresa.');
        }

        $lineasActualizadas = 0;
        $movimientos = 0;
        $asientos = 0;
        $tmHechas = [];

        DB::transaction(function () use ($lineas, $preciosNuevos, &$lineasActualizadas, &$movimientos, &$asientos, &$tmHechas) {
            foreach ($lineas as $linea) {
                $fila = $this->armarFila($linea, $preciosNuevos);
                $precio = (float) $fila['precio_despues'];
                if ($precio <= 0) {
                    throw new \RuntimeException('Sin promedio TITO para '.$fila['sku'].'.');
                }

                $precioDestino = TransferenciaMercaderiaCostoSupport::resolverCostoDestino($precio, [
                    'fl_conversion_formula' => (bool) ($linea->fl_conversion_formula ?? false),
                    'coeficienteconversion' => (float) ($linea->coeficienteconversion ?? 0),
                ]);

                $linea->update([
                    'precio_costo_origen' => $precio,
                    'precio_costo_destino' => $precioDestino,
                ]);
                $lineasActualizadas++;

                $tm = $linea->transferencias;
                if (! $tm instanceof Transferencia_Mercaderia) {
                    continue;
                }

                $linea->precio_costo_origen = $precio;
                $linea->precio_costo_destino = $precioDestino;
                $movimientos += $this->sincronizarMovimiento(
                    (int) ($tm->movimientostock_salida_id ?? 0),
                    $linea,
                    'salida'
                );
                $movimientos += $this->sincronizarMovimiento(
                    (int) ($tm->movimientostock_entrada_id ?? 0),
                    $linea,
                    'entrada'
                );
            }

            foreach ($lineas as $linea) {
                $tm = $linea->transferencias;
                if (! $tm instanceof Transferencia_Mercaderia) {
                    continue;
                }
                $tmId = (int) $tm->id;
                if (isset($tmHechas[$tmId])) {
                    continue;
                }
                $tmHechas[$tmId] = true;
                if ((int) ($tm->asiento_id ?? 0) <= 0) {
                    continue;
                }
                if (! $this->asientoService->debeGenerarAsiento($tm->tipotransaccion_stock)) {
                    continue;
                }
                $this->actualizarAsientoDesdeLineas($tm->fresh(['articulos', 'tipotransaccion_stock', 'asientos']));
                $asientos++;
            }
        });

        return [
            'lineas_actualizadas' => $lineasActualizadas,
            'movimientos_actualizados' => $movimientos,
            'asientos_actualizados' => $asientos,
        ];
    }

    /**
     * Aplica el recálculo del mes en curso sin UI. No hace nada si no hay TITO o no hay diferencias.
     *
     * @return array{aplicado: bool, motivo: string, lineas_actualizadas: int, movimientos_actualizados: int, asientos_actualizados: int}
     */
    public function aplicarAutomaticoMesEnCurso(int $recepcionId): array
    {
        $vacio = [
            'aplicado' => false,
            'motivo' => 'sin_tito',
            'lineas_actualizadas' => 0,
            'movimientos_actualizados' => 0,
            'asientos_actualizados' => 0,
        ];

        $recepcion = Recepcion_Proveedor::query()->find($recepcionId);
        if ($recepcion === null || $this->articulosTito($recepcion) === []) {
            return $vacio;
        }

        $preview = $this->preview($recepcionId);
        $lineaIds = [];
        foreach ($preview['filas'] ?? [] as $fila) {
            if (! empty($fila['requiere_cambio'])) {
                $lineaIds[] = (int) ($fila['linea_id'] ?? 0);
            }
        }
        $lineaIds = array_values(array_filter($lineaIds, static fn ($id) => $id > 0));
        if ($lineaIds === []) {
            $vacio['motivo'] = 'sin_diferencias';

            return $vacio;
        }

        $res = $this->aplicar($recepcionId, $lineaIds);

        return [
            'aplicado' => true,
            'motivo' => 'ok',
            'lineas_actualizadas' => (int) ($res['lineas_actualizadas'] ?? 0),
            'movimientos_actualizados' => (int) ($res['movimientos_actualizados'] ?? 0),
            'asientos_actualizados' => (int) ($res['asientos_actualizados'] ?? 0),
        ];
    }

    /**
     * @return array{0: Recepcion_Proveedor, 1: list<Articulo>, 2: array{desde: string, hasta: string}}
     */
    private function cargarContexto(int $recepcionId): array
    {
        $recepcion = Recepcion_Proveedor::query()->with(['empresas'])->findOrFail($recepcionId);
        $articulos = $this->articulosTito($recepcion);
        if ($articulos === []) {
            throw new \RuntimeException('La recepción no tiene artículos TITO.');
        }

        return [$recepcion, $articulos, TransferenciaMercaderiaTitoRecalculoSupport::rangoMesEnCurso()];
    }

    /**
     * @param  list<Articulo>  $articulos
     * @param  array{desde: string, hasta: string}  $rango
     * @return Collection<int, Transferencia_Mercaderia_Articulo>
     */
    private function buscarLineas(Recepcion_Proveedor $recepcion, array $articulos, array $rango): Collection
    {
        $articuloIds = array_map(static fn (Articulo $a) => (int) $a->id, $articulos);
        $empresaId = (int) $recepcion->empresa_id;

        return Transferencia_Mercaderia_Articulo::query()
            ->select('transferencia_mercaderia_articulo.*')
            ->join('transferencia_mercaderia as tm', 'tm.id', '=', 'transferencia_mercaderia_articulo.transferencia_mercaderia_id')
            ->where('tm.empresa_id', $empresaId)
            ->where('tm.estado', TransferenciaMercaderiaEstados::CONFIRMADA)
            ->whereBetween('tm.fecha', [$rango['desde'], $rango['hasta']])
            ->whereIn('transferencia_mercaderia_articulo.articulo_origen_id', $articuloIds)
            ->with([
                'transferencias.empresas',
                'transferencias.tipotransaccion_stock',
                'articuloOrigen',
            ])
            ->orderBy('tm.fecha')
            ->orderBy('tm.id')
            ->get();
    }

    /**
     * @param  array<int, array{precio: float|null, origen: string|null, compras: list<array<string, mixed>>}>  $preciosNuevos
     * @return array<string, mixed>
     */
    private function armarFila(Transferencia_Mercaderia_Articulo $linea, array $preciosNuevos): array
    {
        $tm = $linea->transferencias;
        $articuloId = (int) $linea->articulo_origen_id;
        $precioAntes = (float) $linea->precio_costo_origen;
        $precioDespues = isset($preciosNuevos[$articuloId]['precio'])
            ? (float) $preciosNuevos[$articuloId]['precio']
            : 0.0;
        $cant = (float) $linea->cantidad_origen;
        $requiere = TransferenciaMercaderiaTitoRecalculoSupport::precioRequiereCambio($precioAntes, $precioDespues)
            && $precioDespues > 0;

        $fecha = '';
        if ($tm && $tm->fecha) {
            $fecha = is_string($tm->fecha) ? substr($tm->fecha, 0, 10) : $tm->fecha->format('Y-m-d');
        }

        return [
            'linea_id' => (int) $linea->id,
            'transferencia_id' => (int) $linea->transferencia_mercaderia_id,
            'codigo' => (string) ($tm->codigo ?? ''),
            'fecha' => $fecha,
            'sku' => (string) (optional($linea->articuloOrigen)->sku ?? ''),
            'cantidad' => $cant,
            'precio_antes' => $precioAntes,
            'precio_despues' => $precioDespues,
            'importe_antes' => round($cant * $precioAntes, 2),
            'importe_despues' => round($cant * $precioDespues, 2),
            'asiento_id' => (int) ($tm->asiento_id ?? 0),
            'requiere_cambio' => $requiere,
        ];
    }

    private function actualizarAsientoDesdeLineas(Transferencia_Mercaderia $tm): void
    {
        $importe = 0.0;
        foreach ($tm->articulos as $lin) {
            $importe += round((float) $lin->cantidad_origen * (float) $lin->precio_costo_origen, 2);
        }
        $importe = round($importe, 2);
        if ($importe <= 0) {
            throw new \RuntimeException('Importe del asiento TRA '.$tm->codigo.' inválido.');
        }

        $movs = DB::table('asiento_movimiento')
            ->where('asiento_id', (int) $tm->asiento_id)
            ->orderBy('id')
            ->get(['id', 'monto']);
        if ($movs->count() !== 2) {
            throw new \RuntimeException('El asiento de '.$tm->codigo.' no tiene exactamente 2 líneas.');
        }
        foreach ($movs as $mov) {
            $nuevo = ((float) $mov->monto) >= 0 ? $importe : -1 * $importe;
            DB::table('asiento_movimiento')->where('id', $mov->id)->update([
                'monto' => $nuevo,
                'updated_at' => now(),
            ]);
        }

        $this->asientoService->sincronizarCtamovAnitaTransferencia(
            $tm->fresh(['asientos.asiento_movimientos', 'tipotransaccion_stock'])
        );
    }

    private function sincronizarMovimiento(int $movimientoStockId, Transferencia_Mercaderia_Articulo $linea, string $lado): int
    {
        if ($movimientoStockId <= 0) {
            return 0;
        }

        $articuloId = $lado === 'salida'
            ? (int) $linea->articulo_origen_id
            : (int) $linea->articulo_destino_id;
        $precio = $lado === 'salida'
            ? (float) $linea->precio_costo_origen
            : (float) $linea->precio_costo_destino;

        $mov = Articulo_Movimiento::query()
            ->where('movimientostock_id', $movimientoStockId)
            ->where('articulo_id', $articuloId)
            ->orderBy('id')
            ->first();
        if ($mov === null) {
            return 0;
        }

        $cambio = false;
        if (abs((float) $mov->precio - $precio) > 0.000001) {
            $mov->precio = $precio;
            $cambio = true;
        }
        if (abs((float) ($mov->costo ?? 0) - $precio) > 0.000001) {
            $mov->costo = $precio;
            $cambio = true;
        }
        if ($cambio) {
            $mov->save();

            return 1;
        }

        return 0;
    }
}
