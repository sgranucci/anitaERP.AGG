<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISO = [
        'menu_url' => 'stock/movimientostock',
        'nombre' => 'Depósitos intercompany en movimientos de stock',
        'slug' => 'deposito-intercompany-movimientos-de-stock',
    ];

    private const ROLES = [
        'Enc-logistica',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::PERMISO['menu_url'])->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);

        foreach ($this->resolverRolIds(self::ROLES) as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
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
