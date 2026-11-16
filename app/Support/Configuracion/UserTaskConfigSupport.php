<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\UserTaskConfig;
use App\Models\Configuracion\UserTaskEscalador;
use App\Models\Configuracion\UserTaskSlaFuente;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Schema;

final class UserTaskConfigSupport
{
    public static function slaHoras(): int
    {
        if (Schema::hasTable('user_task_config')) {
            return max(1, (int) UserTaskConfig::actual()->sla_horas);
        }

        return max(1, (int) config('arbolaprobacion.user_task_sla_horas', 48));
    }

    /**
     * SLA por fuente; si no hay fila, usa el default global.
     */
    public static function slaHorasParaFuente(?string $fuente): int
    {
        $fuente = trim((string) $fuente);
        if ($fuente !== '' && Schema::hasTable('user_task_sla_fuente')) {
            $horas = UserTaskSlaFuente::query()
                ->where('fuente', $fuente)
                ->value('sla_horas');
            if ($horas !== null && (int) $horas > 0) {
                return max(1, (int) $horas);
            }
        }

        return self::slaHoras();
    }

    /**
     * @return array<string, int> fuente => horas
     */
    public static function mapaSlaPorFuente(): array
    {
        if (! Schema::hasTable('user_task_sla_fuente')) {
            return [];
        }

        return UserTaskSlaFuente::query()
            ->pluck('sla_horas', 'fuente')
            ->map(fn ($h) => max(1, (int) $h))
            ->all();
    }

    public static function bandejaDesdeTabla(): bool
    {
        if (Schema::hasTable('user_task_config')) {
            return (bool) UserTaskConfig::actual()->bandeja_desde_tabla;
        }

        return (bool) config('arbolaprobacion.user_task_bandeja_desde_tabla', false);
    }

    /**
     * Escaladores de pantalla + fallback .env.
     *
     * @return list<int>
     */
    public static function idsEscalamiento(): array
    {
        $ids = [];
        if (Schema::hasTable('user_task_escalador')) {
            $ids = UserTaskEscalador::query()
                ->where('activo', true)
                ->pluck('usuario_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($ids === []) {
            $raw = config('arbolaprobacion.user_task_escalamiento_usuario_ids', []);
            if (is_array($raw)) {
                $ids = array_map('intval', $raw);
            }
        }

        return UsuarioOperativoSupport::filtrarIdsActivos($ids);
    }
}
