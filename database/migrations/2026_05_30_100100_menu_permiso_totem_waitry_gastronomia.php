<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/totem-waitry-gastronomia';

    /** Rol encargado de gastronomía (nombre en tabla rol). */
    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/mesa-gastronomia')->value('menu_id') ?? 10);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tótems Waitry',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-tablet',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tótems Waitry',
                'orden' => $orden,
                'icono' => 'fa-tablet',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar tótems Waitry gastronomía', 'slug' => 'listar-totem-waitry-gastronomia'],
            ['nombre' => 'Ingresar tótems Waitry gastronomía', 'slug' => 'crear-totem-waitry-gastronomia'],
            ['nombre' => 'Editar tótems Waitry gastronomía', 'slug' => 'editar-totem-waitry-gastronomia'],
            ['nombre' => 'Actualizar tótems Waitry gastronomía', 'slug' => 'actualizar-totem-waitry-gastronomia'],
            ['nombre' => 'Borrar tótems Waitry gastronomía', 'slug' => 'borrar-totem-waitry-gastronomia'],
        ];

        $rolId = $this->resolverRolEncGastronomiaId();

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

            if ($rolId > 0) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    private function resolverMenuGastronomiaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $ventasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Ventas')
                    ->orWhere('nombre', 'like', '%Módulo de Ventas%');
            })
            ->orderBy('id')
            ->value('id') ?? 51);

        return (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function resolverRolEncGastronomiaId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')
            ->where('nombre', 'like', 'Enc-gastronom%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    public function down(): void
    {
        $slugs = [
            'listar-totem-waitry-gastronomia',
            'crear-totem-waitry-gastronomia',
            'editar-totem-waitry-gastronomia',
            'actualizar-totem-waitry-gastronomia',
            'borrar-totem-waitry-gastronomia',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
