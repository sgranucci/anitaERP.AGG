<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Models\Compras\Requisicion_Articulo;
use App\Models\Stock\Articulo;

/**
 * Línea reqmov (a-reqmae.c graba_reqmov).
 */
final class ReqmovLineaAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            reqv_nro,
            reqv_nro_orden,
            reqv_articulo,
            reqv_desc,
            reqv_marca,
            reqv_linea,
            reqv_agrupacion,
            reqv_unidad_medida,
            reqv_cantidad,
            reqv_cantentr,
            reqv_precio,
            reqv_deposito,
            reqv_tipo_iva,
            reqv_fecha,
            reqv_fecha_ent,
            reqv_emp_sueldos,
            reqv_legajo,
            reqv_usuario,
            reqv_ccosto,
            reqv_cod_umd_comp,
            reqv_cant_unid,
            reqv_cod_umd_stock,
            reqv_unidad_xenv,
            reqv_genero_oc,
            reqv_empresa,
            reqv_proveedor,
            reqv_cantidad_oc,
            reqv_nro_interno,
            reqv_precio_ori,
            reqv_motivo_ahorro
        ';
    }

    public static function valoresInsert(
        RequisicionAnitaSyncContext $ctx,
        Requisicion_Articulo $linea,
        int $nroOrden,
        int $nroInterno,
    ): string {
        $articulo = $linea->articulos;
        $sku = $articulo?->sku ?? '';
        $desc = trim((string) ($linea->detalle ?? ''));
        if ($desc === '' && $articulo) {
            $desc = trim((string) ($articulo->descripcion ?? ''));
        }

        $marca = self::codigoPadded($articulo?->materiales?->codigo ?? $articulo?->mventas?->codigo, 8);
        $lineaCod = self::codigoPadded($articulo?->lineas?->codigo, 6);
        $agrupacion = self::codigoPadded($articulo?->categorias?->codigo, 4);
        $umdStock = (int) ($articulo?->unidadmedida_id ?? $articulo?->unidadesxenvase ?? 0);
        $umdComp = (int) ($articulo?->unidadmedidaalternativa_id ?? $umdStock);
        $unidadMedida = self::unidadMedidaAbrev($articulo);

        return '
            '.AnitaSqlLiteral::int($ctx->numeroRequisicion()).',
            '.AnitaSqlLiteral::int($nroOrden).',
            '.AnitaSqlLiteral::string($ctx->articuloSkuPadded($sku), 13).',
            '.AnitaSqlLiteral::string($desc, 30).',
            '.AnitaSqlLiteral::string($marca, 8).',
            '.AnitaSqlLiteral::string($lineaCod, 6).',
            '.AnitaSqlLiteral::string($agrupacion, 4).',
            '.AnitaSqlLiteral::string($unidadMedida, 3).',
            '.AnitaSqlLiteral::decimal((float) $linea->cantidad).',
            0,
            '.AnitaSqlLiteral::decimal((float) $linea->precio).',
            0,
            '.AnitaSqlLiteral::int(self::tipoIvaArticulo($articulo)).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaYmd()).',
            '.AnitaSqlLiteral::int((int) $ctx->fechaYmd($linea->fechaentrega)).',
            0,
            0,
            '.AnitaSqlLiteral::int($ctx->usuarioAnitaCodigo()).',
            '.AnitaSqlLiteral::int($ctx->centrocostoCodigoLinea($linea)).',
            '.AnitaSqlLiteral::int($umdComp).',
            '.AnitaSqlLiteral::decimal((float) ($linea->cantidadalternativa ?? 0)).',
            '.AnitaSqlLiteral::int($umdStock).',
            '.AnitaSqlLiteral::decimal((float) ($articulo?->unidadesxenvase ?? 0)).',
            '.AnitaSqlLiteral::char(' ').',
            '.AnitaSqlLiteral::int($ctx->empresaCodigo()).',
            '.AnitaSqlLiteral::string($ctx->proveedorCodigo(), 6).',
            0,
            '.AnitaSqlLiteral::int($nroInterno).',
            '.AnitaSqlLiteral::decimal((float) ($linea->preciooriginal ?? $linea->precio)).',
            '.AnitaSqlLiteral::string((string) ($linea->motivoahorro ?? ''), 15).'
        ';
    }

    private static function codigoPadded(mixed $codigo, int $len): string
    {
        $s = trim((string) $codigo);
        if ($s === '') {
            return str_repeat(' ', $len);
        }

        return str_pad($s, $len, ' ', STR_PAD_RIGHT);
    }

    private static function unidadMedidaAbrev(?Articulo $articulo): string
    {
        $abrev = trim((string) ($articulo?->unidadesdemedidas?->abreviatura ?? ''));

        if ($abrev === '') {
            return '   ';
        }

        return str_pad(mb_substr($abrev, 0, 3), 3, ' ', STR_PAD_RIGHT);
    }

    private static function tipoIvaArticulo(?Articulo $articulo): int
    {
        if ($articulo === null) {
            return 0;
        }

        return (int) ($articulo->impuestos?->codigo ?? $articulo->impuesto_id ?? 0);
    }
}
