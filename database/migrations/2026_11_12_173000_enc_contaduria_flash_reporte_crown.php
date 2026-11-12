<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Enc-contaduría (Celina) recibe permisos de Flash Report
 * (menú ya asignado; faltaban los slug de listar/exportar/administrar).
 */
return new class extends Migration
{
    private const ROL = 'Enc-contaduría';

    /** @var list<string> */
    private const SLUGS = [
        'listar-flash-reporte-crown',
        'exportar-flash-reporte-crown',
        'administrar-flash-reporte-crown',
    ];

    private const MENU_URL = 'caja/flash-crown/reporte';

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
            DB::table('menu_rol')->insert(['rol_id' => $rolId, 'menu_id' => $menuId]);
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permisoIds as $permisoId) {
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

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
