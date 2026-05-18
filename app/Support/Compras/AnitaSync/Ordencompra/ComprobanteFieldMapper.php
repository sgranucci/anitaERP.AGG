<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Mapeo campo a campo: occuota (agrupado) → ordencompra_comprobante.
 */
final class ComprobanteFieldMapper
{
    /**
     * @param  list<object>  $cuotasGrupo
     * @return array<string, mixed>
     */
    public static function mapAll(
        array $cuotasGrupo,
        object $cabecera,
        OrdencompraAnitaSyncContext $ctx,
        int $ordencompraId,
        int $creousuarioId,
    ): array {
        $primera = $cuotasGrupo[0];
        $monto = 0.0;
        $minFecha = null;
        foreach ($cuotasGrupo as $c) {
            $monto += (float) ($c->occ_monto ?? 0);
            $f = $ctx->fechaYmd($c->occ_fecha_vto ?? null);
            if ($f !== null && ($minFecha === null || $f < $minFecha)) {
                $minFecha = $f;
            }
        }

        return [
            'ordencompra_id' => $ordencompraId,
            'tipocomprobante' => self::mapTipocomprobante(),
            'fechavencimiento' => $minFecha ?? $ctx->fechaYmd($cabecera->penmp_fecha ?? null) ?? date('Y-m-d'),
            'monto' => $monto,
            'moneda_id' => self::mapMonedaId($cabecera, $ctx),
            'cotizacion' => self::mapCotizacion($cabecera),
            'detalle' => self::mapDetalle($primera),
            'cantidadcuota' => count($cuotasGrupo),
            'condicionpago_id' => self::mapCondicionpagoId($primera, $ctx),
            'creousuario_id' => $creousuarioId,
        ];
    }

    public static function mapTipocomprobante(): string
    {
        return 'FACTURA';
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

    public static function mapDetalle(object $primeraCuota): ?string
    {
        $d = trim((string) ($primeraCuota->occ_detalle ?? ''));

        return $d !== '' ? $d : null;
    }

    public static function mapCondicionpagoId(object $primeraCuota, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCondicionpago($primeraCuota->occ_cond_pago ?? null);
    }
}
