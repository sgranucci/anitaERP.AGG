<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;

/**
 * Valores comunes para sync Anita (compra, concmov, promov).
 */
final class ComprobanteProveedorAnitaContext
{
    public function __construct(
        public readonly Comprobante_Proveedor $comprobante,
        public readonly int $nroInterno,
    ) {}

    public function proveedorCodigo(): string
    {
        $codigo = (string) ($this->comprobante->proveedores?->codigo ?? '0');

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    public function empresaCodigo(): int
    {
        return (int) ($this->comprobante->empresas?->codigo ?? $this->comprobante->empresa_id ?? 1);
    }

    public function tipoComprobante(): string
    {
        return substr((string) ($this->comprobante->tipotransaccion_compras?->abreviatura ?? ''), 0, 3);
    }

    public function letra(): string
    {
        return strtoupper(substr((string) $this->comprobante->letra, 0, 1));
    }

    public function sucursal(): int
    {
        return (int) $this->comprobante->sucursal;
    }

    public function numero(): int
    {
        return (int) $this->comprobante->numerocomprobante;
    }

    /** Fecha de contabilización (asiento, promov, ctamov). */
    public function fechaYmd(): string
    {
        return str_replace('-', '', ComprobanteProveedorFechaContableSupport::fechaYmd($this->comprobante));
    }

    /** Fecha impresa del comprobante: informativa para el libro IVA compras (com_fecha). */
    public function fechaComprobanteYmd(): string
    {
        $fecha = ComprobanteProveedorFechaContableSupport::formatear($this->comprobante->fechacomprobante ?? null);

        return str_replace('-', '', $fecha ?? ComprobanteProveedorFechaContableSupport::fechaYmd($this->comprobante));
    }

    /** Período IVA / contabilización (com_fecha_iva). */
    public function fechaIvaYmd(): string
    {
        return $this->fechaYmd();
    }

    public function monedaCodigoAnita(): string
    {
        $moneda = $this->comprobante->monedas;
        if ($moneda && filled($moneda->codigo)) {
            return (string) $moneda->codigo;
        }

        return (string) ($this->comprobante->moneda_id ?? 1);
    }

    /**
     * Cotización de la factura. En moneda extranjera nunca vale 1: si el comprobante no la
     * trae se resuelve la vigente, para no grabar dólares con coeficiente de peso en Anita.
     */
    public function cotizacion(): string
    {
        $cotizacion = ComprobanteProveedorMonedaMotor::cotizacionValida(
            (int) ($this->comprobante->moneda_id ?: 1),
            $this->comprobante->cotizacion,
            $this->comprobante->fechacomprobante?->format('Y-m-d'),
            'la factura del proveedor',
        );

        return number_format($cotizacion, 4, '.', '');
    }

    public function decimal(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
    }

    public function escape(string $valor, int $maxLen = 0): string
    {
        $texto = str_replace("'", '', $valor);
        if ($maxLen > 0) {
            $texto = mb_substr($texto, 0, $maxLen);
        }

        return $texto;
    }

    public function numeroOrdenCompra(): string
    {
        $oc = $this->comprobante->ordencompras;

        return $oc ? (string) ($oc->numeroordencompra ?? '') : '';
    }

    public function modoCargaAnita(): string
    {
        return substr((string) ($this->comprobante->modo_carga ?? ''), 0, 20);
    }

    public function claveWherePromov(): string
    {
        return " WHERE prov_proveedor = '".$this->proveedorCodigo()."'
            AND prov_tipo = '".$this->tipoComprobante()."'
            AND prov_letra = '".$this->letra()."'
            AND prov_sucursal = '".$this->sucursal()."'
            AND prov_nro = '".$this->numero()."'
            AND prov_nro_interno = '".$this->nroInterno."' ";
    }

    public function claveWhereCompra(): string
    {
        return " WHERE com_proveedor = '".$this->proveedorCodigo()."'
            AND com_tipo = '".$this->tipoComprobante()."'
            AND com_letra = '".$this->letra()."'
            AND com_sucursal = '".$this->sucursal()."'
            AND com_nro = '".$this->numero()."'
            AND com_nro_interno = '".$this->nroInterno."' ";
    }
}
