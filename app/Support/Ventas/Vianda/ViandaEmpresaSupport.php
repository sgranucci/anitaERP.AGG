<?php

namespace App\Support\Ventas\Vianda;

use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Filtro tradicional de empresas para el módulo de viandas: se opera con las empresas
 * asignadas al usuario en sesión (como el resto del sistema), acotadas a las empresas
 * del módulo (config vianda_anita.empresas_sync: 1=Biyemas, 2=Kandiko, 3=Rebisco).
 */
final class ViandaEmpresaSupport
{
    /**
     * IDs de empresas del módulo vianda (config) intersectadas con las asignadas al usuario.
     * Sin empresas asignadas (acceso total) → todas las del módulo.
     *
     * @return list<int>
     */
    public static function idsSeleccionables(): array
    {
        $modulo = array_values(array_unique(array_filter(array_map(
            static fn ($valor): int => (int) $valor,
            (array) config('vianda_anita.empresas_sync', [1])
        ), static fn (int $valor): bool => $valor > 0)));

        if ($modulo === []) {
            $modulo = [1];
        }

        $asignadas = collect(Session::get('usuario_empresas'))
            ->pluck('id')
            ->map(static fn ($valor): int => (int) $valor)
            ->filter(static fn (int $valor): bool => $valor > 0)
            ->all();

        if ($asignadas === []) {
            return $modulo;
        }

        return array_values(array_intersect($modulo, $asignadas));
    }

    /**
     * Empresas seleccionables para dropdowns del módulo (filtros y formularios).
     *
     * @return Collection<int, Empresa>
     */
    public static function empresasSeleccionables(?int $incluirId = null): Collection
    {
        $ids = self::idsSeleccionables();

        if ($incluirId !== null && $incluirId > 0 && ! in_array($incluirId, $ids, true)) {
            $ids[] = $incluirId;
        }

        if ($ids === []) {
            return collect();
        }

        return Empresa::query()->whereIn('id', $ids)->orderBy('id')->get(['id', 'nombre']);
    }

    /**
     * Restringe un query a las empresas asignadas al usuario (filtro estándar del sistema).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroAsignadas($query, string $column = 'empresa_id'): void
    {
        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query, $column);
    }

    public static function empresaPermitida(int $empresaId): bool
    {
        return app(EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId);
    }
}
