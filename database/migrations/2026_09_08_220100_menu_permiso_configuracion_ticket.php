<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ticket/configuracion';

    private const MENU_NOMBRE = 'Configuración';

    private const MENU_ADM_URL = 'ticket/administracion_ticket';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        [
            'slug' => 'listar-configuracion-ticket',
            'nombre' => 'Listar configuración de tickets',
        ],
        [
            'slug' => 'actualizar-configuracion-ticket',
            'nombre' => 'Actualizar configuración de tickets',
        ],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::MENU_ADM_URL)->value('menu_id') ?? 0);
        if ($padreId === 0) {
            $padreId = (int) (DB::table('menu')
                ->where('menu_id', 0)
                ->where(function ($q) {
                    $q->where('nombre', 'Módulo de Tickets')
                        ->orWhere('nombre', 'like', '%Módulo de Tickets%');
                })
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'icono' => 'fa-cog',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as $permisoDef) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permisoDef['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permisoDef['nombre'],
                    'slug' => $permisoDef['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $permisoDef['nombre'],
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

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $permisoDef) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permisoDef['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
