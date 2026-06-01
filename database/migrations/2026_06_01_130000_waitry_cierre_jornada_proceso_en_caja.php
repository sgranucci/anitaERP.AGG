<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Proceso de cierre (redistribución, asientos, facturación) en Caja → Waitry.
 * Quita el menú erróneo bajo Gastronomía y asigna permiso solo a administrador y encargado de tesorería.
 */
return new class extends Migration
{
    private const MENU_GASTRONOMIA_ERRONEO = 'ventas/gastronomia/cierre-jornada-proceso';

    private const MENU_WAITRY = 'caja/waitry-cierre-jornada';

    private const SLUG_PROCESO = 'proceso-cierre-jornada-waitry-caja';

    private const SLUG_PROCESO_GASTRONOMIA = 'proceso-cierre-jornada-gastronomia';

    /** @var list<string> */
    private const ROLES_PROCESO = [
        'administrador',
        'Enc-tesorería',
        'enc-Tesoreria Operativa',
    ];

    public function up(): void
    {
        $this->eliminarMenuGastronomiaErroneo();

        $menuWaitryId = (int) (DB::table('menu')->where('url', self::MENU_WAITRY)->value('id') ?? 0);
        if ($menuWaitryId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(
            'Proceso cierre jornada Waitry (asientos y facturación)',
            self::SLUG_PROCESO,
            $menuWaitryId,
        );

        $this->revocarPermisoDeSlug(self::SLUG_PROCESO_GASTRONOMIA);
        $this->asignarPermisoARoles($permisoId, $this->rolIdsPorNombre(self::ROLES_PROCESO));

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_PROCESO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function eliminarMenuGastronomiaErroneo(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_GASTRONOMIA_ERRONEO)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        foreach (DB::table('permiso')->where('menu_id', $menuId)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        DB::table('menu')->where('id', $menuId)->delete();
    }

    private function revocarPermisoDeSlug(string $slug): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function rolIdsPorNombre(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoARoles(int $permisoId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
