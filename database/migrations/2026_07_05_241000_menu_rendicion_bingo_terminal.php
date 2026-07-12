<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_TERMINAL_URL = 'caja/bingo/rendicion/cargar';

    private const MENU_CAJA_URL = 'caja/rendicionbingo';

    private const MENU_PADRE_BINGO = 'Bingo';

    private const MENU_PADRE_RENDICIONES_URL = '#';

    private const MENU_REF_TERMINAL = 'caja/bingo/habilitacion-turno';

    private const MENU_REF_CAJA = 'caja/rendicionreceptivo';

    public function up(): void
    {
        $this->upsertMenuTerminal();
        $this->reforzarMenuCaja();
        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuTerminal(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_BINGO)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($padreId <= 0) {
            return;
        }

        $orden = 7;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_TERMINAL_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Cargar rendición',
                'url' => self::MENU_TERMINAL_URL,
                'orden' => $orden,
                'icono' => 'fa-clipboard',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Cargar rendición',
                'orden' => $orden,
                'icono' => 'fa-clipboard',
                'updated_at' => now(),
            ]);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_TERMINAL)->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', 'crear-rendicion-bingo-caja')->value('id') ?? 0);

        if ($refMenuId > 0) {
            foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id') as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
                if ($padreId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rid]);
                }
                if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }

    private function reforzarMenuCaja(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', 'Rendiciones')
            ->where('url', self::MENU_PADRE_RENDICIONES_URL)
            ->orderBy('id')
            ->value('id') ?? 262);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CAJA_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Rendiciones bingo',
                'url' => self::MENU_CAJA_URL,
                'orden' => $orden,
                'icono' => 'fa-ticket',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Rendiciones bingo',
                'icono' => 'fa-ticket',
                'updated_at' => now(),
            ]);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_CAJA)->value('id') ?? 0);
        $slugs = [
            'listar-rendicion-bingo-caja',
            'crear-rendicion-bingo-caja',
            'imprimir-rendicion-bingo-caja',
        ];

        if ($refMenuId <= 0) {
            return;
        }

        foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id') as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
            foreach ($slugs as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menu')->where('url', self::MENU_TERMINAL_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
