<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú maestro Turnos + permisos CRUD. Renombra listado operativo a Cierres de turno.
 * Solo Calzados Ferli.
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    private const MAESTRO_URL = 'ventas/facturacion-local/turno';

    private const OPERATIVO_URL = 'ventas/facturacion-local/turnos';

    /** @var list<array{nombre:string,slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar turnos locales', 'slug' => 'listar-turno-local'],
        ['nombre' => 'Crear turnos locales', 'slug' => 'crear-turno-local'],
        ['nombre' => 'Editar turnos locales', 'slug' => 'editar-turno-local'],
        ['nombre' => 'Actualizar turnos locales', 'slug' => 'actualizar-turno-local'],
        ['nombre' => 'Borrar turnos locales', 'slug' => 'borrar-turno-local'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Admin-ventas',
        'Enc-admin',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        DB::table('menu')
            ->where('url', self::OPERATIVO_URL)
            ->update(['nombre' => 'Cierres de turno', 'orden' => 4, 'updated_at' => now()]);

        $maestroId = $this->upsertMenu(self::MAESTRO_URL, 'Turnos', $padreId, 3, 'fa-clock');

        $rolIds = $this->resolverRolIds(self::ROLES);
        $this->asignarRolesMenu($maestroId, $rolIds);

        foreach (self::PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $maestroId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_column(self::PERMISOS, 'slug');
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MAESTRO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        DB::table('menu')
            ->where('url', self::OPERATIVO_URL)
            ->update(['nombre' => 'Turnos', 'orden' => 3, 'updated_at' => now()]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'url' => $url,
            'created_at' => now(),
        ]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
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

    /** @param list<int> $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $exists = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @param list<int> $rolIds */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $exists = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        return DB::table('rol')->whereIn('nombre', $nombres)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
};
