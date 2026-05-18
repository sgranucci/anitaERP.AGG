<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PROCESO_URL = 'stock/gastronomia/proceso-facturacion';

    private const MENU_HIST_URL = 'stock/gastronomia/facturas-dia';

    private const MENU_URL_REF = 'stock/configuracion-puntoventa-gastronomia';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_URL_REF)->value('id') ?? $parentMenuId);

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0);

        $this->insertMenuConPermisos(
            $parentMenuId,
            $refMenuId,
            ++$ordenBase,
            'Proceso facturación',
            self::MENU_PROCESO_URL,
            'fa-shopping-cart',
            [
                ['nombre' => 'Usar proceso facturación gastronomía', 'slug' => 'usar-proceso-facturacion-gastronomia'],
            ]
        );

        $this->insertMenuConPermisos(
            $parentMenuId,
            $refMenuId,
            ++$ordenBase,
            'Facturas del día (PV)',
            self::MENU_HIST_URL,
            'fa-file-text-o',
            [
                ['nombre' => 'Listar facturas gastronomía del día', 'slug' => 'listar-facturas-gastronomia-dia'],
                ['nombre' => 'Ver factura gastronomía', 'slug' => 'ver-factura-gastronomia'],
            ]
        );
    }

    /**
     * @param  list<array{nombre:string,slug:string}>  $slugs
     */
    private function insertMenuConPermisos(
        int $parentMenuId,
        int $refMenuId,
        int $orden,
        string $nombre,
        string $url,
        string $icono,
        array $slugs
    ): void {
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => $nombre,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = 0;

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

            if ($refPermisoId > 0) {
                $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
                foreach ($rolIds as $rolId) {
                    $rid = (int) $rolId;
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rid,
                        ]);
                    }
                }
            }

            if ($refMenuId > 0) {
                $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            } else {
                $rolIdsMenu = $refPermisoId > 0
                    ? DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all()
                    : [];
            }

            foreach ($rolIdsMenu as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rid,
                    ]);
                }
            }

            $refPermisoId = $permisoId;
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

    public function down(): void
    {
        foreach ([self::MENU_PROCESO_URL, self::MENU_HIST_URL] as $url) {
            $menuId = DB::table('menu')->where('url', $url)->value('id');
            if (! $menuId) {
                continue;
            }
            $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id');
            foreach ($permisoIds as $pid) {
                DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
                DB::table('permiso')->where('id', $pid)->delete();
            }
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
