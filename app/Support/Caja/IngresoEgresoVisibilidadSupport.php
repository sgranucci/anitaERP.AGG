<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alcance del listado y acceso a ingresos/egresos de caja (módulo finanzas / tesorería).
 *
 * Las cobranzas POS (gastronomía, estacionamiento, etc.) viven en caja/cobranza y se
 * excluyen siempre de este ABM aunque compartan tabla caja_movimiento.
 *
 * Jerarquía:
 * - listar-todos-ingresos-egresos-caja: sin restricción de alcance
 * - usuario-ingresos-egresos-rol: movimientos cargados por usuarios de su rol
 *   (más chico que el centro de costo; en Administración el CC lo comparten muchos roles)
 * - usuario-ingresos-egresos-centrocosto: movimientos cargados por usuarios de su CC
 * - usuario-ingresos-egresos-oc-centrocosto: además, órdenes de pago de compras
 *   cuya orden de compra es de su centro de costo (no mira quién las cargó)
 * - solo listar: únicamente los propios
 */
final class IngresoEgresoVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-ingresos-egresos-caja';

    public const PERMISO_ROL = 'usuario-ingresos-egresos-rol';

    public const PERMISO_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    public const PERMISO_OC_CENTROCOSTO = 'usuario-ingresos-egresos-oc-centrocosto';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    public static function puedeVerRol(): bool
    {
        return can(self::PERMISO_ROL, false);
    }

    public static function puedeVerCentrocosto(): bool
    {
        return can(self::PERMISO_CENTROCOSTO, false);
    }

    public static function puedeVerOcDeSuCentrocosto(): bool
    {
        return can(self::PERMISO_OC_CENTROCOSTO, false);
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        if (! self::puedeVerCentrocosto()) {
            return null;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function tieneRestriccionPorAlcance(): bool
    {
        return ! self::puedeVerTodos();
    }

    /** @deprecated Use tieneRestriccionPorAlcance() */
    public static function tieneRestriccionPorCentrocosto(): bool
    {
        return self::tieneRestriccionPorAlcance();
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        if (! self::tieneRestriccionPorAlcance()) {
            return null;
        }

        return self::conEtiquetaOrdenesCompra(self::etiquetaAlcancePorQuienCargo());
    }

    /**
     * Cobranzas POS no pertenecen al ABM de ingresos/egresos.
     *
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function excluirCobranzas(Builder $query, string $alias = 'caja_movimiento'): void
    {
        $query->where(function ($q) use ($alias) {
            $q->whereNull("{$alias}.cobranza_id")
                ->orWhere("{$alias}.cobranza_id", 0);
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicarFiltroAlcance(Builder $query, string $alias = 'caja_movimiento'): void
    {
        self::excluirCobranzas($query, $alias);

        if (self::puedeVerTodos()) {
            return;
        }

        $restricciones = self::restriccionesDeAlcance($alias);
        if ($restricciones === []) {
            self::restringirAlUsuarioActual($query, $alias);

            return;
        }

        $query->where(function ($q) use ($restricciones) {
            foreach ($restricciones as $i => $aplicar) {
                $q->{$i === 0 ? 'where' : 'orWhere'}(function ($inner) use ($aplicar) {
                    $aplicar($inner);
                });
            }
        });
    }

    /**
     * Órdenes de pago cuya factura está imputada a una orden de compra del centro de costo.
     *
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicarFiltroOcDelCentrocosto(Builder $query, string $alias, int $centrocostoId): void
    {
        if ($centrocostoId <= 0) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($sub) use ($alias, $centrocostoId) {
            $sub->select(DB::raw('1'))
                ->from('pagoproveedor as pp_oc_alc')
                ->join('pagoproveedor_comprobante as ppc_oc_alc', 'ppc_oc_alc.pagoproveedor_id', '=', 'pp_oc_alc.id')
                ->join('proveedor_cuentacorriente as pcc_oc_alc', 'pcc_oc_alc.id', '=', 'ppc_oc_alc.proveedor_cuentacorriente_id')
                ->join('comprobante_proveedor as cp_oc_alc', 'cp_oc_alc.id', '=', 'pcc_oc_alc.comprobante_proveedor_id')
                ->join('ordencompra as oc_alc', 'oc_alc.id', '=', 'cp_oc_alc.ordencompra_id')
                ->whereColumn('pp_oc_alc.id', $alias.'.pagoproveedor_id')
                ->where('oc_alc.centrocosto_id', $centrocostoId);
        });
    }

    /**
     * Rol activo de la sesión. Si todavía no eligió uno, los roles de la sesión o de su ficha.
     *
     * @return list<int>
     */
    public static function rolIdsFiltroUsuario(): array
    {
        if (self::puedeVerTodos() || ! self::puedeVerRol()) {
            return [];
        }

        $sesion = (int) (session('rol_id') ?? 0);
        if ($sesion > 0) {
            return [$sesion];
        }

        $ids = [];
        foreach ((array) session('roles', []) as $rol) {
            $id = (int) (is_array($rol) ? ($rol['id'] ?? 0) : 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        $usuario = Auth::user();
        if ($usuario && method_exists($usuario, 'roles')) {
            foreach ($usuario->roles()->pluck('rol.id') as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private static function etiquetaAlcancePorQuienCargo(): string
    {
        if (self::puedeVerRol()) {
            $rolIds = self::rolIdsFiltroUsuario();
            if ($rolIds !== []) {
                $nombres = DB::table('rol')
                    ->whereIn('id', $rolIds)
                    ->orderBy('nombre')
                    ->pluck('nombre')
                    ->map(fn ($nombre) => trim((string) $nombre))
                    ->filter(fn (string $nombre) => $nombre !== '')
                    ->values()
                    ->all();

                if ($nombres !== []) {
                    return 'Movimientos de los usuarios del rol '.implode(', ', $nombres);
                }

                return 'Movimientos de los usuarios de su rol';
            }

            return 'Solo movimientos cargados por usted';
        }

        if (self::puedeVerCentrocosto()) {
            $centrocostoId = self::centrocostoFiltroUsuario();
            if ($centrocostoId === null) {
                return 'Solo movimientos cargados por usted (sin centro de costo asignado)';
            }

            $centrocosto = Centrocosto::query()->find($centrocostoId);
            if ($centrocosto === null) {
                return 'Centro de costo #'.$centrocostoId;
            }

            return trim($centrocosto->codigo.' — '.$centrocosto->nombre);
        }

        return 'Solo movimientos cargados por usted';
    }

    private static function conEtiquetaOrdenesCompra(string $base): string
    {
        $oc = self::etiquetaOrdenesCompraCentrocosto();
        if ($oc === null) {
            return $base;
        }

        if ($base === 'Solo movimientos cargados por usted') {
            return $oc;
        }

        return $base.' · '.$oc;
    }

    private static function etiquetaOrdenesCompraCentrocosto(): ?string
    {
        if (! self::puedeVerOcDeSuCentrocosto()) {
            return null;
        }

        $centrocostoId = self::centrocostoDelUsuario();
        if ($centrocostoId === null) {
            return null;
        }

        $centrocosto = Centrocosto::query()->find($centrocostoId);
        $nombre = $centrocosto === null
            ? 'Centro de costo #'.$centrocostoId
            : trim($centrocosto->codigo.' — '.$centrocosto->nombre);

        return 'Órdenes de pago de compras del centro de costo '.$nombre;
    }

    /**
     * @return list<\Closure(Builder<\App\Models\Caja\Caja_Movimiento>): void>
     */
    private static function restriccionesDeAlcance(string $alias): array
    {
        $restricciones = [];
        $tieneAlcancePorQuienCargo = false;

        if (self::puedeVerRol()) {
            $rolIds = self::rolIdsFiltroUsuario();
            if ($rolIds !== []) {
                $usuarioId = (int) (Auth::id() ?? 0);
                $restricciones[] = function ($q) use ($alias, $rolIds, $usuarioId) {
                    $q->whereIn("{$alias}.usuario_id", function ($sub) use ($rolIds) {
                        $sub->from('usuario_rol')
                            ->whereIn('rol_id', $rolIds)
                            ->select('usuario_id');
                    });
                    if ($usuarioId > 0) {
                        $q->orWhere("{$alias}.usuario_id", $usuarioId);
                    }
                };
                $tieneAlcancePorQuienCargo = true;
            }
        } elseif (self::puedeVerCentrocosto()) {
            $centrocostoId = self::centrocostoFiltroUsuario();
            if ($centrocostoId !== null) {
                $restricciones[] = function ($q) use ($alias, $centrocostoId) {
                    $q->whereIn("{$alias}.usuario_id", function ($sub) use ($centrocostoId) {
                        $sub->from('usuario')
                            ->where('centrocosto_id', $centrocostoId)
                            ->select('id');
                    });
                };
                $tieneAlcancePorQuienCargo = true;
            }
        }

        $centrocostoOc = self::puedeVerOcDeSuCentrocosto() ? self::centrocostoDelUsuario() : null;
        if ($centrocostoOc !== null) {
            $restricciones[] = function ($q) use ($alias, $centrocostoOc) {
                self::aplicarFiltroOcDelCentrocosto($q, $alias, $centrocostoOc);
            };
            if (! $tieneAlcancePorQuienCargo) {
                $restricciones[] = function ($q) use ($alias) {
                    self::restringirAlUsuarioActual($q, $alias);
                };
            }
        }

        return $restricciones;
    }

    private static function centrocostoDelUsuario(): ?int
    {
        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    private static function restringirAlUsuarioActual(Builder $query, string $alias): void
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where("{$alias}.usuario_id", $usuarioId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public static function movimientoAccesiblePorId(int $movimientoId): bool
    {
        if ($movimientoId <= 0) {
            return false;
        }

        $query = Caja_Movimiento::query()->where('caja_movimiento.id', $movimientoId);
        self::aplicarFiltroAlcance($query);

        return $query->exists();
    }

    public static function abortSiNoAccesible(int $movimientoId): void
    {
        if (! self::movimientoAccesiblePorId($movimientoId)) {
            abort(404);
        }
    }
}
