<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TransferenciaMercaderiaRepararCostosSupport
{
    /**
     * Recalcula precio_costo_origen/destino y sincroniza precio en movimientos de stock vinculados.
     *
     * @return array{transferencia_id: int, lineas: int, movimientos_actualizados: int, stkmae_actualizados: int, stkmov_actualizados: int}
     */
    public static function recalcularTransferencia(int $transferenciaId): array
    {
        $transferencia = Transferencia_Mercaderia::query()
            ->with(['articulos'])
            ->findOrFail($transferenciaId);

        $empresaId = (int) ($transferencia->empresa_id ?? 0);
        $destinoBien = (int) ($transferencia->bien_uso_destino_id ?? 0) > 0;
        $depositoDestino = null;

        if (! $destinoBien) {
            $depositoId = (int) ($transferencia->deposito_destino_id ?? 0);
            if ($depositoId <= 0) {
                throw new \RuntimeException('La transferencia no tiene depósito destino.');
            }
            $depositoDestino = Depmae::query()->findOrFail($depositoId);
        }

        $movActualizados = 0;

        DB::beginTransaction();
        try {
            foreach ($transferencia->articulos as $linea) {
                $articuloOrigen = Articulo::query()->findOrFail((int) $linea->articulo_origen_id);
                $cantidadOrigen = (float) $linea->cantidad_origen;

                if ($destinoBien) {
                    $conv = TransferenciaMercaderiaLineaSupport::resolverLineaParaBienUso($articuloOrigen, $cantidadOrigen);
                } else {
                    $conv = TransferenciaMercaderiaLineaSupport::resolverLinea(
                        $articuloOrigen,
                        $depositoDestino,
                        $cantidadOrigen,
                        $empresaId > 0 ? $empresaId : null
                    );
                }

                $linea->update([
                    'articulo_destino_id' => (int) $conv['articulo_destino_id'],
                    'cantidad_destino' => (float) $conv['cantidad_destino'],
                    'precio_costo_origen' => (float) $conv['precio_costo_origen'],
                    'precio_costo_destino' => (float) $conv['precio_costo_destino'],
                    'coeficienteconversion' => (float) $conv['coeficienteconversion'],
                    'fl_conversion_formula' => (bool) $conv['fl_conversion_formula'],
                ]);
            }

            $transferencia->load('articulos');

            if ($transferencia->movimientostock_salida_id) {
                $movActualizados += self::sincronizarMovimiento(
                    (int) $transferencia->movimientostock_salida_id,
                    $transferencia->articulos,
                    'salida'
                );
            }

            if ($transferencia->movimientostock_entrada_id) {
                $movActualizados += self::sincronizarMovimiento(
                    (int) $transferencia->movimientostock_entrada_id,
                    $transferencia->articulos,
                    'entrada'
                );
            }

            $stkmaeActualizados = 0;
            if ($transferencia->estado === TransferenciaMercaderiaEstados::CONFIRMADA
                && (int) ($transferencia->movimientostock_entrada_id ?? 0) > 0) {
                $stkmaeActualizados = StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeTransferencia(
                    $transferencia->fresh(['articulos.articuloDestino'])
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'transferencia_id' => $transferenciaId,
            'lineas' => $transferencia->articulos->count(),
            'movimientos_actualizados' => $movActualizados,
            'stkmae_actualizados' => $stkmaeActualizados ?? 0,
            'stkmov_actualizados' => 0,
        ];
    }

    public static function puedeRecalcularDesdeArticulo(): bool
    {
        return can('recalcular-transferencias-formula-articulo', false)
            || can('actualizar-articulos', false)
            || can('actualizar-compras-articulos', false);
    }

    /**
     * Preview / apply de líneas fórmula de un artículo de compra.
     * Conserva precio_costo_origen; recalcula destino con el coeficiente indicado (o el del maestro).
     *
     * @param  array{
     *     modo?: string,
     *     fecha_desde?: string|null,
     *     fecha_hasta?: string|null,
     *     coeficiente?: float|null,
     *     linea_ids?: list<int>|null
     * }  $opciones
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string},
     *     coeficiente: float,
     *     modo: string,
     *     filas: list<array<string, mixed>>,
     *     total: int,
     *     con_cambio: int
     * }
     */
    public static function previewPorArticulo(int $articuloId, array $opciones = []): array
    {
        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'coeficienteconversion')
            ->findOrFail($articuloId);

        $coef = self::resolverCoeficiente($articulo, $opciones['coeficiente'] ?? null);
        $modo = self::normalizarModo($opciones['modo'] ?? 'ultima');
        $lineas = self::buscarLineasFormula($articuloId, $modo, $opciones);

        $filas = [];
        $conCambio = 0;
        foreach ($lineas as $linea) {
            $fila = self::armarFilaPreview($linea, $coef);
            if ($fila['requiere_cambio']) {
                $conCambio++;
            }
            $filas[] = $fila;
        }

        return [
            'articulo' => [
                'id' => (int) $articulo->id,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
            ],
            'coeficiente' => $coef,
            'modo' => $modo,
            'filas' => $filas,
            'total' => count($filas),
            'con_cambio' => $conCambio,
        ];
    }

    /**
     * @param  array{
     *     modo?: string,
     *     fecha_desde?: string|null,
     *     fecha_hasta?: string|null,
     *     coeficiente?: float|null,
     *     linea_ids?: list<int>|null,
     *     solo_con_cambio?: bool
     * }  $opciones
     * @return array{
     *     articulo: array{id: int, sku: string, descripcion: string},
     *     coeficiente: float,
     *     modo: string,
     *     lineas_actualizadas: int,
     *     movimientos_actualizados: int,
     *     filas: list<array<string, mixed>>
     * }
     */
    public static function aplicarPorArticulo(int $articuloId, array $opciones = []): array
    {
        $preview = self::previewPorArticulo($articuloId, $opciones);
        $coef = (float) $preview['coeficiente'];
        $soloConCambio = (bool) ($opciones['solo_con_cambio'] ?? true);

        $lineaIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            $opciones['linea_ids'] ?? array_column($preview['filas'], 'linea_id')
        )));

        if ($lineaIds === []) {
            throw new \InvalidArgumentException('No hay líneas de transferencia para recalcular.');
        }

        $permitidos = collect($preview['filas'])
            ->filter(static function (array $fila) use ($soloConCambio, $lineaIds) {
                if (! in_array((int) $fila['linea_id'], $lineaIds, true)) {
                    return false;
                }

                return $soloConCambio ? (bool) $fila['requiere_cambio'] : true;
            })
            ->values();

        if ($permitidos->isEmpty()) {
            throw new \InvalidArgumentException('No hay líneas con diferencias para aplicar.');
        }

        $movActualizados = 0;
        $lineasActualizadas = 0;
        $filasAplicadas = [];

        DB::beginTransaction();
        try {
            foreach ($permitidos as $filaPreview) {
                $linea = Transferencia_Mercaderia_Articulo::query()
                    ->with(['transferencias'])
                    ->findOrFail((int) $filaPreview['linea_id']);

                if ((int) $linea->articulo_origen_id !== $articuloId) {
                    throw new \RuntimeException('La línea no pertenece al artículo indicado.');
                }
                if (! $linea->fl_conversion_formula) {
                    throw new \RuntimeException('La línea no es conversión a fórmulas.');
                }

                $tm = $linea->transferencias;
                if ($tm === null || $tm->estado !== TransferenciaMercaderiaEstados::CONFIRMADA) {
                    throw new \RuntimeException('Solo se pueden recalcular transferencias confirmadas.');
                }

                $resultado = self::aplicarLineaConservandoCostoOrigen($linea, $coef);
                $lineasActualizadas++;
                $movActualizados += (int) $resultado['movimientos_actualizados'];
                $filasAplicadas[] = self::armarFilaPreview($linea->fresh(['transferencias.empresas', 'transferencias.depositoDestino', 'articuloDestino']), $coef);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'articulo' => $preview['articulo'],
            'coeficiente' => $coef,
            'modo' => $preview['modo'],
            'lineas_actualizadas' => $lineasActualizadas,
            'movimientos_actualizados' => $movActualizados,
            'filas' => $filasAplicadas,
        ];
    }

    /**
     * @return array{movimientos_actualizados: int}
     */
    public static function aplicarLineaConservandoCostoOrigen(
        Transferencia_Mercaderia_Articulo $linea,
        float $coeficiente
    ): array {
        $coef = $coeficiente > 0 ? $coeficiente : 1.0;
        $cantOrigen = (float) $linea->cantidad_origen;
        $precioOrigen = (float) $linea->precio_costo_origen;
        $cantDestino = round($cantOrigen * $coef, 6);
        $precioDestino = $coef > 0 ? round($precioOrigen / $coef, 6) : round($precioOrigen, 6);

        $linea->update([
            'cantidad_destino' => $cantDestino,
            'precio_costo_destino' => $precioDestino,
            'coeficienteconversion' => $coef,
            'fl_conversion_formula' => true,
        ]);

        $linea->refresh();
        $tm = $linea->transferencias ?? Transferencia_Mercaderia::query()->find((int) $linea->transferencia_mercaderia_id);
        if ($tm === null) {
            throw new \RuntimeException('Transferencia no encontrada para la línea.');
        }

        $coleccion = collect([$linea]);
        $mov = 0;
        if ((int) ($tm->movimientostock_salida_id ?? 0) > 0) {
            $mov += self::sincronizarMovimiento((int) $tm->movimientostock_salida_id, $coleccion, 'salida');
        }
        if ((int) ($tm->movimientostock_entrada_id ?? 0) > 0) {
            $mov += self::sincronizarMovimiento((int) $tm->movimientostock_entrada_id, $coleccion, 'entrada');
        }

        return ['movimientos_actualizados' => $mov];
    }

    private static function resolverCoeficiente(Articulo $articulo, mixed $coefOpcional): float
    {
        if ($coefOpcional !== null && $coefOpcional !== '') {
            $coef = (float) $coefOpcional;
            if ($coef <= 0) {
                throw new \InvalidArgumentException('El coeficiente de conversión debe ser mayor a 0.');
            }

            return $coef;
        }

        $coef = (float) ($articulo->coeficienteconversion ?? 0);
        if ($coef <= 0) {
            throw new \InvalidArgumentException('El artículo no tiene coeficiente de conversión válido.');
        }

        return $coef;
    }

    private static function normalizarModo(string $modo): string
    {
        $modo = strtolower(trim($modo));

        return $modo === 'rango' ? 'rango' : 'ultima';
    }

    /**
     * @param  array{fecha_desde?: string|null, fecha_hasta?: string|null, linea_ids?: list<int>|null}  $opciones
     * @return Collection<int, Transferencia_Mercaderia_Articulo>
     */
    private static function buscarLineasFormula(int $articuloId, string $modo, array $opciones): Collection
    {
        $query = Transferencia_Mercaderia_Articulo::query()
            ->where('articulo_origen_id', $articuloId)
            ->where('fl_conversion_formula', true)
            ->whereHas('transferencias', function ($q) use ($modo, $opciones) {
                $q->where('estado', TransferenciaMercaderiaEstados::CONFIRMADA)
                    ->whereNull('deleted_at');

                if ($modo === 'rango') {
                    $desde = trim((string) ($opciones['fecha_desde'] ?? ''));
                    $hasta = trim((string) ($opciones['fecha_hasta'] ?? ''));
                    if ($desde === '' || $hasta === '') {
                        throw new \InvalidArgumentException('Indique fecha desde y hasta para el rango.');
                    }
                    if ($desde > $hasta) {
                        throw new \InvalidArgumentException('La fecha desde no puede ser mayor a la fecha hasta.');
                    }
                    $q->whereDate('fecha', '>=', $desde)
                        ->whereDate('fecha', '<=', $hasta);
                }
            })
            ->with([
                'transferencias.empresas:id,nombre',
                'transferencias.depositoDestino:id,codigo,nombre',
                'articuloDestino:id,sku,descripcion',
            ])
            ->join('transferencia_mercaderia as tm', 'tm.id', '=', 'transferencia_mercaderia_articulo.transferencia_mercaderia_id')
            ->orderByDesc('tm.fecha')
            ->orderByDesc('tm.id')
            ->orderBy('transferencia_mercaderia_articulo.item')
            ->select('transferencia_mercaderia_articulo.*');

        if (! empty($opciones['linea_ids']) && is_array($opciones['linea_ids'])) {
            $ids = array_values(array_filter(array_map('intval', $opciones['linea_ids'])));
            if ($ids !== []) {
                $query->whereIn('transferencia_mercaderia_articulo.id', $ids);
            }
        }

        if ($modo === 'ultima') {
            $primera = (clone $query)->limit(1)->first();
            if ($primera === null) {
                return collect();
            }

            return collect([$primera]);
        }

        return $query->limit(200)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private static function armarFilaPreview(Transferencia_Mercaderia_Articulo $linea, float $coefNuevo): array
    {
        $tm = $linea->transferencias;
        $cantOrigen = (float) $linea->cantidad_origen;
        $precioOrigen = (float) $linea->precio_costo_origen;
        $coefViejo = (float) $linea->coeficienteconversion;
        $cantDestAntes = (float) $linea->cantidad_destino;
        $precioDestAntes = (float) $linea->precio_costo_destino;
        $cantDestDespues = round($cantOrigen * $coefNuevo, 6);
        $precioDestDespues = $coefNuevo > 0 ? round($precioOrigen / $coefNuevo, 6) : round($precioOrigen, 6);

        $requiereCambio = abs($coefViejo - $coefNuevo) > 0.000001
            || abs($cantDestAntes - $cantDestDespues) > 0.000001
            || abs($precioDestAntes - $precioDestDespues) > 0.000001;

        $fecha = $tm && $tm->fecha
            ? (is_string($tm->fecha) ? substr($tm->fecha, 0, 10) : $tm->fecha->format('Y-m-d'))
            : '';

        return [
            'linea_id' => (int) $linea->id,
            'transferencia_id' => (int) $linea->transferencia_mercaderia_id,
            'codigo' => (string) ($tm->codigo ?? ''),
            'fecha' => $fecha,
            'empresa_id' => (int) ($tm->empresa_id ?? 0),
            'empresa_nombre' => (string) (optional(optional($tm)->empresas)->nombre ?? ''),
            'deposito_destino_id' => (int) ($tm->deposito_destino_id ?? 0),
            'deposito_destino' => trim(
                (string) (optional(optional($tm)->depositoDestino)->codigo ?? '')
                .' '
                .(string) (optional(optional($tm)->depositoDestino)->nombre ?? '')
            ),
            'articulo_destino_id' => (int) $linea->articulo_destino_id,
            'articulo_destino_sku' => (string) (optional($linea->articuloDestino)->sku ?? ''),
            'cantidad_origen' => $cantOrigen,
            'precio_costo_origen' => $precioOrigen,
            'coeficiente_antes' => $coefViejo,
            'coeficiente_despues' => $coefNuevo,
            'cantidad_destino_antes' => $cantDestAntes,
            'cantidad_destino_despues' => $cantDestDespues,
            'precio_destino_antes' => $precioDestAntes,
            'precio_destino_despues' => $precioDestDespues,
            'requiere_cambio' => $requiereCambio,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Stock\Transferencia_Mercaderia_Articulo>  $lineas
     */
    private static function sincronizarMovimiento(int $movimientoStockId, $lineas, string $lado): int
    {
        $actualizados = 0;

        foreach ($lineas as $linea) {
            $articuloId = $lado === 'salida'
                ? (int) $linea->articulo_origen_id
                : (int) $linea->articulo_destino_id;
            $precio = $lado === 'salida'
                ? (float) $linea->precio_costo_origen
                : (float) $linea->precio_costo_destino;
            $cantidad = $lado === 'salida'
                ? -abs((float) $linea->cantidad_origen)
                : abs((float) $linea->cantidad_destino);

            $mov = Articulo_Movimiento::query()
                ->where('movimientostock_id', $movimientoStockId)
                ->where('articulo_id', $articuloId)
                ->orderBy('id')
                ->first();

            if ($mov === null) {
                continue;
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
            if (abs((float) $mov->cantidad - $cantidad) > 0.000001) {
                $mov->cantidad = $cantidad;
                $cambio = true;
            }

            if ($cambio) {
                $mov->save();
                $actualizados++;
            }
        }

        return $actualizados;
    }
}
