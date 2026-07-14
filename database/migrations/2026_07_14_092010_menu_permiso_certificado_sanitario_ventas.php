<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/certificado-sanitario';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Despacho',
        'Facturacion',
        'Enc-contaduría',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar certificado sanitario', 'slug' => 'listar-certificado-sanitario'],
        ['nombre' => 'Crear certificado sanitario', 'slug' => 'crear-certificado-sanitario'],
        ['nombre' => 'Editar certificado sanitario', 'slug' => 'editar-certificado-sanitario'],
        ['nombre' => 'Actualizar certificado sanitario', 'slug' => 'actualizar-certificado-sanitario'],
        ['nombre' => 'Borrar certificado sanitario', 'slug' => 'borrar-certificado-sanitario'],
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/pedido')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('nombre', 'Módulo de Ventas')->where('url', '#')->value('id') ?? 51);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = $menuId > 0
            ? (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Certificado sanitario SENASA',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Certificado sanitario SENASA',
                'orden' => $orden > 0 ? $orden : (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1,
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        $rolIds = $this->resolverRolIds();

        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
        DB::table('menu')->where('id', $menuId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
