<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración Mercado Libre:
 * - Canónica: Configuración → por módulo → Ventas
 * - Atajo en Facturación Local: solo administrador
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/configuracion-mercadolibre';

    private const MENU_NOMBRE = 'Configuración Mercado Libre';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    private const PADRE_OPERATIVO = '#facturacion-local';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar configuración Mercado Libre', 'slug' => 'editar-configuracion-mercadolibre'],
        ['nombre' => 'Actualizar configuración Mercado Libre', 'slug' => 'actualizar-configuracion-mercadolibre'],
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
                'icono' => 'fa-shopping-bag',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $moduloVentasId,
                'nombre' => self::MENU_NOMBRE,
                'icono' => 'fa-shopping-bag',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_CONFIG)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);

        foreach (self::PERMISOS as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        // Atajo operativo solo administrador
        $padreOp = (int) (DB::table('menu')->where('url', self::PADRE_OPERATIVO)->orderBy('id')->value('id') ?? 0);
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($padreOp > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('url', self::MENU_URL)
                ->where('menu_id', $padreOp)
                ->value('id') ?? 0);
            if ($atajoId <= 0) {
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $padreOp,
                    'nombre' => self::MENU_NOMBRE,
                    'url' => self::MENU_URL,
                    'orden' => 99,
                    'icono' => 'fa-cog',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->asignarRolesMenu($atajoId, [$adminId]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function siguienteOrden(int $padreId): int
    {
        return (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id === 0) {
            $payload['created_at'] = now();

            return (int) DB::table('permiso')->insertGetId($payload);
        }
        DB::table('permiso')->where('id', $id)->update($payload);

        return $id;
    }

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @param list<int> $rolIds */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
