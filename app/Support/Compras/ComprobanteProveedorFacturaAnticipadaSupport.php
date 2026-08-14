<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;

/**
 * Factura sobre OC anticipada sin recepción COM: el neto va a cuenta de anticipo
 * (Capex → bienes de uso; sin Capex → anticipo proveedores). Intangible no aplica aún.
 */
final class ComprobanteProveedorFacturaAnticipadaSupport
{
    /**
     * OC anticipada y modo distinto de factura contra COM (aún no hay recepción a revertir).
     */
    public static function aplica(Comprobante_Proveedor $comprobante): bool
    {
        if ($comprobante->modo_carga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            return false;
        }

        $oc = $comprobante->ordencompras;
        if (! $oc) {
            return false;
        }

        $fecha = null;
        if ($comprobante->fechacomprobante instanceof \DateTimeInterface) {
            $fecha = $comprobante->fechacomprobante->format('Y-m-d');
        } elseif (filled($comprobante->fechacomprobante ?? null)) {
            $fecha = substr((string) $comprobante->fechacomprobante, 0, 10);
        }
        if (OrdencompraContratoRutaFacturaSupport::aplicaSinRecepcion($oc, $fecha)) {
            return false;
        }

        return ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc);
    }

    public static function ocTieneCapex(?Ordencompra $oc): bool
    {
        if (! $oc) {
            return false;
        }

        $oc->loadMissing('ordencompra_articulos');

        foreach ($oc->ordencompra_articulos as $linea) {
            if ((int) ($linea->capex_id ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function claveCuentaAnticipo(bool $tieneCapex): string
    {
        return $tieneCapex
            ? CuentaAutomaticaClaves::RECEPCION_ANTICIPO_BIENES_USO
            : CuentaAutomaticaClaves::RECEPCION_FACTURA_ANTICIPADA;
    }

    public static function resolverCuentaAnticipoId(int $empresaId, bool $tieneCapex): int
    {
        $clave = self::claveCuentaAnticipo($tieneCapex);
        $mensaje = $tieneCapex
            ? 'OC anticipada con Capex: falta configurar la cuenta de anticipo a proveedores bienes de uso para la empresa.'
            : 'OC anticipada sin Capex: falta configurar la cuenta de anticipo a proveedores (factura anticipada) para la empresa.';

        return CuentaAutomaticaResolver::resolverIdObligatorio($empresaId, $clave, $mensaje);
    }

    public static function observacionDebe(bool $tieneCapex): string
    {
        return $tieneCapex
            ? 'Anticipo a proveedores BS uso (factura anticipada Capex)'
            : 'Anticipo a proveedores (factura anticipada)';
    }
}
