<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\UserTask;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Materializa la bandeja viva en user_task (pendiente/cerrada).
 * Las acciones siguen en cada dominio; esta tabla es índice/consulta.
 */
class UserTaskSyncService
{
    public function __construct(
        private UserTaskBandejaService $bandeja,
    ) {}

    public function sincronizarUsuario(int $usuarioId): array
    {
        if (! Schema::hasTable('user_task') || $usuarioId <= 0) {
            return ['abiertas' => 0, 'cerradas' => 0];
        }

        $pendientes = $this->bandeja->listarPendientesParaUsuario($usuarioId);
        $vivos = [];
        $abiertas = 0;

        foreach ($pendientes as $item) {
            $fuente = (string) ($item['fuente'] ?? '');
            $entityId = (int) ($item['comprobante_id'] ?? $item['entity_id'] ?? $item['id'] ?? 0);
            if ($fuente === '' || $entityId <= 0) {
                continue;
            }
            $clave = $fuente.'|'.$entityId;
            $vivos[$clave] = true;

            $titulo = (string) ($item['titulo']
                ?? $item['numero']
                ?? $item['etiqueta_tipo']
                ?? $fuente);
            $url = (string) ($item['url_ver'] ?? $item['url_detalle'] ?? $item['url'] ?? '');

            $task = UserTask::query()->firstOrNew([
                'fuente' => $fuente,
                'entity_id' => $entityId,
                'usuario_id' => $usuarioId,
            ]);
            $task->entity_type = (string) ($item['tipo'] ?? $fuente);
            $task->titulo = mb_substr($titulo, 0, 255);
            $task->subtitulo = isset($item['subtitulo'])
                ? mb_substr((string) $item['subtitulo'], 0, 255)
                : null;
            $task->url = $url !== '' ? mb_substr($url, 0, 500) : null;
            $empresaId = (int) ($item['empresa_id'] ?? $item['empresa']['id'] ?? 0);
            if ($empresaId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('user_task', 'empresa_id')) {
                $task->empresa_id = $empresaId;
            }
            $task->meta = [
                'task_key' => $item['task_key'] ?? null,
                'urgencia' => $item['urgencia'] ?? null,
                'dias_pendiente' => $item['dias_pendiente'] ?? null,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
            ];
            $reabriendo = $task->exists && $task->getOriginal('estado') === UserTask::ESTADO_CERRADA;
            $task->estado = UserTask::ESTADO_PENDIENTE;
            $task->cerrada_en = null;
            if (! $task->exists || ! $task->abierta_en || $reabriendo) {
                $task->abierta_en = now();
            }
            if ($reabriendo) {
                $task->vence_en = null;
                $task->escalado_en = null;
                $task->escalado_a_usuario_id = null;
            }
            app(UserTaskSlaService::class)->aplicarSlaAlAbrir($task);
            $task->save();
            $abiertas++;
        }

        $cerradas = 0;
        $abiertasDb = UserTask::query()
            ->where('usuario_id', $usuarioId)
            ->where('estado', UserTask::ESTADO_PENDIENTE)
            ->get();
        foreach ($abiertasDb as $task) {
            $clave = $task->fuente.'|'.$task->entity_id;
            if (isset($vivos[$clave])) {
                continue;
            }
            $task->estado = UserTask::ESTADO_CERRADA;
            $task->cerrada_en = now();
            $task->save();
            $cerradas++;
        }

        return ['abiertas' => $abiertas, 'cerradas' => $cerradas];
    }

    public function sincronizarActivos(int $limite = 200): array
    {
        if (! Schema::hasTable('user_task')) {
            return ['usuarios' => 0];
        }

        $ids = Usuario::query()
            ->where(function ($q) {
                $q->where('suspendido', false)->orWhereNull('suspendido');
            })
            ->orderBy('id')
            ->limit(max(1, $limite))
            ->pluck('id');

        $n = 0;
        foreach ($ids as $id) {
            try {
                $this->sincronizarUsuario((int) $id);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('UserTaskSync: falló usuario', [
                    'usuario_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['usuarios' => $n];
    }
}
