<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteAuditoria;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Registro de quién consultó qué en Consulta Inteligente.
 */
final class ConsultaInteligenteAuditoriaSupport
{
    public const ACCION_CATALOGO = 'catalogo';

    public const ACCION_EXPLORAR = 'explorar';

    public const ACCION_EXPORTAR = 'exportar';

    public const ACCION_NL = 'nl';

    public const ACCION_CRUZAR = 'cruzar';

    public const ACCION_UNIR = 'unir';

    public const ACCION_SNAPSHOT = 'snapshot';

    public const ACCION_TABLERO = 'tablero';

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function registrar(
        string $accion,
        ?string $entidad = null,
        ?string $detalle = null,
        array $meta = []
    ): void {
        try {
            if (! Schema::hasTable('consulta_inteligente_auditoria')) {
                return;
            }
            ConsultaInteligenteAuditoria::query()->create([
                'usuario_id' => auth()->id() ? (int) auth()->id() : null,
                'accion' => mb_substr($accion, 0, 40),
                'entidad' => $entidad !== null ? mb_substr($entidad, 0, 80) : null,
                'detalle' => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
                'meta_json' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Nunca frenar la consulta por falla de auditoría.
        }
    }

    /**
     * @return list<ConsultaInteligenteAuditoria>
     */
    public static function recientes(int $limite = 50, ?int $usuarioId = null): array
    {
        if (! Schema::hasTable('consulta_inteligente_auditoria')) {
            return [];
        }
        $q = ConsultaInteligenteAuditoria::query()
            ->with('usuario:id,nombre,usuario')
            ->orderByDesc('id')
            ->limit(max(1, min(200, $limite)));
        if ($usuarioId !== null && $usuarioId > 0) {
            $q->where('usuario_id', $usuarioId);
        }

        return $q->get()->all();
    }
}
