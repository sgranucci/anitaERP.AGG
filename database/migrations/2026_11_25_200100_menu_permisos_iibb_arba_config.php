<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos de «Configuración ARBA» (parámetros de liquidación y solicitud de pago).
 *
 * Ubicación: Configuración → Configuración por módulo → Contable → Presentaciones ARCA,
 * como hermana de «Configuración IIBB».
 * Roles: los mismos que ya tienen el permiso de «Configuración IIBB» (sin nombres fijos).
 */
return new class extends Migration
{
    private const URL_IIBB_CONFIG = 'contable/ingresos-brutos-config';

    private const SLUG_REFERENCIA = 'listar-ingresos-brutos-config';

    private const URL = 'contable/iibb-arba-config';

    private const NOMBRE = 'Configuración ARBA';

    private const ICONO = 'fa-cogs';

    private const PERMISOS = [
        ['Listar configuración ARBA', 'listar-iibb-arba-config'],
        ['Crear configuración ARBA', 'crear-iibb-arba-config'],
        ['Editar configuración ARBA', 'editar-iibb-arba-config'],
        ['Actualizar configuración ARBA', 'actualizar-iibb-arba-config'],
        ['Eliminar configuración ARBA', 'eliminar-iibb-arba-config'],
    ];

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::URL_IIBB_CONFIG)->value('menu_id') ?? 0);
        // Base vacía (CI lab PostgreSQL): el padre no existe porque sus menús se crean
        // desde la estructura del cliente. Igual que 2026_07_24_180200, se omite sin error.
        if ($padreId <= 0) {
            return;
        }

        $menuId = $this->upsertMenu(
            self::URL,
            self::NOMBRE,
            $padreId,
            $this->siguienteOrden($padreId),
            self::ICONO,
        );

        $rolIds = $this->rolesConPermiso(self::SLUG_REFERENCIA);

        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $menuId, 'rol_id' => $rolId],
                []
            );
            DB::table('menu_rol')->updateOrInsert(
                ['menu_id' => $padreId, 'rol_id' => $rolId],
                []
            );
        }

        foreach (self::PERMISOS as [$nombre, $slug]) {
            $permisoId = $this->upsertPermiso($nombre, $slug, $menuId);
            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_map(static fn (array $p) => $p[1], self::PERMISOS);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function rolesConPermiso(string $slug): array
    {
        return array_values(array_unique(array_map(
            'intval',
            DB::table('permiso_rol')
                ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
                ->where('permiso.slug', $slug)
                ->pluck('permiso_rol.rol_id')
                ->all(),
        )));
    }

    private function siguienteOrden(int $padreId): int
    {
        return (int) DB::table('menu')->where('menu_id', $padreId)->max('orden') + 1;
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
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
};
