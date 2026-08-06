<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos Surmar (recepción + movimientos + trazabilidad).
 * Solo EL BIERZO. En AGG no tiene efecto.
 */
return new class extends Migration
{
    private const MENUS = [
        [
            'url' => 'stock/recepcion-proveedor-surmar',
            'nombre' => 'Recepción Surmar',
            'icono' => 'fa-truck',
            'permisos' => [
                ['slug' => 'listar-recepcion-proveedor-surmar', 'nombre' => 'Listar recepción Surmar'],
                ['slug' => 'crear-recepcion-proveedor-surmar', 'nombre' => 'Crear recepción Surmar'],
                ['slug' => 'editar-recepcion-proveedor-surmar', 'nombre' => 'Editar recepción Surmar'],
                ['slug' => 'actualizar-recepcion-proveedor-surmar', 'nombre' => 'Actualizar recepción Surmar'],
                ['slug' => 'confirmar-recepcion-proveedor-surmar', 'nombre' => 'Confirmar recepción Surmar'],
                ['slug' => 'anular-recepcion-proveedor-surmar', 'nombre' => 'Anular recepción Surmar'],
                ['slug' => 'imprimir-etiqueta-recepcion-surmar', 'nombre' => 'Imprimir etiquetas recepción Surmar'],
            ],
        ],
        [
            'url' => 'stock/movimiento-surmar',
            'nombre' => 'Movimientos Surmar',
            'icono' => 'fa-exchange',
            'permisos' => [
                ['slug' => 'listar-movimiento-surmar', 'nombre' => 'Listar movimientos Surmar'],
                ['slug' => 'crear-movimiento-surmar', 'nombre' => 'Crear movimientos Surmar'],
                ['slug' => 'editar-movimiento-surmar', 'nombre' => 'Editar movimientos Surmar'],
                ['slug' => 'actualizar-movimiento-surmar', 'nombre' => 'Actualizar movimientos Surmar'],
                ['slug' => 'confirmar-movimiento-surmar', 'nombre' => 'Confirmar movimientos Surmar'],
                ['slug' => 'anular-movimiento-surmar', 'nombre' => 'Anular movimientos Surmar'],
                ['slug' => 'imprimir-etiqueta-movimiento-surmar', 'nombre' => 'Imprimir etiquetas movimiento Surmar'],
            ],
        ],
        [
            'url' => 'stock/trazabilidad-surmar',
            'nombre' => 'Trazabilidad Surmar',
            'icono' => 'fa-sitemap',
            'permisos' => [
                ['slug' => 'listar-trazabilidad-surmar', 'nombre' => 'Consultar trazabilidad Surmar'],
            ],
        ],
    ];

    public function up(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        $stockMenuId = $this->resolverMenuStockId();
        if ($stockMenuId <= 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/recepcion-proveedor')->value('id')
            ?: DB::table('menu')->where('url', 'stock/movimientostock')->value('id')
            ?: $stockMenuId);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $refMenuId)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($rolIds === []) {
            $rolIds = DB::table('rol')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0);

        foreach (self::MENUS as $i => $def) {
            $orden = $ordenBase + $i + 1;
            $menuId = (int) (DB::table('menu')->where('url', $def['url'])->value('id') ?? 0);
            if ($menuId === 0) {
                $menuId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $stockMenuId,
                    'nombre' => $def['nombre'],
                    'url' => $def['url'],
                    'orden' => $orden,
                    'icono' => $def['icono'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('menu')->where('id', $menuId)->update([
                    'menu_id' => $stockMenuId,
                    'nombre' => $def['nombre'],
                    'icono' => $def['icono'],
                    'updated_at' => now(),
                ]);
            }

            $permisoIds = [];
            foreach ($def['permisos'] as $permiso) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
                if ($permisoId === 0) {
                    $permisoId = (int) DB::table('permiso')->insertGetId([
                        'nombre' => $permiso['nombre'],
                        'slug' => $permiso['slug'],
                        'menu_id' => $menuId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('permiso')->where('id', $permisoId)->update([
                        'menu_id' => $menuId,
                        'nombre' => $permiso['nombre'],
                        'updated_at' => now(),
                    ]);
                }
                $permisoIds[] = $permisoId;
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
                foreach ($permisoIds as $permisoId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rolId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        foreach (self::MENUS as $def) {
            foreach ($def['permisos'] as $permiso) {
                $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
                if ($permisoId) {
                    DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                    DB::table('permiso')->where('id', $permisoId)->delete();
                }
            }
            $menuId = DB::table('menu')->where('url', $def['url'])->value('id');
            if ($menuId) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
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

        return (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 0);
    }
};
