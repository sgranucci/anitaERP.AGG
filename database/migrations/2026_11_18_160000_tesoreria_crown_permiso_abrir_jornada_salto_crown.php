<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso especial: abrir jornada salteando más de un día respecto de la última.
 * Solo rol administrador (Sergio / ops); el resto solo puede abrir última+1.
 */
return new class extends Migration
{
    private const SLUG = 'abrir-tesoreria-crown-jornada-salto';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/jornada')->value('id')
            ?? DB::table('menu')->where('url', 'like', '%tesoreria-crown/jornada%')->value('id');
        $ahora = now();
        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            $permisoId = DB::table('permiso')->insertGetId([
                'nombre' => 'Abrir jornada Crown con salto de fechas',
                'slug' => self::SLUG,
                'ayuda' => 'Permite abrir una jornada más de un día después de la última (saltar huecos). Sin este permiso solo se abre el día siguiente (última+1). No habilita abrir fechas hacia atrás.',
                'menu_id' => $menuId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Abrir jornada Crown con salto de fechas',
                'ayuda' => 'Permite abrir una jornada más de un día después de la última (saltar huecos). Sin este permiso solo se abre el día siguiente (última+1). No habilita abrir fechas hacia atrás.',
                'menu_id' => $menuId,
                'updated_at' => $ahora,
            ]);
        }

        $adminRolId = DB::table('rol')->where('nombre', 'administrador')->value('id');
        if ($adminRolId && ! DB::table('permiso_rol')->where('rol_id', $adminRolId)->where('permiso_id', $permisoId)->exists()) {
            DB::table('permiso_rol')->insert([
                'rol_id' => $adminRolId,
                'permiso_id' => $permisoId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            return;
        }
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
