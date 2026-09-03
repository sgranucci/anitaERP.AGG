<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Sector_Legajocompra;
use App\Services\Compras\ComprobanteProveedorRecepcionesSupport;
use Illuminate\Support\Facades\DB;

/**
 * Validaciones obligatorias al enviar un legajo (OC) a CUENTAS A PAGAR.
 */
final class OrdencompraEnvioCuentasAPagarGateSupport
{
    public const SECTOR_CUENTAS_A_PAGAR = 'CUENTAS A PAGAR';

    public const SECTOR_COMPRAS = 'COMPRAS';

    public const SECTOR_PAGOS = 'PAGOS';

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
     *     exige_flujo_empresa: bool,
     *     precarga_id: int|null
     * }
     */
    public static function evaluar(Ordencompra $oc): array
    {
        $oc->loadMissing('empresas:id,codigo,nombre');
        $facturaPdf = self::resolverPrecargaConPdf($oc);
        $precarga = $facturaPdf ?? self::queryPrecargaDelLegajo($oc)->first();
        $tieneScanAnita = OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) !== [];
        $tieneFactura = $facturaPdf !== null || $precarga !== null || $tieneScanAnita;
        $tieneCom = self::tieneComDisponible((int) $oc->id);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tieneCom);
        $exigeCom = self::exigeRecepcionSegunPolitica($politica);

        $errores = [];
        if (! $tieneFactura) {
            $errores[] = 'Debe asignar una factura (precarga o PDF escaneado) al legajo antes de enviarlo a Cuentas a pagar.';
        }
        if ($exigeCom && ! $tieneCom) {
            $errores[] = self::mensajeFaltaCom($politica);
        }

        $erroresContrato = app(\App\Services\Compras\ContratoValidacionAbonoService::class)
            ->erroresEnvioCuentasAPagar($oc);
        foreach ($erroresContrato as $errorContrato) {
            $errores[] = $errorContrato;
        }

        return [
            'ok' => $errores === [],
            'errores' => $errores,
            'requiere_pdf' => ! $tieneFactura,
            'tiene_factura' => $tieneFactura,
            'tiene_com' => $tieneCom,
            'exige_com' => $exigeCom,
            'exige_flujo_empresa' => (bool) ($politica['exige_flujo'] ?? false),
            'precarga_id' => $facturaPdf?->id ?? $precarga?->id,
        ];
    }

    /**
     * Gate al mandar el legajo a Cuentas a pagar (paquete + autorización Gastronomía si aplica).
     *
     * @return array{
     *     ok: bool,
     *     errores: list<string>,
     *     requiere_pdf: bool,
     *     tiene_factura: bool,
     *     tiene_com: bool,
     *     exige_com: bool,
     *     exige_flujo_empresa: bool,
     *     precarga_id: int|null,
     *     requiere_gastronomia: bool
     * }
     */
    public static function evaluarCuentasAPagar(Ordencompra $oc): array
    {
        $gate = self::evaluar($oc);
        $erroresGastro = OrdencompraLegajoGastronomiaSupport::erroresEnvioCuentasAPagar($oc);
        foreach ($erroresGastro as $errorGastro) {
            $gate['errores'][] = $errorGastro;
        }
        $gate['ok'] = $gate['errores'] === [];
        $gate['requiere_gastronomia'] = OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc);

        return $gate;
    }

    public static function resolverPrecargaConPdf(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        return self::queryPrecargaDelLegajo($oc)
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->first();
    }

    public static function precargaDelLegajo(Ordencompra $oc): ?Precarga_Comprobante_Proveedor
    {
        return self::resolverPrecargaConPdf($oc)
            ?? self::queryPrecargaDelLegajo($oc)->first();
    }

    /**
     * ¿El envío del legajo exige COM asociada a la factura?
     *
     * 1) Contrato vigente: manda su circuito (con o sin recepción).
     * 2) Si no: configuración de Cuentas a pagar de la empresa
     *    (exige_flujo_oc_com_fac: OC→COM→FAC vs COM optativa).
     * 3) En flujo flexible, si igual hay COM disponible, hay que asociarla.
     *
     * @param  bool|null  $tieneComDisponibles  Si viene informado, no consulta recepciones.
     */
    public static function exigeRecepcionCom(Ordencompra $oc, ?bool $tieneComDisponibles = null): bool
    {
        if ((int) ($oc->id ?? 0) <= 0) {
            return false;
        }
        $tiene = $tieneComDisponibles ?? self::tieneComDisponible((int) $oc->id);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($oc, $tiene);

        return self::exigeRecepcionSegunPolitica($politica);
    }

    /** @param  array<string, mixed>  $politica */
    public static function exigeRecepcionSegunPolitica(array $politica): bool
    {
        return (bool) ($politica['bloquea_sin_com'] ?? false)
            || (bool) ($politica['debe_asignar_com'] ?? false);
    }

    /** @param  array<string, mixed>  $politica */
    private static function mensajeFaltaCom(array $politica): string
    {
        if (! empty($politica['contrato_vigente']) && ($politica['contrato_requiere_recepcion'] ?? false)) {
            return 'El contrato vigente exige recepción COM asociada a la factura.';
        }

        return 'La factura debe tener una recepción COM confirmada asociada (con provisión contable). '
            .'La empresa tiene configurado el flujo OC → COM → factura.';
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
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>
     */
    private static function queryPrecargaDelLegajo(Ordencompra $oc)
    {
        $numero = trim((string) ($oc->numeroordencompra ?? ''));
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $query = Precarga_Comprobante_Proveedor::query()->whereRaw('1 = 0');
        if ($numero === '' || $empresaId <= 0) {
            return $query;
        }

        return Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id');
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

    public static function tipotransaccionCompraIdPorCodigoAfip(string $codigoAfip): int
    {
        $norm = ComprobanteProveedorUnicidadSupport::normalizarCodigoAfip($codigoAfip);
        $ids = ComprobanteProveedorUnicidadSupport::tipotransaccionIdsPorCodigoAfip($norm);
        if ($ids === []) {
            return 0;
        }

        $prefer = (int) (DB::table('tipotransaccion_compra')
            ->whereIn('id', $ids)
            ->whereIn('abreviatura', ['FGA', 'FGB', 'FGC', 'NDA', 'NDB', 'NDC', 'NCA', 'NCB', 'NCC'])
            ->orderBy('id')
            ->value('id') ?? 0);

        return $prefer > 0 ? $prefer : (int) $ids[0];
    }
}
