<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_URL = 'ventas/cliente';

    private const MENU_FACTURA_URL = 'ventas/factura';

    private const PERMISO = 'modifica-emite-nota-de-credito';

    public function up(): void
    {
        $menuClienteId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        if ($menuClienteId === 0) {
            return;
        }

        DB::table('permiso')
            ->where('slug', self::PERMISO)
            ->update([
                'nombre' => 'Modificar emisión de notas de crédito en clientes',
                'menu_id' => $menuClienteId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuFacturaId = (int) (DB::table('menu')->where('url', self::MENU_FACTURA_URL)->value('id') ?? 0);

        DB::table('permiso')
            ->where('slug', self::PERMISO)
            ->update([
                'nombre' => 'Modifica si emite o no notas de credito de ventas',
                'menu_id' => $menuFacturaId > 0 ? $menuFacturaId : null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
