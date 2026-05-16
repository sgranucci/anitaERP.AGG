<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Mapeo campo a campo: occuota → ordencompra_comprobante_cuota.
 */
final class ComprobanteCuotaFieldMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function mapAll(
        object $cuota,
        object $cabecera,
        OrdencompraAnitaSyncContext $ctx,
        int $ordencompraComprobanteId,
        int $creousuarioId,
    ): array {
        return [
            'ordencompra_comprobante_id' => $ordencompraComprobanteId,
            'fechavencimiento' => self::mapFechavencimiento($cuota, $ctx),
            'monto' => self::mapMonto($cuota),
            'moneda_id' => self::mapMonedaId($cabecera, $ctx),
            'cotizacion' => self::mapCotizacion($cabecera),
            'formapago_id' => self::mapFormapagoId($cuota, $ctx),
            'detalle' => self::mapDetalle($cuota),
            'creousuario_id' => $creousuarioId,
        ];
    }

    public static function mapFechavencimiento(object $cuota, OrdencompraAnitaSyncContext $ctx): string
    {
        return $ctx->fechaYmd($cuota->occ_fecha_vto ?? null) ?? date('Y-m-d');
    }

    public static function mapMonto(object $cuota): float
    {
        return (float) ($cuota->occ_monto ?? 0);
    }

    public static function mapMonedaId(object $cabecera, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkMoneda($cabecera->penmp_cod_mon ?? null);
    }

    public static function mapCotizacion(object $cabecera): ?float
    {
        $c = (float) ($cabecera->penmp_cotizacion ?? 0);

        return $c > 0 ? $c : null;
    }

    public static function mapFormapagoId(object $cuota, OrdencompraAnitaSyncContext $ctx): int
    {
        return $ctx->fkFormapagoMedio($cuota->occ_medio_pago ?? '');
    }

    public static function mapDetalle(object $cuota): ?string
    {
        $d = trim((string) ($cuota->occ_detalle ?? ''));

        return $d !== '' ? $d : null;
    }
}
