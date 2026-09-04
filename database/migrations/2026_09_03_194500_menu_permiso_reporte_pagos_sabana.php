<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Informe sábana de pagos (l-movim formato completo) bajo Reportes de Cuentas a pagar.
 * Roles: pagos + finanzas + administrador.
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'compras/pagos-sabana',
        'nombre' => 'Pagos (sábana)',
        'icono' => 'fa-table',
    ];

    private const PERMISO = [
        'slug' => 'listar-reporte-pagos-sabana',
        'nombre' => 'Listar reporte de pagos sábana',
    ];

    public function up(): void
    {
        $cuentasAPagarId = (int) (DB::table('menu')
            ->where('nombre', 'Cuentas a pagar')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        $parentMenuId = 0;
        if ($cuentasAPagarId > 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('menu_id', $cuentasAPagarId)
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('id', 497)->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU['nombre'],
                'url' => self::MENU['url'],
                'orden' => $orden,
                'icono' => self::MENU['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => self::MENU['nombre'],
                'icono' => self::MENU['icono'],
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO['nombre'],
                'slug' => self::PERMISO['slug'],
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO['nombre'],
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%pagos%')
                    ->orWhere('nombre', 'like', 'Enc-finanz%')
                    ->orWhere('nombre', 'like', 'Op-Finanz%')
                    ->orWhere('nombre', 'administrador');
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
