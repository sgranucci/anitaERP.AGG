<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'uif/unificar-clientes';

    private const PERMISO_SLUG = 'unificar-cliente-uif';

    private const PERMISO_NOMBRE = 'Unificar clientes UIF';

    /** @var list<string> */
    private const REFERENCIAS_UIF = [
        'uif/cliente_uif',
        'uif/premio_uif',
        'uif/crearexportaoperacion',
        'uif/cliente_congelado_uif',
        'uif/conciliacion-wigos',
    ];

    public function up(): void
    {
        $padreUifId = $this->resolverMenuPadreUifId();
        if ($padreUifId === 0) {
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreUifId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Unificar clientes', $padreUifId, $orden, 'fa-object-ungroup');

        $rolIds = $this->resolverRolIdsConSupervisorUif();
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $menuId);
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
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

    private function resolverMenuPadreUifId(): int
    {
        foreach (self::REFERENCIAS_UIF as $url) {
            $padreId = (int) (DB::table('menu')->where('url', $url)->value('menu_id') ?? 0);
            if ($padreId > 0) {
                return $padreId;
            }
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%UIF%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * Roles con permiso supervisor-uif + administrador.
     *
     * @return list<int>
     */
    private function resolverRolIdsConSupervisorUif(): array
    {
        $ids = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->where('p.slug', 'supervisor-uif')
            ->pluck('pr.rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $ids[] = $adminId;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
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
