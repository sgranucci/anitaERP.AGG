<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_JORNADA = 'ventas/gastronomia/jornada';

    private const SLUGS = [
        'gestionar-saneamiento-turno-gastronomia',
        'ejecutar-saneamiento-turno-gastronomia',
    ];

    public function up(): void
    {
        $menuJornadaId = (int) (DB::table('menu')->where('url', self::MENU_URL_JORNADA)->value('id') ?? 0);
        if ($menuJornadaId === 0) {
            return;
        }

        foreach (self::SLUGS as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuJornadaId,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuSaneamientoId = (int) (DB::table('menu')
            ->where('url', 'ventas/gastronomia/saneamiento-turno')
            ->value('id') ?? 0);

        if ($menuSaneamientoId === 0) {
            return;
        }

        foreach (self::SLUGS as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuSaneamientoId,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
