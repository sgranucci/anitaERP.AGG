<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Sup-contaduria (Lana / Julieta) ve y exporta Percepciones de IVA.
 * El alta original solo asignó administrador / Enc-contaduría / impuestos.
 * SiFeRe ya se habilitó en 2026_11_12_190000; este menú hermano quedó afuera.
 */
return new class extends Migration
{
    private const ROL = 'Sup-contaduria';

    private const MENU_URL = 'contable/percepciones-iva';

    /** @var list<string> */
    private const SLUGS = [
        'listar-percepcion-iva',
        'exportar-percepcion-iva',
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
            $actual = $menuId;
            $vistos = [];
            while ($actual > 0 && ! isset($vistos[$actual])) {
                $vistos[$actual] = true;
                $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
                if ($padre <= 0) {
                    break;
                }
                if (! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $padre)->exists()) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $padre,
                    ]);
                }
                $actual = $padre;
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
