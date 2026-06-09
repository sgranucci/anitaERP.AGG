<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_ANTIGUA = 'ventas/actividad_arca';
    private const MENU_URL = 'configuracion/actividad_arca';

    private const PERMISO_SLUGS = [
        'crear-actividad-arca',
        'listar-actividad-arca',
        'editar-actividad-arca',
        'actualizar-actividad-arca',
        'borrar-actividad-arca',
        'importar-actividad-arca',
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('nombre', 'like', '%Configuraci%')
                ->where('url', '#')
                ->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL_ANTIGUA)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        }

        $ordenTipodocumento = (int) (DB::table('menu')->where('url', 'configuracion/tipodocumento')->value('orden') ?? 0);
        $orden = $ordenTipodocumento > 0 ? $ordenTipodocumento + 1 : ((int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Actividad ARCA',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Actividad ARCA',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        foreach (self::PERMISO_SLUGS as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $ventasTablasMenuId = (int) (DB::table('menu')
            ->where('nombre', 'Tablas de ventas')
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($ventasTablasMenuId === 0) {
            $ventasTablasMenuId = (int) (DB::table('menu')->where('url', 'ventas/cliente')->value('menu_id') ?? 53);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $ventasTablasMenuId,
            'url' => self::MENU_URL_ANTIGUA,
            'orden' => 2,
            'updated_at' => now(),
        ]);

        foreach (self::PERMISO_SLUGS as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }
    }
};
