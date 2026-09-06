<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorInternoTipos;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\NumeroALetrasEs;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF sintético de comprobantes internos (FIN / CIN).
 *
 * Estos tipos no tienen escaneo: el documento lo emite el ERP. El layout sigue
 * el de la orden de pago (logo, datos fiscales, importes en letras, firmas)
 * para que se lea como un comprobante oficial del sistema.
 */
class ComprobanteProveedorInternoPdfService
{
    public function puedeGenerar(Comprobante_Proveedor $comprobante): bool
    {
        $comprobante->loadMissing('tipotransaccion_compras');

        return ComprobanteProveedorInternoTipos::esInterno(
            $comprobante->tipotransaccion_compras?->abreviatura
        );
    }

    public function generarRespuesta(Comprobante_Proveedor $comprobante, bool $descargar = false): Response
    {
        if (! $this->puedeGenerar($comprobante)) {
            abort(404, 'Este tipo de comprobante no genera PDF interno.');
        }

        $pdf = $this->armarPdf($comprobante);
        $nombre = $this->nombreArchivo($comprobante);
        $disposicion = $descargar ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposicion.'; filename="'.$nombre.'"',
        ]);
    }

    public function armarPdf(Comprobante_Proveedor $comprobante)
    {
        return Pdf::loadView(
            'compras.comprobante_proveedor.interno',
            $this->datosVista($comprobante)
        )->setPaper('a4');
    }

    /**
     * @return array<string, mixed>
     */
    public function datosVista(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'empresas.localidad',
            'empresas.provincia',
            'proveedores.condicionivas',
            'proveedores.localidades',
            'proveedores.provincias',
            'tipotransaccion_compras',
            'monedas',
            'asientos',
            'conceptogastos',
            'condicionpagos',
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_conceptos.cuentacontablesdebe',
            'comprobante_proveedor_cuotas.monedas',
        ]);

        $empresa = $comprobante->empresas;
        $proveedor = $comprobante->proveedores;
        $tipo = $comprobante->tipotransaccion_compras;
        $abrev = (string) ($tipo?->abreviatura ?? 'FIN');

        $numero = sprintf(
            '%s %s %04d-%08d',
            $abrev,
            trim((string) $comprobante->letra),
            (int) $comprobante->sucursal,
            (int) $comprobante->numerocomprobante,
        );

        $total = abs((float) $comprobante->total);
        $moneda = $comprobante->monedas;
        $simbolo = trim((string) ($moneda?->simbolo ?? $moneda?->abreviatura ?? '$'));
        if ($simbolo === '') {
            $simbolo = '$';
        }

        return [
            'comprobante' => $comprobante,
            'empresa' => $empresa,
            'proveedor' => $proveedor,
            'tipo' => $tipo,
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresa?->nombre) ?? [],
            'titulo' => ComprobanteProveedorInternoTipos::tituloDocumento($abrev),
            'numero' => $numero,
            'direccionEmpresa' => $this->direccionEmpresa($empresa),
            'lugarFecha' => $this->lugarFecha($empresa, $comprobante->fechacomprobante),
            'generadoEn' => now()->format('d/m/Y H:i'),
            'usuarioLogin' => (string) (auth()->user()->login ?? auth()->user()->name ?? ''),
            'total' => $total,
            'subtotal' => abs((float) ($comprobante->subtotal ?? $total)),
            'simboloMoneda' => $simbolo,
            'nombreMoneda' => (string) ($moneda?->nombre ?? 'Pesos'),
            'importeLetras' => mb_strtoupper(NumeroALetrasEs::monto($total), 'UTF-8'),
            'conceptos' => $comprobante->comprobante_proveedor_conceptos,
            'cuotas' => $comprobante->comprobante_proveedor_cuotas,
            'leyenda' => trim((string) ($comprobante->leyenda ?? '')),
            'estado' => (string) ($comprobante->estado ?? ''),
            'asientoNumero' => (int) ($comprobante->asientos?->numeroasiento ?? 0),
            'asientoFecha' => $this->fmtFecha($comprobante->asientos?->fecha),
            'fechaComprobante' => $this->fmtFecha($comprobante->fechacomprobante),
            'fechaVencimiento' => $this->fmtFecha($comprobante->fechavencimiento),
            'fechaIva' => $this->fmtFecha($comprobante->fechaiva),
            'conceptoGasto' => (string) ($comprobante->conceptogastos?->nombre ?? ''),
            'condicionPago' => (string) ($comprobante->condicionpagos?->nombre ?? ''),
            'cotizacion' => (float) ($comprobante->cotizacion ?? 1),
        ];
    }

    public function nombreArchivo(Comprobante_Proveedor $comprobante): string
    {
        $comprobante->loadMissing('tipotransaccion_compras');

        return sprintf(
            'interno_%s_%s_%04d-%08d.pdf',
            strtolower((string) ($comprobante->tipotransaccion_compras?->abreviatura ?? 'fin')),
            $comprobante->letra ?? '',
            (int) $comprobante->sucursal,
            (int) $comprobante->numerocomprobante,
        );
    }

    private function direccionEmpresa(?object $empresa): string
    {
        if ($empresa === null) {
            return '';
        }

        $partes = array_filter([
            trim((string) ($empresa->domicilio ?? '')),
            trim((string) ($empresa->localidad?->nombre ?? '')),
            trim((string) ($empresa->provincia?->nombre ?? '')),
        ]);

        return implode(' — ', $partes);
    }

    private function lugarFecha(?object $empresa, mixed $fecha): string
    {
        $lugar = trim((string) ($empresa?->localidad?->nombre ?? ''));
        $fechaFmt = $this->fmtFecha($fecha) ?: now()->format('d/m/Y');

        return $lugar !== '' ? $lugar.', '.$fechaFmt : $fechaFmt;
    }

    private function fmtFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }
}
