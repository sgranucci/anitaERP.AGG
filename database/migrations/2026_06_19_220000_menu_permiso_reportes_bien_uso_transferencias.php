<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{nombre: string, slug: string, url: string, icono: string}> */
    private const REPORTES = [
        [
            'nombre' => 'Movimientos por bien de uso',
            'slug' => 'listar-reporte-movimientos-bien-uso',
            'url' => 'stock/reporte-movimientos-bien-uso',
            'icono' => 'fa-laptop',
        ],
        [
            'nombre' => 'Transferencias pendientes',
            'slug' => 'listar-reporte-transferencias-pendientes',
            'url' => 'stock/reporte-transferencias-pendientes',
            'icono' => 'fa-clock',
        ],
    ];

    public function up(): void
    {
        $stockMenuId = (int) (DB::table('menu')->where('url', '#')->where('nombre', 'Módulo de Stock')->value('id') ?? 10);
        $reportesPadreId = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->where('nombre', 'Reportes')->where('url', '#')->value('id') ?? 0);

        if ($reportesPadreId <= 0) {
            $ordenPadre = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
            $reportesPadreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $stockMenuId,
                'nombre' => 'Reportes',
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => 'fa-print',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesPadreId)->max('orden') ?? 0);
        $roles = $this->rolesStock();

        foreach (self::REPORTES as $reporte) {
            $orden++;
            $menuId = $this->upsertMenu($reporte['url'], $reporte['nombre'], $reportesPadreId, $orden, $reporte['icono']);
            $permisoId = $this->upsertPermiso($reporte['nombre'], $reporte['slug'], $menuId);

            foreach ($roles as $rolId) {
                DB::table('menu_rol')->updateOrInsert(['menu_id' => $menuId, 'rol_id' => $rolId], []);
                DB::table('permiso_rol')->updateOrInsert(['permiso_id' => $permisoId, 'rol_id' => $rolId], []);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::REPORTES as $reporte) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $reporte['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
            $menuId = (int) (DB::table('menu')->where('url', $reporte['url'])->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function rolesStock(): array
    {
        $slugs = [
            'listar-transferencias-pendientes',
            'crear-transferencia-mercaderia',
            'listar-bien-uso',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        $rolIds = DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->pluck('rol_id')->unique();

        if ($rolIds->isEmpty()) {
            $rolIds = DB::table('rol')->whereIn('nombre', ['administrador', 'Enc-admin'])->pluck('id');
        }

        return $rolIds->map(fn ($id) => (int) $id)->values()->all();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
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
};
