<?php

namespace App\Support\Configuracion;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filtros y consulta del historial audits (cualquier modelo).
 */
class AuditoriaDatosListadoFiltros
{
    /**
     * @return array{
     *   auditable_type: string,
     *   auditable_id: ?int,
     *   registro_busqueda: string,
     *   event: string,
     *   campo: string,
     *   usuario_id: ?int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   consultar_datos: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request, array $filtrosBase = []): array
    {
        $usuarioId = $request->input('usuario_id', $filtrosBase['usuario_id'] ?? null);
        $usuarioId = $usuarioId !== null && $usuarioId !== '' ? (int) $usuarioId : null;

        $auditableId = $request->input('auditable_id');
        $auditableId = $auditableId !== null && $auditableId !== '' ? (int) $auditableId : null;

        $type = trim((string) $request->input('auditable_type', ''));
        // Evitar FQCN arbitrario: solo tipos del catálogo o favoritos.
        if ($type !== '' && ! self::tipoPermitido($type)) {
            $type = '';
        }

        return [
            'auditable_type' => $type,
            'auditable_id' => $auditableId && $auditableId > 0 ? $auditableId : null,
            'registro_busqueda' => trim((string) $request->input('registro_busqueda', '')),
            'event' => trim((string) $request->input('event', '')),
            'campo' => trim((string) $request->input('campo', '')),
            'usuario_id' => $usuarioId,
            'fecha_desde' => (string) ($filtrosBase['fecha_desde'] ?? $request->input('fecha_desde') ?: now()->subDays(7)->toDateString()),
            'fecha_hasta' => (string) ($filtrosBase['fecha_hasta'] ?? $request->input('fecha_hasta') ?: now()->toDateString()),
            'consultar_datos' => $request->boolean('consultar') || $request->has('auditable_type') || $request->has('auditable_id') || $request->filled('registro_busqueda'),
        ];
    }

    /** @param  array<string, mixed>  $filtros */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (($filtros['auditable_type'] ?? '') !== '') {
            $out['auditable_type'] = $filtros['auditable_type'];
        }
        if (! empty($filtros['auditable_id'])) {
            $out['auditable_id'] = $filtros['auditable_id'];
        }
        if (($filtros['registro_busqueda'] ?? '') !== '') {
            $out['registro_busqueda'] = $filtros['registro_busqueda'];
        }
        if (($filtros['event'] ?? '') !== '') {
            $out['event'] = $filtros['event'];
        }
        if (($filtros['campo'] ?? '') !== '') {
            $out['campo'] = $filtros['campo'];
        }

        return $out;
    }

    /**
     * Regla de seguridad de performance: exige modelo o (usuario + fechas).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function criteriosSuficientes(array $filtros): bool
    {
        if (($filtros['auditable_type'] ?? '') !== '') {
            return true;
        }
        if (! empty($filtros['usuario_id'])) {
            return true;
        }

        return false;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '') {
            $query->where('audits.created_at', '>=', $desde.' 00:00:00');
        }
        if ($hasta !== '') {
            $query->where('audits.created_at', '<=', $hasta.' 23:59:59');
        }
        if (($filtros['auditable_type'] ?? '') !== '') {
            $query->where('audits.auditable_type', $filtros['auditable_type']);
        }
        if (! empty($filtros['auditable_id'])) {
            $query->where('audits.auditable_id', (int) $filtros['auditable_id']);
        }
        if (($filtros['event'] ?? '') !== '') {
            $query->where('audits.event', $filtros['event']);
        }
        if (! empty($filtros['usuario_id'])) {
            $query->where('audits.user_id', (int) $filtros['usuario_id']);
        }
        if (($filtros['campo'] ?? '') !== '') {
            $campo = $filtros['campo'];
            // Busca la clave JSON en old/new (sin escapar comillas del nombre).
            $like = '%"'.str_replace(['%', '_', '"'], ['\\%', '\\_', ''], $campo).'"%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('audits.old_values', 'like', $like)
                    ->orWhere('audits.new_values', 'like', $like);
            });
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function queryBase(array $filtros): Builder
    {
        return self::aplicar(DB::table('audits'), $filtros)
            ->orderByDesc('audits.created_at')
            ->orderByDesc('audits.id');
    }

    public static function tipoPermitido(string $type): bool
    {
        return AuditoriaDatosCatalogoSupport::tipoConocido($type);
    }

    /**
     * Diff campo a campo a partir de old_values / new_values JSON.
     *
     * @return list<array{campo:string,antes:mixed,despues:mixed}>
     */
    public static function diffValores(?string $oldJson, ?string $newJson): array
    {
        $old = self::decodeJson($oldJson);
        $new = self::decodeJson($newJson);
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
        sort($keys);
        $out = [];
        foreach ($keys as $k) {
            $antes = array_key_exists($k, $old) ? $old[$k] : null;
            $despues = array_key_exists($k, $new) ? $new[$k] : null;
            if ($antes === $despues) {
                continue;
            }
            $out[] = [
                'campo' => (string) $k,
                'antes' => $antes,
                'despues' => $despues,
            ];
        }

        return $out;
    }

    public static function formatearValor(mixed $v): string
    {
        if ($v === null) {
            return '∅';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_scalar($v)) {
            return (string) $v;
        }

        return (string) json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private static function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
