<?php

namespace App\Services\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Models\Sala\CumplimientoRequisicionSalaArticulo;
use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaArticulo;
use App\Models\Sala\RequisicionSalaEstado;
use App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Auth;
use Carbon\Carbon;
use DB;

class CumplimientoRequisicionSalaRevertirService
{
    public function __construct(
        private CumplimientoRequisicionSalaRepositoryInterface $repository,
        private RequisicionSalaRepositoryInterface $requisicionSalaRepository,
        private RequisicionSalaEstadoRepositoryInterface $requisicionSalaEstadoRepository,
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

            $reqIds = [];
            foreach ($cumplimiento->articulos as $lineaCumple) {
                $this->revertirLineaRequisicion($lineaCumple);
                $reqIds[(int) $lineaCumple->requisicion_sala_id] = true;
            }

            foreach (array_keys($reqIds) as $reqId) {
                $this->actualizarEstadoCabeceraRequisicion((int) $reqId, $usuarioId, $obs);
            }

            $cumplimiento->update([
                'estado' => CumplimientoRequisicionSala::ESTADO_REVERTIDO,
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

    private function revertirLineaRequisicion(CumplimientoRequisicionSalaArticulo $lineaCumple): void
    {
        $linea = RequisicionSalaArticulo::query()->find((int) $lineaCumple->requisicion_sala_articulo_id);
        if (! $linea) {
            throw new \RuntimeException('Línea de requisición no encontrada (id '.$lineaCumple->requisicion_sala_articulo_id.').');
        }

        $nuevaEntregada = max(0, (float) ($lineaCumple->cantidadentregada_antes ?? 0));
        $estadoRestaurar = $lineaCumple->estado_linea_antes;
        if ($estadoRestaurar === null || $estadoRestaurar === '') {
            $estadoRestaurar = $nuevaEntregada > 0 ? ' ' : ($linea->estado ?? ' ');
        }

        $linea->update([
            'cantidadentregada' => $nuevaEntregada,
            'estado' => $estadoRestaurar,
            'estadoparcial' => $lineaCumple->estadoparcial_antes,
            'fecha_entrega' => $lineaCumple->fecha_entrega_antes,
            'numeroremito' => $lineaCumple->numeroremito_antes,
            'nombreresponsable' => $lineaCumple->nombreresponsable_antes,
            'tecnico_laboratorio_id' => $lineaCumple->tecnico_laboratorio_id_antes,
            'deposito_origen_id' => $lineaCumple->deposito_origen_id_antes,
            'numeroparte' => $lineaCumple->numeroparte_antes,
        ]);
    }

    private function actualizarEstadoCabeceraRequisicion(int $reqId, int $usuarioId, string $obs): void
    {
        $req = RequisicionSala::query()->find($reqId);
        if (! $req) {
            return;
        }

        $trait = RequisicionSalaEstado::class;
        $aprobada = $trait::$enumEstado[array_search('A', array_column($trait::$enumEstado, 'valor'))]['nombre'];
        $parcial = $trait::$enumEstado[array_search('2', array_column($trait::$enumEstado, 'valor'))]['nombre'];
        $cumplido = $trait::$enumEstado[array_search('3', array_column($trait::$enumEstado, 'valor'))]['nombre'];

        $lineas = RequisicionSalaArticulo::query()->where('requisicion_sala_id', $reqId)->get();
        $algunaEntrega = false;
        $todasCompletas = true;
        foreach ($lineas as $linea) {
            if ((float) ($linea->cantidadentregada ?? 0) > 0) {
                $algunaEntrega = true;
            }
            if ((float) ($linea->cantidadentregada ?? 0) < (float) $linea->cantidad) {
                $todasCompletas = false;
            }
        }

        $nuevo = $algunaEntrega ? ($todasCompletas ? $cumplido : $parcial) : $aprobada;
        if ($nuevo === $req->estado) {
            return;
        }

        $this->requisicionSalaEstadoRepository->creaEstado(
            $reqId,
            Carbon::now()->toDateTimeString(),
            $nuevo,
            $usuarioId,
            'Reversión cumplimiento sala'.($obs !== '' ? ': '.$obs : '')
        );
        $this->requisicionSalaRepository->update(['estado' => $nuevo], $reqId);
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function actualizarLeyenda(int $cumplimientoId, ?string $leyenda): array
    {
        $cumplimiento = CumplimientoRequisicionSala::query()->find($cumplimientoId);
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
