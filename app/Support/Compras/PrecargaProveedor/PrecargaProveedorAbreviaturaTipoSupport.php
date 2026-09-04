<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Contable\Centrocosto;
use Throwable;

/**
 * Abreviatura fina de factura compra (FIB/FGA/FIS…) según CC destino + tipo IVA + ítem.
 *
 * Misma regla que listaConcepto / PrecargaProveedorConceptosListaSupport:
 * - CC 85 → xGA (gastronomía)
 * - CC 104 → xEG
 * - resto → inicial AFIP + 1ª letra tipoiva (I/D/N) + tipo ítem (B/S/L/U)
 */
final class PrecargaProveedorAbreviaturaTipoSupport
{
    /**
     * @param  'FC'|'ND'|'NC'|'REC'|'REM'|string  $tipoComprobante
     */
    public static function abreviatura(
        string $tipoComprobante,
        int|string $codigoCentroCosto,
        string $tipoIva,
        string $tipoItem,
    ): string {
        $tipoComprobante = strtoupper(trim($tipoComprobante));
        if (in_array($tipoComprobante, ['REC', 'REM'], true)) {
            return $tipoComprobante;
        }

        $inicial = match ($tipoComprobante) {
            'FC' => 'F',
            'ND' => 'D',
            'NC' => 'C',
            default => '',
        };
        if ($inicial === '') {
            return '';
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) $codigoCentroCosto);

        return match ($codigo) {
            85 => $inicial.'GA',
            104 => $inicial.'EG',
            default => $inicial.strtoupper(substr(trim($tipoIva), 0, 1)).strtoupper(trim($tipoItem) ?: 'B'),
        };
    }

    /**
     * Resuelve abreviatura desde OC ERP (sin Anita). Null si falta CC/tipoiva válido.
     *
     * @param  'FC'|'ND'|'NC'|string  $tipoComprobante
     */
    public static function abreviaturaDesdeOrdencompra(Ordencompra $oc, string $tipoComprobante = 'FC'): ?string
    {
        $oc->loadMissing([
            'centrocostos:id,codigo,tipoiva',
            'ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
            'ordencompra_articulos.articulos.tipoarticulos:id,nombre,abreviatura',
            'ordencompra_articulos.articulos.categorias:id,nombre,codigo',
        ]);

        $centrocosto = self::centrocostoDestinoDesdeOrdencompra($oc);
        if (! $centrocosto) {
            return null;
        }

        $tipoIva = (string) ($centrocosto->tipoiva ?? '');
        if (! in_array(substr($tipoIva, 0, 1), ['I', 'D', 'N'], true)) {
            return null;
        }

        $tipoItem = self::tipoItemDesdeOrdencompra($oc);
        $abrev = self::abreviatura(
            $tipoComprobante,
            (string) ($centrocosto->codigo ?? ''),
            $tipoIva,
            $tipoItem,
        );

        return $abrev !== '' ? $abrev : null;
    }

    public static function tipotransaccionIdDesdeOrdencompra(Ordencompra $oc, string $tipoComprobante = 'FC'): int
    {
        $abrev = self::abreviaturaDesdeOrdencompra($oc, $tipoComprobante);
        if ($abrev === null) {
            return 0;
        }

        try {
            return (int) (Tipotransaccion_Compra::query()
                ->where('abreviatura', $abrev)
                ->value('id') ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    public static function centrocostoDestinoDesdeOrdencompra(Ordencompra $oc): ?Centrocosto
    {
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $cc = $linea->centrocostos_destino;
            if ($cc && trim((string) ($cc->codigo ?? '')) !== '' && (string) $cc->codigo !== '0') {
                return $cc;
            }
        }

        return $oc->centrocostos;
    }

    public static function tipoItemDesdeOrdencompra(Ordencompra $oc): string
    {
        $proveedorId = (int) ($oc->proveedor_id ?? 0);
        if (PrecargaProveedorTipoItemSupport::proveedorTieneServicios(null, $proveedorId > 0 ? $proveedorId : null)) {
            return 'S';
        }

        $items = [];
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $art = $linea->articulos;
            if (! $art) {
                continue;
            }
            $abrevTipo = strtoupper(trim((string) ($art->tipoarticulos->abreviatura ?? '')));
            $codigoCat = trim((string) ($art->categorias->codigo ?? ''));
            $items[] = (object) [
                'sku' => (string) ($art->sku ?? ''),
                'stkm_tipo_articulo' => in_array($abrevTipo, ['S', 'U', 'B', 'L'], true) ? $abrevTipo : 'B',
                'stkm_agrupacion' => str_pad(ltrim($codigoCat, '0') !== '' ? $codigoCat : '0', 4, '0', STR_PAD_LEFT),
                'es_indumentaria' => PrecargaProveedorTipoItemSupport::esIndumentariaDesdeMaestros(
                    $art->tipoarticulos->abreviatura ?? null,
                    $art->tipoarticulos->nombre ?? null,
                    $art->categorias->nombre ?? null,
                ),
            ];
        }

        return PrecargaProveedorTipoItemSupport::resolverDesdeItemsOc($items);
    }
}
