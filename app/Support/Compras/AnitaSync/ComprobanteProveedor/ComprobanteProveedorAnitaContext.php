<?php

namespace App\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Models\Compras\Comprobante_Proveedor;
use Carbon\Carbon;

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

    public function fechaYmd(): string
    {
        $fecha = $this->comprobante->fechacomprobante
            ? Carbon::parse($this->comprobante->fechacomprobante)
            : now();

        return $fecha->format('Ymd');
    }

    public function fechaIvaYmd(): string
    {
        $fecha = $this->comprobante->fechaiva
            ? Carbon::parse($this->comprobante->fechaiva)
            : ($this->comprobante->fechacomprobante ? Carbon::parse($this->comprobante->fechacomprobante) : now());

        return $fecha->format('Ymd');
    }

    public function monedaCodigoAnita(): string
    {
        $moneda = $this->comprobante->monedas;
        if ($moneda && filled($moneda->codigo)) {
            return (string) $moneda->codigo;
        }

        return (string) ($this->comprobante->moneda_id ?? 1);
    }

    public function cotizacion(): string
    {
        return number_format((float) ($this->comprobante->cotizacion ?? 1), 4, '.', '');
    }

    public function decimal(mixed $valor): string
    {
        return number_format((float) $valor, 4, '.', '');
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
