<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numeración de ventas acotada por empresa (PV comparte sucursal Anita entre empresas).
 *
 * La secuencia CAEA/fiscal es por venta.codigo_afip + PV (misma clave que el unique El Bierzo).
 * 201 FCE A y 1 FAC A son series distintas; no se numera por tipotransaccion_id ni por letra.
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

    /**
     * Serie fiscal El Bierzo: unique y max()+1 usan (codigo_afip, puntoventa_id).
     * 201 ya es FCE A; 206 es FCE B. No filtrar por letra ni por tipotransaccion.codigo
     * (una FCE por umbral MiPyME sigue siendo FAC/001 en el ABM).
     *
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    public static function aplicarFiltroSerieCodigoAfip(Builder $query, int $codigoAfipObjetivo): bool
    {
        if ($codigoAfipObjetivo <= 0 || ! Schema::hasColumn('venta', 'codigo_afip')) {
            return false;
        }

        $query->where('venta.codigo_afip', $codigoAfipObjetivo);

        return true;
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

        $query = Venta::query()->where('venta.puntoventa_id', $puntoventaId);

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        if (self::aplicarFiltroSerieCodigoAfip($query, $codigoAfipObjetivo)) {
            return (int) ($query->max('venta.numerocomprobante') ?? 0);
        }

        $query->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->whereNull('tt.deleted_at');

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

    /**
     * AGG unique (puntoventa_id, numerocomprobante): todos los tipos del PV.
     * FBI y FSL (y cualquier otro) comparten la misma sucursal y no pueden repetir número.
     */
    public static function maxNumerocomprobanteErpPorPuntoventa(
        int $puntoventaId,
        ?int $empresaId = null,
    ): int {
        if ($puntoventaId <= 0) {
            return 0;
        }

        $query = Venta::query()->where('venta.puntoventa_id', $puntoventaId);

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        return (int) ($query->max('venta.numerocomprobante') ?? 0);
    }

    /**
     * Próximo número que respeta el unique vigente.
     * AGG: max del PV (todos los tipos) + 1.
     * El Bierzo: max de la serie codigo_afip + PV + 1.
     *
     * $mayorQue fuerza a saltar un número que acabó de chocar (INSERT fallido no actualiza el max).
     */
    public static function siguienteNumerocomprobanteParaUnique(
        int $puntoventaId,
        int $tipotransaccionId,
        string $letra = 'B',
        ?int $empresaId = null,
        int $mayorQue = 0,
    ): int {
        if ($puntoventaId <= 0) {
            return max(1, $mayorQue + 1);
        }

        if (EntornoEmpresaSupport::esElBierzo()) {
            $codigoAlmacenado = (int) (Tipotransaccion::query()
                ->whereKey($tipotransaccionId)
                ->value('codigo') ?? 0);
            $ultimo = self::maxNumerocomprobanteErpDesdeTipotransaccion(
                $puntoventaId,
                $codigoAlmacenado,
                $letra,
                $empresaId,
            );
        } else {
            $ultimo = self::maxNumerocomprobanteErpPorPuntoventa($puntoventaId, $empresaId);
        }

        return max($ultimo, $mayorQue) + 1;
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

    public static function formatearPuntoVentaNumero(?string $codigoPuntoventa, int $numero): string
    {
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);
        $sucursal = preg_replace('/\D+/', '', (string) $codigoPuntoventa);
        $nro = str_pad((string) max(0, $numero), $digitosComprobante, '0', STR_PAD_LEFT);
        if ($sucursal === '') {
            return $numero > 0 ? $nro : '';
        }

        return str_pad($sucursal, $digitosSucursal, '0', STR_PAD_LEFT).'-'.$nro;
    }
}
