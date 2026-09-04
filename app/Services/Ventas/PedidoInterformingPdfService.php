<?php

namespace App\Services\Ventas;

use App\Services\Configuracion\ImpuestoService;
use App\Support\Ventas\PedidoInterformingPdfSupport;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class PedidoInterformingPdfService
{
    public function __construct(
        private PedidoInterformingService $pedidoService,
        private ImpuestoService $impuestoService,
    ) {
    }

    /**
     * @return array{contenido: string, nombre: string}|null
     */
    public function generarBytes(int $pedidoId): ?array
    {
        PedidoInterformingSupport::abortSiNoInterforming();

        $pedido = $this->pedidoService->leePedido($pedidoId);
        if (! $pedido) {
            return null;
        }

        $pdfData = PedidoInterformingPdfSupport::armar($pedido, $this->impuestoService);

        $html = View::make('exports.ventas.pedido_interforming', [
            'pedido' => $pedido,
            'pdf' => $pdfData,
        ])->render();

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        $nombre = 'pedido_'.$pdfData['numeroPedido'].'_'.date('Ymd_His').'.pdf';
        $nombre = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $nombre) ?: ('pedido_'.$pedidoId.'.pdf');

        return [
            'contenido' => $pdf->output(),
            'nombre' => $nombre,
        ];
    }

    public function descargar(int $pedidoId)
    {
        $ret = $this->generarBytes($pedidoId);
        if (! $ret) {
            abort(404, 'Pedido inexistente');
        }

        return response($ret['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$ret['nombre'].'"',
        ]);
    }
}
