<?php

namespace App\Support\Contable;

use App\Models\Contable\BienUso;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alcance del ABM bienes de uso según centrocosto contable (maestro centrocosto).
 */
final class BienUsoVisibilidadSupport
{
    /**
     * @return list<int>|null null = sin restricción
     */
    public static function centrocostoIdsPermitidos(): ?array
    {
        $codigos = self::centrocostoCodigosPermitidos();
        if ($codigos === null) {
            return null;
        }

        return self::idsDesdeCodigos($codigos);
    }

    /**
     * @return list<string>|null
     */
    public static function centrocostoCodigosPermitidos(): ?array
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return null;
        }

        if (can(config('bien_uso.permiso_ver_todos'), false)) {
            return null;
        }

        $rolesSinRestriccion = config('bien_uso.roles_sin_restriccion', []);
        $rolNombre = (string) session()->get('rol_nombre', '');
        if ($rolNombre !== '' && in_array($rolNombre, $rolesSinRestriccion, true)) {
            return null;
        }

        $codigoCc = self::codigoCentrocostoSesion();
        if ($codigoCc === null || $codigoCc === '') {
            return null;
        }

        $mapa = config('bien_uso.rol_cc_ve_centrocosto_codigos', []);
        $permitidos = $mapa[$codigoCc] ?? null;
        if ($permitidos === null || $permitidos === []) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('strval', $permitidos))));
    }

    public static function tieneRestriccionPorPerfil(): bool
    {
        return self::centrocostoIdsPermitidos() !== null;
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    public static function aplicarScope(Builder $query, ?int $filtroCentrocostoId = null): void
    {
        $permitidos = self::centrocostoIdsPermitidos();

        if ($filtroCentrocostoId !== null && $filtroCentrocostoId > 0) {
            if ($permitidos !== null && ! in_array($filtroCentrocostoId, $permitidos, true)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->where('bien_uso.centrocosto_id', $filtroCentrocostoId);

            return;
        }

        if ($permitidos !== null) {
            $query->whereIn('bien_uso.centrocosto_id', $permitidos);
        }
    }

    public static function puedeAcceder(?int $centrocostoId): bool
    {
        $permitidos = self::centrocostoIdsPermitidos();
        if ($permitidos === null) {
            return true;
        }

        return in_array((int) $centrocostoId, $permitidos, true);
    }

    public static function puedeAccederRegistro(BienUso $bien): bool
    {
        return self::puedeAcceder((int) $bien->centrocosto_id);
    }

    public static function abortSiNoPuedeAccederRegistro(BienUso $bien): void
    {
        if (! self::puedeAccederRegistro($bien)) {
            abort(403, 'No tiene permiso para acceder a este bien de uso.');
        }
    }

    /**
     * @return Collection<int, Centrocosto>
     */
    public static function opcionesCentrocostoAbm(): Collection
    {
        $codigosAbm = config('bien_uso.centrocosto_codigos_abm', []);
        $query = Centrocosto::query()->orderBy('codigo');

        if ($codigosAbm !== []) {
            $query->whereIn('codigo', $codigosAbm);
        }

        $opciones = $query->get();
        $permitidos = self::centrocostoIdsPermitidos();

        if ($permitidos === null) {
            return $opciones;
        }

        return $opciones->whereIn('id', $permitidos)->values();
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        $codigos = self::centrocostoCodigosPermitidos();
        if ($codigos === null) {
            return null;
        }

        $nombres = Centrocosto::query()
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->get()
            ->map(fn (Centrocosto $cc) => trim($cc->codigo.' — '.$cc->nombre))
            ->all();

        return implode(', ', $nombres);
    }

    /**
     * @param  list<string>  $codigos
     * @return list<int>
     */
    private static function idsDesdeCodigos(array $codigos): array
    {
        return Centrocosto::query()
            ->whereIn('codigo', $codigos)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private static function codigoCentrocostoSesion(): ?string
    {
        $rolId = (int) session()->get('rol_id', 0);
        if ($rolId > 0) {
            $codigo = DB::table('rol')
                ->join('centrocosto', 'centrocosto.id', '=', 'rol.centrocosto_id')
                ->where('rol.id', $rolId)
                ->value('centrocosto.codigo');
            if (is_string($codigo) && $codigo !== '') {
                return $codigo;
            }
        }

        $centro = session()->get('centrocosto');
        if (is_object($centro) && isset($centro->codigo) && $centro->codigo !== '') {
            return (string) $centro->codigo;
        }

        $ccId = (int) session()->get('centrocosto_id', 0);
        if ($ccId > 0) {
            $codigo = DB::table('centrocosto')->where('id', $ccId)->value('codigo');

            return is_string($codigo) && $codigo !== '' ? $codigo : null;
        }

        return null;
    }
}
