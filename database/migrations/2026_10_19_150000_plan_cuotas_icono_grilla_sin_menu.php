<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El plan de cuotas se abre desde un ícono de la grilla de comprobantes.
 * No tiene entrada de menú. El permiso queda en Enc-pagos, Op-Pagos y administrador.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/comprobante-proveedor/plan-cuotas';

    private const SLUG = 'editar-plan-cuotas-comprobante-proveedor';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $menuComprobantes = (int) (DB::table('menu')->where('url', 'compras/comprobante-proveedor')->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuComprobantes > 0 ? $menuComprobantes : null,
                'updated_at' => now(),
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('permiso')->where('menu_id', $menuId)->update(['menu_id' => $menuComprobantes > 0 ? $menuComprobantes : null]);
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        if ($permisoId === 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-pagos', 'Op-Pagos'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->when($rolIds !== [], fn ($q) => $q->whereNotIn('rol_id', $rolIds))
            ->delete();

        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
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
        // El menú propio no se restaura: la entrada es el ícono de la grilla.
    }
};
