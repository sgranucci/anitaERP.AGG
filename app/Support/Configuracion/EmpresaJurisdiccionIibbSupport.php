<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\Empresa_Jurisdiccion_Iibb;
use App\Models\Configuracion\Provincia;
use Illuminate\Support\Facades\Schema;

/**
 * Nominación de agente IIBB por empresa jurídica × jurisdicción.
 *
 * Las alícuotas y mínimos viven en provincia / provincia_tasaiibb (patrimonio
 * del fisco). Esta tabla solo dice si ESTA empresa percibe o retiene ahí.
 */
final class EmpresaJurisdiccionIibbSupport
{
    /**
     * Jurisdicciones AFIP (901…) donde la empresa es agente de percepción.
     * Sin filas en la tabla (instalación nueva) → fallback .env.
     *
     * @return list<int>
     */
    public static function jurisdiccionesPercepcion(?int $empresaId = null): array
    {
        return self::jurisdicciones('es_agente_percepcion', $empresaId, 'agente_percepcion_iibb');
    }

    /**
     * @return list<int>
     */
    public static function jurisdiccionesRetencion(?int $empresaId = null): array
    {
        return self::jurisdicciones('es_agente_retencion', $empresaId, 'agente_retencion_iibb');
    }

    /**
     * ¿Esta empresa es agente de retención en esa provincia?
     * Tabla vacía (instalación nueva) → no corta (legado Anita / .env).
     */
    public static function esAgenteRetencion(?int $empresaId, ?int $provinciaId): bool
    {
        if ($provinciaId === null || $provinciaId <= 0) {
            return false;
        }
        if (! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return true;
        }
        if (! Empresa_Jurisdiccion_Iibb::query()->exists()) {
            return true;
        }
        if ($empresaId === null || $empresaId <= 0) {
            return Empresa_Jurisdiccion_Iibb::query()
                ->where('provincia_id', $provinciaId)
                ->where('es_agente_retencion', true)
                ->exists();
        }

        return Empresa_Jurisdiccion_Iibb::query()
            ->where('empresa_id', $empresaId)
            ->where('provincia_id', $provinciaId)
            ->where('es_agente_retencion', true)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public static function provinciaIdsRetencion(?int $empresaId = null): array
    {
        if (! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return [];
        }

        $query = Empresa_Jurisdiccion_Iibb::query()->where('es_agente_retencion', true);
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query
            ->orderBy('provincia_id')
            ->pluck('provincia_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Unión percepción + retención (panel de padrones).
     *
     * @return list<int>
     */
    public static function jurisdiccionesAgente(?int $empresaId = null): array
    {
        $jurs = array_values(array_unique(array_merge(
            self::jurisdiccionesPercepcion($empresaId),
            self::jurisdiccionesRetencion($empresaId),
        )));
        sort($jurs);

        return $jurs;
    }

    /**
     * @return list<int>
     */
    private static function jurisdicciones(string $flag, ?int $empresaId, string $envClave): array
    {
        if (! Schema::hasTable('empresa_jurisdiccion_iibb')) {
            return self::desdeEnv($envClave);
        }

        $hayFilas = Empresa_Jurisdiccion_Iibb::query()->exists();
        if (! $hayFilas) {
            return self::desdeEnv($envClave);
        }

        $query = Empresa_Jurisdiccion_Iibb::query()
            ->where($flag, true)
            ->join('provincia', 'provincia.id', '=', 'empresa_jurisdiccion_iibb.provincia_id')
            ->whereNotNull('provincia.jurisdiccion');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_jurisdiccion_iibb.empresa_id', $empresaId);
        }

        $jurs = $query
            ->distinct()
            ->orderBy('provincia.jurisdiccion')
            ->pluck('provincia.jurisdiccion')
            ->map(static fn ($j) => (int) $j)
            ->filter(static fn (int $j) => $j >= 900)
            ->values()
            ->all();

        return $jurs;
    }

    /**
     * @return list<int>
     */
    public static function desdeEnv(string $clave): array
    {
        $jurs = [];
        foreach (explode(',', (string) config('anita.'.$clave, '')) as $valor) {
            $valor = trim($valor);
            if ($valor !== '' && ctype_digit($valor)) {
                $jurs[] = (int) $valor;
            }
        }

        return array_values(array_unique($jurs));
    }

    /**
     * Matriz para la pantalla: 24 provincias × empresas.
     *
     * @param  iterable<int, object>  $empresas
     * @return list<array<string, mixed>>
     */
    public static function matrizParaFormulario(iterable $empresas): array
    {
        $empresaIds = [];
        foreach ($empresas as $empresa) {
            $empresaIds[] = (int) $empresa->id;
        }

        $filas = [];
        if (Schema::hasTable('empresa_jurisdiccion_iibb') && $empresaIds !== []) {
            foreach (Empresa_Jurisdiccion_Iibb::query()
                ->whereIn('empresa_id', $empresaIds)
                ->get() as $fila) {
                $filas[(int) $fila->empresa_id][(int) $fila->provincia_id] = $fila;
            }
        }

        $provincias = Provincia::query()
            ->where('jurisdiccion', '>=', 900)
            ->orderBy('jurisdiccion')
            ->get(['id', 'nombre', 'jurisdiccion']);

        $out = [];
        foreach ($provincias as $provincia) {
            $celdas = [];
            foreach ($empresas as $empresa) {
                $fila = $filas[(int) $empresa->id][(int) $provincia->id] ?? null;
                $celdas[(int) $empresa->id] = [
                    'percepcion' => (bool) ($fila->es_agente_percepcion ?? false),
                    'retencion' => (bool) ($fila->es_agente_retencion ?? false),
                ];
            }
            $out[] = [
                'provincia_id' => (int) $provincia->id,
                'nombre' => (string) $provincia->nombre,
                'jurisdiccion' => (int) $provincia->jurisdiccion,
                'empresas' => $celdas,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int|string, array<int|string, array<string, mixed>>>  $payload
     *        [empresa_id][provincia_id][percepcion|retencion]
     */
    public static function guardarMatriz(array $payload): void
    {
        foreach ($payload as $empresaId => $provincias) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0 || ! is_array($provincias)) {
                continue;
            }
            foreach ($provincias as $provinciaId => $flags) {
                $provinciaId = (int) $provinciaId;
                if ($provinciaId <= 0 || ! is_array($flags)) {
                    continue;
                }
                $percibe = ! empty($flags['percepcion']);
                $retiene = ! empty($flags['retencion']);
                if (! $percibe && ! $retiene) {
                    Empresa_Jurisdiccion_Iibb::query()
                        ->where('empresa_id', $empresaId)
                        ->where('provincia_id', $provinciaId)
                        ->get()
                        ->each(static fn (Empresa_Jurisdiccion_Iibb $f) => $f->delete());
                    continue;
                }
                Empresa_Jurisdiccion_Iibb::query()->updateOrCreate(
                    ['empresa_id' => $empresaId, 'provincia_id' => $provinciaId],
                    [
                        'es_agente_percepcion' => $percibe,
                        'es_agente_retencion' => $retiene,
                    ]
                );
            }
        }
    }
}
