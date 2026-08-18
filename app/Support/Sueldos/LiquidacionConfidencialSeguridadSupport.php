<?php

namespace App\Support\Sueldos;

use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Visibilidad de corridas/recibos: empresas asignadas + nómina confidencial.
 */
final class LiquidacionConfidencialSeguridadSupport
{
    public const PERMISO_VER = 'ver-confidencial-recibo-sueldos';

    public const PERMISO_IMPORTAR = 'importar-liquidacion-confidencial-sueldos';

    public static function puedeVerConfidencial(?Usuario $usuario = null): bool
    {
        if ($usuario === null) {
            return can(self::PERMISO_VER, false);
        }

        return self::usuarioTienePermiso($usuario, self::PERMISO_VER);
    }

    public static function puedeImportarConfidencial(?Usuario $usuario = null): bool
    {
        if ($usuario === null) {
            return can(self::PERMISO_IMPORTAR, false);
        }

        return self::usuarioTienePermiso($usuario, self::PERMISO_IMPORTAR);
    }

    public static function assertLiquidacionVisible(Liquidacion_Sueldos $liq): void
    {
        if (! app(EmpresaRepositoryInterface::class)->empresaIdPermitida((int) $liq->empresa_id)) {
            abort(404);
        }
    }

    /**
     * @return list<int>|null null = sin restricción (acceso total)
     */
    public static function empresaIdsAutorizadas(): ?array
    {
        $ids = collect(Session::get('usuario_empresas'))->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();

        return $ids === [] ? null : $ids;
    }

    public static function aplicarFiltroEmpresaQuery(Builder $query, string $columna = 'empresa_id'): void
    {
        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($query, $columna);
    }

    public static function aplicarVisibilidadRecibos(Builder|Relation $query, ?Usuario $usuario = null): void
    {
        if (self::puedeVerConfidencial($usuario)) {
            return;
        }

        $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNull('confidencial')
                    ->orWhere('confidencial', false);
            })->where(function ($q3) {
                $q3->whereDoesntHave('empleado', function ($eq) {
                    $eq->where('confidencial', true);
                });
            });
        });
    }

    public static function reciboVisibleDeCorrida(int $liquidacionId, int $reciboId): Liquidacion_Recibo_Sueldos
    {
        $query = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->where('id', $reciboId)
            ->with(['empleado', 'liquidacion.empresa', 'detalles']);

        self::aplicarVisibilidadRecibos($query);

        $recibo = $query->first();
        if (! $recibo) {
            abort(404);
        }

        if ($recibo->liquidacion) {
            self::assertLiquidacionVisible($recibo->liquidacion);
        }

        return $recibo;
    }

    /**
     * @return array{cantidad:int,rem:float,norem:float,desc:float,neto:float}
     */
    public static function totalesVisiblesCorrida(Liquidacion_Sueldos $liq): array
    {
        $query = Liquidacion_Recibo_Sueldos::query()
            ->where('liquidacion_id', $liq->id);

        self::aplicarVisibilidadRecibos($query);

        $row = $query->selectRaw(
            'COUNT(*) as cantidad,
             COALESCE(SUM(total_remunerativo),0) as rem,
             COALESCE(SUM(total_no_remunerativo),0) as norem,
             COALESCE(SUM(total_descuentos),0) as descuentos,
             COALESCE(SUM(neto_a_pagar),0) as neto'
        )->first();

        return [
            'cantidad' => (int) ($row->cantidad ?? 0),
            'rem' => (float) ($row->rem ?? 0),
            'norem' => (float) ($row->norem ?? 0),
            'desc' => (float) ($row->descuentos ?? 0),
            'neto' => (float) ($row->neto ?? 0),
        ];
    }

    /**
     * Reemplaza en memoria los totales globales por los permitidos al operador.
     * Evita revelar masa salarial confidencial en index, PDF, Excel o CSV.
     *
     * @param  Collection<int, Liquidacion_Sueldos>|LengthAwarePaginator  $liquidaciones
     */
    public static function aplicarTotalesVisiblesColeccion($liquidaciones): void
    {
        if (self::puedeVerConfidencial()) {
            return;
        }

        $coleccion = $liquidaciones instanceof LengthAwarePaginator
            ? $liquidaciones->getCollection()
            : $liquidaciones;
        $ids = $coleccion->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $query = Liquidacion_Recibo_Sueldos::query()->whereIn('liquidacion_id', $ids);
        self::aplicarVisibilidadRecibos($query);
        $totales = $query
            ->selectRaw(
                'liquidacion_id,
                 COUNT(*) as cantidad,
                 COALESCE(SUM(total_remunerativo),0) as rem,
                 COALESCE(SUM(total_no_remunerativo),0) as norem,
                 COALESCE(SUM(total_descuentos),0) as descuentos,
                 COALESCE(SUM(neto_a_pagar),0) as neto'
            )
            ->groupBy('liquidacion_id')
            ->get()
            ->keyBy('liquidacion_id');

        foreach ($coleccion as $liq) {
            $row = $totales->get($liq->id);
            $liq->cantidad_recibos = (int) ($row->cantidad ?? 0);
            $liq->total_remunerativo = (float) ($row->rem ?? 0);
            $liq->total_no_remunerativo = (float) ($row->norem ?? 0);
            $liq->total_bruto = (float) ($row->rem ?? 0) + (float) ($row->norem ?? 0);
            $liq->total_descuentos = (float) ($row->descuentos ?? 0);
            $liq->total_neto = (float) ($row->neto ?? 0);
        }
    }

    public static function usuarioPuedeImportar(Usuario $usuario, int $empresaId): bool
    {
        if ((bool) ($usuario->suspendido ?? false)) {
            return false;
        }

        if (! self::puedeImportarConfidencial($usuario)) {
            return false;
        }

        $asignadas = $usuario->usuario_empresas()
            ->pluck('empresa.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $asignadas === [] || in_array($empresaId, $asignadas, true);
    }

    private static function usuarioTienePermiso(Usuario $usuario, string $slug): bool
    {
        $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
        if (! $permisoId) {
            return false;
        }

        return DB::table('usuario_rol as ur')
            ->join('permiso_rol as pr', 'pr.rol_id', '=', 'ur.rol_id')
            ->where('ur.usuario_id', $usuario->id)
            ->where('pr.permiso_id', $permisoId)
            ->exists();
    }
}
