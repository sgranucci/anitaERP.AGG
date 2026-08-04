<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para cargar en la requisición el CC del árbol (default = CC de origen).
 * Asignado a roles de Capital Humano (+ administrador). Sin el permiso, el flujo multi-CC actual no cambia.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/requisicion';

    private const PERMISO_NOMBRE = 'Cargar centro de costo del árbol en requisición';

    private const PERMISO_SLUG = 'cargar-centrocosto-arbol-requisicion';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
        'opcont-capitalhumano',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) (DB::table('permiso')->where('slug', 'crear-requisicion')->value('menu_id') ?? 0);
        }
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $menuId);

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
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
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

        // Fallback: cualquier rol Capital Humano por LIKE (por si cambian nombres).
        $likeIds = DB::table('rol')
            ->where('nombre', 'like', '%apital%umano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $likeIds)));
    }
};
