<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSalaEstado;
use App\Repositories\Sala\RequisicionSalaArchivoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaArticuloRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Configuracion\ModuloAvisoService;
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

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

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
            return ['mensaje' => 'error', 'errores' => 'Solo se puede editar en estado PENDIENTE, A COMPRAS o RECHAZADA.'];
        }

        $data = $request->all();
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

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function enviarArbolAprobacionDesdeEnCompras(int $id): array
    {
        $req = $this->requisicionSalaRepository->find($id);
        $nombreEnCompras = RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        if ($req->estado !== $nombreEnCompras) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede enviar al árbol cuando está en A COMPRAS.'];
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
                'Enviada al árbol de aprobación (desde A COMPRAS)'
            );
            $this->requisicionSalaRepository->update(['estado' => $nombreEnArbol], $id);
            $this->arbolaprobacionService->procesaArbolaprobacion('RS', $id, 'resume');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function leeHistoria(int $id)
    {
        return $this->requisicionSalaEstadoRepository->leeHistoria($id);
    }

    public function esEditable(?string $estado): bool
    {
        $pendiente = RequisicionSalaEstado::$enumEstado[array_search('0', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $enCompras = RequisicionSalaEstado::$enumEstado[array_search('5', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
        $rechazada = RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];

        return in_array($estado, [$pendiente, $enCompras, $rechazada], true);
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
