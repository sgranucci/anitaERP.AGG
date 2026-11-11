<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Resultado online deja de ser hoja operativa de Tesorería Crown:
 * pasa bajo Flash (módulo de Guillermo / Enc-tesorería) y queda con roles
 * restringidos — el personal de piso no lo ve ni lo edita.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/tesoreria-crown/resultado-online';

    private const FLASH_URL = '#flash';

    /** @var list<string> */
    private const SLUGS = [
        'listar-tesoreria-crown-resultado-online',
        'crear-tesoreria-crown-resultado-online',
        'editar-tesoreria-crown-resultado-online',
        'actualizar-tesoreria-crown-resultado-online',
        'eliminar-tesoreria-crown-resultado-online',
    ];

    /** Roles con permiso especial (Flash / Enc-tesorería). */
    private const ROLES_ESPECIALES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'Enc-tesoreria',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Enc-contaduría',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = Menu::query()->where('url', self::MENU_URL)->first();
        $flash = Menu::query()->where('url', self::FLASH_URL)->first();
        if (! $menu || ! $flash) {
            return;
        }

        $menu->menu_id = $flash->id;
        $menu->orden = 3;
        $menu->icono = $menu->icono ?: 'fa-line-chart';
        $menu->save();

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_ESPECIALES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menu->id,
                'rol_id' => $rolId,
            ]);
        }

        // Padres visibles para esos roles (Flash + Tesorería Crown).
        foreach (array_filter([(int) $flash->id, (int) $flash->menu_id]) as $padreId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $padreId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso')->whereIn('id', $permisoIds)->update([
                'menu_id' => $menu->id,
                'updated_at' => now(),
            ]);
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            foreach ($permisoIds as $permisoId) {
                foreach ($rolIds as $rolId) {
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
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = Menu::query()->where('url', self::MENU_URL)->first();
        $tesoreria = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $menu || ! $tesoreria) {
            return;
        }

        $menu->menu_id = $tesoreria->id;
        $menu->orden = 11;
        $menu->save();

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $tesoreria->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menu->id,
                'rol_id' => $rolId,
            ]);
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            foreach ($permisoIds as $permisoId) {
                foreach ($rolIds as $rolId) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
