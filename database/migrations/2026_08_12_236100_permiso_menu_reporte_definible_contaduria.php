<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reportes definibles: permisos creados sin menu_id (no se ven en el ABM)
 * y Op-contaduria tenía el menú sin permiso. Suma Enc/Op-contaduría.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/reporte-definible';

    /** @var list<array{slug: string, nombre: string}> */
    private const PERMISOS = [
        ['slug' => 'listar-reporte-definible', 'nombre' => 'Listar reportes contables definibles'],
        ['slug' => 'crear-reporte-definible', 'nombre' => 'Crear reporte contable definible'],
        ['slug' => 'editar-reporte-definible', 'nombre' => 'Editar reporte contable definible'],
        ['slug' => 'actualizar-reporte-definible', 'nombre' => 'Actualizar reporte contable definible'],
        ['slug' => 'eliminar-reporte-definible', 'nombre' => 'Eliminar reporte contable definible'],
        ['slug' => 'ejecutar-reporte-definible', 'nombre' => 'Ejecutar reporte contable definible'],
        ['slug' => 'importar-reporte-definible', 'nombre' => 'Importar reportes definibles desde Anita'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['slug'], $perm['nombre'], $menuId);
        }

        $rolIds = $this->resolverRolIdsContaduria();
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
        $this->forgetPermisoRolCache($rolIds);
    }

    public function down(): void
    {
        $opIds = $this->resolverRolIdsPorLike('Op-contadur%');
        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty() && $opIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $opIds)
                ->delete();
        }
        foreach ($slugs as $slug) {
            DB::table('permiso')->where('slug', $slug)->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId,
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

    /**
     * @return list<int>
     */
    private function resolverRolIdsContaduria(): array
    {
        $ids = [];
        foreach (['administrador'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        foreach ($this->resolverRolIdsPorLike('Op-contadur%') as $id) {
            $ids[] = $id;
        }
        foreach ($this->resolverRolIdsPorLike('Enc-contadur%') as $id) {
            $ids[] = $id;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsPorLike(string $like): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', $like)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param list<int> $rolIds */
    private function forgetPermisoRolCache(array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            try {
                cache()->tags('Permiso')->forget('Permiso.rolid.'.$rolId);
            } catch (\Throwable) {
            }
        }
    }
};
