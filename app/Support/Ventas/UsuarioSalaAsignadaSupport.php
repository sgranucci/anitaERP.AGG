<?php

namespace App\Support\Ventas;

use App\Models\Configuracion\Sala;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Salas asignadas al usuario (Crown). Misma semántica que empresas:
 * - Sin filas en usuario_sala → acceso a todas las salas.
 * - Con ≥1 → restringe whereIn(sala_id).
 * Fuera de Crown no aplica.
 */
final class UsuarioSalaAsignadaSupport
{
    /**
     * Código de sala Crown → grupo_codigo de centrocosto (maestro contable).
     *
     * @var array<string, string>
     */
    private const SALA_A_GRUPO_CENTROCOSTO = [
        'CAT' => '13',
        'CIP' => '2',
        'LGR' => '3',
        'VIE' => '4',
        'GRO' => '5',
        'EFA' => '6',
        'CHO' => '7',
        'VRE' => '9',
        'CSA' => '16',
    ];

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
     * @return list<string>
     */
    public static function codigosDesdeSesion(): array
    {
        if (! self::activo()) {
            return [];
        }

        return collect(Session::get('usuario_salas', []))
            ->map(function ($s) {
                if (is_array($s)) {
                    return strtoupper(trim((string) ($s['codigo'] ?? '')));
                }
                if (is_object($s)) {
                    return strtoupper(trim((string) ($s->codigo ?? '')));
                }

                return '';
            })
            ->filter(fn (string $codigo) => $codigo !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Centros de costo del/los casinos asignados (vía grupo_codigo).
     * Vacío = sin recorte por sala/grupo.
     *
     * @return list<int>
     */
    public static function centrocostoIdsAlcance(): array
    {
        $codigosSala = self::codigosDesdeSesion();
        if ($codigosSala === []) {
            return [];
        }

        $grupos = [];
        foreach ($codigosSala as $codigo) {
            $grupo = self::SALA_A_GRUPO_CENTROCOSTO[$codigo] ?? null;
            if ($grupo !== null && $grupo !== '') {
                $grupos[] = $grupo;
            }
        }
        $grupos = array_values(array_unique($grupos));
        if ($grupos === []) {
            return [];
        }

        return DB::table('centrocosto')
            ->whereIn('grupo_codigo', $grupos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Salas para formularios / chips (Crown). Sin asignación = todas; con ≥1 = solo esas.
     *
     * @return Collection<int, Sala>
     */
    public static function coleccionParaSelector(): Collection
    {
        if (! self::activo()) {
            return collect();
        }

        $query = Sala::query()
            ->orderBy('codigo')
            ->orderBy('nombre');

        $ids = self::idsDesdeSesion();
        if (count($ids) >= 1) {
            $query->whereIn('id', $ids);
        }

        return $query->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function aplicarFiltro(Builder|QueryBuilder $query, string $column = 'sala_id', bool $incluirNull = false): void
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
