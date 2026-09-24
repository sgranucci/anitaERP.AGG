<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * PDF FAE Interforming: layout Anita (solo exportación).
 */
final class InterformingFacturaExportacionPdfSupport
{
    public static function corresponde(?Venta $venta): bool
    {
        if (! EntornoEmpresaSupport::esInterforming() || $venta === null) {
            return false;
        }

        $modo = strtoupper(trim((string) ($venta->puntoventas->modofacturacion ?? '')));
        if ($modo === 'E') {
            return true;
        }

        $letra = strtoupper(trim((string) ($venta->tipotransacciones->letra ?? '')));
        if ($letra === 'E') {
            return true;
        }

        return (int) ($venta->codigo_afip ?? 0) === 19;
    }

    /**
     * Descripción de línea en FAE: articulo.detalle (ABM), con fallback a descripcion / snapshot.
     */
    public static function detalleLineaExportacion(?object $articulo, string $detalleEmision = ''): string
    {
        $detalleAbm = trim((string) ($articulo->detalle ?? ''));
        if ($detalleAbm !== '') {
            return $detalleAbm;
        }
        $desc = trim((string) ($articulo->descripcion ?? ''));
        if ($desc !== '') {
            return $desc;
        }

        return trim($detalleEmision);
    }

    /**
     * Nro. Anita: 0004-00000703 (PV + número, sin prefijo FAE E-).
     */
    public static function numeroComprobanteAnita(Venta $venta): string
    {
        $pvRaw = trim((string) ($venta->puntoventas->codigo ?? ''));
        $pvNum = (int) preg_replace('/\D+/', '', $pvRaw);
        $nro = (int) ($venta->numerocomprobante ?? 0);

        return str_pad((string) $pvNum, 4, '0', STR_PAD_LEFT)
            .'-'
            .str_pad((string) $nro, 8, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *   exportacion: ?object,
     *   incoterm_abrev: string,
     *   incoterm_nombre: string,
     *   forma_pago: string,
     *   peso_neto: float,
     *   bultos: float,
     *   destino: string,
     *   lugar_emision: string,
     *   caja_jub: string,
     *   fax: string,
     *   web: string,
     *   leyenda_iva: string,
     *   inicio_act: string,
     *   iibb: string,
     *   domicilio_emisor: string,
     *   telefono_emisor: string
     * }
     */
    public static function contextoVista(Venta $venta): array
    {
        $exp = $venta->venta_exportaciones->first();
        $incoterm = $exp?->incoterms;
        $formapago = $exp?->formapagos;
        $empresa = $venta->puntoventas->empresas ?? null;
        $empresaId = (int) ($empresa->id ?? $venta->puntoventas->empresa_id ?? 0);
        $membrete = FacturaPdfMembreteSupport::paraEmpresa($empresaId > 0 ? $empresaId : null);

        $inicio = $empresa->fechainicioactividad ?? null;
        $inicioFmt = '';
        if ($inicio && (string) $inicio !== '0000-00-00') {
            $inicioFmt = date('d-m-y', strtotime((string) $inicio));
        } elseif (trim((string) ($membrete[FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK] ?? '')) !== '') {
            $inicioFmt = trim((string) $membrete[FacturaPdfMembreteSupport::CLAVE_INICIO_FALLBACK]);
        }

        $pv = $venta->puntoventas;
        $domPv = trim((string) ($pv->domicilio ?? ''));
        if ($domPv === '-' || $domPv === '.') {
            $domPv = trim((string) ($empresa->domicilio ?? ''));
        }
        $loc = trim((string) ($pv->localidades->nombre ?? $empresa->localidad->nombre ?? ''));
        $cp = trim((string) ($pv->codigopostal ?? $empresa->codigopostal ?? ''));
        $prov = trim((string) ($pv->provincias->nombre ?? $empresa->provincia->nombre ?? ''));
        $domicilioLinea = $domPv;
        if ($cp !== '' || $loc !== '') {
            $domicilioLinea .= ' - '.($cp !== '' ? '('.$cp.') ' : '').$loc;
        }
        if ($prov !== '') {
            $domicilioLinea .= ' - Prov '.$prov;
        }

        $destino = trim((string) ($venta->paises->nombre ?? $venta->provincias->nombre ?? ''));

        return [
            'exportacion' => $exp,
            'incoterm_abrev' => trim((string) ($incoterm->abreviatura ?? '')),
            'incoterm_nombre' => trim((string) ($incoterm->nombre ?? '')),
            'forma_pago' => trim((string) ($formapago->nombre ?? $venta->condicionventas->nombre ?? '')),
            'peso_neto' => (float) ($exp->peso_neto ?? 0),
            'bultos' => (float) ($venta->cantidadbulto ?? 0),
            'destino' => $destino,
            'lugar_emision' => trim((string) ($membrete[FacturaPdfMembreteSupport::CLAVE_LUGAR] ?? '')),
            'caja_jub' => trim((string) ($membrete['pdf_caja_jubilacion'] ?? '')),
            'fax' => trim((string) ($membrete['pdf_fax'] ?? '')),
            'web' => trim((string) ($membrete[FacturaPdfMembreteSupport::CLAVE_WEB] ?? '')),
            'leyenda_iva' => trim((string) ($membrete[FacturaPdfMembreteSupport::CLAVE_LEYENDA_IVA] ?? 'IVA Responsable Inscripto')),
            'inicio_act' => $inicioFmt,
            'iibb' => trim((string) ($empresa->numeroiibb ?? '')),
            'domicilio_emisor' => trim($domicilioLinea),
            'telefono_emisor' => trim((string) ($pv->telefono ?? '')),
        ];
    }
}
