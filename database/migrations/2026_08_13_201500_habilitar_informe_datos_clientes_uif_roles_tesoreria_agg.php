<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: habilita informe datos clientes UIF (Excel/PDF/XML) a Enc-Uif y Op-Uif,
 * y aclara el nombre del menú.
 *
 * Nota: una versión previa asignaba también a tesorería; corregida por
 * 2026_08_13_201800_restringir_informe_datos_clientes_uif_enc_op_uif_agg.
 */
return new class extends Migration
{
    private const MENU_URL = 'uif/crearexportaoperacion';

    private const MENU_NOMBRE = 'Informe datos clientes UIF';

    private const PERMISO_SLUG = 'exportar-operacion-uif';

    private const ROLES_UIF = [
        'Enc-Uif',
        'Op-Uif',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'nombre' => self::MENU_NOMBRE,
        ]);

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $menuPadreId = (int) (DB::table('menu')->where('id', 180)->value('id') ?? 0);

        if (! DB::table('rol')->where('nombre', 'Op-Uif')->exists()) {
            DB::table('rol')->insert(['nombre' => 'Op-Uif']);
        }

        foreach (self::ROLES_UIF as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                ]);
            }

            if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists()) {
                DB::table('menu_rol')->insert([
                    'rol_id' => $rolId,
                    'menu_id' => $menuId,
                ]);
            }

            if ($menuPadreId > 0
                && ! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuPadreId)->exists()) {
                DB::table('menu_rol')->insert([
                    'rol_id' => $rolId,
                    'menu_id' => $menuPadreId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Exporta Operaciones',
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $opUifId = (int) (DB::table('rol')->where('nombre', 'Op-Uif')->value('id') ?? 0);

        if ($opUifId > 0 && $permisoId > 0) {
            DB::table('permiso_rol')
                ->where('rol_id', $opUifId)
                ->where('permiso_id', $permisoId)
                ->delete();
        }

        if ($opUifId > 0 && $menuId > 0) {
            DB::table('menu_rol')
                ->where('rol_id', $opUifId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }
};
