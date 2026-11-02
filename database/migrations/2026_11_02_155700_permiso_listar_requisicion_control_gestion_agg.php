<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Daniel Picerno (Op-Control de Gestión) y Romina Eirin (Enc-Control de Gestión)
 * no pueden generar requisiciones. El listado exige listar-requisicion; sin ese slug
 * el menú redirige con "No tienes permisos para entrar en este modulo".
 * Ya tienen crear/editar/actualizar. Enc además no tenía el ítem de menú.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/requisicion';

    private const MENU_PADRE = 'Módulo de Compras';

    private const PERMISO_SLUG = 'listar-requisicion';

    /** @var list<string> */
    private const ROLES = [
        'Enc-Control de Gestión',
        'Op-Control de Gestión',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            throw new \RuntimeException('Falta el permiso listar-requisicion.');
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }

            foreach (array_filter([$padreId, $menuId]) as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $mid,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $encId = (int) (DB::table('rol')->where('nombre', 'Enc-Control de Gestión')->value('id') ?? 0);
        if ($menuId > 0 && $encId > 0) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $encId)
                ->delete();
        }

        $this->invalidarCacheRoles($rolIds);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCacheRoles(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0) {
                $like = str_starts_with($nombre, 'Enc-')
                    ? 'Enc-Control de Gesti%'
                    : 'Op-Control de Gesti%';
                $id = (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
