<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú bajo API Interbanking: archivo ASCII de pagos (ERP + Anita / p-pagoxbanco).
 * Hereda roles de Lectura Interbanking (incluye Enc-pagos / Op-Pagos).
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/interbanking/archivo-pago';

    private const MENU_NOMBRE = 'Archivo pagos (ASCII)';

    private const PADRE_URL = 'caja/interbanking';

    private const PERMISO = 'generar-archivo-pago-interbanking';

    private const PERMISO_REF = 'listar-interbanking-transferencias-persistidas';

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')
                ->where('nombre', 'API Interbanking')
                ->where('url', '#')
                ->value('id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'icono' => 'fa-file-alt',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-file-alt',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Generar archivo ASCII pagos Interbanking',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Generar archivo ASCII pagos Interbanking',
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Roles: mismos que Transferencias IB + roles de pagos + administrador
        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', (int) (DB::table('permiso')->where('slug', self::PERMISO_REF)->value('id') ?? 0))
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rolIds = array_values(array_unique(array_merge(
            $rolIds,
            DB::table('rol')
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%pagos%')
                        ->orWhere('nombre', 'administrador');
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        )));

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
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
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
};
