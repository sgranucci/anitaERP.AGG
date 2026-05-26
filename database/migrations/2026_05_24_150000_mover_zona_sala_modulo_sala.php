<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_OLD = 'configuracion/zona-sala';

    private const MENU_URL = 'sala/zona-sala';

    public function up(): void
    {
        $moduloSalaId = $this->resolverModuloSalaId();
        if ($moduloSalaId === 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')
            ->whereIn('url', [self::MENU_URL_OLD, self::MENU_URL])
            ->value('id') ?? 0);

        if ($menuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloSalaId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $moduloSalaId,
            'url' => self::MENU_URL,
            'nombre' => 'Zonas de sala',
            'orden' => $orden > 0 ? $orden : 1,
            'icono' => 'fa-th-large',
            'updated_at' => now(),
        ]);

        $rolIdsZona = DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id')->unique()->all();
        foreach ($rolIdsZona as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $moduloSalaId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $moduloSalaId,
                    'rol_id' => $rid,
                ]);
            }
        }
    }

    public function down(): void
    {
        $salaMenuId = (int) (DB::table('menu')->where('url', 'configuracion/sala')->value('id') ?? 0);
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0 || $salaMenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $salaMenuId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $salaMenuId,
            'url' => self::MENU_URL_OLD,
            'orden' => $orden > 0 ? $orden : 1,
            'updated_at' => now(),
        ]);
    }

    private function resolverModuloSalaId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Sala')
                    ->orWhere('nombre', 'like', '%Módulo de Sala%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
