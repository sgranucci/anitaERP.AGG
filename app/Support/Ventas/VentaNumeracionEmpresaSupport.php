<?php

namespace App\Support\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Numeración de ventas acotada por empresa (PV comparte sucursal Anita entre empresas).
 *
 * La secuencia CAEA/fiscal es por tipo AFIP efectivo (tipotransaccion.codigo + letra + FCE),
 * no por tipotransaccion_id.
 */
final class VentaNumeracionEmpresaSupport
{
    /**
     * @deprecated Use maxNumerocomprobanteErpPorCodigoAfip or maxNumerocomprobanteErpDesdeTipotransaccion.
     */
    public static function maxNumerocomprobanteErp(
        int $puntoventaId,
        int $tipotransaccionId,
        ?int $empresaId = null,
    ): int {
        if ($puntoventaId <= 0 || $tipotransaccionId <= 0) {
            return 0;
        }

        $codigoAlmacenado = (int) (Tipotransaccion::query()->whereKey($tipotransaccionId)->value('codigo') ?? 0);

        return self::maxNumerocomprobanteErpDesdeTipotransaccion(
            $puntoventaId,
            $codigoAlmacenado,
            'B',
            $empresaId,
        );
    }

    public static function maxNumerocomprobanteErpPorCodigoAfip(
        int $puntoventaId,
        int $codigoAfipObjetivo,
        ?int $empresaId = null,
        ?string $letraEmision = null,
    ): int {
        if ($puntoventaId <= 0 || $codigoAfipObjetivo <= 0) {
            return 0;
        }

        $query = Venta::query()
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->where('venta.puntoventa_id', $puntoventaId)
            ->whereNull('venta.deleted_at')
            ->whereNull('tt.deleted_at');

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        if ($letraEmision !== null && $letraEmision !== '') {
            $letra = strtoupper(trim($letraEmision));
            $bases = TipotransaccionCodigoAfipSupport::codigosBaseAlmacenadosPosibles($codigoAfipObjetivo, $letra);
            if ($bases !== []) {
                $query->whereIn(DB::raw(SqlDialectSupport::castEntero('tt.codigo')), $bases);
            }
            $query->where(static function ($q) use ($letra): void {
                $q->where('venta.codigo', 'like', '% '.$letra.'-%')
                    ->orWhere('venta.codigo', 'like', '% '.$letra.' %');
            });
        }

        return (int) ($query->max('venta.numerocomprobante') ?? 0);
    }

    public static function maxNumerocomprobanteErpDesdeTipotransaccion(
        int $puntoventaId,
        int|string $codigoAlmacenadoTipotransaccion,
        string $letra,
        ?int $empresaId = null,
        ?string $modoFacturacionCliente = null,
        ?float $totalComprobante = null,
    ): int {
        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision(
            $codigoAlmacenadoTipotransaccion,
            $letra,
            $modoFacturacionCliente,
            $totalComprobante,
        );

        return self::maxNumerocomprobanteErpPorCodigoAfip(
            $puntoventaId,
            $codigoAfip,
            $empresaId,
            $letra,
        );
    }

    public static function numeroDesdeCodigoVenta(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        $partes = explode('-', $codigo);
        $ultimo = trim((string) end($partes));
        if ($ultimo === '' || ! ctype_digit($ultimo)) {
            return 0;
        }

        return (int) $ultimo;
    }

    public static function formatearCodigoVenta(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        int $numero,
    ): string {
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return trim($tipoAnita).' '.trim($letra).'-'
            .str_pad(trim($sucursal), $digitosSucursal, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numero, $digitosComprobante, '0', STR_PAD_LEFT);
    }

    public static function etiquetaFactura(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        int $numero,
    ): string {
        return trim($tipoAnita).' '.trim($letra).' '.trim($sucursal).'-'.$numero;
    }
}
