<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tablero de dirección. Menú y permiso solo para el rol administrador.
 * Pantalla genérica del ERP (todos los clientes).
 */
return new class extends Migration
{
    private const PADRE_URL = '#direccion';

    private const PADRE_NOMBRE = 'Dirección';

    private const MENU_URL = 'direccion/tablero';

    private const MENU_NOMBRE = 'Tablero';

    /** @var list<string> */
    private const ROLES = ['administrador'];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar tablero de dirección', 'slug' => 'listar-tablero-direccion'],
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('id') ?? 0);
        if ($padreId <= 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
            $padreId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::PADRE_NOMBRE,
                'url' => self::PADRE_URL,
                'menu_id' => 0,
                'orden' => $orden,
                'icono' => 'fa-compass',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'orden' => 1,
                'icono' => 'fa-chart-line',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => 1,
                'icono' => 'fa-chart-line',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ([$padreId, $menuId] as $nodoId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $nodoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $nodoId, 'rol_id' => $rolId]);
                }
            }
        }

        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $perm['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            } else {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('id') ?? 0);
        if ($padreId > 0 && ! DB::table('menu')->where('menu_id', $padreId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
