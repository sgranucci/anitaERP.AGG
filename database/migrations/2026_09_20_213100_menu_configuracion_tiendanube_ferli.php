<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración Tiendanube:
 * - Canónica: Configuración → por módulo → Ventas
 * - Atajo en Facturación Local: solo administrador
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/configuracion-tiendanube';

    private const MENU_NOMBRE = 'Configuración Tiendanube';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    private const PADRE_OPERATIVO = '#facturacion-local';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar configuración Tiendanube', 'slug' => 'editar-configuracion-tiendanube'],
        ['nombre' => 'Actualizar configuración Tiendanube', 'slug' => 'actualizar-configuracion-tiendanube'],
    ];

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-admin',
        'Enc-sistemas',
        'Ger-administracion',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('menu') || ! DB::getSchemaBuilder()->hasTable('permiso')) {
            return;
        }

        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloVentasId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($grupoId <= 0) {
            return;
        }
        if ($moduloVentasId <= 0) {
            $moduloVentasId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $grupoId,
                'nombre' => 'Ventas',
                'url' => self::MODULO_URL,
                'orden' => $this->siguienteOrden($grupoId),
                'icono' => 'fa-shopping-cart',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->orderBy('id')->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloVentasId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $this->siguienteOrden($moduloVentasId),
                'icono' => 'fa-cloud',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $moduloVentasId,
                'nombre' => self::MENU_NOMBRE,
                'icono' => 'fa-cloud',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->reemplazarMenuRoles($menuId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $configRootId = (int) (DB::table('menu')->where('nombre', 'Configuración')->whereNull('menu_id')->value('id')
            ?? DB::table('menu')->where('url', '#')->where('nombre', 'Configuración')->value('id')
            ?? 0);
        if ($configRootId > 0) {
            $this->asignarRolesMenu($configRootId, $rolIds);
        }

        foreach (self::PERMISOS as $p) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $p['slug'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $p['nombre'],
                    'slug' => $p['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $p['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        // Atajo bajo Facturación Local: solo administrador
        $padreOpId = (int) (DB::table('menu')->where('url', self::PADRE_OPERATIVO)->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($padreOpId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $padreOpId)
                ->where('url', self::MENU_URL)
                ->where('id', '!=', $menuId)
                ->value('id') ?? 0);
            if ($atajoId <= 0) {
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $padreOpId,
                    'nombre' => self::MENU_NOMBRE,
                    'url' => self::MENU_URL,
                    'orden' => $this->siguienteOrden($padreOpId),
                    'icono' => 'fa-cog',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->reemplazarMenuRoles($atajoId, [$adminId]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('menu')) {
            return;
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        foreach ($menuIds as $menuId) {
            $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        foreach (self::PERMISOS as $p) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $p['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<int> $rolIds */
    private function reemplazarMenuRoles(int $menuId, array $rolIds): void
    {
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    private function siguienteOrden(int $padreId): int
    {
        return (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
    }
};
