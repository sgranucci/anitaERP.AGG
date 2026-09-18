<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: el ABM Ingresos y Egresos exige can(listar/crear/editar-…)
 * pero esos slugs nunca se crearon (el menú sí). Al asignar el menú al rol
 * Sueldos, Mariana entra al ítem y can() la manda al inicio.
 *
 * Solo Ferli.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/ingresoegreso';

    private const ROL_SUELDOS = 'Sueldos';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_OPERATIVOS = [
        ['nombre' => 'Listar ingresos y egresos de caja', 'slug' => 'listar-ingresos-egresos-caja'],
        ['nombre' => 'Crear ingresos y egresos de caja', 'slug' => 'crear-ingresos-egresos-caja'],
        ['nombre' => 'Editar ingresos y egresos de caja', 'slug' => 'editar-ingresos-egresos-caja'],
    ];

    private const SLUG_ACTUALIZAR = 'actualizar-ingresos-egresos-caja';

    private const SLUG_TODOS = 'listar-todos-ingresos-egresos-caja';

    private const NOMBRE_TODOS = 'Listar todos los ingresos y egresos de caja';

    private const SLUG_BORRAR = 'borrar-ingresos-egresos-caja';

    private const NOMBRE_BORRAR = 'Borrar ingresos y egresos de caja';

    private const SLUG_CENTROCOSTO = 'usuario-ingresos-egresos-centrocosto';

    private const NOMBRE_CENTROCOSTO = 'Ver ingresos/egresos de su centro de costo';

    /** @var list<string> */
    private const ROLES_VER_TODOS = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
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

        $rolOperativos = $this->rolesConMenu($menuId);
        $sueldosId = (int) (DB::table('rol')->where('nombre', self::ROL_SUELDOS)->value('id') ?? 0);
        if ($sueldosId > 0) {
            $rolOperativos[] = $sueldosId;
            $rolOperativos = array_values(array_unique($rolOperativos));
        }

        foreach (self::PERMISOS_OPERATIVOS as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            $this->asignarPermisoRoles($permisoId, $rolOperativos);
        }

        $actualizarId = (int) (DB::table('permiso')->where('slug', self::SLUG_ACTUALIZAR)->value('id') ?? 0);
        if ($actualizarId > 0) {
            $this->asignarPermisoRoles($actualizarId, $rolOperativos);
        }

        $todosId = $this->upsertPermiso(self::NOMBRE_TODOS, self::SLUG_TODOS, $menuId);
        $this->asignarPermisoRoles($todosId, $this->resolverRolIds(self::ROLES_VER_TODOS));

        $borrarId = $this->upsertPermiso(self::NOMBRE_BORRAR, self::SLUG_BORRAR, $menuId);
        $this->asignarPermisoRoles($borrarId, $this->resolverRolIds(['administrador']));

        $this->upsertPermiso(self::NOMBRE_CENTROCOSTO, self::SLUG_CENTROCOSTO, $menuId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_merge(
            array_column(self::PERMISOS_OPERATIVOS, 'slug'),
            [self::SLUG_TODOS, self::SLUG_BORRAR, self::SLUG_CENTROCOSTO]
        );
        $ids = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
            'menu_id' => $menuId > 0 ? $menuId : null,
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
    private function rolesConMenu(int $menuId): array
    {
        return DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
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
