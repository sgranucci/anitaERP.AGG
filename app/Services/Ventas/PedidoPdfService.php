<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

class PedidoPdfService
{
    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
    ) {
    }

    /**
     * @return array{contenido: string, nombre: string}|null
     */
    public function generarBytes(int $pedidoId): ?array
    {
        $filas = $this->pedidoQuery->leePedidoporId($pedidoId);
        $pedido = $filas[0] ?? null;
        if (! $pedido) {
            return null;
        }

        $nombreCliente = preg_replace('/[^\w\-]+/', '_', (string) optional($pedido->clientes)->nombre);
        $nombre = 'pedido-'.$pedidoId.'-'.$nombreCliente.'.pdf';

        $html = View::make('exports.ventas.pedido', compact('pedido'))->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return [
            'contenido' => $pdf->output(),
            'nombre' => $nombre,
        ];
    }
}
