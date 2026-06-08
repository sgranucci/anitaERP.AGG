<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/recepcion-proveedor';
    private const MENU_URL_CONFIG = 'stock/configuracion-recepcion-proveedor';

    public function up(): void
    {
        $stockMenuId = $this->resolverMenuStockId();
        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('id') ?? $stockMenuId);
        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;

        $menuId = $this->upsertMenu(self::MENU_URL, 'Recepción proveedores', $stockMenuId, $orden, 'fa-truck');
        $this->upsertPermisos([
            ['nombre' => 'Listar recepciones de proveedor', 'slug' => 'listar-recepcion-proveedor'],
            ['nombre' => 'Ingresar recepción de proveedor', 'slug' => 'crear-recepcion-proveedor'],
            ['nombre' => 'Editar recepción de proveedor', 'slug' => 'editar-recepcion-proveedor'],
            ['nombre' => 'Actualizar recepción de proveedor', 'slug' => 'actualizar-recepcion-proveedor'],
            ['nombre' => 'Confirmar recepción de proveedor', 'slug' => 'confirmar-recepcion-proveedor'],
            ['nombre' => 'Registrar devolución a proveedor', 'slug' => 'devolver-recepcion-proveedor'],
            ['nombre' => 'Anular recepción de proveedor', 'slug' => 'anular-recepcion-proveedor'],
            ['nombre' => 'Cargar recepción por OCR', 'slug' => 'ocr-recepcion-proveedor'],
        ], $menuId, $refMenuId);

        $orden++;
        $menuConfigId = $this->upsertMenu(self::MENU_URL_CONFIG, 'Config. recepción proveedores', $stockMenuId, $orden, 'fa-cog');
        $this->upsertPermisos([
            ['nombre' => 'Editar configuración recepción proveedores', 'slug' => 'editar-configuracion-recepcion-proveedor'],
            ['nombre' => 'Actualizar configuración recepción proveedores', 'slug' => 'actualizar-configuracion-recepcion-proveedor'],
        ], $menuConfigId, $refMenuId);
    }

    private function resolverMenuStockId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Stock')->orWhere('nombre', 'like', '%Stock%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 10);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<int, array{nombre:string, slug:string}> $slugs */
    private function upsertPermisos(array $slugs, int $menuId, int $refMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

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

            foreach ($rolIdsMenuRef as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'listar-recepcion-proveedor', 'crear-recepcion-proveedor', 'editar-recepcion-proveedor',
            'actualizar-recepcion-proveedor', 'confirmar-recepcion-proveedor', 'devolver-recepcion-proveedor',
            'anular-recepcion-proveedor', 'ocr-recepcion-proveedor',
            'editar-configuracion-recepcion-proveedor', 'actualizar-configuracion-recepcion-proveedor',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        foreach ([self::MENU_URL, self::MENU_URL_CONFIG] as $url) {
            $menuId = DB::table('menu')->where('url', $url)->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
    }
};
