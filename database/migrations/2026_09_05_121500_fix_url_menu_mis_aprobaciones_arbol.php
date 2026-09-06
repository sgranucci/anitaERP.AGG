<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige URL del menú Mis aprobaciones a la ruta canónica corta
 * (evita 404 por path viejo / opcache) y asegura padres visibles.
 */
return new class extends Migration
{
    private const URL_NUEVA = 'mis-aprobaciones';

    private const URL_VIEJA = 'configuracion/mis-aprobaciones';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::URL_VIEJA)->value('id')
            ?? DB::table('menu')->where('url', self::URL_NUEVA)->value('id')
            ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'url' => self::URL_NUEVA,
                'nombre' => 'Mis aprobaciones',
                'icono' => 'fa-inbox',
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        DB::table('menu')->where('url', self::URL_NUEVA)->update([
            'url' => self::URL_VIEJA,
            'updated_at' => now(),
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }
};
