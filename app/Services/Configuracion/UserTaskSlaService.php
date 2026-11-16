<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\UserTask;
use App\Support\Configuracion\UserTaskConfigSupport;
use App\Support\Configuracion\UsuarioAvisoPreferenciaSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SLA / escalamiento / reasignación sobre user_task.
 */
class UserTaskSlaService
{
    public function __construct(
        private AnitaNotificacionService $notificacionService,
    ) {}

    public function horasDefault(): int
    {
        return UserTaskConfigSupport::slaHoras();
    }

    /**
     * Aplica vence_en al sincronizar si aún no tiene.
     */
    public function aplicarSlaAlAbrir(UserTask $task, ?int $slaHoras = null): void
    {
        if ($task->vence_en) {
            return;
        }
        $horas = $slaHoras && $slaHoras > 0
            ? $slaHoras
            : UserTaskConfigSupport::slaHorasParaFuente($task->fuente);
        $task->sla_horas = $horas;
        $abierta = $task->abierta_en ? Carbon::parse($task->abierta_en) : now();
        $task->vence_en = $abierta->copy()->addHours($horas);
    }

    /**
     * Escala tareas vencidas no escaladas: notifica al titular + destinos de escalamiento.
     *
     * @return array{escaladas: int}
     */
    public function escalarVencidas(int $limite = 100): array
    {
        if (! Schema::hasTable('user_task')) {
            return ['escaladas' => 0];
        }

        $destinoIds = $this->idsEscalamiento();
        $tareas = UserTask::query()
            ->pendientes()
            ->whereNull('escalado_en')
            ->whereNotNull('vence_en')
            ->where('vence_en', '<', now())
            ->orderBy('vence_en')
            ->limit(max(1, $limite))
            ->get();

        $n = 0;
        foreach ($tareas as $task) {
            try {
                $this->escalarUna($task, $destinoIds);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('UserTaskSla: falló escalamiento', [
                    'user_task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['escaladas' => $n];
    }

    /**
     * @param  list<int>  $destinoIds
     */
    private function escalarUna(UserTask $task, array $destinoIds): void
    {
        $titulo = 'SLA vencido: '.$task->titulo;
        $cuerpo = 'La tarea «'.$task->titulo.'» ('.$task->fuente.' #'.$task->entity_id.') venció'
            .($task->vence_en ? ' el '.$task->vence_en->format('d/m/Y H:i') : '')
            .' y sigue pendiente.';

        $avisar = array_values(array_unique(array_filter(array_merge(
            [(int) $task->usuario_id],
            $destinoIds
        ))));
        $avisar = UsuarioAvisoPreferenciaSupport::filtrarIds($avisar, UsuarioAvisoPreferenciaSupport::CANAL_IN_APP);

        if ($avisar !== []) {
            $this->notificacionService->avisarSistemaAUsuarios(
                $avisar,
                $titulo,
                $cuerpo,
                $task->url,
                [
                    'user_task_id' => $task->id,
                    'sla' => true,
                ]
            );
        }

        $escaladoA = $destinoIds[0] ?? null;
        $task->escalado_en = now();
        $task->escalado_a_usuario_id = $escaladoA;
        if ($task->prioridad < 2) {
            $task->prioridad = 2;
        }
        $task->save();

        // Copia opcional al primer escalador si no es el mismo titular
        if ($escaladoA && (int) $escaladoA !== (int) $task->usuario_id) {
            $this->asegurarCopiaParaEscalador($task, (int) $escaladoA);
        }
    }

    private function asegurarCopiaParaEscalador(UserTask $origen, int $escaladorId): void
    {
        $copia = UserTask::query()->firstOrNew([
            'fuente' => $origen->fuente,
            'entity_id' => $origen->entity_id,
            'usuario_id' => $escaladorId,
        ]);
        $copia->entity_type = $origen->entity_type;
        $copia->titulo = '[Escalado] '.$origen->titulo;
        $copia->subtitulo = $origen->subtitulo;
        $copia->url = $origen->url;
        $copia->meta = array_merge(is_array($origen->meta) ? $origen->meta : [], [
            'escalado_desde_user_task_id' => $origen->id,
        ]);
        $copia->estado = UserTask::ESTADO_PENDIENTE;
        $copia->prioridad = max(2, (int) $origen->prioridad);
        $copia->sla_horas = $origen->sla_horas;
        $copia->abierta_en = $copia->abierta_en ?: now();
        $copia->vence_en = $copia->vence_en ?: now()->addHours($this->horasDefault());
        $copia->reasignado_de_usuario_id = (int) $origen->usuario_id;
        $copia->cerrada_en = null;
        $copia->save();
    }

    /**
     * Reasigna la tarea al nuevo usuario (cierra la del origen y abre/copia al destino).
     */
    public function reasignar(
        int $userTaskId,
        int $nuevoUsuarioId,
        int $actorId,
        ?string $motivo = null
    ): UserTask {
        $task = UserTask::query()->findOrFail($userTaskId);
        $esTitular = (int) $task->usuario_id === $actorId;
        $esAdmin = function_exists('can') && can('aprobar-mis-aprobaciones-arbol', false);
        if (! $esTitular && ! $esAdmin) {
            abort(403, 'No podés reasignar esta tarea.');
        }
        if (! UsuarioOperativoSupport::find($nuevoUsuarioId)) {
            throw new \InvalidArgumentException('El usuario destino no es operativo.');
        }
        if ((int) $task->usuario_id === $nuevoUsuarioId) {
            return $task;
        }

        $motivo = trim((string) $motivo);
        if (mb_strlen($motivo) > 255) {
            $motivo = mb_substr($motivo, 0, 255);
        }

        $nueva = UserTask::query()->firstOrNew([
            'fuente' => $task->fuente,
            'entity_id' => $task->entity_id,
            'usuario_id' => $nuevoUsuarioId,
        ]);
        $nueva->entity_type = $task->entity_type;
        $nueva->titulo = $task->titulo;
        $nueva->subtitulo = $task->subtitulo;
        $nueva->url = $task->url;
        if (Schema::hasColumn('user_task', 'empresa_id')) {
            $nueva->empresa_id = $task->empresa_id;
        }
        $meta = is_array($task->meta) ? $task->meta : [];
        if ($motivo !== '') {
            $meta['motivo_reasignacion'] = $motivo;
            $meta['reasignado_por_usuario_id'] = $actorId;
            $meta['reasignado_en'] = now()->toDateTimeString();
        }
        $nueva->meta = $meta;
        $nueva->estado = UserTask::ESTADO_PENDIENTE;
        $nueva->prioridad = $task->prioridad;
        $nueva->sla_horas = $task->sla_horas;
        $nueva->abierta_en = now();
        $nueva->vence_en = now()->addHours((int) ($task->sla_horas ?: UserTaskConfigSupport::slaHorasParaFuente($task->fuente)));
        $nueva->reasignado_de_usuario_id = (int) $task->usuario_id;
        $nueva->cerrada_en = null;
        $nueva->escalado_en = null;
        $nueva->escalado_a_usuario_id = null;
        $nueva->save();

        $task->estado = UserTask::ESTADO_CERRADA;
        $task->cerrada_en = now();
        $task->save();

        $ids = UsuarioAvisoPreferenciaSupport::filtrarIds(
            [$nuevoUsuarioId],
            UsuarioAvisoPreferenciaSupport::CANAL_IN_APP
        );
        if ($ids !== []) {
            $cuerpo = 'Te reasignaron una aprobación pendiente.';
            if ($motivo !== '') {
                $cuerpo .= ' Motivo: '.$motivo;
            }
            $url = $nueva->url;
            if ($url && ! preg_match('#^https?://#i', $url) && function_exists('urlAppAbsoluta')) {
                $url = urlAppAbsoluta(ltrim($url, '/'));
            }
            $this->notificacionService->avisarSistemaAUsuarios(
                $ids,
                'Tarea reasignada: '.$nueva->titulo,
                $cuerpo,
                $url,
                ['user_task_id' => $nueva->id, 'reasignada' => true, 'motivo' => $motivo ?: null]
            );
        }

        return $nueva;
    }

    /**
     * Reasignación masiva: todas las pendientes de un usuario, o de todos los usuarios de un rol.
     *
     * @return array{reasignadas: int, omitidas: int}
     */
    public function reasignarMasivo(
        int $destinoUsuarioId,
        int $actorId,
        ?int $desdeUsuarioId = null,
        ?int $desdeRolId = null,
        ?string $fuente = null,
        int $limite = 500,
        ?string $motivo = null
    ): array {
        if (! UsuarioOperativoSupport::find($destinoUsuarioId)) {
            throw new \InvalidArgumentException('El usuario destino no es operativo.');
        }
        if (! $desdeUsuarioId && ! $desdeRolId) {
            throw new \InvalidArgumentException('Indicá usuario origen o rol origen.');
        }

        $origenIds = [];
        if ($desdeUsuarioId && $desdeUsuarioId > 0) {
            $origenIds[] = $desdeUsuarioId;
        }
        if ($desdeRolId && $desdeRolId > 0) {
            $deRol = \App\Models\Seguridad\Usuario::query()
                ->whereHas('roles', fn ($q) => $q->where('rol.id', $desdeRolId))
                ->where(function ($q) {
                    $q->where('suspendido', false)->orWhereNull('suspendido');
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $origenIds = array_values(array_unique(array_merge($origenIds, $deRol)));
        }
        $origenIds = array_values(array_filter($origenIds, fn ($id) => $id > 0 && $id !== $destinoUsuarioId));
        if ($origenIds === []) {
            return ['reasignadas' => 0, 'omitidas' => 0];
        }

        $query = UserTask::query()
            ->pendientes()
            ->whereIn('usuario_id', $origenIds)
            ->orderBy('id')
            ->limit(max(1, min(2000, $limite)));
        if ($fuente) {
            $query->where('fuente', $fuente);
        }

        $reasignadas = 0;
        $omitidas = 0;
        foreach ($query->get() as $task) {
            try {
                $this->reasignar((int) $task->id, $destinoUsuarioId, $actorId, $motivo);
                $reasignadas++;
            } catch (\Throwable) {
                $omitidas++;
            }
        }

        return ['reasignadas' => $reasignadas, 'omitidas' => $omitidas];
    }

    /**
     * @return list<int>
     */
    private function idsEscalamiento(): array
    {
        return UserTaskConfigSupport::idsEscalamiento();
    }
}
