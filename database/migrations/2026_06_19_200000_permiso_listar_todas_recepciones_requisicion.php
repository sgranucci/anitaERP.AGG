<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISOS = [
        [
            'menu_url' => 'stock/recepcion-proveedor',
            'nombre' => 'Listar todas las recepciones de proveedor',
            'slug' => 'listar-todas-recepciones-proveedor',
        ],
        [
            'menu_url' => 'compras/requisicion',
            'nombre' => 'Listar todas las requisiciones',
            'slug' => 'listar-todas-requisicion',
        ],
    ];

    public function up(): void
    {
        $rolIds = $this->resolverRolesContaduriaCompras();

        foreach (self::PERMISOS as $permiso) {
            $menuId = (int) (DB::table('menu')->where('url', $permiso['menu_url'])->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);

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
        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesContaduriaCompras(): array
    {
        $rolIds = DB::table('rol')
            ->where(function ($q) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
                    ->orWhereRaw('LOWER(nombre) LIKE ?', ['%compra%']);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $permisoComprasId = (int) (DB::table('permiso')->where('slug', 'usuario-requisicion-compras')->value('id') ?? 0);
        if ($permisoComprasId > 0) {
            $desdePermiso = DB::table('permiso_rol')
                ->where('permiso_id', $permisoComprasId)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $rolIds = array_merge($rolIds, $desdePermiso);
        }

        return array_values(array_unique(array_filter($rolIds, fn (int $id) => $id > 0)));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
    }
};
