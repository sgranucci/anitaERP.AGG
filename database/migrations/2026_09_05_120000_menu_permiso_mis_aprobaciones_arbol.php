<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permiso para la bandeja unificada «Mis aprobaciones» del árbol.
 * Bajo Configuración → Árbol de aprobación (orden 0).
 * Se asigna a todos los roles con al menos un menú (firmantes pueden ser cualquiera).
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Árbol de aprobación';

    private const URL = 'configuracion/mis-aprobaciones';

    private const NOMBRE = 'Mis aprobaciones';

    private const ICONO = 'fa-inbox';

    private const PERMISO_NOMBRE = 'Aprobar mis pendientes del árbol';

    private const PERMISO_SLUG = 'aprobar-mis-aprobaciones-arbol';

    public function up(): void
    {
        $configId = $this->resolverMenuConfiguracionId();
        if ($configId === 0) {
            return;
        }

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configId)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            $ordenPadre = (int) (DB::table('menu')->where('url', 'configuracion/arbolaprobacion')->value('orden') ?? 13);
            $padreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => $ordenPadre,
                'icono' => 'fa-sitemap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuId = $this->upsertMenuHijo(self::URL, self::NOMBRE, $padreId, 0, self::ICONO);
        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $menuId);

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($configId, $rolId);
            $this->asegurarMenuRol($padreId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        // Carga / reemplazo quedan después de la bandeja.
        $cargaId = (int) (DB::table('menu')->where('url', 'configuracion/arbolaprobacion')->value('id') ?? 0);
        if ($cargaId > 0) {
            DB::table('menu')->where('id', $cargaId)->update(['orden' => 1, 'updated_at' => now()]);
        }
        $reemplazoId = (int) (DB::table('menu')->where('url', 'configuracion/reemplazo-firmante-arbol')->value('id') ?? 0);
        if ($reemplazoId > 0) {
            DB::table('menu')->where('id', $reemplazoId)->update(['orden' => 2, 'updated_at' => now()]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
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

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 33;
    }

    private function upsertMenuHijo(string $url, string $nombre, int $padreId, int $orden, ?string $icono): int
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
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        // Todos los roles que ya tienen algún menú asignado (operativos / firmantes).
        $ids = DB::table('menu_rol')->distinct()->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $admin = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%dmin%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($ids, $admin)));
    }
};
