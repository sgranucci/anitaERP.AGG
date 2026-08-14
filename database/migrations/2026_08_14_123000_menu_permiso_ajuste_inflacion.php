<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/ajuste-inflacion';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        ['slug' => 'listar-ajuste-inflacion', 'nombre' => 'Consultar ajuste por inflación'],
        ['slug' => 'configurar-ajuste-inflacion', 'nombre' => 'Configurar cuentas del ajuste por inflación'],
        ['slug' => 'importar-indices-ajuste-inflacion', 'nombre' => 'Cargar índices del ajuste por inflación'],
        ['slug' => 'simular-ajuste-inflacion', 'nombre' => 'Simular ajuste por inflación'],
        ['slug' => 'confirmar-ajuste-inflacion', 'nombre' => 'Confirmar ajuste y generar asiento AJ'],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría'];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Contable')
            ->where('url', '#')
            ->value('id') ?? 43);
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Ajuste por inflación',
                'url' => self::MENU_URL,
                'orden' => (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1,
                'icono' => 'fa-line-chart',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $permiso['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $permiso['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
