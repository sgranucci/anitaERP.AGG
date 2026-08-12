<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cierra el submenú de cuentas a pagar:
 *
 * - Propuesta de pagos pasa dentro de «Cuentas a pagar» (antes colgaba de Compras).
 * - Los roles de pagos ya podían confirmar, anular y conciliar órdenes de pago pero no
 *   listarlas ni crearlas: se completan listar/crear/editar/actualizar y el ítem de menú.
 */
return new class extends Migration
{
    private const CONTENEDOR = 'Cuentas a pagar';

    private const MENU_PROPUESTA = 'compras/propuesta-pago';

    private const MENU_PAGOPROVEEDOR = 'compras/pagoproveedor';

    /** @var list<string> */
    private const PERMISOS_PAGOPROVEEDOR = [
        'listar-pagoproveedor',
        'crear-pagoproveedor',
        'editar-pagoproveedor',
        'actualizar-pagoproveedor',
    ];

    public function up(): void
    {
        $contenedorId = $this->contenedorId();

        if ($contenedorId > 0) {
            DB::table('menu')->where('url', self::MENU_PROPUESTA)->update([
                'menu_id' => $contenedorId,
                'nombre' => 'Propuesta de pagos',
                'orden' => 4,
                'updated_at' => now(),
            ]);

            $reportesId = (int) (DB::table('menu')
                ->where('menu_id', $contenedorId)
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->value('id') ?? 0);

            if ($reportesId > 0) {
                DB::table('menu')->where('id', $reportesId)->update(['orden' => 5, 'updated_at' => now()]);
            }
        }

        $rolIds = $this->rolesPagos();
        if ($rolIds !== []) {
            foreach (self::PERMISOS_PAGOPROVEEDOR as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId === 0) {
                    continue;
                }

                foreach ($rolIds as $rolId) {
                    $existe = DB::table('permiso_rol')
                        ->where('permiso_id', $permisoId)
                        ->where('rol_id', $rolId)
                        ->exists();

                    if (! $existe) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }

            $menuPagoId = (int) (DB::table('menu')->where('url', self::MENU_PAGOPROVEEDOR)->value('id') ?? 0);
            if ($menuPagoId > 0) {
                foreach ($rolIds as $rolId) {
                    $existe = DB::table('menu_rol')
                        ->where('menu_id', $menuPagoId)
                        ->where('rol_id', $rolId)
                        ->exists();

                    if (! $existe) {
                        DB::table('menu_rol')->insert(['menu_id' => $menuPagoId, 'rol_id' => $rolId]);
                    }
                }
            }
        }

        if ($contenedorId > 0) {
            $this->sincronizarRolesContenedor($contenedorId);
        }

        $this->invalidarCachePermisos();
    }

    public function down(): void
    {
        $comprasId = (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('menu_id') ?? 0);
        if ($comprasId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $comprasId)->max('orden') ?? 0) + 1;
            DB::table('menu')->where('url', self::MENU_PROPUESTA)->update([
                'menu_id' => $comprasId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->rolesPagos(false);
        if ($rolIds === []) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_PAGOPROVEEDOR)
            ->pluck('id')
            ->all();

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        $menuPagoId = (int) (DB::table('menu')->where('url', self::MENU_PAGOPROVEEDOR)->value('id') ?? 0);
        if ($menuPagoId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuPagoId)->whereIn('rol_id', $rolIds)->delete();
        }

        $this->invalidarCachePermisos();
    }

    private function contenedorId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::CONTENEDOR)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * Roles de pagos; `administrador` solo se incluye al asignar (en el rollback no se toca).
     *
     * @return list<int>
     */
    private function rolesPagos(bool $conAdministrador = true): array
    {
        $query = DB::table('rol')->where('nombre', 'like', '%pagos%');

        if ($conAdministrador) {
            $query->orWhere('nombre', 'administrador');
        }

        return $query->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * El contenedor necesita los roles de sus descendientes: el aside filtra por `menu_rol`.
     */
    private function sincronizarRolesContenedor(int $contenedorId): void
    {
        $descendientes = [];
        $pendientes = [$contenedorId];

        while ($pendientes !== []) {
            $hijos = DB::table('menu')
                ->whereIn('menu_id', $pendientes)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $nuevos = array_values(array_diff($hijos, $descendientes));
            if ($nuevos === []) {
                break;
            }

            $descendientes = array_merge($descendientes, $nuevos);
            $pendientes = $nuevos;
        }

        if ($descendientes === []) {
            return;
        }

        $rolIds = DB::table('menu_rol')
            ->whereIn('menu_id', $descendientes)
            ->distinct()
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $contenedorId)
                ->where('rol_id', $rolId)
                ->exists();

            if (! $existe) {
                DB::table('menu_rol')->insert(['menu_id' => $contenedorId, 'rol_id' => $rolId]);
            }
        }
    }

    /**
     * `can()` cachea los permisos por rol: sin invalidar, los roles siguen sin ver el ítem.
     */
    private function invalidarCachePermisos(): void
    {
        try {
            cache()->tags('Permiso')->flush();
        } catch (\Throwable) {
            cache()->flush();
        }
    }
};
