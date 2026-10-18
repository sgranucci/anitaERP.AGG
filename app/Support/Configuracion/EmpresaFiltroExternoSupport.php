<?php

namespace App\Support\Configuracion;

/**
 * Chips de filtro por empresa en listados (todas las instalaciones).
 *
 * - Más de una empresa en sesión (`usuario_empresas`): mostrar chips.
 * - Una o ninguna: no mostrar. Con ninguna (acceso total) allFiltrado()
 *   devolvería el catálogo completo; no hay que pintarlo como etiquetas.
 */
final class EmpresaFiltroExternoSupport
{
    public static function cantidadAsignadasSesion(): int
    {
        return collect(session('usuario_empresas', []))
            ->pluck('id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->count();
    }

    public static function mostrarChips(): bool
    {
        return self::cantidadAsignadasSesion() > 1;
    }

    /**
     * Primera empresa asignada para default del listado, o null si no hay
     * asignación explícita (acceso a todas → sin filtro de empresa).
     */
    public static function empresaDefaultDesdeSesion(): ?int
    {
        $ids = collect(session('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return $ids->isNotEmpty() ? (int) $ids->first() : null;
    }
}
