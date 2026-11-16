<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteNlSinonimo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Sinónimos editables NL → entidad (prioridad sobre el diccionario hardcodeado).
 */
final class ConsultaInteligenteNlDiccionarioSupport
{
    /**
     * @return list<array{frase: string, entidad: string, prioridad: int}>
     */
    public static function activos(): array
    {
        if (! self::tablaLista()) {
            return [];
        }

        return Cache::remember(self::claveCache(), 600, static function (): array {
            return ConsultaInteligenteNlSinonimo::query()
                ->where('activo', true)
                ->orderBy('prioridad')
                ->orderBy('id')
                ->get(['frase', 'entidad', 'prioridad'])
                ->map(static fn (ConsultaInteligenteNlSinonimo $s) => [
                    'frase' => mb_strtolower(trim((string) $s->frase)),
                    'entidad' => (string) $s->entidad,
                    'prioridad' => (int) $s->prioridad,
                ])
                ->filter(static fn (array $r) => $r['frase'] !== '' && $r['entidad'] !== '')
                ->values()
                ->all();
        });
    }

    public static function olvidarCache(): void
    {
        Cache::forget(self::claveCache());
    }

    private static function claveCache(): string
    {
        return generaKey('ci-nl-sinonimos-activos');
    }

    /**
     * Resuelve entidad por sinónimo editable (frase contenida en el texto normalizado).
     */
    public static function detectarEntidad(string $textoNormalizado): ?string
    {
        $mejor = null;
        $mejorLen = 0;
        $mejorPrio = PHP_INT_MAX;
        foreach (self::activos() as $row) {
            if (! EntidadSemanticaCatalogo::existe($row['entidad'])) {
                continue;
            }
            $frase = $row['frase'];
            if ($frase === '' || ! str_contains($textoNormalizado, $frase)) {
                continue;
            }
            $len = mb_strlen($frase);
            $prio = $row['prioridad'];
            if ($len > $mejorLen || ($len === $mejorLen && $prio < $mejorPrio)) {
                $mejor = $row['entidad'];
                $mejorLen = $len;
                $mejorPrio = $prio;
            }
        }

        return $mejor;
    }

    /**
     * @return list<ConsultaInteligenteNlSinonimo>
     */
    public static function listarTodos(): array
    {
        if (! self::tablaLista()) {
            return [];
        }

        return ConsultaInteligenteNlSinonimo::query()
            ->orderBy('prioridad')
            ->orderBy('frase')
            ->get()
            ->all();
    }

    /**
     * @param  array{frase?: string, entidad?: string, prioridad?: int, activo?: bool, metrica?: string|null}  $data
     */
    public static function guardar(?int $id, array $data): ?ConsultaInteligenteNlSinonimo
    {
        if (! self::tablaLista()) {
            return null;
        }
        $frase = mb_strtolower(trim((string) ($data['frase'] ?? '')));
        $entidad = trim((string) ($data['entidad'] ?? ''));
        if ($frase === '' || $entidad === '' || ! EntidadSemanticaCatalogo::existe($entidad)) {
            return null;
        }
        $attrs = [
            'frase' => mb_substr($frase, 0, 120),
            'entidad' => $entidad,
            'prioridad' => max(1, min(999, (int) ($data['prioridad'] ?? 100))),
            'activo' => (bool) ($data['activo'] ?? true),
        ];
        if (ConsultaInteligenteNlMetricasDiccionarioSupport::tieneColumnaMetrica()) {
            $metrica = trim((string) ($data['metrica'] ?? ''));
            $attrs['metrica'] = ($metrica !== '' && in_array($metrica, ConsultaInteligenteNlMetricasDiccionarioSupport::METRICAS, true))
                ? $metrica
                : null;
        }
        if ($id !== null && $id > 0) {
            $row = ConsultaInteligenteNlSinonimo::query()->find($id);
            if (! $row) {
                return null;
            }
            $row->fill($attrs);
            $row->save();
        } else {
            $row = ConsultaInteligenteNlSinonimo::query()->create($attrs);
        }
        self::olvidarCache();
        ConsultaInteligenteNlMetricasDiccionarioSupport::olvidarCache();

        return $row;
    }

    public static function eliminar(int $id): bool
    {
        if (! self::tablaLista()) {
            return false;
        }
        $row = ConsultaInteligenteNlSinonimo::query()->find($id);
        if (! $row) {
            return false;
        }
        $row->delete();
        self::olvidarCache();
        ConsultaInteligenteNlMetricasDiccionarioSupport::olvidarCache();

        return true;
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_nl_sinonimo');
        } catch (\Throwable) {
            return false;
        }
    }
}
