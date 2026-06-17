<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_INGRESO_EGRESO_URL = 'caja/ingresoegreso';

    private const MENU_COBRANZA_URL = 'caja/cobranza';

    private const MENU_RETENCION_COBRANZA_URL = 'configuracion/retencion_cobranza';

    /** @var array<string, list<string>> menu.url => slugs verificados en controllers/vistas del ABM */
    private const PERMISOS_POR_MENU = [
        self::MENU_INGRESO_EGRESO_URL => [
            'crear-ingresos-egresos-caja',
            'listar-ingresos-egresos-caja',
            'editar-ingresos-egresos-caja',
            'actualizar-ingresos-egresos-caja',
            'borrar-ingresos-egresos-caja',
        ],
        self::MENU_COBRANZA_URL => [
            'crear-cobranza',
            'listar-cobranza',
            'editar-cobranza',
            'actualizar-cobranza',
            'borrar-cobranza',
            'revertir-cobranza',
            'emitir-cobranza',
            'confirmar-cobranza',
        ],
        self::MENU_RETENCION_COBRANZA_URL => [
            'crear-retencion-cobranza',
            'listar-retencion-cobranza',
            'editar-retencion-cobranza',
            'actualizar-retencion-cobranza',
            'borrar-retencion-cobranza',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS_POR_MENU as $menuUrl => $slugs) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }

            DB::table('permiso')
                ->whereIn('slug', $slugs)
                ->where(function ($query) {
                    $query->whereNull('menu_id')->orWhere('menu_id', 0);
                })
                ->update([
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [];
        foreach (self::PERMISOS_POR_MENU as $rows) {
            $slugs = array_merge($slugs, $rows);
        }

        DB::table('permiso')
            ->whereIn('slug', array_values(array_unique($slugs)))
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
