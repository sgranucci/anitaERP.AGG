<?php

namespace App\Services\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use App\Models\Compras\CumplimientoRequisicionCompraArticulo;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Repositories\Compras\CumplimientoRequisicionCompraRepositoryInterface;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Auth;
use Carbon\Carbon;
use DB;

class CumplimientoRequisicionCompraRevertirService
{
    public function __construct(
        private CumplimientoRequisicionCompraRepositoryInterface $repository,
        private Requisicion_EstadoRepositoryInterface $requisicionEstadoRepository,
        private TransferenciaMercaderiaService $transferenciaService,
    ) {
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function revertir(int $cumplimientoId, ?string $observacion = null): array
    {
        $cumplimiento = $this->repository->findConDetalle($cumplimientoId);
        if (! $cumplimiento) {
            return ['mensaje' => 'error', 'errores' => 'Cumplimiento no encontrado.'];
        }
        if (! $cumplimiento->estaActivo()) {
            return ['mensaje' => 'error', 'errores' => 'El cumplimiento ya fue revertido.'];
        }

        $usuarioId = (int) Auth::id();
        $obs = trim((string) $observacion);

        DB::beginTransaction();
        try {
            foreach ($cumplimiento->transferencias as $pivot) {
                $tm = $pivot->transferenciaMercaderia;
                if (! $tm) {
                    continue;
                }
                if ((int) ($tm->transferencia_revertido_por_id ?? 0) > 0) {
                    continue;
                }
                if ($tm->estado === TransferenciaMercaderiaEstados::CONFIRMADA) {
                    $this->transferenciaService->revertirTransferenciaConfirmada((int) $tm->id);
                }
            }

            $estadosAntesPorReq = [];
            foreach ($cumplimiento->articulos as $lineaCumple) {
                $this->revertirLineaRequisicion($lineaCumple);
                $reqId = (int) $lineaCumple->requisicion_id;
                if (! isset($estadosAntesPorReq[$reqId])) {
                    $estadosAntesPorReq[$reqId] = (string) ($lineaCumple->estado_requisicion_antes ?? '');
                }
            }

            foreach ($estadosAntesPorReq as $reqId => $estadoAntes) {
                $this->actualizarEstadoCabeceraRequisicion((int) $reqId, $estadoAntes, $usuarioId, $obs);
            }

            $cumplimiento->update([
                'estado' => CumplimientoRequisicionCompra::ESTADO_REVERTIDO,
                'revertido_por_id' => $usuarioId,
                'revertido_en' => Carbon::now(),
                'observacion_reversion' => $obs !== '' ? $obs : 'Reversión de cumplimiento #'.$cumplimiento->numero,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => strip_tags($e->getMessage())];
        }

        return ['mensaje' => 'ok'];
    }

    private function revertirLineaRequisicion(CumplimientoRequisicionCompraArticulo $lineaCumple): void
    {
        $linea = Requisicion_Articulo::query()->find((int) $lineaCumple->requisicion_articulo_id);
        if (! $linea) {
            throw new \RuntimeException('Línea de requisición no encontrada (id '.$lineaCumple->requisicion_articulo_id.').');
        }

        $nuevaEntregada = max(0, (float) ($lineaCumple->cantidadentregada_antes ?? 0));
        $update = ['cantidadentregada' => $nuevaEntregada];

        $articuloOriginal = (int) ($lineaCumple->articulo_id_original ?? 0);
        if ($articuloOriginal > 0 && (int) $linea->articulo_id !== $articuloOriginal) {
            $update['articulo_id'] = $articuloOriginal;
        }

        $linea->update($update);
    }

    private function actualizarEstadoCabeceraRequisicion(int $reqId, string $estadoAntes, int $usuarioId, string $obs): void
    {
        $req = Requisicion::query()->find($reqId);
        if (! $req) {
            return;
        }

        $lineas = Requisicion_Articulo::query()->where('requisicion_id', $reqId)->get();
        $todasCompletas = true;
        foreach ($lineas as $linea) {
            if ((float) ($linea->cantidadentregada ?? 0) < (float) $linea->cantidad) {
                $todasCompletas = false;
            }
        }

        // Solo se cumplen requisiciones APROBADA: mientras quede pendiente vuelve/permanece
        // en el estado previo (APROBADA); si quedara todo entregado, CUMPLIDA.
        $nuevo = $todasCompletas
            ? $this->nombreEstadoCumplida()
            : ($estadoAntes !== '' ? $estadoAntes : (string) $req->estado);

        if ($nuevo === (string) $req->estado) {
            return;
        }

        $this->requisicionEstadoRepository->creaEstado(
            $reqId,
            Carbon::now()->toDateTimeString(),
            $nuevo,
            $usuarioId,
            'Reversión cumplimiento compra'.($obs !== '' ? ': '.$obs : '')
        );
        $req->update(['estado' => $nuevo]);
    }

    private function nombreEstadoCumplida(): string
    {
        foreach (\App\Models\Compras\Requisicion_Estado::$enumEstado as $row) {
            if ($row['valor'] === 'C') {
                return $row['nombre'];
            }
        }

        return 'CUMPLIDA';
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function actualizarLeyenda(int $cumplimientoId, ?string $leyenda): array
    {
        $cumplimiento = CumplimientoRequisicionCompra::query()->find($cumplimientoId);
        if (! $cumplimiento) {
            return ['mensaje' => 'error', 'errores' => 'Cumplimiento no encontrado.'];
        }
        if (! $cumplimiento->estaActivo()) {
            return ['mensaje' => 'error', 'errores' => 'No se puede modificar un cumplimiento revertido.'];
        }

        $leyenda = trim((string) $leyenda);
        $cumplimiento->update(['leyenda' => $leyenda !== '' ? $leyenda : null]);

        return ['mensaje' => 'ok'];
    }
}
