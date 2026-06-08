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

    /**
     * Mensaje legible para el operador del POS según la clase del error.
     *
     * - `InvalidArgumentException` (validaciones internas / preflight) se devuelven tal cual:
     *   ya están redactadas para el operador ("Configure el tipo de transacción…", "No hay
     *   configuración de PV…", "Receptor manual incompleto…", etc.).
     * - El resto delega en `ArcaWsfeEmisionResiliencia::formatearMensajeOperador()` que arma
     *   un texto con prefijo y sugerencia de acción según `transporte` / `datos` / `sistema` /
     *   `sin_clasificar`.
     *
     * @param  array{intento_caea?:bool,reintento_caea_habilitado?:bool}|null  $contexto
     */
    public static function mensajeErrorEmision(\Throwable $e, ?array $contexto = null): string
    {
        $msg = trim($e->getMessage());

        if ($e instanceof \InvalidArgumentException) {
            return $msg !== ''
                ? $msg
                : 'No se pudo completar la facturación: validación interna falló sin detalle. Revise la configuración del PV gastronomía.';
        }

        if (self::esErrorContencionBaseDatos($msg)) {
            return self::mensajeContencionBaseDatos($msg);
        }

        return \App\Support\Ventas\ArcaWsfeEmisionResiliencia::formatearMensajeOperador(
            $msg,
            null,
            $contexto,
        );
    }

    private static function esErrorContencionBaseDatos(string $mensaje): bool
    {
        $m = strtolower($mensaje);

        return str_contains($m, 'lock wait timeout')
            || str_contains($m, '1205')
            || str_contains($m, 'deadlock found');
    }

    private static function mensajeContencionBaseDatos(string $detalle): string
    {
        $detalleVisible = trim($detalle) !== '' ? trim($detalle) : '(sin detalle adicional)';

        return 'No se pudo completar la facturación: el sistema está ocupado procesando otra operación '
            .'(espera de bloqueo en base de datos). '
            .'Detalle técnico: '.$detalleVisible.'. '
            .'Espere unos segundos e intente nuevamente. '
            .'Si el error persiste con varias terminales activas, avise a soporte técnico '
            .'(puede haber una emisión anterior demorada bloqueando la cobranza). '
            .'No reintente con los mismos datos hasta confirmar si la factura ya se emitió.';
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
