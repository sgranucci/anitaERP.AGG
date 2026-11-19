<?php

namespace App\Services\Ventas;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Ventas\PedidoInterforming;
use App\Support\Ventas\PedidoEstadosInterforming;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Support\Collection;

/**
 * Reengancha pedidos Interforming al árbol PE cuando quedaron sin pendiente usable.
 * Cubre: sin movimiento, o movimiento ya Aprobado/Rechazado tras reimport Anita a P/C.
 */
class PedidoInterformingArbolBackfillService
{
    /**
     * Pedidos con ítems P/C, no suspendidos/anulados, sin movimiento Pendiente.
     *
     * @return Collection<int, PedidoInterforming>
     */
    public function candidatos(?int $pedidoId = null): Collection
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            return collect();
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $q = PedidoInterforming::query()
            ->with(['clientes:id,codigo,nombre', 'pedido_articulos:id,pedido_id,estado'])
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('pedido_articulo as pa')
                    ->whereColumn('pa.pedido_id', 'pedido.id')
                    ->where(function ($w) {
                        $w->whereNull('pa.estado')
                            ->orWhereIn('pa.estado', [
                                PedidoEstadosInterforming::ITEM_PENDIENTE,
                                PedidoEstadosInterforming::ITEM_CONDICIONAL,
                            ]);
                    });
            })
            ->whereNotExists(function ($sub) use ($nombrePendiente) {
                $sub->selectRaw('1')
                    ->from('arbolaprobacion_movimiento as m')
                    ->whereColumn('m.pedido_id', 'pedido.id')
                    ->where('m.estado', $nombrePendiente);
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

        $integracion = app(PedidoInterformingArbolIntegracionService::class);

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
                $sync = $integracion->sincronizarTrasImport((int) $pedido->id);
                $disparados++;
                $row['resultado'] = $sync;

                $tienePendiente = Arbolaprobacion_Movimiento::query()
                    ->where('pedido_id', $pedido->id)
                    ->where('estado', $nombrePendiente)
                    ->exists();

                if ($tienePendiente) {
                    $conPendiente++;
                    if ($sync === 'disparado') {
                        $row['resultado'] = 'pendiente_creado';
                    }
                } elseif ($sync === 'noop_sin_pc' || $sync === 'sin_arbol') {
                    $sinNivel++;
                } else {
                    $sinNivel++;
                    $row['resultado'] = $sync.'_sin_pendiente';
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
