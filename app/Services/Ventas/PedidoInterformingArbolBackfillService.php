<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Ventas\PedidoInterforming;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Support\Collection;

/**
 * Reengancha pedidos Interforming al árbol PE cuando quedaron sin movimiento.
 */
class PedidoInterformingArbolBackfillService
{
    /**
     * Pedidos con ítems P/C, no suspendidos/anulados, sin filas en arbolaprobacion_movimiento.
     *
     * @return Collection<int, PedidoInterforming>
     */
    public function candidatos(?int $pedidoId = null): Collection
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return collect();
        }

        $q = PedidoInterforming::query()
            ->with(['clientes:id,codigo,nombre', 'pedido_articulos:id,pedido_id,estado'])
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('pedido_articulo as pa')
                    ->whereColumn('pa.pedido_id', 'pedido.id')
                    ->whereIn('pa.estado', [
                        PedidoEstadosInterforming::ITEM_PENDIENTE,
                        PedidoEstadosInterforming::ITEM_CONDICIONAL,
                    ]);
            })
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('arbolaprobacion_movimiento as m')
                    ->whereColumn('m.pedido_id', 'pedido.id');
            })
            ->where(function ($w) {
                $w->whereNull('estadopedido')
                    ->orWhereNotIn('estadopedido', [
                        PedidoEstadosInterforming::CAB_SUSPENDIDO,
                        PedidoEstadosInterforming::CAB_ANULADO,
                    ]);
            })
            ->orderBy('id');

        if ($pedidoId !== null && $pedidoId > 0) {
            $q->whereKey($pedidoId);
        }

        return $q->get();
    }

    /**
     * @return array{
     *   candidatos: int,
     *   disparados: int,
     *   con_pendiente: int,
     *   sin_nivel: int,
     *   errores: list<array{id:int,codigo:string,error:string}>,
     *   detalle: list<array{id:int,codigo:string,resultado:string,nivel?:int}>
     * }
     */
    public function ejecutar(bool $persistir, ?int $pedidoId = null): array
    {
        $candidatos = $this->candidatos($pedidoId);
        $detalle = [];
        $errores = [];
        $disparados = 0;
        $conPendiente = 0;
        $sinNivel = 0;

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        foreach ($candidatos as $pedido) {
            $row = [
                'id' => (int) $pedido->id,
                'codigo' => (string) $pedido->codigo,
                'resultado' => 'pendiente_dry_run',
            ];

            if (! $persistir) {
                $detalle[] = $row;
                continue;
            }

            try {
                $nivel = app(PedidoInterformingArbolIntegracionService::class)
                    ->dispararAlGuardar((int) $pedido->id);
                $disparados++;
                $row['nivel'] = (int) $nivel;

                $tienePendiente = Arbolaprobacion_Movimiento::query()
                    ->where('pedido_id', $pedido->id)
                    ->where('estado', $nombrePendiente)
                    ->exists();

                if ($tienePendiente) {
                    $conPendiente++;
                    $row['resultado'] = 'pendiente_creado';
                } elseif ($nivel === -1) {
                    $row['resultado'] = 'arbol_completo_auto';
                } else {
                    $sinNivel++;
                    $row['resultado'] = 'sin_pendiente_ni_nivel';
                }
            } catch (\Throwable $e) {
                report($e);
                $errores[] = [
                    'id' => (int) $pedido->id,
                    'codigo' => (string) $pedido->codigo,
                    'error' => $e->getMessage(),
                ];
                $row['resultado'] = 'error';
            }

            $detalle[] = $row;
        }

        return [
            'candidatos' => $candidatos->count(),
            'disparados' => $disparados,
            'con_pendiente' => $conPendiente,
            'sin_nivel' => $sinNivel,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }
}
