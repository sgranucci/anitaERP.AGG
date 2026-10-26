<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Tipoarticulo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Throwable;

/**
 * Abreviatura fina de factura compra (FIB/FGA/FIS/FIA…) según CC destino + tipo IVA + ítem.
 *
 * Misma regla que listaConcepto / PrecargaProveedorConceptosListaSupport:
 * - La 3ª letra sale del tipo de artículo: abreviatura B/S/L/U, o letra_comprobante_compra del maestro.
 * - En AGG, si ese tipo tiene comprobante_compra_iva_indirecto, la 2ª letra es I
 *   (no mira el tipo de IVA del centro ni los atajos de gastronomía / EGA).
 * - CC 85 → xGA (gastronomía)
 * - CC 104 → xEG
 * - resto → inicial AFIP + 1ª letra tipoiva (I/D/N) + tipo ítem
 */
final class PrecargaProveedorAbreviaturaTipoSupport
{
    /** @var array<string, bool> */
    private static array $cacheIvaIndirecto = [];

    /**
     * @param  'FC'|'ND'|'NC'|'REC'|'REM'|string  $tipoComprobante
     * @param  bool|null  $ivaIndirectoForzado  null = leer el maestro (solo aplica en AGG)
     */
    public static function abreviatura(
        string $tipoComprobante,
        int|string $codigoCentroCosto,
        string $tipoIva,
        string $tipoItem,
        ?bool $ivaIndirectoForzado = null,
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

        $letraItem = self::normalizarLetraItem($tipoItem);
        $forzarIndirecto = $ivaIndirectoForzado ?? self::tipoFuerzaIvaIndirecto($letraItem);
        if ($forzarIndirecto) {
            return $inicial.'I'.$letraItem;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) $codigoCentroCosto);

        return match ($codigo) {
            85 => $inicial.'GA',
            104 => $inicial.'EG',
            default => $inicial.strtoupper(substr(trim($tipoIva), 0, 1)).$letraItem,
        };
    }

    /**
     * Letra de una posición (B/S/L/U/A…). Cualquier otra cosa cae en bienes.
     */
    public static function normalizarLetraItem(string $tipoItem): string
    {
        $letra = strtoupper(trim($tipoItem));

        return preg_match('/^[A-Z]$/', $letra) === 1 ? $letra : 'B';
    }

    /**
     * Resuelve abreviatura desde OC ERP (sin Anita). Null si falta CC/tipoiva válido.
     *
     * @param  'FC'|'ND'|'NC'|string  $tipoComprobante
     */
    public static function abreviaturaDesdeOrdencompra(Ordencompra $oc, string $tipoComprobante = 'FC'): ?string
    {
        $tipoComprobante = strtoupper(trim($tipoComprobante));
        // El recibo no depende del centro de costo: el maestro es la abreviatura REC.
        if ($tipoComprobante === 'REC') {
            return 'REC';
        }

        $oc->loadMissing([
            'centrocostos:id,codigo,tipoiva',
            'ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
            'ordencompra_articulos.articulos.tipoarticulos:id,nombre,abreviatura,letra_comprobante_compra,comprobante_compra_iva_indirecto',
            'ordencompra_articulos.articulos.categorias:id,nombre,codigo',
        ]);

        $tipoItem = self::tipoItemDesdeOrdencompra($oc);
        $centros = PrecargaProveedorProrrateoMultiCcSupport::centrosConPesoDesdeOrdencompraErp($oc);
        if (count($centros) >= 2) {
            $prorrateo = app(PrecargaProveedorProrrateoMultiCcSupport::class)
                ->resolverDesdeCentros($tipoComprobante, $tipoItem, $centros);
            if ($prorrateo['activo'] && $prorrateo['tipocomprobante'] !== '') {
                return $prorrateo['tipocomprobante'];
            }
        }

        $centrocosto = self::centrocostoDestinoDesdeOrdencompra($oc);
        if (! $centrocosto) {
            return null;
        }

        $tipoIva = (string) ($centrocosto->tipoiva ?? '');
        if (! in_array(substr($tipoIva, 0, 1), ['I', 'D', 'N'], true)) {
            return null;
        }

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
        return in_array(strtoupper(trim($tipo)), ['FC', 'NC', 'ND', 'REC'], true);
    }

    /**
     * Opciones para corregir el tipo en precarga / bandeja (finos de todos los CC + gastronomía).
     * Incluye variantes bienes (B) y servicios (S) para poder pasar p. ej. FIB ↔ FIS en prepagas.
     *
     * @param  string|list<string>|null  $abrevActual  Abreviatura(s) ya grabada(s) en precarga(s)
     * @return list<array{value: string, label: string}>
     */
    public static function opcionesCorreccionTipo(Ordencompra $oc, string|array|null $abrevActual = null): array
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

        // Unión B+S: la OC puede resolverse como bienes y aun así la factura ser servicio (prepaga/FIS).
        $tiposItem = array_values(array_unique([$tipoItem, 'B', 'S']));
        $porFamilia = ['FC' => [], 'NC' => [], 'ND' => []];
        foreach ($tiposItem as $ti) {
            $parcial = self::abreviaturasFinoDesdeCentros($centros, $ti, true);
            foreach (['FC', 'NC', 'ND'] as $fam) {
                $porFamilia[$fam] = array_values(array_unique(array_merge(
                    $porFamilia[$fam],
                    $parcial[$fam] ?? []
                )));
            }
        }

        $abrevsActuales = is_array($abrevActual) ? $abrevActual : [$abrevActual];
        foreach ($abrevsActuales as $abrev) {
            $abrev = strtoupper(trim((string) $abrev));
            if ($abrev === '' || self::esTipoGenerico($abrev)) {
                continue;
            }
            if (PrecargaProveedorProrrateoMultiCcSupport::esTipoProrrateado($abrev)
                && ! PrecargaProveedorProrrateoMultiCcSupport::ocTieneMultiplesCentrosDestino($oc)) {
                continue;
            }
            $fam = match (substr($abrev, 0, 1)) {
                'C' => 'NC',
                'D' => 'ND',
                default => 'FC',
            };
            $porFamilia[$fam][] = $abrev;
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
            'REC' => 'REC — Recibo',
        ] as $val => $label) {
            if (! isset($seen[$val])) {
                $opciones[] = ['value' => $val, 'label' => $label];
            }
        }

        return $opciones;
    }

    public static function tipoItemDesdeOrdencompra(Ordencompra $oc): string
    {
        $oc->loadMissing([
            'ordencompra_articulos.articulos.tipoarticulos:id,nombre,abreviatura,letra_comprobante_compra,comprobante_compra_iva_indirecto',
            'ordencompra_articulos.articulos.categorias:id,nombre,codigo',
        ]);

        $letraTipo = self::letraComprobanteUniforme($oc);
        if ($letraTipo !== null) {
            return $letraTipo;
        }

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

    /**
     * Si todas las líneas con artículo tienen la misma letra_comprobante_compra, esa es la 3ª letra.
     */
    private static function letraComprobanteUniforme(Ordencompra $oc): ?string
    {
        $letras = [];
        $conArticulo = 0;
        foreach ($oc->ordencompra_articulos ?? [] as $linea) {
            $art = $linea->articulos;
            if (! $art) {
                continue;
            }
            $conArticulo++;
            $letra = strtoupper(trim((string) ($art->tipoarticulos->letra_comprobante_compra ?? '')));
            if (preg_match('/^[A-Z]$/', $letra) !== 1) {
                return null;
            }
            $letras[$letra] = true;
        }
        if ($conArticulo === 0 || count($letras) !== 1) {
            return null;
        }

        return (string) array_key_first($letras);
    }

    /**
     * El maestro del tipo marca IVA indirecto y la instalación es AGG.
     */
    private static function tipoFuerzaIvaIndirecto(string $letra): bool
    {
        if (! self::entornoEsAgg()) {
            return false;
        }
        if (array_key_exists($letra, self::$cacheIvaIndirecto)) {
            return self::$cacheIvaIndirecto[$letra];
        }

        try {
            $fuerza = Tipoarticulo::query()
                ->where('letra_comprobante_compra', $letra)
                ->where('comprobante_compra_iva_indirecto', true)
                ->exists();
        } catch (Throwable) {
            $fuerza = false;
        }

        return self::$cacheIvaIndirecto[$letra] = $fuerza;
    }

    private static function entornoEsAgg(): bool
    {
        try {
            if (! function_exists('app') || ! app()->bound('config')) {
                return false;
            }

            return EntornoEmpresaSupport::esAgg();
        } catch (Throwable) {
            return false;
        }
    }
}
