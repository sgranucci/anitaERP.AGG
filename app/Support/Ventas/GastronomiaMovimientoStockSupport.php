<?php

namespace App\Support\Ventas;

use App\Models\Stock\Combinacion;

/**
 * Valores seguros para movimientos de stock en gastronomía.
 * FK opcionales: NULL si no aplican (nunca 0, que viola la restricción).
 */
final class GastronomiaMovimientoStockSupport
{
    /**
     * Primera combinación del artículo o null si no tiene (insumos sin talle).
     */
    public static function combinacionIdParaArticulo(int $articuloId): ?int
    {
        if ($articuloId <= 0) {
            return null;
        }

        $id = Combinacion::query()
            ->where('articulo_id', $articuloId)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarPayloadMovimiento(array $data): array
    {
        $articuloId = (int) ($data['articulo_id'] ?? 0);

        if (! array_key_exists('combinacion_id', $data) || self::fkVacio($data['combinacion_id'])) {
            $data['combinacion_id'] = self::combinacionIdParaArticulo($articuloId);
        } else {
            $data['combinacion_id'] = (int) $data['combinacion_id'];
        }

        foreach ([
            'pedido_combinacion_id',
            'ordentrabajo_id',
            'modulo_id',
            'movimientostock_id',
            'pedido_articulo_id',
            'venta_emision_id',
            'loteimportacion_id',
            'listaprecio_id',
        ] as $fk) {
            $data[$fk] = self::nullableFkId($data[$fk] ?? null);
        }

        $data['lote'] = (int) ($data['lote'] ?? 0);
        $data['codigocombinacion'] = (string) ($data['codigocombinacion'] ?? '');
        $data['despacho'] = (string) ($data['despacho'] ?? '');
        $data['descuentointegrado'] = (string) ($data['descuentointegrado'] ?? '');
        $data['deposito_id'] = (int) ($data['deposito_id'] ?? 0);
        if ($data['deposito_id'] <= 0) {
            $data['deposito_id'] = (int) (config('facturacion.DEPOSITO_VENTA_ID') ?: 1);
        }
        $data['moneda_id'] = (int) ($data['moneda_id'] ?? 1);
        if ($data['moneda_id'] <= 0) {
            $data['moneda_id'] = 1;
        }
        $data['incluyeimpuesto'] = $data['incluyeimpuesto'] ?? 1;
        $data['precio'] = (string) ($data['precio'] ?? '0');
        $data['costo'] = (float) ($data['costo'] ?? 0);
        $data['descuento'] = (float) ($data['descuento'] ?? 0);

        return $data;
    }

    public static function mensajeErrorEmision(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if ($msg === '') {
            return 'No se pudo completar la facturación. Revise la configuración e intente nuevamente.';
        }

        if ($e instanceof \InvalidArgumentException) {
            return $msg;
        }

        return 'No se pudo completar la facturación: '.$msg;
    }

    public static function nullableFkId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private static function fkVacio(mixed $value): bool
    {
        return self::nullableFkId($value) === null;
    }
}
