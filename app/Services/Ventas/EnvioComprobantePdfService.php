<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

/**
 * Scaffold PDF ENVÍO (pack Facturación). Formato definitivo: pendiente (lunes).
 */
final class EnvioComprobantePdfService
{
    public function generarPdfDesdeVenta(int $ventaId): string
    {
        ini_set('memory_limit', '256M');

        $venta = Venta::query()
            ->with(['puntoventas.empresas', 'clientes', 'transportes'])
            ->find($ventaId);
        if (! $venta) {
            throw new \RuntimeException('Venta inexistente para ENVÍO');
        }

        $nombreCliente = preg_replace('/[^\w\-]+/', '_', (string) ($venta->nombre ?? 'cliente')) ?: 'cliente';
        $nombrePdf = 'envio-'.$ventaId.'-'.$nombreCliente;
        // Mismo directorio que facturas (writable por www-data); prefijo envio- distingue el scaffold.
        $path = storage_path('pdf/ventas');
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new \RuntimeException('No se pudo crear el directorio de PDF de envío.');
        }

        $view = View::make('exports.ventas.envio_scaffold', [
            'venta' => $venta,
            'scaffoldPendiente' => true,
        ])->render();

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return $path.'/'.$nombrePdf.'.pdf';
    }
}
