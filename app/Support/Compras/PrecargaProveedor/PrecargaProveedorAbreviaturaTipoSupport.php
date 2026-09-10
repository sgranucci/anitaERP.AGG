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
        foreach (self::centrocostosDestinoTodosDesdeOrdencompra($oc) as $cc) {
            return $cc;
        }

        return null;
    }

    /**
     * Todos los CC destino de líneas (y cabecera), en orden. Sirve para OC mixtas (FIB + FGA).
     *
     * @return list<Centrocosto>
     */
    public static function centrocostosDestinoTodosDesdeOrdencompra(Ordencompra $oc): array
    {
        $oc->loadMissing([
            'centrocostos:id,codigo,tipoiva',
            'ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
        ]);

        $out = [];
        $seen = [];
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $cc = $linea->centrocostos_destino;
            if (! $cc || trim((string) ($cc->codigo ?? '')) === '' || (string) $cc->codigo === '0') {
                continue;
            }
            $key = (string) $cc->codigo;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $cc;
        }

        $header = $oc->centrocostos;
        if ($header && trim((string) ($header->codigo ?? '')) !== '' && (string) $header->codigo !== '0') {
            $key = (string) $header->codigo;
            if (! isset($seen[$key])) {
                $out[] = $header;
            }
        }

        return $out;
    }

    /**
     * Abreviaturas finas (FIB/FGA/…) por familia AFIP, a partir de varios CC.
     *
     * @param  list<array{codigo?: int|string, tipoiva?: string}>  $centros
     * @return array{FC: list<string>, NC: list<string>, ND: list<string>}
     */
    public static function abreviaturasFinoDesdeCentros(
        array $centros,
        string $tipoItem,
        bool $incluirGastronomia = true,
    ): array {
        $out = ['FC' => [], 'NC' => [], 'ND' => []];
        foreach (['FC', 'NC', 'ND'] as $gen) {
            $set = [];
            foreach ($centros as $cc) {
                $abrev = self::abreviatura(
                    $gen,
                    $cc['codigo'] ?? 0,
                    (string) ($cc['tipoiva'] ?? ''),
                    $tipoItem,
                );
                if ($abrev !== '') {
                    $set[$abrev] = true;
                }
            }
            if ($incluirGastronomia) {
                $gastro = self::abreviatura($gen, 85, 'I', $tipoItem);
                if ($gastro !== '') {
                    $set[$gastro] = true;
                }
            }
            $out[$gen] = array_keys($set);
        }

        return $out;
    }

    public static function esTipoGenerico(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), ['FC', 'NC', 'ND'], true);
    }

    /**
     * Opciones para corregir el tipo en precarga / bandeja (finos de todos los CC + gastronomía).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function opcionesCorreccionTipo(Ordencompra $oc, ?string $abrevActual = null): array
    {
        $centros = [];
        foreach (self::centrocostosDestinoTodosDesdeOrdencompra($oc) as $cc) {
            $centros[] = [
                'codigo' => $cc->codigo ?? '',
                'tipoiva' => (string) ($cc->tipoiva ?? ''),
            ];
        }

        $tipoItem = 'B';
        try {
            $tipoItem = self::tipoItemDesdeOrdencompra($oc);
        } catch (Throwable) {
            $tipoItem = 'B';
        }

        $porFamilia = self::abreviaturasFinoDesdeCentros($centros, $tipoItem, true);
        $abrevActual = strtoupper(trim((string) $abrevActual));
        if ($abrevActual !== '' && ! self::esTipoGenerico($abrevActual)) {
            $fam = match (substr($abrevActual, 0, 1)) {
                'C' => 'NC',
                'D' => 'ND',
                default => 'FC',
            };
            $porFamilia[$fam][] = $abrevActual;
            $porFamilia[$fam] = array_values(array_unique($porFamilia[$fam]));
        }

        $abrevs = [];
        foreach ($porFamilia as $lista) {
            foreach ($lista as $a) {
                $abrevs[$a] = true;
            }
        }

        $nombres = [];
        try {
            if ($abrevs !== []) {
                $nombres = Tipotransaccion_Compra::query()
                    ->whereIn('abreviatura', array_keys($abrevs))
                    ->pluck('nombre', 'abreviatura')
                    ->all();
            }
        } catch (Throwable) {
            $nombres = [];
        }

        $opciones = [];
        $seen = [];
        foreach (['FC', 'NC', 'ND'] as $fam) {
            foreach ($porFamilia[$fam] ?? [] as $abrev) {
                if (isset($seen[$abrev])) {
                    continue;
                }
                $seen[$abrev] = true;
                $nombre = trim((string) ($nombres[$abrev] ?? ''));
                $opciones[] = [
                    'value' => $abrev,
                    'label' => $nombre !== '' ? $abrev.' — '.$nombre : $abrev,
                ];
            }
        }

        foreach ([
            'FC' => 'FC — Factura (según primer centro de costo de la OC)',
            'NC' => 'NC — Nota de crédito (no exige COM)',
            'ND' => 'ND — Nota de débito (no exige COM)',
        ] as $val => $label) {
            if (! isset($seen[$val])) {
                $opciones[] = ['value' => $val, 'label' => $label];
            }
        }

        return $opciones;
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
