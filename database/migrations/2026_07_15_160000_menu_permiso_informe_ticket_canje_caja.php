<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/canjes/informe';

    private const MENU_PADRE_NOMBRE = 'Canjes';

    private const PERMISO_SLUG = 'listar-informe-ticket-canje-caja';

    /** @var list<array{nombre: string, like?: string}> */
    private const ROLES = [
        ['nombre' => 'administrador'],
        ['nombre' => 'Enc-tesorería', 'like' => 'Enc-tesorer%'],
        ['nombre' => 'enc-Tesoreria Operativa', 'like' => 'enc-Tesoreria Operativa%'],
        ['nombre' => 'Ger-Tesoreria', 'like' => 'Ger-Tesorer%'],
        ['nombre' => 'Op-tesoreria', 'like' => 'Op-tesorer%'],
        ['nombre' => 'op-Tesoreria Operativa', 'like' => 'op-Tesoreria Operativa%'],
        ['nombre' => 'Sup-tesoreria', 'like' => 'Sup-tesorer%'],
    ];

    public function up(): void
    {
        $moduloCajaId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Caja')
                    ->orWhere('nombre', 'like', '%Módulo de Caja%');
            })
            ->orderBy('id')
            ->value('id') ?? 104);

        $menuCanjesId = (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($menuCanjesId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $menuCanjesId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $menuCanjesId,
                'nombre' => 'Informe de canjes',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-file-excel',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $menuCanjesId,
                'nombre' => 'Informe de canjes',
                'orden' => $orden,
                'icono' => 'fa-file-excel',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar informe tickets canje caja',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Listar informe tickets canje caja',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
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
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
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
        foreach (self::ROLES as $def) {
            $id = (int) (DB::table('rol')->where('nombre', $def['nombre'])->value('id') ?? 0);
            if ($id === 0 && ! empty($def['like'])) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', $def['like'])->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
