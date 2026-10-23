<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Paula Schell (único usuario del rol Sup-Marketing) carga solicitudes de pago.
 * Menú del módulo + listar/crear/editar/actualizar. Sin borrar, sin ver todas
 * y sin ABM de sectores, conceptos ni formas de pago.
 */
return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Solicitudes de Pago';

    private const MENU_URL = 'solicitudpago/solicitudpago';

    private const ROL = 'Sup-Marketing';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-solicitud-pago',
        'crear-solicitud-pago',
        'editar-solicitud-pago',
        'actualizar-solicitud-pago',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($rolId <= 0 || $moduloId <= 0 || $menuId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (count($permisoIds) !== count(self::PERMISO_SLUGS)) {
            throw new \RuntimeException('Faltan permisos de solicitud de pago para asignar a Sup-Marketing.');
        }

        foreach ([$moduloId, $menuId] as $mid) {
            if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $mid,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach ($permisoIds as $permisoId) {
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
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $menuIds = array_values(array_filter([$moduloId, $menuId]));
        if ($menuIds !== []) {
            DB::table('menu_rol')->where('rol_id', $rolId)->whereIn('menu_id', $menuIds)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
