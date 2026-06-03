<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendiciongastronomia';

    /** @var list<string> */
    private const ROLES_DIA = ['Op-tesoreria', 'op-Tesoreria Operativa'];

    /** Encargados: pueden actualizar rendiciones de cualquier fecha. */
    private const ROLES_SIN_RESTRICCION = [
        'Enc-tesorería',
        'enc-Tesoreria Operativa',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisos = [
            [
                'nombre' => 'Actualizar rendición gastronomía caja en el día',
                'slug' => 'actualizar-rendicion-gastronomia-caja-dia',
                'roles' => self::ROLES_DIA,
            ],
            [
                'nombre' => 'Actualizar rendición gastronomía caja sin restricción de fecha',
                'slug' => 'actualizar-rendicion-gastronomia-caja-encargado',
                'roles' => [],
            ],
        ];

        foreach ($permisos as $row) {
            $permisoId = $this->upsertPermiso($row['nombre'], $row['slug'], $menuId);
            $this->asignarARoles(
                $permisoId,
                $row['slug'] === 'actualizar-rendicion-gastronomia-caja-encargado'
                    ? self::ROLES_SIN_RESTRICCION
                    : $row['roles'],
            );
        }

        SuitecrmPermiso::flushCachePermisos();
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
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    /**
     * @param  list<string>  $nombresRol
     */
    private function asignarARoles(int $permisoId, array $nombresRol): void
    {
        $rolIds = DB::table('rol')
            ->whereIn('nombre', $nombresRol)
            ->pluck('id')
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'actualizar-rendicion-gastronomia-caja-dia',
            'actualizar-rendicion-gastronomia-caja-encargado',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
