<?php

namespace App\Services\Sala;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Sala\RequisicionSalaEstado;
use App\Repositories\Sala\RequisicionSalaArchivoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaArticuloRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Sala\RequisicionSalaEdicionSupport;
use App\Support\Sala\RequisicionSalaTransferenciaLaboratorioDeferred;
use App\Support\Sala\RequisicionSalaTransferenciaAsociadaSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Auth;
use Carbon\Carbon;
use DB;

class RequisicionSalaService
{
    public function __construct(
        private RequisicionSalaRepositoryInterface $requisicionSalaRepository,
        private RequisicionSalaEstadoRepositoryInterface $requisicionSalaEstadoRepository,
        private RequisicionSalaArticuloRepositoryInterface $requisicionSalaArticuloRepository,
        private RequisicionSalaArchivoRepositoryInterface $requisicionSalaArchivoRepository,
        private ArbolaprobacionService $arbolaprobacionService,
        private ModuloAvisoService $moduloAvisoService,
        private TransferenciaMercaderiaService $transferenciaMercaderiaService,
    ) {
    }

    public function guardaRequisicionSala($request): array
    {
        $data = $request->all();
        $pendiente = RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];

        $data['fechas'][] = Carbon::now()->toDateTimeString();
        $data['estados'][] = $pendiente;
        $data['usuario_ids'][] = Auth::user()->id;
        $data['observacionestados'][] = 'Alta de requisición de sala';
        $data['creousuario_id'] = Auth::user()->id;
        $data['usuario_id'] = Auth::user()->id;
        $data['estado'] = $pendiente;

        try {
            $this->arbolaprobacionService->validaRequisicionSalaRequestContraArbol($data);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        DB::beginTransaction();
        try {
            $cabecera = self::armaCabecera($data);
            $requisicion = $this->requisicionSalaRepository->create($cabecera);
            $this->requisicionSalaEstadoRepository->create($data, $requisicion->id);
            $this->requisicionSalaArticuloRepository->syncFromRequest($data, $requisicion->id);
            $this->requisicionSalaArchivoRepository->create($request, $requisicion->id);
            $this->arbolaprobacionService->procesaArbolaprobacion('RS', $requisicion->id, 'insert');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            RequisicionSalaTransferenciaLaboratorioDeferred::descartarPendientes();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        RequisicionSalaTransferenciaLaboratorioDeferred::procesarPendientes();

        $this->moduloAvisoService->enviar('sala', 'requisicion_sala_creacion', (int) $requisicion->id);

        return ['mensaje' => 'ok'];
    }

    public function actualizaRequisicionSala($request, int $id): array
    {
        $existente = $this->requisicionSalaRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Requisición de sala no encontrada.'];
        }
        if (! $this->esEditable($existente->estado)) {
            return ['mensaje' => 'error', 'errores' => RequisicionSalaEdicionSupport::mensajeNoEditable()];
        }

        $data = $request->all();
        $errorTransferencia = RequisicionSalaTransferenciaAsociadaSupport::validarActualizacion($existente, $data);
        if ($errorTransferencia !== null) {
            return ['mensaje' => 'error', 'errores' => $errorTransferencia];
        }

        $pendiente = RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $rechazada = RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $reenviarArbol = false;
        if ($existente->estado === $rechazada) {
            $data['estado'] = $pendiente;
            $reenviarArbol = true;
        }
        if (($data['estado'] ?? '') === $pendiente) {
            $data['requisicion_sala_id'] = $id;
            try {
                $this->arbolaprobacionService->validaRequisicionSalaRequestContraArbol($data);
            } catch (\RuntimeException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        DB::beginTransaction();
        try {
            $cabecera = self::armaCabecera($data);
            $cabecera['estado'] = $data['estado'] ?? $existente->estado;
            unset($cabecera['creousuario_id'], $cabecera['usuario_id']);
            $this->requisicionSalaRepository->update($cabecera, $id);
            $this->requisicionSalaArticuloRepository->syncFromRequest($data, $id);
            $this->requisicionSalaArchivoRepository->update($request, $id);
            if (($data['estado'] ?? '') === $pendiente && ($existente->estado === $pendiente || $reenviarArbol)) {
                if ($reenviarArbol) {
                    $this->requisicionSalaEstadoRepository->creaEstado(
                        $id,
                        Carbon::now()->toDateTimeString(),
                        $pendiente,
                        Auth::user()->id,
                        'Requisición corregida tras rechazo; reenvío al árbol de aprobación'
                    );
                }
                $this->arbolaprobacionService->procesaArbolaprobacion('RS', $id, 'insert');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            RequisicionSalaTransferenciaLaboratorioDeferred::descartarPendientes();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        RequisicionSalaTransferenciaLaboratorioDeferred::procesarPendientes();

        return ['mensaje' => 'ok'];
    }

    /**
     * Corrección de datos no estructurales en APROBADA/PARCIAL (sin reabrir árbol ni tocar TM).
     *
     * @return array{mensaje: string, errores?: string}
     */
    public function actualizaDatosMenores($request, int $id): array
    {
        $existente = $this->requisicionSalaRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Requisición de sala no encontrada.'];
        }
        if (! RequisicionSalaEdicionSupport::permiteEdicionMenor($existente->estado)) {
            return [
                'mensaje' => 'error',
                'errores' => 'La edición menor solo aplica en estado APROBADA o PARCIAL.',
            ];
        }

        $data = $request->all();

        $errorArticulo = RequisicionSalaEdicionSupport::validarCambioArticuloEdicionMenor($existente, $data);
        if ($errorArticulo !== null) {
            return ['mensaje' => 'error', 'errores' => $errorArticulo];
        }

        DB::beginTransaction();
        try {
            $this->requisicionSalaRepository->update([
                'fecha_entrega' => $data['fecha_entrega'] ?? $existente->fecha_entrega,
                'zona_sala_id' => ! empty($data['zona_sala_id']) ? $data['zona_sala_id'] : null,
                'prioridad_sala_id' => ! empty($data['prioridad_sala_id']) ? $data['prioridad_sala_id'] : null,
                'comentario' => $data['comentario'] ?? '',
                'detalle' => $data['detalle'] ?? '',
            ], $id);

            $this->requisicionSalaArticuloRepository->syncDatosMenoresFromRequest($data, $id);

            $this->requisicionSalaEstadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $existente->estado,
                Auth::user()->id,
                'Corrección de datos menores / artículo (sin reabrir aprobación)'
            );

            if (method_exists($this->requisicionSalaArchivoRepository, 'update')) {
                $this->requisicionSalaArchivoRepository->update($request, $id);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * Desaprueba / reabre para cambio de negocio: limpia árbol, revierte TM lab si aplica,
     * vuelve a PENDIENTE (editable). Al guardar luego se reenvía al árbol.
     *
     * @return array{mensaje: string, errores?: string}
     */
    public function reabrirDesaprobar(int $id, string $motivo): array
    {
        $existente = $this->requisicionSalaRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Requisición de sala no encontrada.'];
        }
        if (! RequisicionSalaEdicionSupport::permiteReabrir($existente->estado)) {
            return [
                'mensaje' => 'error',
                'errores' => 'Solo se puede reabrir en estado APROBADA o PARCIAL.',
            ];
        }

        $activos = RequisicionSalaEdicionSupport::cantidadCumplimientosActivos($existente);
        if ($activos > 0) {
            return [
                'mensaje' => 'error',
                'errores' => RequisicionSalaEdicionSupport::mensajeBloqueoReabrirPorCumplimientos(),
            ];
        }

        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['mensaje' => 'error', 'errores' => 'Indicá el motivo de la reapertura.'];
        }

        $pendiente = RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];

        DB::beginTransaction();
        try {
            $tmLab = RequisicionSalaTransferenciaAsociadaSupport::transferenciaLaboratorio($existente);
            if ($tmLab
                && (int) ($tmLab->transferencia_revertido_por_id ?? 0) <= 0
                && $tmLab->estado === TransferenciaMercaderiaEstados::CONFIRMADA
            ) {
                $this->transferenciaMercaderiaService->revertirTransferenciaConfirmada((int) $tmLab->id);
            }

            Arbolaprobacion_Movimiento::query()
                ->where('requisicion_sala_id', $id)
                ->delete();

            $this->requisicionSalaEstadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $pendiente,
                Auth::user()->id,
                'Reapertura / desaprobación: '.$motivo
            );
            $this->requisicionSalaRepository->update(['estado' => $pendiente], $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            RequisicionSalaTransferenciaLaboratorioDeferred::descartarPendientes();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        RequisicionSalaTransferenciaLaboratorioDeferred::procesarPendientes();

        return ['mensaje' => 'ok'];
    }

    public function enviarArbolAprobacionDesdeEnLaboratorio(int $id): array
    {
        $req = $this->requisicionSalaRepository->find($id);
        $nombreEnLaboratorio = RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        if ($req->estado !== $nombreEnLaboratorio) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede enviar al árbol cuando está en EN LABORATORIO.'];
        }

        try {
            $this->arbolaprobacionService->validaRequisicionSalaModeloContraArbol($req);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        DB::beginTransaction();
        try {
            $nombreEnArbol = RequisicionSalaEstado::$enumEstado[array_search('R', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
            $this->requisicionSalaEstadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $nombreEnArbol,
                Auth::user()->id,
                'Enviada al árbol de aprobación (desde EN LABORATORIO)'
            );
            $this->requisicionSalaRepository->update(['estado' => $nombreEnArbol], $id);
            $this->arbolaprobacionService->procesaArbolaprobacion('RS', $id, 'resume');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            RequisicionSalaTransferenciaLaboratorioDeferred::descartarPendientes();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        RequisicionSalaTransferenciaLaboratorioDeferred::procesarPendientes();

        return ['mensaje' => 'ok'];
    }

    /** @deprecated Use enviarArbolAprobacionDesdeEnLaboratorio() */
    public function enviarArbolAprobacionDesdeEnCompras(int $id): array
    {
        return $this->enviarArbolAprobacionDesdeEnLaboratorio($id);
    }

    public function leeHistoria(int $id)
    {
        return $this->requisicionSalaEstadoRepository->leeHistoria($id);
    }

    public function esEditable(?string $estado): bool
    {
        return RequisicionSalaEdicionSupport::permiteEdicionCompleta($estado);
    }

    public function permiteEdicionMenor(?string $estado): bool
    {
        return RequisicionSalaEdicionSupport::permiteEdicionMenor($estado);
    }

    public function permiteReabrir(?string $estado): bool
    {
        return RequisicionSalaEdicionSupport::permiteReabrir($estado);
    }

    private static function armaCabecera(array $data): array
    {
        return [
            'fecha' => $data['fecha'] ?? null,
            'fecha_entrega' => $data['fecha_entrega'] ?? null,
            'empresa_id' => $data['empresa_id'] ?? null,
            'centrocosto_id' => $data['centrocosto_id'] ?? null,
            'deposito_id' => $data['deposito_id'] ?? null,
            'zona_sala_id' => ! empty($data['zona_sala_id']) ? $data['zona_sala_id'] : null,
            'prioridad_sala_id' => ! empty($data['prioridad_sala_id']) ? $data['prioridad_sala_id'] : null,
            'usuario_id' => $data['usuario_id'] ?? Auth::user()->id,
            'comentario' => $data['comentario'] ?? '',
            'detalle' => $data['detalle'] ?? '',
            'estado' => $data['estado'] ?? null,
            'creousuario_id' => $data['creousuario_id'] ?? Auth::user()->id,
        ];
    }
}
