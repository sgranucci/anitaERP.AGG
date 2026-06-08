<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/recuento';

    public function up(): void
    {
        $stockMenuId = $this->resolverMenuStockId();
        $refMenuId = (int) (DB::table('menu')->where('url', 'stock/movimientostock')->value('id') ?? $stockMenuId);
        $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;

        $menuId = $this->upsertMenu(self::MENU_URL, 'Recuento de inventario', $stockMenuId, $orden, 'fa-clipboard');

        $slugs = [
            ['nombre' => 'Listar recuentos', 'slug' => 'listar-recuento'],
            ['nombre' => 'Ingresar recuento', 'slug' => 'crear-recuento'],
            ['nombre' => 'Editar recuento', 'slug' => 'editar-recuento'],
            ['nombre' => 'Actualizar recuento', 'slug' => 'actualizar-recuento'],
            ['nombre' => 'Borrar recuento', 'slug' => 'borrar-recuento'],
            ['nombre' => 'Ver recuento', 'slug' => 'ver-recuento'],
            ['nombre' => 'Suspender recuento', 'slug' => 'suspender-recuento'],
            ['nombre' => 'Reactivar recuento', 'slug' => 'reactivar-recuento'],
            ['nombre' => 'Anular recuento', 'slug' => 'anular-recuento'],
            ['nombre' => 'Cerrar recuento parcial', 'slug' => 'cerrar-recuento-parcial'],
            ['nombre' => 'Cerrar recuento total', 'slug' => 'cerrar-recuento-total'],
            ['nombre' => 'Anular cierre de recuento', 'slug' => 'anular-cierre-recuento'],
            ['nombre' => 'Imprimir recuento PDF', 'slug' => 'imprimir-recuento'],
            ['nombre' => 'Importar recuento Excel', 'slug' => 'importar-recuento'],
            ['nombre' => 'Generar recuento aleatorio', 'slug' => 'recuento-aleatorio'],
        ];
        $this->upsertPermisos($slugs, $menuId, $refMenuId);
    }

    private function resolverMenuStockId(): int
    {
        $id = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Stock')
                    ->orWhere('nombre', 'like', '%Stock%')
                    ->orWhere('nombre', 'like', '%stock%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        $padreFallback = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('menu_id') ?? 0);

        return $padreFallback > 0 ? $padreFallback : 10;
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

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
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
            'listar-recuento', 'crear-recuento', 'editar-recuento', 'actualizar-recuento',
            'borrar-recuento', 'ver-recuento', 'suspender-recuento', 'reactivar-recuento',
            'anular-recuento', 'cerrar-recuento-parcial', 'cerrar-recuento-total',
            'anular-cierre-recuento', 'imprimir-recuento', 'importar-recuento', 'recuento-aleatorio',
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
