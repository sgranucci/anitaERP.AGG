<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: permiso temporal para importar pedidos/tareas desde L8 a L12
 * mientras producción sigue en el sistema legacy.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/pedido';

    private const PERMISO = [
        'nombre' => 'Importar pedido/tareas desde L8',
        'slug' => 'importar-pedido-l8',
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Admin-ventas',
        'Ventas',
        'Oficina',
        'Enc-sistemas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);
        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarPermisoRoles($permisoId, $rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $existente = DB::table('permiso')->where('slug', $slug)->first();
        if ($existente) {
            DB::table('permiso')->where('id', $existente->id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
