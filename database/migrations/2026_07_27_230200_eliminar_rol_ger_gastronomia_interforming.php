<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interforming: el rol Ger-gastronomía es de AGG y no corresponde.
 *
 * Gate obligatorio: solo INTERFORMING. En AGG esta migración corrió sin filtro
 * (12/ago/2026) y dejó a hdattilo sin rol; restore en
 * 2026_08_12_210000_restaurar_rol_ger_gastronomia_agg.
 */
return new class extends Migration
{
    private const ROL_NOMBRE = 'Ger-gastronomía';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_NOMBRE)->value('id') ?? 0);
        if ($rolId <= 0) {
            $rolId = (int) (DB::table('rol')->where('nombre', 'like', 'Ger-gastronom%')->orderBy('id')->value('id') ?? 0);
        }
        if ($rolId <= 0) {
            return;
        }

        if (Schema::hasTable('usuario_rol')) {
            DB::table('usuario_rol')->where('rol_id', $rolId)->delete();
        }
        if (Schema::hasTable('menu_rol')) {
            DB::table('menu_rol')->where('rol_id', $rolId)->delete();
        }
        if (Schema::hasTable('permiso_rol')) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->delete();
        }

        DB::table('rol')->where('id', $rolId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No recrear: el rol no aplica a Interforming.
    }
};
