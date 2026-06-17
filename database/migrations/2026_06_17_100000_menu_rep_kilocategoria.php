<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/repkilocategoria';

    private const MENU_PADRE_ID = 191;

    private const MENU_REF_ROLES_ID = 192;

    public function up(): void
    {
        $orden = (int) (DB::table('menu')->where('menu_id', self::MENU_PADRE_ID)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Kilos por categoría', self::MENU_PADRE_ID, $orden);

        $this->copiarRolesMenu(self::MENU_REF_ROLES_ID, $menuId);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function copiarRolesMenu(int $refMenuId, int $menuId): void
    {
        $rolIds = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
