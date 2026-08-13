<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja el informe de recepción de proveedores solo en:
 * administrador, Enc/Sup gastronomía, y todos los roles de compras y logística.
 */
return new class extends Migration
{
    private const MENU_URL = 'stock/reporte-recepcion-proveedor';

    private const PERMISO_SLUG = 'listar-reporte-recepcion-proveedor';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $permitidos = $this->rolIdsObjetivo();

        if ($permisoId > 0) {
            $query = DB::table('permiso_rol')->where('permiso_id', $permisoId);
            if ($permitidos !== []) {
                $query->whereNotIn('rol_id', $permitidos);
            }
            $query->delete();

            foreach ($permitidos as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        if ($menuId > 0) {
            $query = DB::table('menu_rol')->where('menu_id', $menuId);
            if ($permitidos !== []) {
                $query->whereNotIn('rol_id', $permitidos);
            }
            $query->delete();

            foreach ($permitidos as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No reponer roles extra: el alta original ya no es la fuente de verdad.
    }

    /** @return list<int> */
    private function rolIdsObjetivo(): array
    {
        $ids = [];

        $id = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }

        $likes = [
            'Sup-Gastronom%',
            'Enc-gastronom%',
            '%compras%',
            '%Compras%',
            '%logistica%',
            '%Logistica%',
            '%logística%',
        ];

        foreach ($likes as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $rolId) {
                $ids[] = (int) $rolId;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
};
