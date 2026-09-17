<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraLegajoScanAnitaDescartarService;
use Illuminate\Support\Facades\Log;

/**
 * Factura escaneada en Anita (base_admin.scanfactura + documentos → /scan/compras/documentos).
 */
final class OrdencompraLegajoAnitaScanFacturaSupport
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $filasPorNrosCache = [];

    /** @var array<int, list<array<string, mixed>>> */
    private static array $facturasPorOcCache = [];

    /** @var array<int, list<array<string, mixed>>> cache sin filtrar descartes (para resolver al borrar) */
    private static array $facturasPorOcCacheCrudo = [];

    public static function forgetCache(): void
    {
        self::$filasPorNrosCache = [];
        self::$facturasPorOcCache = [];
        self::$facturasPorOcCacheCrudo = [];
    }

    /**
     * @param  list<Ordencompra>  $ocs
     * @return array<int, list<array<string, mixed>>>
     */
    public static function facturasPorOcs(iterable $ocs): array
    {
        $crudo = self::facturasPorOcsCrudo($ocs);
        $ocIds = array_keys($crudo);
        $descartados = self::documentoIdsDescartados($ocIds);

        $out = [];
        foreach ($crudo as $ocId => $scans) {
            $omitidos = $descartados[$ocId] ?? [];
            if ($omitidos === []) {
                $out[$ocId] = $scans;
                self::$facturasPorOcCache[$ocId] = $scans;

                continue;
            }
            $filtrados = [];
            foreach ($scans as $scan) {
                $docId = (int) ($scan['documento_id'] ?? 0);
                if ($docId > 0 && isset($omitidos[$docId])) {
                    continue;
                }
                $filtrados[] = $scan;
            }
            $out[$ocId] = $filtrados;
            self::$facturasPorOcCache[$ocId] = $filtrados;
        }

        return $out;
    }

    /**
     * Igual que facturasPorOcs pero incluye scans ya descartados en ERP
     * (útil al borrar precarga para resolver el documento_id a desvincular).
     *
     * @param  list<Ordencompra>  $ocs
     * @return array<int, list<array<string, mixed>>>
     */
    public static function facturasPorOcsCrudo(iterable $ocs): array
    {
        $porClave = [];
        $listaOcs = [];
        foreach ($ocs as $oc) {
            $listaOcs[] = $oc;
            $ocId = (int) $oc->id;
            if ($ocId > 0 && array_key_exists($ocId, self::$facturasPorOcCacheCrudo)) {
                continue;
            }
            $nro = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
            $emp = self::empresaAnitaId($oc);
            if ($nro <= 0 || $emp <= 0) {
                if ($ocId > 0) {
                    self::$facturasPorOcCacheCrudo[$ocId] = [];
                }
                continue;
            }
            $porClave[$emp.'|'.$nro] = $ocId;
        }
        if ($porClave === []) {
            $out = [];
            foreach ($listaOcs as $oc) {
                $ocId = (int) $oc->id;
                $out[$ocId] = self::$facturasPorOcCacheCrudo[$ocId] ?? [];
            }

            return $out;
        }

        $nros = array_values(array_unique(array_map(
            static fn (string $k) => (int) explode('|', $k, 2)[1],
            array_keys($porClave)
        )));
        $filas = self::listarScanFactura($nros);
        foreach ($porClave as $ocId) {
            self::$facturasPorOcCacheCrudo[$ocId] = self::$facturasPorOcCacheCrudo[$ocId] ?? [];
        }
        foreach ($filas as $fila) {
            $emp = (int) ($fila['iempresaid'] ?? 0);
            $nro = (int) ($fila['iotid'] ?? 0);
            $docId = (int) ($fila['idocumentoid'] ?? 0);
            $ocId = $porClave[$emp.'|'.$nro] ?? null;
            if ($ocId === null || $docId <= 0) {
                continue;
            }
            $numero = self::numero($fila);
            $tipoGen = \App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport::normalizar(
                (string) ($fila['ctipo'] ?? 'FC')
            );
            $tipoLabel = OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipoGen);
            $etiqueta = OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipoGen, $numero);
            self::$facturasPorOcCacheCrudo[$ocId][] = [
                'id' => 'anita-'.$docId,
                'origen' => 'anita',
                'origen_label' => PrecargaComprobanteOrigenEntrada::etiqueta(PrecargaComprobanteOrigenEntrada::SCAN_ANITA),
                'documento_id' => $docId,
                'tipo' => $tipoGen,
                'tipo_label' => $tipoLabel,
                'exige_com' => OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipoGen),
                'numero' => $etiqueta,
                'etiqueta' => $etiqueta,
                'fecha' => self::fecha($fila['ifecha'] ?? ''),
                'total' => null,
                'estado' => PrecargaComprobanteOrigenEntrada::etiqueta(PrecargaComprobanteOrigenEntrada::SCAN_ANITA),
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_anita_pdf', [
                    'id' => $ocId,
                    'documento' => $docId,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => null,
            ];
        }

        $out = [];
        foreach ($listaOcs as $oc) {
            $ocId = (int) $oc->id;
            $out[$ocId] = self::$facturasPorOcCacheCrudo[$ocId] ?? [];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function facturasDeOc(Ordencompra $oc): array
    {
        $ocId = (int) $oc->id;
        if ($ocId > 0 && array_key_exists($ocId, self::$facturasPorOcCache)) {
            return self::$facturasPorOcCache[$ocId];
        }

        return self::facturasPorOcs([$oc])[$ocId] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function facturasDeOcIncluyendoDescartados(Ordencompra $oc): array
    {
        $ocId = (int) $oc->id;
        if ($ocId > 0 && array_key_exists($ocId, self::$facturasPorOcCacheCrudo)) {
            return self::$facturasPorOcCacheCrudo[$ocId];
        }

        return self::facturasPorOcsCrudo([$oc])[$ocId] ?? [];
    }

    /**
     * @param  list<int>  $ordencompraIds
     * @return array<int, array<int, true>>
     */
    private static function documentoIdsDescartados(array $ordencompraIds): array
    {
        try {
            return app(OrdencompraLegajoScanAnitaDescartarService::class)
                ->documentoIdsDescartadosPorOcIds($ordencompraIds);
        } catch (\Throwable $e) {
            Log::warning('bandeja.anita_scan_factura.descartados', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public static function rutaPdf(int $documentoId): ?string
    {
        if ($documentoId <= 0) {
            return null;
        }
        $nombre = sprintf('docu_%010d.pdf', $documentoId);
        $dirs = [
            rtrim((string) config('comprobante_proveedor_pdf_ia.corpus.scan_legacy_dir', '/scan/compras/documentos'), '/'),
            '/scan/compras/documentos',
        ];
        foreach (array_unique($dirs) as $dir) {
            $path = $dir.'/'.$nombre;
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Elige el PDF Anita que corresponde al comprobante (nunca el "primero" del legajo).
     *
     * Prioridad: documento explícito → match letra/sucursal/número (+tipo si hay varios)
     * → único scan del legajo si el prefill aún no tiene número.
     *
     * @param  list<array<string, mixed>>  $scans
     */
    public static function documentoIdCompatible(
        array $scans,
        string $letra = '',
        int $sucursal = 0,
        int $numero = 0,
        ?string $tipo = null,
        ?int $documentoIdPreferido = null,
    ): ?int {
        if ($documentoIdPreferido !== null && $documentoIdPreferido > 0) {
            foreach ($scans as $scan) {
                if ((int) ($scan['documento_id'] ?? 0) === $documentoIdPreferido) {
                    return $documentoIdPreferido;
                }
            }

            return $documentoIdPreferido;
        }

        $clave = self::claveNumeroFactura($letra, $sucursal, $numero);
        if ($clave !== '') {
            $matches = [];
            foreach ($scans as $scan) {
                $scanClave = self::claveDesdeEtiqueta((string) ($scan['numero'] ?? $scan['etiqueta'] ?? ''));
                if ($scanClave !== $clave) {
                    continue;
                }
                $docId = (int) ($scan['documento_id'] ?? 0);
                if ($docId <= 0) {
                    continue;
                }
                $matches[] = [
                    'documento_id' => $docId,
                    'tipo' => strtoupper(trim((string) ($scan['tipo'] ?? ''))),
                ];
            }
            if ($matches === []) {
                return null;
            }
            $tipoNorm = strtoupper(trim((string) $tipo));
            if ($tipoNorm !== '' && count($matches) > 1) {
                $porTipo = array_values(array_filter(
                    $matches,
                    static fn (array $m) => ($m['tipo'] === '' || $m['tipo'] === $tipoNorm)
                ));
                if (count($porTipo) === 1) {
                    return $porTipo[0]['documento_id'];
                }
                if ($porTipo !== []) {
                    $matches = $porTipo;
                }
            }
            if (count($matches) === 1) {
                return $matches[0]['documento_id'];
            }

            return null;
        }

        if (count($scans) === 1) {
            $docId = (int) ($scans[0]['documento_id'] ?? 0);

            return $docId > 0 ? $docId : null;
        }

        return null;
    }

    public static function documentoIdDesdeAnitaRef(string $anitaRef): int
    {
        $anitaRef = trim($anitaRef);
        if ($anitaRef === '') {
            return 0;
        }
        if (preg_match('/^anita-(\d+)$/i', $anitaRef, $m)) {
            return (int) $m[1];
        }
        if (ctype_digit($anitaRef)) {
            return (int) $anitaRef;
        }

        return 0;
    }

    private static function claveNumeroFactura(string $letra, int $sucursal, int $numero): string
    {
        $letra = strtoupper(trim($letra));
        if ($letra === '' && $numero <= 0) {
            return '';
        }

        return ($letra !== '' ? $letra : 'FC').'|'.$sucursal.'|'.$numero;
    }

    private static function claveDesdeEtiqueta(string $etiqueta): string
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if (preg_match('/([A-Z])\s+(\d{1,5})-(\d{1,8})/', $etiqueta, $m)) {
            return $m[1].'|'.((int) $m[2]).'|'.((int) $m[3]);
        }

        return '';
    }

    public static function perteneceAlLegajo(Ordencompra $oc, int $documentoId): bool
    {
        return self::filaDeOc($oc, $documentoId) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function filaDeOc(Ordencompra $oc, int $documentoId): ?array
    {
        if ($documentoId <= 0) {
            return null;
        }
        if (self::estaDescartadoEnOc($oc, $documentoId)) {
            return null;
        }
        $nro = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
        $emp = self::empresaAnitaId($oc);
        if ($nro <= 0 || $emp <= 0) {
            return null;
        }
        $filas = self::listarScanFactura([$nro], $documentoId);

        foreach ($filas as $fila) {
            if ((int) ($fila['iempresaid'] ?? 0) === $emp
                && (int) ($fila['iotid'] ?? 0) === $nro
                && (int) ($fila['idocumentoid'] ?? 0) === $documentoId) {
                return $fila;
            }
        }

        return null;
    }

    private static function estaDescartadoEnOc(Ordencompra $oc, int $documentoId): bool
    {
        try {
            return app(OrdencompraLegajoScanAnitaDescartarService::class)
                ->estaDescartado($oc, $documentoId);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function empresaAnitaId(Ordencompra $oc): int
    {
        $codigo = (int) ($oc->empresas->codigo ?? $oc->empresa_id ?? 0);
        if ($codigo > 0 && $codigo < 10) {
            return $codigo;
        }

        return (int) ($oc->empresa_id ?? 0);
    }

    /**
     * @param  list<int>  $nrosOc
     * @return list<array<string, mixed>>
     */
    private static function listarScanFactura(array $nrosOc, ?int $documentoId = null): array
    {
        $nrosOc = array_values(array_filter(array_map('intval', $nrosOc), static fn (int $n) => $n > 0));
        if ($nrosOc === []) {
            return [];
        }

        $where = ' WHERE iotid IN ('.implode(',', $nrosOc).') AND idocumentoid > 0';
        if ($documentoId !== null && $documentoId > 0) {
            $where .= ' AND idocumentoid = '.(int) $documentoId;
        }

        try {
            $cacheKey = implode(',', $nrosOc).'|'.(int) ($documentoId ?? 0);
            if (array_key_exists($cacheKey, self::$filasPorNrosCache)) {
                return self::$filasPorNrosCache[$cacheKey];
            }

            $raw = (new ApiAnita())->apiCall([
                'acc' => 'list',
                'sistema' => 'base_admin',
                'tabla' => 'scanfactura',
                'campos' => 'iempresaid, cproveedor, ctipo, cletra, isucursal, inumero, idocumentoid, iotid, ifecha',
                'whereArmado' => $where,
                'orderBy' => 'idocumentoid DESC',
            ]);
        } catch (\Throwable $e) {
            Log::warning('bandeja.anita_scan_factura', ['error' => $e->getMessage()]);

            return self::$filasPorNrosCache[$cacheKey ?? ''] = [];
        }

        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        $out = [];
        foreach ($filas as $fila) {
            $out[] = (array) $fila;
        }

        return self::$filasPorNrosCache[$cacheKey] = $out;
    }

    /** @param  array<string, mixed>  $fila */
    private static function numero(array $fila): string
    {
        $letra = trim((string) ($fila['cletra'] ?? ''));
        $suc = (int) ($fila['isucursal'] ?? 0);
        $nro = (int) ($fila['inumero'] ?? 0);

        return trim(sprintf(
            '%s %04d-%08d',
            $letra !== '' ? $letra : 'FC',
            $suc,
            $nro
        ));
    }

    private static function fecha(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) === 8) {
            return substr($ymd, 6, 2).'/'.substr($ymd, 4, 2).'/'.substr($ymd, 0, 4);
        }

        return $ymd;
    }
}
