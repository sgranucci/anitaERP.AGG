<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/efe-mensual';

    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const MENU_REF_ROLES_URL = 'contable/mayor-concepto';

    public function up(): void
    {
        $padreId = $this->resolverMenuContableId();
        if ($padreId === 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Estado de flujo mensual (EFE)', $padreId, $orden, 'fa-water');

        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach ($rolIdsMenuRef as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
        }
    }

    private function resolverMenuContableId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : 43;
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
