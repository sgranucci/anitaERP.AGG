<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permisos certificado SENASA Surmar (remito cárnico).
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    private const MENU = [
        'url' => 'stock/certificado-senasa-surmar',
        'nombre' => 'Cert. SENASA Surmar',
        'icono' => 'fa-certificate',
        'permisos' => [
            ['slug' => 'listar-certificado-senasa-surmar', 'nombre' => 'Listar certificado SENASA Surmar'],
            ['slug' => 'crear-certificado-senasa-surmar', 'nombre' => 'Crear certificado SENASA Surmar'],
            ['slug' => 'editar-certificado-senasa-surmar', 'nombre' => 'Editar certificado SENASA Surmar'],
            ['slug' => 'actualizar-certificado-senasa-surmar', 'nombre' => 'Actualizar certificado SENASA Surmar'],
            ['slug' => 'confirmar-certificado-senasa-surmar', 'nombre' => 'Confirmar certificado SENASA Surmar'],
            ['slug' => 'anular-certificado-senasa-surmar', 'nombre' => 'Anular certificado SENASA Surmar'],
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

        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/trazabilidad-surmar')->value('id')
            ?: DB::table('menu')->where('url', 'stock/recepcion-proveedor-surmar')->value('id')
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

        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
        $def = self::MENU;

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
        foreach ($def['permisos'] as $perm) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $perm['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $perm['nombre'],
                    'slug' => $perm['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $perm['nombre'],
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

    public function down(): void
    {
        if (! $this->esEntornoSurmar()) {
            return;
        }

        foreach (self::MENU['permisos'] as $perm) {
            $permisoId = DB::table('permiso')->where('slug', $perm['slug'])->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
        $menuId = DB::table('menu')->where('url', self::MENU['url'])->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
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

        return (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id')
            ?: DB::table('menu')->where('url', 'stock/recepcion-proveedor-surmar')->value('menu_id')
            ?: 0);
    }

    private function esEntornoSurmar(): bool
    {
        return strtoupper((string) config('app.empresa')) === 'EL BIERZO';
    }
};
