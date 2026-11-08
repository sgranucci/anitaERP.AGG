<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos Flash Crown (directivos). Solo CROWN.
 */
return new class extends Migration
{
    private const MENU_PADRE_URL = '#flash';

    private const MENU_PADRE_NOMBRE = 'Flash';

    /** @var list<array{nombre: string, url: string, icono: string, orden: int}> */
    private const HOJAS = [
        [
            'nombre' => 'Flash diario',
            'url' => 'caja/flash-crown',
            'icono' => 'fa-bolt',
            'orden' => 1,
        ],
        [
            'nombre' => 'Flash Report',
            'url' => 'caja/flash-crown/reporte',
            'icono' => 'fa-file-excel-o',
            'orden' => 2,
        ],
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar Flash Crown', 'slug' => 'listar-flash-crown'],
        ['nombre' => 'Crear Flash Crown', 'slug' => 'crear-flash-crown'],
        ['nombre' => 'Editar Flash Crown', 'slug' => 'editar-flash-crown'],
        ['nombre' => 'Actualizar Flash Crown', 'slug' => 'actualizar-flash-crown'],
        ['nombre' => 'Eliminar Flash Crown', 'slug' => 'eliminar-flash-crown'],
        ['nombre' => 'Validar Flash Crown', 'slug' => 'validar-flash-crown'],
        ['nombre' => 'Exportar Flash Crown', 'slug' => 'exportar-flash-crown'],
        ['nombre' => 'Listar Flash Report Crown', 'slug' => 'listar-flash-reporte-crown'],
        ['nombre' => 'Exportar Flash Report Crown', 'slug' => 'exportar-flash-reporte-crown'],
        ['nombre' => 'Administrar envíos Flash Report Crown', 'slug' => 'administrar-flash-reporte-crown'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Gerencia',
        'Gerente',
        'Director',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $tesoreriaId = $this->resolverMenuTesoreriaCrownId();
        if ($tesoreriaId <= 0) {
            return;
        }

        $padreId = $this->upsertMenu(
            self::MENU_PADRE_URL,
            self::MENU_PADRE_NOMBRE,
            $tesoreriaId,
            (int) (DB::table('menu')->where('menu_id', $tesoreriaId)->max('orden') ?? 0) + 1,
            'fa-bolt'
        );

        $hojaIds = [];
        foreach (self::HOJAS as $hoja) {
            $hojaIds[$hoja['url']] = $this->upsertMenu(
                $hoja['url'],
                $hoja['nombre'],
                $padreId,
                $hoja['orden'],
                $hoja['icono']
            );
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $permiso) {
            $menuId = str_contains($permiso['slug'], 'reporte')
                ? ($hojaIds['caja/flash-crown/reporte'] ?? $padreId)
                : ($hojaIds['caja/flash-crown'] ?? $padreId);
            $permisoIds[] = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        $menuIds = array_values(array_filter(array_merge([$tesoreriaId, $padreId], array_values($hojaIds))));
        foreach ($rolIds as $rolId) {
            foreach ($menuIds as $menuId) {
                $this->vincularMenuRol($menuId, $rolId);
            }
            foreach ($permisoIds as $permisoId) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        foreach (array_column(self::PERMISOS, 'slug') as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $urls = array_merge([self::MENU_PADRE_URL], array_column(self::HOJAS, 'url'));
        $ids = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $ids)->delete();
            DB::table('menu')->whereIn('id', $ids)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuTesoreriaCrownId(): int
    {
        return (int) (DB::table('menu')->where('url', '#tesoreria-crown')->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $admin = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($admin > 0) {
            $ids[] = $admin;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $existente = DB::table('menu')->where('url', $url)->first();
        if ($existente) {
            DB::table('menu')->where('id', $existente->id)->update([
                'nombre' => $nombre,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return (int) $existente->id;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        $existe = DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists();
        if (! $existe) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        $existe = DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists();
        if (! $existe) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }
};
