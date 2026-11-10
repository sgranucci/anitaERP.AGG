<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del saneamiento simple de cuentas Crown (solo EMPRESA=CROWN).
 * Se cuelga de la misma rama que la Jornada de gastronomía.
 */
return new class extends Migration
{
    private const URL = 'ventas/gastronomia-crown/saneamiento';

    private const URL_JORNADA = 'ventas/gastronomia/jornada';

    private const URL_POS = 'ventas/gastronomia-crown/pos';

    /** @var array<string, string> */
    private const PERMISOS = [
        'gestionar-saneamiento-cuentas-gastronomia-crown' => 'Gestionar saneamiento cuentas gastronomía Crown',
        'ejecutar-saneamiento-cuentas-gastronomia-crown' => 'Ejecutar saneamiento cuentas gastronomía Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();

        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $nombre,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $permisoId;
        }

        $jornada = DB::table('menu')->where('url', self::URL_JORNADA)->first(['menu_id', 'orden']);
        $menuPadreId = (int) ($jornada->menu_id
            ?? DB::table('menu')->where('url', self::URL_POS)->value('menu_id')
            ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        if (! $menuId) {
            $orden = $jornada !== null
                ? ((int) $jornada->orden) + 1
                : (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;

            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'Saneamiento cuentas',
                'url' => self::URL,
                'orden' => $orden,
                'icono' => 'fa-wrench',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $menuPadreId,
                'nombre' => 'Saneamiento cuentas',
                'icono' => 'fa-wrench',
                'updated_at' => $ahora,
            ]);
        }

        foreach ($permisoIds as $permisoId) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'updated_at' => $ahora,
            ]);
        }

        // Mismos perfiles operativos que cierran jornada / saneamiento AGG, más admin/sistemas.
        $rolIds = DB::table('rol')
            ->whereIn('nombre', [
                'administrador',
                'Enc-sistemas',
                'Enc-admin',
                'Enc-gastronomía',
                'Sup-Gastronomia',
                'Ger-gastronomía',
                'Ref-gastronomía',
                'Enc-contaduría',
            ])
            ->pluck('id');

        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                $existePerm = DB::table('permiso_rol')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();
                if (! $existePerm) {
                    DB::table('permiso_rol')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }

            foreach ([$menuId, $menuPadreId] as $idMenu) {
                $existeMenu = DB::table('menu_rol')
                    ->where('rol_id', $rolId)
                    ->where('menu_id', $idMenu)
                    ->exists();
                if (! $existeMenu) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $idMenu,
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

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);

        foreach (array_keys(self::PERMISOS) as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
