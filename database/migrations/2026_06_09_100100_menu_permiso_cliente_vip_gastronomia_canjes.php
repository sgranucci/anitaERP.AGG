<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CANJES_URL = '#';

    private const MENU_CLIENTE_VIP_URL = 'ventas/gastronomia/canjes/cliente-vip';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/mesa-gastronomia')->value('menu_id') ?? 10);
        }

        $canjesMenuId = (int) (DB::table('menu')->where('url', self::MENU_CANJES_URL)->value('id') ?? 0);
        $ordenCanjes = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        if ($canjesMenuId === 0) {
            $canjesMenuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Canjes',
                'url' => self::MENU_CANJES_URL,
                'orden' => $ordenCanjes,
                'icono' => 'fa-gift',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $canjesMenuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Canjes',
                'orden' => $ordenCanjes,
                'icono' => 'fa-gift',
                'updated_at' => now(),
            ]);
        }

        $ordenClienteVip = (int) (DB::table('menu')->where('menu_id', $canjesMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_VIP_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Clientes VIP',
                'url' => self::MENU_CLIENTE_VIP_URL,
                'orden' => $ordenClienteVip,
                'icono' => 'fa-star',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Clientes VIP',
                'orden' => $ordenClienteVip,
                'icono' => 'fa-star',
                'updated_at' => now(),
            ]);
        }

        $slugs = [
            ['nombre' => 'Listar clientes VIP gastronomía', 'slug' => 'listar-cliente-vip-gastronomia'],
            ['nombre' => 'Ingresar clientes VIP gastronomía', 'slug' => 'crear-cliente-vip-gastronomia'],
            ['nombre' => 'Editar clientes VIP gastronomía', 'slug' => 'editar-cliente-vip-gastronomia'],
            ['nombre' => 'Actualizar clientes VIP gastronomía', 'slug' => 'actualizar-cliente-vip-gastronomia'],
            ['nombre' => 'Borrar clientes VIP gastronomía', 'slug' => 'borrar-cliente-vip-gastronomia'],
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
                if (! DB::table('menu_rol')->where('menu_id', $canjesMenuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $canjesMenuId,
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
            'listar-cliente-vip-gastronomia',
            'crear-cliente-vip-gastronomia',
            'editar-cliente-vip-gastronomia',
            'actualizar-cliente-vip-gastronomia',
            'borrar-cliente-vip-gastronomia',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuIds = DB::table('menu')->whereIn('url', [self::MENU_CLIENTE_VIP_URL, self::MENU_CANJES_URL])->pluck('id')->all();
        foreach ($menuIds as $mid) {
            DB::table('menu_rol')->where('menu_id', $mid)->delete();
            DB::table('menu')->where('id', $mid)->delete();
        }
    }
};
