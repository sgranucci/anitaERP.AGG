<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * Mapeo campo a campo: pendmovp (+ movpresup, ocvley) → ordencompra_articulo.
 */
final class ArticuloLineaFieldMapper
{
    public static function mapFechaentrega(object $linea, OrdencompraAnitaSyncContext $ctx): ?string
    {
        return $ctx->fechaYmd($linea->penvp_fecha_ent ?? null);
    }

    public static function mapArticuloId(object $linea, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkArticuloSku($linea->penvp_articulo ?? null);
    }

    public static function mapPenvpNroInterno(object $linea): ?int
    {
        $nro = (int) ($linea->penvp_nro_interno ?? 0);

        return $nro > 0 ? $nro : null;
    }

    public static function mapPenvpOrden(object $linea): ?int
    {
        $orden = (int) ($linea->penvp_orden ?? 0);

        return $orden > 0 ? $orden : null;
    }

    public static function mapCantidad(object $linea): float
    {
        return (float) ($linea->penvp_cantidad ?? 0);
    }

    public static function mapPrecio(object $linea): float
    {
        return (float) ($linea->penvp_precio ?? 0);
    }

    public static function mapMonedaId(object $linea, object $cabecera, OrdencompraAnitaSyncContext $ctx): ?int
    {
        $cod = $linea->penvp_cod_mon ?? $cabecera->penmp_cod_mon ?? null;

        return $ctx->fkMoneda($cod);
    }

    public static function mapCotizacion(object $cabecera): float
    {
        $c = (float) ($cabecera->penmp_cotizacion ?? 1);

        return $c > 0 ? $c : 1.0;
    }

    public static function mapDescuento(object $linea): ?float
    {
        if (! isset($linea->penvp_dto_art)) {
            return null;
        }

        return (float) $linea->penvp_dto_art;
    }

    public static function mapCantidadalternativa(): float
    {
        return 0.0;
    }

    public static function mapDetalle(object $linea, ?object $leyendaFila): string
    {
        $base = trim((string) ($linea->penvp_desc ?? ''));
        if ($leyendaFila !== null) {
            $extra = trim((string) ($leyendaFila->ocvl_leyenda ?? ''));
            if ($extra !== '') {
                $base = $base !== '' ? $base.' — '.$extra : $extra;
            }
        }

        return $base;
    }

    public static function mapCentrocostodestinoId(object $linea, OrdencompraAnitaSyncContext $ctx): ?int
    {
        return $ctx->fkCentrocosto($linea->penvp_ccosto ?? null);
    }

    public static function mapPartidagastoId(?object $movpresup, OrdencompraAnitaSyncContext $ctx): ?int
    {
        if ($movpresup === null) {
            return null;
        }

        return $ctx->fkPartidagasto($movpresup->movp_partida ?? null);
    }

    public static function mapCapexId(?object $movpresup, OrdencompraAnitaSyncContext $ctx): ?int
    {
        if ($movpresup === null) {
            return null;
        }

        return $ctx->fkCapex($movpresup->movp_proyecto ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAll(
        object $linea,
        object $cabecera,
        ?object $movpresup,
        ?object $leyendaFila,
        OrdencompraAnitaSyncContext $ctx,
        int $ordencompraId,
    ): array {
        return [
            'ordencompra_id' => $ordencompraId,
            'penvp_orden' => self::mapPenvpOrden($linea),
            'penvp_nro_interno' => self::mapPenvpNroInterno($linea),
            'fechaentrega' => self::mapFechaentrega($linea, $ctx),
            'articulo_id' => self::mapArticuloId($linea, $ctx),
            'cantidad' => self::mapCantidad($linea),
            'precio' => self::mapPrecio($linea),
            'moneda_id' => self::mapMonedaId($linea, $cabecera, $ctx),
            'cotizacion' => self::mapCotizacion($cabecera),
            'descuento' => self::mapDescuento($linea),
            'cantidadalternativa' => self::mapCantidadalternativa(),
            'detalle' => self::mapDetalle($linea, $leyendaFila),
            'centrocostodestino_id' => self::mapCentrocostodestinoId($linea, $ctx),
            'partidagasto_id' => self::mapPartidagastoId($movpresup, $ctx),
            'capex_id' => self::mapCapexId($movpresup, $ctx),
        ];
    }
}
