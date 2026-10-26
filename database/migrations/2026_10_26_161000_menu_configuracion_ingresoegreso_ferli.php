<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración de ingresos y egresos (cobranza de tarjeta):
 * - Canónica: Configuración → por módulo → Caja
 * - Atajo en Módulo de Caja: solo administrador
 *
 * Solo Ferli.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/ingresoegreso/configuracion';

    private const MENU_NOMBRE = 'Configuración de ingresos y egresos';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-caja';

    private const MENU_IE_URL = 'caja/ingresoegreso';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar configuración ingresos y egresos', 'slug' => 'editar-configuracion-ingresoegreso'],
        ['nombre' => 'Actualizar configuración ingresos y egresos', 'slug' => 'actualizar-configuracion-ingresoegreso'],
    ];

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
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
        if ($grupoId <= 0) {
            return;
        }

        $moduloCajaId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($moduloCajaId <= 0) {
            $moduloCajaId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $grupoId,
                'nombre' => 'Caja',
                'url' => self::MODULO_URL,
                'orden' => $this->siguienteOrden($grupoId),
                'icono' => 'fa-building',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->orderBy('id')->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloCajaId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $this->siguienteOrden($moduloCajaId),
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $moduloCajaId,
                'nombre' => self::MENU_NOMBRE,
                'icono' => 'fa-cog',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->reemplazarMenuRoles($menuId, $rolIds);
        $this->asignarRolesMenu($moduloCajaId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $configRootId = (int) (DB::table('menu')->where('nombre', 'Configuración')->where('menu_id', 0)->value('id')
            ?? DB::table('menu')->where('nombre', 'Configuración')->whereNull('menu_id')->value('id')
            ?? 0);
        if ($configRootId > 0) {
            $this->asignarRolesMenu($configRootId, $rolIds);
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $permiso['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $permiso['nombre'],
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

        $padreOpId = $this->padreOperativoIngresoEgreso();
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

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function padreOperativoIngresoEgreso(): int
    {
        $filas = DB::table('menu as m')
            ->join('menu as p', 'p.id', '=', 'm.menu_id')
            ->where('m.url', self::MENU_IE_URL)
            ->get(['m.menu_id', 'p.nombre', 'p.url']);

        foreach ($filas as $fila) {
            if ((string) $fila->nombre === 'Módulo de Caja') {
                return (int) $fila->menu_id;
            }
        }

        return (int) ($filas->first()->menu_id ?? 0);
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
