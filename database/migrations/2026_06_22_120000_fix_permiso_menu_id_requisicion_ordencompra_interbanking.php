<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** CRUD OC mal vinculado al menú Requisiciones (225) en TablaPermisoSeeder. */
    private const SLUGS_ORDENCOMPRA = [
        'listar-ordencompra',
        'crear-ordencompra',
        'editar-ordencompra',
        'actualizar-ordencompra',
        'borrar-ordencompra',
    ];

    /** Lectura Interbanking en vivo; deben agruparse bajo caja/interbanking (224). */
    private const SLUGS_INTERBANKING_LECTURA = [
        'listar-saldo-cuenta-interbanking',
        'ver-movimientos-cuenta-interbanking',
    ];

    public function up(): void
    {
        $menuOrdencompraId = (int) (DB::table('menu')->where('url', 'compras/ordencompra')->value('id') ?? 0);
        if ($menuOrdencompraId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::SLUGS_ORDENCOMPRA)
                ->update([
                    'menu_id' => $menuOrdencompraId,
                    'updated_at' => now(),
                ]);
        }

        $menuInterbankingId = (int) (DB::table('menu')->where('url', 'caja/interbanking')->value('id') ?? 0);
        if ($menuInterbankingId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::SLUGS_INTERBANKING_LECTURA)
                ->update([
                    'menu_id' => $menuInterbankingId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuRequisicionId = (int) (DB::table('menu')->where('url', 'compras/requisicion')->value('id') ?? 0);
        if ($menuRequisicionId > 0) {
            DB::table('permiso')
                ->whereIn('slug', array_merge(self::SLUGS_ORDENCOMPRA, self::SLUGS_INTERBANKING_LECTURA))
                ->update([
                    'menu_id' => $menuRequisicionId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
