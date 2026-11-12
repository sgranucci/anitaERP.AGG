<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Informe propinas por casino (Anita l-propinaxcas.c) bajo Tesorería Crown.
 * Roles: administrador / tesorería + Capital Humano.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/tesoreria-crown/propina-casino';

    private const PADRE_URL = '#tesoreria-crown';

    private const PERMISO_SLUG = 'listar-tesoreria-crown-propina-casino';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'Enc-tesoreria',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
        'opcont-capitalhumano',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => 'Propinas por casino',
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-hand-holding-usd',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Propinas por casino',
                'menu_id' => $padreId,
                'icono' => 'fa-hand-holding-usd',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar propinas por casino Crown',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Listar propinas por casino Crown',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $padresVisibles = array_values(array_filter([
            $menuId,
            $padreId,
            (int) (DB::table('menu')->where('id', $padreId)->value('menu_id') ?? 0),
        ], fn (int $id) => $id > 0));

        foreach ($rolIds as $rolId) {
            foreach ($padresVisibles as $menuVisibleId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuVisibleId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuVisibleId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);

        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
