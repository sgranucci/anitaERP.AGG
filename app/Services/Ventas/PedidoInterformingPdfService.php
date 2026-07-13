<?php

namespace App\Services\Ventas;

use App\Models\Ventas\PedidoInterforming;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use PDF;

class PedidoInterformingPdfService
{
    public function __construct(
        private PedidoInterformingService $pedidoService
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

        $estadosCabecera = PedidoEstadosInterforming::etiquetasCabecera();
        $estadosItem = PedidoEstadosInterforming::etiquetasItem();

        $html = View::make('exports.ventas.pedido_interforming', compact(
            'pedido',
            'estadosCabecera',
            'estadosItem'
        ))->render();

        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        $nombre = 'pedido_'.$pedido->codigo.'_'.date('Ymd_His').'.pdf';

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
