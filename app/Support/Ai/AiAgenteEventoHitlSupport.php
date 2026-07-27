<?php

namespace App\Support\Ai;

use App\Models\Ai\AiAgenteEvento;
use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Cola HITL de ai_agente_evento: listado y transiciones visto / descartar / resolver.
 * No ejecuta efectos de negocio del plan; solo gobierna el estado del evento.
 */
final class AiAgenteEventoHitlSupport
{
    /**
     * @return array<string, string>
     */
    public static function estadosEtiquetas(): array
    {
        return [
            AiAgenteEvento::ESTADO_PENDIENTE => 'Pendiente',
            AiAgenteEvento::ESTADO_VISTO => 'Visto',
            AiAgenteEvento::ESTADO_DESCARTADO => 'Descartado',
            AiAgenteEvento::ESTADO_RESUELTO => 'Resuelto',
        ];
    }

    /**
     * @param  array{estado?: string|null, severidad?: string|null, evento?: string|null}  $filtros
     */
    public static function listar(array $filtros = [], bool $paginar = true, int $porPagina = 15): LengthAwarePaginator|\Illuminate\Support\Collection
    {
        if (! Schema::hasTable('ai_agente_evento')) {
            return $paginar
                ? new \Illuminate\Pagination\LengthAwarePaginator([], 0, $porPagina)
                : collect();
        }

        $q = AiAgenteEvento::query()->orderByDesc('id');

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '' && array_key_exists($estado, self::estadosEtiquetas())) {
            $q->where('estado', $estado);
        }

        $sev = trim((string) ($filtros['severidad'] ?? ''));
        if (in_array($sev, ['baja', 'media', 'alta'], true)) {
            $q->where('severidad', $sev);
        }

        $evento = trim((string) ($filtros['evento'] ?? ''));
        if ($evento !== '') {
            $q->where('evento', $evento);
        }

        return $paginar ? $q->paginate($porPagina) : $q->get();
    }

    public static function marcarVisto(int $id, ?int $usuarioId = null): AiAgenteEvento
    {
        $evento = self::buscar($id);
        if ($evento->estado === AiAgenteEvento::ESTADO_DESCARTADO
            || $evento->estado === AiAgenteEvento::ESTADO_RESUELTO) {
            throw new InvalidArgumentException('El evento ya está cerrado.');
        }

        $evento->estado = AiAgenteEvento::ESTADO_VISTO;
        $evento->visto_at = $evento->visto_at ?? now();
        $evento->save();

        self::asegurarDecision($evento, AiDecision::ACCION_SUGERIDA, $usuarioId);

        return $evento->fresh();
    }

    public static function descartar(int $id, ?int $usuarioId = null): AiAgenteEvento
    {
        $evento = self::buscar($id);
        if ($evento->estado === AiAgenteEvento::ESTADO_RESUELTO) {
            throw new InvalidArgumentException('El evento ya está resuelto.');
        }

        $evento->estado = AiAgenteEvento::ESTADO_DESCARTADO;
        $evento->visto_at = $evento->visto_at ?? now();
        $evento->resuelto_at = now();
        $evento->save();

        self::cerrarDecision($evento, AiDecision::ACCION_DESCARTADA, $usuarioId);

        return $evento->fresh();
    }

    public static function resolver(int $id, ?int $usuarioId = null): AiAgenteEvento
    {
        $evento = self::buscar($id);
        if ($evento->estado === AiAgenteEvento::ESTADO_DESCARTADO) {
            throw new InvalidArgumentException('El evento está descartado.');
        }

        $evento->estado = AiAgenteEvento::ESTADO_RESUELTO;
        $evento->visto_at = $evento->visto_at ?? now();
        $evento->resuelto_at = now();
        $evento->save();

        self::cerrarDecision($evento, AiDecision::ACCION_CONFIRMADA, $usuarioId);

        return $evento->fresh();
    }

    /**
     * Deep-link opcional a pantallas conocidas.
     */
    public static function urlEntidad(AiAgenteEvento $evento): ?string
    {
        $tipo = (string) ($evento->entidad_tipo ?? '');
        $id = (int) ($evento->entidad_id ?? 0);
        $nombreEvento = (string) $evento->evento;

        try {
            if ($nombreEvento === AiAgenteOperativoSupport::EVENTO_DESVIO_CONCILIACION
                && \Route::has('conciliacion_bancaria')) {
                return route('conciliacion_bancaria');
            }
            if ($nombreEvento === AiAgenteOperativoSupport::EVENTO_Z_TRANSMISION_FALTANTE
                && \Route::has('gastronomia_jornada')) {
                return route('gastronomia_jornada');
            }
            if ($tipo !== '' && $id > 0 && \Route::has('editar_'.$tipo)) {
                return route('editar_'.$tipo, $id);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private static function buscar(int $id): AiAgenteEvento
    {
        $evento = AiAgenteEvento::query()->find($id);
        if (! $evento) {
            throw new InvalidArgumentException('Evento inexistente.');
        }

        return $evento;
    }

    private static function asegurarDecision(AiAgenteEvento $evento, string $accion, ?int $usuarioId): void
    {
        if ($evento->ai_decision_id) {
            return;
        }

        try {
            $decision = app(AiDecisionLogger::class)->registrar([
                'skill' => 'agente_evento',
                'accion' => $accion,
                'empresa_id' => $evento->empresa_id,
                'usuario_id' => $usuarioId,
                'entidad_tipo' => $evento->entidad_tipo ?? 'ai_agente_evento',
                'entidad_id' => $evento->id,
                'payload' => [
                    'origen_hitl' => 'ai_agente_evento',
                    'evento' => $evento->evento,
                    'origen' => $evento->origen,
                    'resumen' => $evento->resumen,
                ],
            ]);
            if ($decision) {
                $evento->ai_decision_id = $decision->id;
                $evento->save();
            }
        } catch (Throwable) {
            // no interrumpir HITL
        }
    }

    private static function cerrarDecision(AiAgenteEvento $evento, string $accion, ?int $usuarioId): void
    {
        self::asegurarDecision($evento, AiDecision::ACCION_SUGERIDA, $usuarioId);
        $evento->refresh();
        if (! $evento->ai_decision_id) {
            return;
        }

        try {
            app(AiDecisionLogger::class)->resolver(
                (int) $evento->ai_decision_id,
                $accion,
                $usuarioId,
                [
                    'entidad_tipo' => 'ai_agente_evento',
                    'entidad_id' => $evento->id,
                ]
            );
        } catch (Throwable) {
            // no interrumpir HITL
        }
    }
}
