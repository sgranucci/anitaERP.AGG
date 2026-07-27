<?php

namespace App\Support\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alcance de listado y acceso a solicitudes de pago:
 *
 * - listar-solicitud-pago: empresas asignadas + centrocosto_id de cabecera = CC del usuario.
 * - listar-todas-solicitud-pago: sin restricción de CC (impuestos / supervisión),
 *   salvo que elijan alcance "mi_cc" en el index (toggle).
 *
 * El CC de cabecera se fija al cargar (usuario en sesión) y no se edita en el CRUD.
 */
final class SolicitudpagoVisibilidadSupport
{
    public const PERMISO_VER_TODAS = 'listar-todas-solicitud-pago';

    public const ALCANCE_TODAS = 'todas';

    public const ALCANCE_MI_CC = 'mi_cc';

    public static function puedeVerTodasSinRestriccion(): bool
    {
        return can(self::PERMISO_VER_TODAS, false);
    }

    public static function centrocostoUsuario(): ?int
    {
        $id = (int) (Auth::user()->centrocosto_id ?? Session::get('centrocosto_id') ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return list<int> */
    public static function empresaIdsAsignadas(): array
    {
        return collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     * @param  bool  $forzarAlcanceCentrocosto  true = aplicar filtro de CC aunque tenga listar-todas
     */
    public static function aplicarFiltroListado(
        Builder $query,
        string $columnaEmpresa = 'solicitudpago.empresa_id',
        string $columnaCentrocosto = 'solicitudpago.centrocosto_id',
        bool $forzarAlcanceCentrocosto = false
    ): void {
        if (self::puedeVerTodasSinRestriccion() && ! $forzarAlcanceCentrocosto) {
            return;
        }

        self::aplicarRestriccionEmpresaYCentrocosto($query, $columnaEmpresa, $columnaCentrocosto);
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     */
    public static function aplicarRestriccionEmpresaYCentrocosto(
        Builder $query,
        string $columnaEmpresa = 'solicitudpago.empresa_id',
        string $columnaCentrocosto = 'solicitudpago.centrocosto_id'
    ): void {
        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn($columnaEmpresa, $empresas);
        }

        $ccId = self::centrocostoUsuario();
        if ($ccId !== null) {
            $query->where($columnaCentrocosto, $ccId);

            return;
        }

        // Sin CC en el usuario: solo las que cargó/modificó él.
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where('solicitudpago.usuario_umod_id', $usuarioId);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public static function forzarAlcanceCentrocostoDesdeFiltros(array $filtros): bool
    {
        return self::puedeVerTodasSinRestriccion()
            && ($filtros['alcance'] ?? self::ALCANCE_TODAS) === self::ALCANCE_MI_CC;
    }

    public static function solicitudAccesiblePorId(int $solicitudpagoId): bool
    {
        if ($solicitudpagoId <= 0) {
            return false;
        }

        if (self::puedeVerTodasSinRestriccion()) {
            return Solicitudpago::query()->whereKey($solicitudpagoId)->exists();
        }

        $query = Solicitudpago::query()->where('solicitudpago.id', $solicitudpagoId);
        self::aplicarFiltroListado($query);

        return $query->exists();
    }
}
