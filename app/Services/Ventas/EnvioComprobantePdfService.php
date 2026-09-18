<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Support\Ventas\EnvioEtiquetaDatosSupport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

/**
 * PDF etiqueta de ENVÍO (pack Facturación). Layout según modelo físico Ferli.
 */
final class EnvioComprobantePdfService
{
    public function generarPdfDesdeVenta(int $ventaId): string
    {
        ini_set('memory_limit', '256M');

        $venta = Venta::query()
            ->with([
                'puntoventas.empresas',
                'puntoventas.localidades',
                'clientes.localidades',
                'clientes.provincias',
                'transportes',
                'remitos.remito_articulos',
                'venta_emisiones',
            ])
            ->find($ventaId);
        if (! $venta) {
            throw new \RuntimeException('Venta inexistente para ENVÍO');
        }

        $etiqueta = EnvioEtiquetaDatosSupport::desdeVenta($venta);
        $cantidadEnvios = EnvioEtiquetaDatosSupport::cantidadEtiquetasDesdeVenta($venta);

        $nombreCliente = preg_replace('/[^\w\-]+/', '_', (string) ($venta->nombre ?? 'cliente')) ?: 'cliente';
        $nombrePdf = 'envio-'.$ventaId.'-'.$nombreCliente;
        $path = storage_path('pdf/ventas');
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException('No se pudo crear el directorio de PDF de envío.');
        }

        $view = View::make('exports.ventas.envio', [
            'venta' => $venta,
            'etiqueta' => $etiqueta,
            'cantidadEnvios' => $cantidadEnvios,
        ])->render();

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return $path.'/'.$nombrePdf.'.pdf';
    }
}
