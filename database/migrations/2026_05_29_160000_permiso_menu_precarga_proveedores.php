<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'compras/precarga_comprobante_proveedor';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'crear-precarga-proveedores',
        'listar-precarga-proveedores',
        'editar-precarga-proveedores',
        'actualizar-precarga-proveedores',
        'borrar-precarga-proveedores',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $menuProveedorId = (int) (DB::table('menu')->where('url', 'compras/proveedor')->value('id') ?? 0);

        DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->where('menu_id', $menuId)
            ->update([
                'menu_id' => $menuProveedorId > 0 ? $menuProveedorId : null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
