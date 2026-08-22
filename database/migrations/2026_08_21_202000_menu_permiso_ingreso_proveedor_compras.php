<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos de tickets de ingreso para administrador y toda Compras.
 * Pedir (crear) y ver (listar/editar). Control de portería se ve; ENTRO/SALIO sigue en Seguridad.
 */
return new class extends Migration
{
    private const MENU_URLS = [
        '#',
        'seguridad/control-ingreso',
        'seguridad/ingreso-proveedor',
    ];

    /** @var list<string> */
    private const PERMISOS_PEDIR_Y_VER = [
        'listar-ingreso-proveedor',
        'crear-ingreso-proveedor',
        'editar-ingreso-proveedor',
        'actualizar-ingreso-proveedor',
    ];

    public function up(): void
    {
        $rolIds = $this->rolesComprasYAdmin();
        $menuIds = $this->menuIds();
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_PEDIR_Y_VER)
            ->pluck('id');

        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Seguridad')
            ->value('id') ?? 0);
        if ($moduloId > 0) {
            $menuIds[] = $moduloId;
        }

        foreach ($rolIds as $rolId) {
            foreach (array_unique(array_filter($menuIds)) as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
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
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No se quita: administrador y Enc-compras ya tenían el set original.
    }

    /** @return list<int> */
    private function rolesComprasYAdmin(): array
    {
        $ids = [];
        foreach (['administrador', 'Enc-admin'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%compras%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }
        foreach (DB::table('rol')->where('nombre', 'like', '%Compras%')->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /** @return list<int> */
    private function menuIds(): array
    {
        return DB::table('menu')
            ->whereIn('url', ['seguridad/control-ingreso', 'seguridad/ingreso-proveedor'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};
