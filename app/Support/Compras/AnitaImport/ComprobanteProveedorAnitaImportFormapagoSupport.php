<?php

namespace App\Support\Compras\AnitaImport;

use App\Models\Ventas\Formapago;
use Illuminate\Support\Facades\DB;

/**
 * Forma de pago al importar comprobantes Anita → ERP.
 *
 * `promov` no trae medio. Anita muestra el de la OC (`occ_medio_pago`); si no hay
 * vínculo, el del proveedor. El default Efectivo del config solo es último recurso.
 */
final class ComprobanteProveedorAnitaImportFormapagoSupport
{
    /** @var array<int, int|null> */
    private static array $cacheOc = [];

    /** @var array<int, int|null> */
    private static array $cacheProveedor = [];

    private static ?int $efectivoId = null;

    private static ?int $fallbackId = null;

    public static function resetCache(): void
    {
        self::$cacheOc = [];
        self::$cacheProveedor = [];
        self::$efectivoId = null;
        self::$fallbackId = null;
    }

    public static function idEfectivo(): int
    {
        if (self::$efectivoId !== null) {
            return self::$efectivoId;
        }

        $id = Formapago::query()->where('abreviatura', 'E')->value('id');

        return self::$efectivoId = $id !== null ? (int) $id : 1;
    }

    public static function idFallbackConfig(): int
    {
        if (self::$fallbackId !== null) {
            return self::$fallbackId;
        }

        $id = (int) config('comprobante_proveedor.import_anita.formapago_id', self::idEfectivo());

        return self::$fallbackId = $id > 0 ? $id : self::idEfectivo();
    }

    /**
     * Prioridad: OC (no-Efectivo si hay) → proveedor (no-Efectivo si hay) → config.
     */
    public static function resolverId(?int $proveedorId, ?int $ordencompraId = null): int
    {
        $desdeOc = self::desdeOrdencompra($ordencompraId);
        if ($desdeOc !== null && $desdeOc > 0) {
            return $desdeOc;
        }

        $desdeProv = self::desdeProveedor($proveedorId);
        if ($desdeProv !== null && $desdeProv > 0) {
            return $desdeProv;
        }

        return self::idFallbackConfig();
    }

    public static function desdeOrdencompra(?int $ordencompraId): ?int
    {
        $ordencompraId = (int) $ordencompraId;
        if ($ordencompraId <= 0) {
            return null;
        }
        if (array_key_exists($ordencompraId, self::$cacheOc)) {
            return self::$cacheOc[$ordencompraId];
        }

        $efectivo = self::idEfectivo();
        $row = DB::table('ordencompra_comprobante as occ')
            ->join('ordencompra_comprobante_cuota as occu', 'occu.ordencompra_comprobante_id', '=', 'occ.id')
            ->where('occ.ordencompra_id', $ordencompraId)
            ->whereNotNull('occu.formapago_id')
            ->where('occu.formapago_id', '>', 0)
            ->selectRaw(
                'MIN(CASE WHEN occu.formapago_id <> ? THEN occu.formapago_id END) as fp_no_e, MIN(occu.formapago_id) as fp_any',
                [$efectivo]
            )
            ->first();

        $id = null;
        if ($row) {
            $noE = (int) ($row->fp_no_e ?? 0);
            $any = (int) ($row->fp_any ?? 0);
            $id = $noE > 0 ? $noE : ($any > 0 ? $any : null);
        }

        return self::$cacheOc[$ordencompraId] = $id;
    }

    public static function desdeProveedor(?int $proveedorId): ?int
    {
        $proveedorId = (int) $proveedorId;
        if ($proveedorId <= 0) {
            return null;
        }
        if (array_key_exists($proveedorId, self::$cacheProveedor)) {
            return self::$cacheProveedor[$proveedorId];
        }

        $efectivo = self::idEfectivo();
        $row = DB::table('proveedor_formapago')
            ->where('proveedor_id', $proveedorId)
            ->whereNotNull('formapago_id')
            ->where('formapago_id', '>', 0)
            ->selectRaw(
                'MIN(CASE WHEN formapago_id <> ? THEN formapago_id END) as fp_no_e, MIN(formapago_id) as fp_any',
                [$efectivo]
            )
            ->first();

        $id = null;
        if ($row) {
            $noE = (int) ($row->fp_no_e ?? 0);
            $any = (int) ($row->fp_any ?? 0);
            $id = $noE > 0 ? $noE : ($any > 0 ? $any : null);
        }

        return self::$cacheProveedor[$proveedorId] = $id;
    }
}
