<?php

namespace App\Support\Ventas\CertificadoSanitario;

use Illuminate\Support\Collection;

/**
 * Aplana el preview de pedidos: detalle, subtotal por pedido y total final.
 */
final class CertificadoSanitarioPreviewAplanado
{
    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @return Collection<int, array<string, mixed>>
     */
    public static function aplanar(Collection $lineas): Collection
    {
        $out = collect();
        if ($lineas->isEmpty()) {
            return $out;
        }

        $totalKilos = 0.0;
        $totalCajas = 0.0;

        foreach ($lineas->groupBy(fn (PedidoCertificadoLinea $l) => $l->origen.'|'.$l->codigoPedido) as $grupo) {
            $kilos = 0.0;
            $cajas = 0.0;
            /** @var PedidoCertificadoLinea $primera */
            $primera = $grupo->first();
            foreach ($grupo as $linea) {
                $out->push([
                    'tipo_fila' => 'detalle',
                    'linea' => $linea,
                ]);
                $kilos += $linea->kilos;
                $cajas += $linea->cajas;
            }
            $out->push([
                'tipo_fila' => 'subtotal_pedido',
                'codigoPedido' => $primera->codigoPedido,
                'origen' => $primera->origen,
                'kilos' => $kilos,
                'cajas' => $cajas,
            ]);
            $totalKilos += $kilos;
            $totalCajas += $cajas;
        }

        $out->push([
            'tipo_fila' => 'total_final',
            'kilos' => $totalKilos,
            'cajas' => $totalCajas,
        ]);

        return $out;
    }

    /**
     * @param  Collection<int, PedidoCertificadoLinea>  $lineas
     * @return array{kilos: float, cajas: float, lineas: int, pedidos: int}
     */
    public static function totales(Collection $lineas): array
    {
        $pedidos = $lineas
            ->map(fn (PedidoCertificadoLinea $l) => $l->origen.'|'.$l->codigoPedido)
            ->unique()
            ->count();

        return [
            'kilos' => (float) $lineas->sum('kilos'),
            'cajas' => (float) $lineas->sum('cajas'),
            'lineas' => $lineas->count(),
            'pedidos' => $pedidos,
        ];
    }
}
