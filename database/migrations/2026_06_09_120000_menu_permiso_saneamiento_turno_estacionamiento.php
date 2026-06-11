<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/estacionamiento/saneamiento-turno';

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')
            ->where('nombre', 'Estacionamiento')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'caja/estacionamiento/habilitacion-turno')->value('menu_id') ?? 0);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Saneamiento turnos',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-wrench',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Saneamiento turnos',
                'orden' => $orden,
                'icono' => 'fa-wrench',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            [
                'nombre' => 'Gestionar saneamiento turno estacionamiento',
                'slug' => 'gestionar-saneamiento-turno-estacionamiento',
            ],
            [
                'nombre' => 'Ejecutar saneamiento turno estacionamiento',
                'slug' => 'ejecutar-saneamiento-turno-estacionamiento',
            ],
        ];

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                DB::table('permiso')->insertGetId([
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
        }
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        foreach (['gestionar-saneamiento-turno-estacionamiento', 'ejecutar-saneamiento-turno-estacionamiento'] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
