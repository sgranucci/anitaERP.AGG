<?php

namespace App\Support\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use Illuminate\Support\Facades\Log;

/**
 * Factura escaneada en Anita (base_admin.scanfactura + documentos → /scan/compras/documentos).
 */
final class OrdencompraLegajoAnitaScanFacturaSupport
{
    /**
     * @param  list<Ordencompra>  $ocs
     * @return array<int, list<array<string, mixed>>>
     */
    public static function facturasPorOcs(iterable $ocs): array
    {
        $porClave = [];
        foreach ($ocs as $oc) {
            $nro = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
            $emp = self::empresaAnitaId($oc);
            if ($nro <= 0 || $emp <= 0) {
                continue;
            }
            $porClave[$emp.'|'.$nro] = (int) $oc->id;
        }
        if ($porClave === []) {
            return [];
        }

        $nros = array_values(array_unique(array_map(
            static fn (string $k) => (int) explode('|', $k, 2)[1],
            array_keys($porClave)
        )));
        $filas = self::listarScanFactura($nros);
        $out = [];
        foreach ($filas as $fila) {
            $emp = (int) ($fila['iempresaid'] ?? 0);
            $nro = (int) ($fila['iotid'] ?? 0);
            $docId = (int) ($fila['idocumentoid'] ?? 0);
            $ocId = $porClave[$emp.'|'.$nro] ?? null;
            if ($ocId === null || $docId <= 0) {
                continue;
            }
            $out[$ocId][] = [
                'id' => 'anita-'.$docId,
                'origen' => 'anita',
                'documento_id' => $docId,
                'etiqueta' => self::etiqueta($fila),
                'fecha' => self::fecha($fila['ifecha'] ?? ''),
                'total' => null,
                'estado' => 'Anita',
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_anita_pdf', [
                    'id' => $ocId,
                    'documento' => $docId,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function facturasDeOc(Ordencompra $oc): array
    {
        return self::facturasPorOcs([$oc])[(int) $oc->id] ?? [];
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

    public static function perteneceAlLegajo(Ordencompra $oc, int $documentoId): bool
    {
        if ($documentoId <= 0) {
            return false;
        }
        $nro = (int) preg_replace('/\D+/', '', (string) $oc->numeroordencompra);
        $emp = self::empresaAnitaId($oc);
        if ($nro <= 0 || $emp <= 0) {
            return false;
        }
        $filas = self::listarScanFactura([$nro], $documentoId);

        foreach ($filas as $fila) {
            if ((int) ($fila['iempresaid'] ?? 0) === $emp
                && (int) ($fila['iotid'] ?? 0) === $nro
                && (int) ($fila['idocumentoid'] ?? 0) === $documentoId) {
                return true;
            }
        }

        return false;
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

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        $out = [];
        foreach ($filas as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    /** @param  array<string, mixed>  $fila */
    private static function etiqueta(array $fila): string
    {
        $letra = trim((string) ($fila['cletra'] ?? ''));
        $suc = (int) ($fila['isucursal'] ?? 0);
        $nro = (int) ($fila['inumero'] ?? 0);

        return trim(sprintf('%s %04d-%08d (Anita)', $letra !== '' ? $letra : 'FC', $suc, $nro));
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
