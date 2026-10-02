<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Evita que un movimiento de stock reviente con un error SQL de clave foránea.
 * Los id vacíos o en cero de campos opcionales se graban nulos. La moneda vacía
 * queda en pesos (id 1). Si un id viene cargado y no existe, se corta con un
 * mensaje antes de insertar.
 */
final class ArticuloMovimientoReferenciasSupport
{
    /** @var array<string, true> */
    private static array $existentes = [];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const REFERENCIAS_LINEA = [
        'articulo_id' => ['articulo', 'el artículo'],
        'combinacion_id' => ['combinacion', 'la combinación'],
        'modulo_id' => ['modulo', 'el módulo'],
        'color_id' => ['color', 'el color'],
        'talle_id' => ['talle', 'el talle'],
        'deposito_id' => ['depmae', 'el depósito'],
        'bien_uso_id' => ['bien_uso', 'el bien de uso'],
        'listaprecio_id' => ['listaprecio', 'la lista de precios'],
        'moneda_id' => ['moneda', 'la moneda'],
        'loteimportacion_id' => ['lote', 'el lote de importación'],
        'tipotransaccion_id' => ['tipotransaccion', 'el tipo de transacción'],
        'tipotransaccion_stock_id' => ['tipotransaccion_stock', 'el tipo de transacción'],
        'ordentrabajo_id' => ['ordentrabajo', 'la orden de trabajo'],
        'movimientostock_id' => ['movimientostock', 'el movimiento de stock'],
        'pedido_combinacion_id' => ['pedido_combinacion', 'la línea de pedido'],
        'pedido_articulo_id' => ['pedido_articulo', 'el ítem de pedido'],
        'venta_id' => ['venta', 'la venta'],
        'venta_emision_id' => ['venta_emision', 'la emisión de la venta'],
        'vianda_consumo_id' => ['vianda_consumo', 'el consumo de vianda'],
    ];

    /** @var list<string> */
    private const OBLIGATORIOS_LINEA = [
        'articulo_id',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarLinea(array $data): array
    {
        $monedaId = self::idPositivo($data['moneda_id'] ?? null);
        $data['moneda_id'] = $monedaId ?? 1;

        foreach (self::REFERENCIAS_LINEA as $columna => [$tabla, $etiqueta]) {
            if ($columna === 'moneda_id') {
                self::assertExiste($tabla, (int) $data['moneda_id'], $etiqueta);
                continue;
            }

            $id = self::idPositivo($data[$columna] ?? null);
            if ($id === null) {
                $data[$columna] = null;
                if (in_array($columna, self::OBLIGATORIOS_LINEA, true)) {
                    throw new RuntimeException('No se puede grabar el movimiento: falta '.$etiqueta.'.');
                }
                continue;
            }

            self::assertExiste($tabla, $id, $etiqueta);
            $data[$columna] = $id;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarTalle(array $data): array
    {
        $talleId = self::idPositivo($data['talle_id'] ?? null);
        if ($talleId === null) {
            throw new RuntimeException('No se puede grabar el movimiento: falta el talle.');
        }
        self::assertExiste('talle', $talleId, 'el talle');
        $data['talle_id'] = $talleId;

        $pedidoTalleId = self::idPositivo($data['pedido_combinacion_talle_id'] ?? null);
        if ($pedidoTalleId === null) {
            $data['pedido_combinacion_talle_id'] = null;
        } else {
            self::assertExiste('pedido_combinacion_talle', $pedidoTalleId, 'el talle del pedido');
            $data['pedido_combinacion_talle_id'] = $pedidoTalleId;
        }

        return $data;
    }

    public static function mensajeClaveForanea(Throwable $e): string
    {
        $texto = $e->getMessage();
        foreach (self::REFERENCIAS_LINEA as [$tabla, $etiqueta]) {
            if (str_contains($texto, $tabla)) {
                return 'No se puede grabar el movimiento: '.$etiqueta.' no existe o no se puede usar en esta línea.';
            }
        }
        if (str_contains($texto, 'talle')) {
            return 'No se puede grabar el movimiento: el talle no existe o no se puede usar en esta línea.';
        }

        return 'No se puede grabar el movimiento: un dato de la línea no existe en las tablas relacionadas.';
    }

    private static function assertExiste(string $tabla, int $id, string $etiqueta): void
    {
        $clave = $tabla.':'.$id;
        if (isset(self::$existentes[$clave])) {
            return;
        }

        $existe = DB::table($tabla)->where('id', $id)->exists();
        if (! $existe) {
            throw new RuntimeException(
                'No se puede grabar el movimiento: '.$etiqueta.' '.$id.' no existe.'
            );
        }

        self::$existentes[$clave] = true;
    }

    private static function idPositivo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || $valor === 'NaN') {
            return null;
        }
        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
