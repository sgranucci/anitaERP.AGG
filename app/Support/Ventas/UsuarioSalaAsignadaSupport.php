<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

/**
 * Salas asignadas al usuario (Crown). Misma semántica que empresas:
 * - Sin filas en usuario_sala → acceso a todas las salas.
 * - Con ≥1 → restringe whereIn(sala_id).
 * Fuera de Crown no aplica.
 */
final class UsuarioSalaAsignadaSupport
{
    public static function activo(): bool
    {
        return EntornoEmpresaSupport::esCrown();
    }

    /**
     * @return list<int>
     */
    public static function idsDesdeSesion(): array
    {
        if (! self::activo()) {
            return [];
        }

        return collect(Session::get('usuario_salas', []))
            ->map(function ($s) {
                if (is_array($s)) {
                    return (int) ($s['id'] ?? 0);
                }
                if (is_object($s)) {
                    return (int) ($s->id ?? 0);
                }

                return (int) $s;
            })
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFiltro(Builder $query, string $column = 'sala_id', bool $incluirNull = false): void
    {
        if (! self::activo()) {
            return;
        }

        $ids = self::idsDesdeSesion();
        if (count($ids) < 1) {
            return;
        }

        if ($incluirNull) {
            $query->where(function ($q) use ($ids, $column) {
                $q->whereIn($column, $ids)->orWhereNull($column);
            });

            return;
        }

        $query->whereIn($column, $ids);
    }

    public static function salaIdPermitida(int $salaId): bool
    {
        if (! self::activo()) {
            return true;
        }
        $ids = self::idsDesdeSesion();
        if (count($ids) === 0) {
            return true;
        }

        return in_array($salaId, $ids, true);
    }

    /**
     * Combina sala explícita (PV / request) con las asignadas.
     * Si hay sala explícita y el usuario tiene asignaciones, debe estar permitida.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFiltroConSalaOpcional(Builder $query, ?int $salaId, string $column = 'sala_id'): void
    {
        $salaId = (int) ($salaId ?? 0);
        if ($salaId > 0) {
            if (! self::salaIdPermitida($salaId)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where($column, $salaId);

            return;
        }

        self::aplicarFiltro($query, $column, false);
    }
}
