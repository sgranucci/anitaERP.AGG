<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const URL_UNICA = 'ventas/gastronomia/canjes/cliente-vip-emita';

    private const URL_NOMBRE = 'ventas/gastronomia/canjes/cliente-vip-emita/nombre';

    private const URL_ALIAS = 'ventas/gastronomia/canjes/cliente-vip-emita/alias';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuNombre = DB::table('menu')->where('url', self::URL_NOMBRE)->first();
        if ($menuNombre) {
            DB::table('menu')->where('id', $menuNombre->id)->update([
                'nombre' => 'Consulta VIP Emita',
                'url' => self::URL_UNICA,
                'icono' => 'fa-search',
                'updated_at' => now(),
            ]);
        }

        $aliasIds = DB::table('menu')->where('url', self::URL_ALIAS)->pluck('id');
        foreach ($aliasIds as $menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        }
        EloquentAuditDeleteSupport::each(Menu::query()->where('url', self::URL_ALIAS));

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menu = DB::table('menu')->where('url', self::URL_UNICA)->first();
        if (! $menu) {
            return;
        }

        DB::table('menu')->where('id', $menu->id)->update([
            'nombre' => 'VIP Emita por nombre',
            'url' => self::URL_NOMBRE,
            'icono' => 'fa-user',
            'updated_at' => now(),
        ]);

        $aliasId = (int) (DB::table('menu')->where('url', self::URL_ALIAS)->value('id') ?? 0);
        if ($aliasId === 0) {
            $aliasId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $menu->menu_id,
                'nombre' => 'VIP Emita por alias',
                'url' => self::URL_ALIAS,
                'orden' => ((int) $menu->orden) + 1,
                'icono' => 'fa-id-badge',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roles = DB::table('menu_rol')->where('menu_id', $menu->id)->pluck('rol_id');
            foreach ($roles as $rolId) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $aliasId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
