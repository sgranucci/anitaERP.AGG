<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: «Conceptos de venta» estaba bajo Abonos (contratos).
 * En Ferli se busca en Tablas de ventas, igual que el resto de maestros.
 * Oficina opera cheques y no veía Abonos, así que tampoco podía cargar
 * la cuenta de NDR-CHEQUE.
 *
 * Solo Ferli: no toca AGG ni otros clientes.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/concepto-venta';

    private const MENU_NOMBRE = 'Conceptos de venta';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar conceptos de venta', 'slug' => 'listar-conceptos-venta'],
        ['nombre' => 'Crear conceptos de venta', 'slug' => 'crear-conceptos-venta'],
        ['nombre' => 'Editar conceptos de venta', 'slug' => 'editar-conceptos-venta'],
        ['nombre' => 'Actualizar conceptos de venta', 'slug' => 'actualizar-conceptos-venta'],
        ['nombre' => 'Borrar conceptos de venta', 'slug' => 'borrar-conceptos-venta'],
    ];

    /** Mismos roles que ya ven Tablas de ventas. */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Admin-ventas',
        'Ventas',
        'Oficina',
    ];

    /** Rol que no tenía el ítem antes de esta migración. */
    private const ROL_NUEVO = 'Oficina';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $parentId = (int) (DB::table('menu')
            ->where('nombre', 'Tablas de ventas')
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($parentId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($parentId, $rolIds);

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Ventas')
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        $abonosId = 0;
        if ($moduloId > 0) {
            $abonosId = (int) (DB::table('menu')
                ->where('menu_id', $moduloId)
                ->where('nombre', 'Abonos')
                ->where('url', '#')
                ->value('id') ?? 0);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0 && $abonosId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $abonosId,
                'orden' => 3,
                'updated_at' => now(),
            ]);
        }

        $rolOficina = (int) (DB::table('rol')->where('nombre', self::ROL_NUEVO)->value('id') ?? 0);
        if ($menuId > 0 && $rolOficina > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolOficina)->delete();
        }

        if ($rolOficina > 0) {
            $permisoIds = DB::table('permiso')
                ->whereIn('slug', array_column(self::PERMISOS, 'slug'))
                ->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')
                    ->whereIn('permiso_id', $permisoIds->all())
                    ->where('rol_id', $rolOficina)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
