<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

/**
 * Signo de retenciones IIBB en presentaciones (ARCIBA / archivos).
 * NC y AOP restan; las devoluciones parciales de retención (DEV RET) también.
 */
final class IngresosBrutosRetencionSignoSupport
{
    /**
     * Anulación de OP / nota de crédito: restan la retención aunque el importe
     * en Anita venga en positivo con el mismo nro de certificado.
     */
    public static function movimientoResta(string $tipo): bool
    {
        $tipo = strtoupper(trim($tipo));

        return str_starts_with($tipo, 'NC') || str_starts_with($tipo, 'AOP');
    }

    /**
     * OP hija de devolución parcial de retenciones (herramienta DEV RET).
     */
    public static function esDevolucionRetencionDetalle(string $detalle): bool
    {
        return preg_match('/^DEV\s+RET\b/iu', trim($detalle)) === 1;
    }

    /**
     * @param  object|array<string, mixed>  $pago  pagoproveedor o fila con tipocomprobante/detalle/origen
     */
    public static function esDevolucionRetencionPago(object|array $pago): bool
    {
        $arr = is_array($pago) ? $pago : [
            'tipocomprobante' => $pago->tipocomprobante ?? '',
            'detalle' => $pago->detalle ?? '',
            'pagoproveedor_origen_id' => $pago->pagoproveedor_origen_id ?? null,
        ];

        if (self::esDevolucionRetencionDetalle((string) ($arr['detalle'] ?? ''))) {
            return true;
        }

        $tipo = strtoupper(substr(trim((string) ($arr['tipocomprobante'] ?? '')), 0, 3));

        return self::movimientoResta($tipo);
    }
}
