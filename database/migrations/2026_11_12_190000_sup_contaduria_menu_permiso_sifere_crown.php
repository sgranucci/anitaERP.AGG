<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Sup-contaduria (Lana / Julieta) ve y usa SiFeRe percepciones.
 * El alta original solo asignó administrador / Enc-contaduría / impuestos.
 */
return new class extends Migration
{
    private const ROL = 'Sup-contaduria';

    private const MENU_URL = 'contable/sifere';

    /** @var list<string> */
    private const SLUGS = [
        'listar-sifere',
        'exportar-sifere',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0 && ! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists()) {
            DB::table('menu_rol')->insert([
                'rol_id' => $rolId,
                'menu_id' => $menuId,
            ]);
        }

        // Padres del árbol (Presentaciones a organismos + Módulo Contable).
        if ($menuId > 0) {
            $submenuId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
            $padreIds = [];
            if ($submenuId > 0) {
                $padreIds[] = $submenuId;
                $moduloId = (int) (DB::table('menu')->where('id', $submenuId)->value('menu_id') ?? 0);
                if ($moduloId > 0) {
                    $padreIds[] = $moduloId;
                }
            }
            foreach ($padreIds as $padreId) {
                if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $padreId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $padreId,
                    ]);
                }
            }
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'rol_id' => $rolId,
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

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
