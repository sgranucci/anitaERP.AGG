<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menú formal: worklist de excepciones de conciliación bancaria.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/conciliacion-bancaria-excepciones';

    private const MENU_NOMBRE = 'Worklist excepciones bancarias';

    private const PERMISO_SLUG = 'listar-conciliacion-bancaria-excepciones';

    private const PERMISO_NOMBRE = 'Listar worklist excepciones conciliación bancaria';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Ger-administracion', 'Enc-admin'];

    public function up(): void
    {
        if (! Schema::hasTable('menu') || ! Schema::hasTable('permiso')) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', 'contable/conciliacion-bancaria')->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('url', 'contable/conciliacion-bancaria-aging')->value('menu_id') ?? 0);
        }
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('url', '#contable')->value('id') ?? 0);
        }
        if ($padreId <= 0) {
            return;
        }

        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $padreId, 'fa-clipboard-list');
        $rolIds = $this->resolverRolIds();
        $this->asignarMenuRoles($menuId, $rolIds);

        $permisoId = $this->upsertPermiso(self::PERMISO_SLUG, self::PERMISO_NOMBRE, $menuId);
        foreach ($rolIds as $rolId) {
            DB::table('permiso_rol')->updateOrInsert(
                ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                []
            );
        }

        // Aging ya abre la worklist; el permiso aging sigue váliendo como fallback en el controller.
        $agingPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-conciliacion-bancaria-aging')->value('id') ?? 0);
        if ($agingPermisoId > 0) {
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $agingPermisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu') || ! Schema::hasTable('permiso')) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->orWhereRaw('LOWER(nombre) LIKE ?', ['%contadur%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertMenu(string $url, string $nombre, int $padre, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $padre)->max('orden') ?? 0) + 1;
        $payload = [
            'menu_id' => $padre,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            unset($payload['orden']);
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
    }

    /** @param  list<int>  $rolIds */
    private function asignarMenuRoles(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
        }
    }
};
