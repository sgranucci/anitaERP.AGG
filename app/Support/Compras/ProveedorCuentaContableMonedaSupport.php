<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cuenta contable de proveedores MN/ME según moneda de la OC (preferente).
 *
 * Misma regla que Anita (`filtra_moneda_oc` / `penmp_cod_mon` → `prom_cta_contable` vs `_me`):
 * - Con OC: moneda del primer ítem de la OC (puede diferir de la moneda de la factura).
 * - Sin OC: moneda del comprobante.
 * - En un mismo pago no se mezclan comprobantes cuyas OC tengan distinta moneda.
 *
 * Regla de cuenta: moneda nacional (id 1) → cuentacontable_id (MN).
 *                  moneda extranjera (id >= 2) → cuentacontableme_id (ME).
 */
class ProveedorCuentaContableMonedaSupport
{
    public const ORIGEN_OC = 'oc';

    public const ORIGEN_COMPROBANTE = 'comprobante';

    public static function esMonedaExtranjera(int $monedaId): bool
    {
        $default = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        return $monedaId > $default;
    }

    /**
     * Moneda de cabecera de la OC (primer ítem), misma fuente que Anita `penmp_cod_mon`.
     */
    public static function monedaIdDesdeOrdencompra(?Ordencompra $oc): int
    {
        if ($oc === null) {
            return 0;
        }

        $oc->loadMissing('ordencompra_articulos');
        $linea = $oc->ordencompra_articulos->sortBy('id')->first();

        return max(0, (int) ($linea->moneda_id ?? 0));
    }

    /**
     * Moneda que decide MN/ME: OC si hay; si no, moneda del comprobante.
     *
     * @return array{moneda_id: int, origen: self::ORIGEN_*}
     */
    public static function resolverMonedaParaCuentaProveedor(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing('ordencompras.ordencompra_articulos');

        $desdeOc = self::monedaIdDesdeOrdencompra($comprobante->ordencompras);
        if ($desdeOc > 0) {
            return [
                'moneda_id' => $desdeOc,
                'origen' => self::ORIGEN_OC,
            ];
        }

        return [
            'moneda_id' => max(1, (int) ($comprobante->moneda_id ?: 1)),
            'origen' => self::ORIGEN_COMPROBANTE,
        ];
    }

    /**
     * Moneda que decide MN/ME para un comprobante (OC o fallback factura).
     */
    public static function monedaIdParaCuentaProveedor(Comprobante_Proveedor $comprobante): int
    {
        return self::resolverMonedaParaCuentaProveedor($comprobante)['moneda_id'];
    }

    /**
     * Id de cuenta contable a usar en el Haber del asiento / aplicación a proveedores.
     */
    public static function cuentaProveedorId(?Proveedor $proveedor, int $monedaId): int
    {
        if ($proveedor === null || $monedaId <= 0) {
            return 0;
        }

        if (self::esMonedaExtranjera($monedaId)) {
            $me = (int) ($proveedor->cuentacontableme_id ?? 0);
            if ($me > 0) {
                return $me;
            }

            // Fallback operativo si falta ME (datos legacy); el preview avisa.
            return (int) ($proveedor->cuentacontable_id
                ?: $proveedor->cuentacontablecompra_id
                ?: 0);
        }

        return (int) ($proveedor->cuentacontable_id
            ?: $proveedor->cuentacontablecompra_id
            ?: 0);
    }

    public static function cuentaProveedorDesdeComprobante(
        Comprobante_Proveedor $comprobante,
        ?Proveedor $proveedor = null,
    ): int {
        return self::cuentaProveedorId(
            $proveedor ?? $comprobante->proveedores,
            self::monedaIdParaCuentaProveedor($comprobante),
        );
    }

    public static function etiquetaCuentaEsperada(int $monedaId): string
    {
        return self::esMonedaExtranjera($monedaId)
            ? 'proveedores moneda extranjera (cuenta contable m/e)'
            : 'proveedores moneda nacional (cuenta contable)';
    }

    public static function etiquetaOrigenMoneda(string $origen): string
    {
        return $origen === self::ORIGEN_OC
            ? 'moneda de la OC'
            : 'moneda del comprobante (sin OC)';
    }

    /**
     * Anita: "No puede aplicar distintas monedas de oc".
     * Solo compara moneda de OC cuando el comprobante tiene OC; sin OC no aporta al set.
     *
     * @param  iterable<int, Comprobante_Proveedor>  $comprobantes
     *
     * @throws RuntimeException
     */
    public static function assertMismaMonedaOcEnPago(iterable $comprobantes): int
    {
        $monedaOcRef = 0;
        $tieneOc = false;

        foreach ($comprobantes as $comp) {
            if (! $comp instanceof Comprobante_Proveedor) {
                throw new InvalidArgumentException('Se esperaba Comprobante_Proveedor.');
            }

            $comp->loadMissing('ordencompras.ordencompra_articulos');
            $monedaOc = self::monedaIdDesdeOrdencompra($comp->ordencompras);
            if ($monedaOc <= 0) {
                continue;
            }

            $tieneOc = true;
            if ($monedaOcRef === 0) {
                $monedaOcRef = $monedaOc;
                continue;
            }

            if ($monedaOc !== $monedaOcRef) {
                throw new RuntimeException(
                    'No puede aplicar comprobantes de órdenes de compra con distinta moneda en el mismo pago.'
                );
            }
        }

        return $tieneOc ? $monedaOcRef : 0;
    }
}
