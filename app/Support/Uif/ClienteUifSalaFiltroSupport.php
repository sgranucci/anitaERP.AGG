<?php

namespace App\Support\Uif;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro multi-sala UIF (migración a cliente único con premios por sala):
 *
 * - Clientes: aparecen en una sala si tienen premios en esa sala;
 *   si no tienen ningún premio, vale el origen de carga (`anita_origen`).
 * - Premios: se filtran por `cliente_premio_uif.sala_id` / empresa de la sala.
 */
final class ClienteUifSalaFiltroSupport
{
    /**
     * @param  Builder<\App\Models\Uif\Cliente_Uif>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarEnClientes(Builder $query, array $filtros): void
    {
        $salaIds = self::salaIdsDesdeFiltros($filtros);
        if ($salaIds === null) {
            return;
        }

        if ($salaIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $origenes = self::origenesDesdeSalaIds($salaIds);

        $query->where(function ($q) use ($salaIds, $origenes) {
            $q->whereExists(function ($e) use ($salaIds) {
                $e->selectRaw('1')
                    ->from('cliente_premio_uif')
                    ->whereColumn('cliente_premio_uif.cliente_uif_id', 'cliente_uif.id')
                    ->whereIn('cliente_premio_uif.sala_id', $salaIds);
            })->orWhere(function ($q2) use ($origenes) {
                $q2->whereIn('cliente_uif.anita_origen', $origenes)
                    ->whereNotExists(function ($e) {
                        $e->selectRaw('1')
                            ->from('cliente_premio_uif')
                            ->whereColumn('cliente_premio_uif.cliente_uif_id', 'cliente_uif.id');
                    });
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Uif\Cliente_Premio_Uif>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarEnPremios(Builder $query, array $filtros): void
    {
        $salaIds = self::salaIdsDesdeFiltros($filtros);
        if ($salaIds === null) {
            return;
        }

        if ($salaIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('cliente_premio_uif.sala_id', $salaIds);
    }

    /**
     * null = sin filtro de sala (todas las permitidas / acceso total).
     * list = salas a incluir (puede quedar vacío → 0 resultados).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<int>|null
     */
    public static function salaIdsDesdeFiltros(array $filtros): ?array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if (($filtros['empresa_scope'] ?? '') === 'una' && $empresaId > 0) {
            $salaId = self::salaIdDesdeEmpresaId($empresaId);
            if ($salaId === null) {
                $origen = (string) ($filtros['anita_origen'] ?? '');
                $salaId = $origen !== '' ? ClienteUifArchivoStorage::salaId($origen) : null;
            }

            return $salaId !== null && $salaId > 0 ? [$salaId] : [];
        }

        if (($filtros['anita_origen'] ?? '') !== '') {
            $salaId = ClienteUifArchivoStorage::salaId((string) $filtros['anita_origen']);

            return $salaId > 0 ? [$salaId] : [];
        }

        $permitidos = $filtros['origenes_permitidos'] ?? null;
        if (! is_array($permitidos) || $permitidos === []) {
            return null;
        }

        $todosOrigenes = array_map('strval', array_keys(config('uif.anita_origenes', [])));
        $cruzados = array_values(array_intersect($permitidos, $todosOrigenes));
        if ($cruzados === [] || count($cruzados) >= count($todosOrigenes)) {
            return null;
        }

        $salaIds = [];
        foreach ($cruzados as $origen) {
            $sid = ClienteUifArchivoStorage::salaId((string) $origen);
            if ($sid > 0) {
                $salaIds[] = $sid;
            }
        }

        return array_values(array_unique($salaIds));
    }

    public static function salaIdDesdeEmpresaId(int $empresaId): ?int
    {
        if ($empresaId <= 0) {
            return null;
        }

        foreach (config('uif.anita_origenes', []) as $cfg) {
            if ((int) ($cfg['empresa_id'] ?? 0) === $empresaId) {
                $salaId = (int) ($cfg['sala_id'] ?? 0);

                return $salaId > 0 ? $salaId : null;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $salaIds
     * @return list<string>
     */
    public static function origenesDesdeSalaIds(array $salaIds): array
    {
        $origenes = [];
        foreach (config('uif.anita_origenes', []) as $origen => $cfg) {
            if (in_array((int) ($cfg['sala_id'] ?? 0), $salaIds, true)) {
                $origenes[] = (string) $origen;
            }
        }

        return $origenes !== [] ? $origenes : ['__ninguno__'];
    }
}
