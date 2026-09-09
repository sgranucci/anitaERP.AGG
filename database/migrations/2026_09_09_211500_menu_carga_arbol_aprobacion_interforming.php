<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: restaura «Carga de árbol» bajo Configuración → Árbol de aprobación
 * (faltaba menu url configuracion/arbolaprobacion y permisos CRUD del ABM).
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Árbol de aprobación';

    private const CARGA_URL = 'configuracion/arbolaprobacion';

    private const CARGA_NOMBRE = 'Carga de árbol';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Lista arbol de aprobacion', 'slug' => 'lista-arbol-de-aprobacion'],
        ['nombre' => 'Crea arbol de aprobacion', 'slug' => 'crea-arbol-de-aprobacion'],
        ['nombre' => 'Edita arbol de aprobacion', 'slug' => 'edita-arbol-de-aprobacion'],
        ['nombre' => 'Actualiza arbol de aprobacion', 'slug' => 'actualiza-arbol-de-aprobacion'],
        ['nombre' => 'Borra arbol de aprobacion', 'slug' => 'borra-arbol-de-aprobacion'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

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
            $padreId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $configId,
                'nombre' => self::PADRE_NOMBRE,
                'url' => '#',
                'orden' => 13,
                'icono' => 'fa-sitemap',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $cargaId = $this->upsertMenuHijo(self::CARGA_URL, self::CARGA_NOMBRE, $padreId, 1, 'fa-check');

        // Mis aprobaciones (0) / Carga (1) / Reemplazo (2)
        $misId = (int) (DB::table('menu')->where('url', 'mis-aprobaciones')->value('id') ?? 0);
        if ($misId === 0) {
            $misId = (int) (DB::table('menu')->where('url', 'configuracion/mis-aprobaciones')->value('id') ?? 0);
        }
        if ($misId > 0) {
            DB::table('menu')->where('id', $misId)->update([
                'menu_id' => $padreId,
                'orden' => 0,
                'updated_at' => now(),
            ]);
        }

        $reemplazoId = (int) (DB::table('menu')->where('url', 'configuracion/reemplazo-firmante-arbol')->value('id') ?? 0);
        if ($reemplazoId > 0) {
            DB::table('menu')->where('id', $reemplazoId)->update([
                'menu_id' => $padreId,
                'orden' => 2,
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $cargaId);
        }

        $rolIds = $this->resolverRolIds($padreId, $cargaId, $reemplazoId);
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($configId, $rolId);
            $this->asegurarMenuRol($padreId, $rolId);
            $this->asegurarMenuRol($cargaId, $rolId);
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $cargaId = (int) (DB::table('menu')->where('url', self::CARGA_URL)->value('id') ?? 0);
        if ($cargaId > 0) {
            DB::table('menu_rol')->where('menu_id', $cargaId)->delete();
            DB::table('menu')->where('id', $cargaId)->delete();
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

        return 0;
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
    private function resolverRolIds(int $padreId, int $cargaId, int $reemplazoId): array
    {
        $ids = collect();
        foreach ([$padreId, $cargaId, $reemplazoId] as $menuId) {
            if ($menuId > 0) {
                $ids = $ids->merge(DB::table('menu_rol')->where('menu_id', $menuId)->pluck('rol_id'));
            }
        }

        $admin = DB::table('rol')
            ->where(function ($q) {
                $q->where('nombre', 'administrador')
                    ->orWhere('nombre', 'like', '%dmin%');
            })
            ->pluck('id');

        return $ids->merge($admin)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
};
