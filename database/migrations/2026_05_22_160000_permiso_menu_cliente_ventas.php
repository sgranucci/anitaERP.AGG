<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_URL = 'ventas/cliente';

    /**
     * Permisos del ABM ventas/cliente y funciones en la misma pantalla (incl. SuiteCRM).
     * No incluye tipos suspensión cliente (ventas/tiposuspensioncliente) ni módulo UIF.
     *
     * @var array<int, string>
     */
    private const SLUGS_MENU_CLIENTE = [
        'crear-clientes',
        'listar-clientes',
        'editar-clientes',
        'actualizar-clientes',
        'borrar-clientes',
        'listar-cuentacorriente-cliente',
        'suspender-clientes',
        'modificar-descuento-cliente',
        'cargar-coeficiente-cliente',
        'listar-clientes-vendedor',
        'cargar-articulo-suspendido-cliente',
        'cargar-seguimiento-cliente',
        'listar-notas-suitecrm-cliente',
        'gestionar-notas-suitecrm-cliente',
        'sincronizar-cuenta-suitecrm-cliente',
        'ver-notas-supervisor-suitecrm-cliente',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        DB::table('permiso')
            ->whereIn('slug', self::SLUGS_MENU_CLIENTE)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        DB::table('permiso')
            ->whereIn('slug', self::SLUGS_MENU_CLIENTE)
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
