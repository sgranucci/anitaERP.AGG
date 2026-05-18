<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL_OLD = 'caja/usomediopago';

    private const MENU_URL = 'caja/usocuentacaja';

    private const SLUG_MAP = [
        'listar-usomediopago' => ['nombre' => 'Listar usos de cuenta de caja', 'slug' => 'listar-usocuentacaja'],
        'crear-usomediopago' => ['nombre' => 'Ingresar usos de cuenta de caja', 'slug' => 'crear-usocuentacaja'],
        'editar-usomediopago' => ['nombre' => 'Editar usos de cuenta de caja', 'slug' => 'editar-usocuentacaja'],
        'actualizar-usomediopago' => ['nombre' => 'Actualizar usos de cuenta de caja', 'slug' => 'actualizar-usocuentacaja'],
        'borrar-usomediopago' => ['nombre' => 'Borrar usos de cuenta de caja', 'slug' => 'borrar-usocuentacaja'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL_OLD)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        }

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Usos de cuenta de caja',
                'url' => self::MENU_URL,
                'updated_at' => now(),
            ]);
        }

        foreach (self::SLUG_MAP as $oldSlug => $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $oldSlug)->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            }
            if ($permisoId > 0) {
                $update = [
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'updated_at' => now(),
                ];
                if ($menuId > 0) {
                    $update['menu_id'] = $menuId;
                }
                DB::table('permiso')->where('id', $permisoId)->update($update);
            }
        }
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => 'Usos de medio de pago',
                'url' => self::MENU_URL_OLD,
                'updated_at' => now(),
            ]);
        }

        foreach (self::SLUG_MAP as $oldSlug => $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => str_replace('cuenta de caja', 'medio de pago', $row['nombre']),
                    'slug' => $oldSlug,
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
