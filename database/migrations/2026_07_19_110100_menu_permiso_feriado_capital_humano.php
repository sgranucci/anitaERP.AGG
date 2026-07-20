<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const HIJO_URL = 'configuracion/feriado';

    private const HIJO_NOMBRE = 'Días feriados';

    private const HIJO_ICONO = 'fa-calendar-alt';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar feriados', 'slug' => 'listar-feriado'],
        ['nombre' => 'Crear feriado', 'slug' => 'crear-feriado'],
        ['nombre' => 'Editar feriado', 'slug' => 'editar-feriado'],
        ['nombre' => 'Actualizar feriado', 'slug' => 'actualizar-feriado'],
        ['nombre' => 'Borrar feriado', 'slug' => 'borrar-feriado'],
        ['nombre' => 'Importar feriados', 'slug' => 'importar-feriado'],
    ];

    public function up(): void
    {
        $padreId = $this->resolverMenuConfiguracionId();

        $menuId = (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId, 'nombre' => self::HIJO_NOMBRE, 'url' => self::HIJO_URL,
                'orden' => $orden, 'icono' => self::HIJO_ICONO, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::HIJO_NOMBRE, 'icono' => self::HIJO_ICONO, 'updated_at' => now(),
            ]);
            $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? $padreId);
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        foreach ($this->resolverRolIds() as $rolId) {
            $this->asegurarMenuRol($menuId, $rolId);
            if ($padreId > 0) {
                $this->asegurarMenuRol($padreId, $rolId);
            }
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
        // Solo se revierte el permiso nuevo de importación y las asignaciones a roles;
        // el menú y los permisos base preexistentes del feriado se conservan.
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_map(fn ($p) => $p['slug'], self::PERMISOS))
            ->pluck('id');

        $rolIds = $this->resolverRolIds();
        if ($permisoIds->isNotEmpty() && $rolIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        DB::table('permiso')->where('slug', 'importar-feriado')->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        // Fallback: parent del feriado si ya existe.
        return (int) (DB::table('menu')->where('url', self::HIJO_URL)->value('menu_id') ?? 0);
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
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /**
     * Roles destino: administrador + todos los de Capital Humano.
     *
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
};
