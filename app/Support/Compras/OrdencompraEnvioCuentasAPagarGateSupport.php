<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use Illuminate\Support\Facades\DB;

/**
 * Validaciones obligatorias al enviar un legajo (OC) a CUENTAS A PAGAR.
 */
final class OrdencompraEnvioCuentasAPagarGateSupport
{
    public const SECTOR_CUENTAS_A_PAGAR = 'CUENTAS A PAGAR';

    public const SECTOR_COMPRAS = 'COMPRAS';

    public static function esSectorCuentasAPagar(int $sectorId): bool
    {
        if ($sectorId <= 0) {
            return false;
        }

        $nombre = Sector_Legajocompra::query()->whereKey($sectorId)->value('nombre');

        return strtoupper(trim((string) $nombre)) === self::SECTOR_CUENTAS_A_PAGAR;
    }

    public static function sectorIdPorNombre(string $nombre): int
    {
        return (int) (Sector_Legajocompra::query()
            ->whereRaw('UPPER(TRIM(nombre)) = ?', [strtoupper(trim($nombre))])
            ->value('id') ?? 0);
    }

    /**
     * @return array{
     *     ok: bool,
     *     errores: list<string>,
     *     requiere_pdf: bool,
     *     tiene_factura: bool,
     *     tiene_com: bool,
     *     exige_com: bool,
     *     precarga_id: int|null
     * }
     */
    public static function evaluar(Ordencompra $oc): array
    {
        $factura = self::resolverPrecargaConPdf($oc);
        $exigeCom = self::exigeRecepcionCom($oc);
        $tieneCom = ! $exigeCom || self::tieneComDisponible((int) $oc->id);

        $errores = [];
        if ($factura === null) {
            $errores[] = 'Debe asignar una factura (precarga o PDF escaneado) al legajo antes de enviarlo a Cuentas a pagar.';
        }
        if ($exigeCom && ! $tieneCom) {
            $errores[] = 'Debe existir al menos una recepción COM confirmada con provisión contable (sin facturar) para esta orden de compra.';
        }

        return [
            'ok' => $errores === [],
            'errores' => $errores,
            'requiere_pdf' => $factura === null,
            'tiene_factura' => $factura !== null,
            'tiene_com' => $tieneCom,
            'exige_com' => $exigeCom,
            'precarga_id' => $factura?->id,
        ];
    }

    public static function resolverPrecargaConPdf(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($numero === '' || $empresaId <= 0) {
            return null;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * OC con al menos un renglón de artículo de stock → exige COM.
     * Solo partidas de gasto / sin articulo_id → gasto/servicio (no exige COM).
     */
    public static function exigeRecepcionCom(Ordencompra $oc): bool
    {
        $ocId = (int) $oc->id;
        if ($ocId <= 0) {
            return false;
        }

        return Ordencompra_Articulo::query()
            ->where('ordencompra_id', $ocId)
            ->whereNotNull('articulo_id')
            ->where('articulo_id', '>', 0)
            ->exists();
    }

    public static function tieneComDisponible(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        /** @var ComprobanteProveedorRecepcionesSupport $support */
        $support = app(ComprobanteProveedorRecepcionesSupport::class);

        return $support->listarDisponibles($ordencompraId)->isNotEmpty();
    }

    /**
     * Precargas del legajo (con o sin PDF) para reutilizar al adjuntar archivo.
     */
    public static function precargaDelLegajoSinPdf(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        if ($numero === '' || $empresaId <= 0) {
            return null;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->where(function ($q) {
                $q->whereNull('rutaalmacenamiento')->orWhere('rutaalmacenamiento', '');
            })
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->first();
    }

    public static function tipotransaccionCompraDefaultId(): int
    {
        $id = (int) (DB::table('tipotransaccion_compra')->where('abreviatura', 'FGA')->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('tipotransaccion_compra')->orderBy('id')->value('id') ?? 0);
    }
}
