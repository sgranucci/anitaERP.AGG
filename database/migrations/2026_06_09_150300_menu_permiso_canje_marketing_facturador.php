<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CANJES_NOMBRE = 'Canjes';

    private const MENU_FACTURADOR_URL = 'ventas/gastronomia/canjes/proceso-facturacion';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $canjesMenuId = $this->resolverMenuCanjesId();
        if ($canjesMenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $canjesMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_FACTURADOR_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Facturador canjes marketing',
                'url' => self::MENU_FACTURADOR_URL,
                'orden' => $orden,
                'icono' => 'fa-cash-register',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Facturador canjes marketing',
                'orden' => $orden,
                'icono' => 'fa-cash-register',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Usar facturador canjes marketing', 'slug' => 'usar-facturador-canje-marketing'],
        ];

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);
        $rolesMarketing = DB::table('rol')
            ->whereIn('nombre', ['enc-Marketing y CAC', 'Sup-Marketing', 'Op-Marketing'])
            ->pluck('id')
            ->all();

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            $rolIds = array_filter(array_merge([$rolId], array_map('intval', $rolesMarketing)));
            foreach ($rolIds as $rid) {
                if ($rid <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $canjesMenuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $canjesMenuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    private function resolverMenuCanjesId(): int
    {
        $gastronomiaId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($gastronomiaId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $gastronomiaId)
            ->where('nombre', self::MENU_CANJES_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_FACTURADOR_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', 'usar-facturador-canje-marketing')->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }
};
