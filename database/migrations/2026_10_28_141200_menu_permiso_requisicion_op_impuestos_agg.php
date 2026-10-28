<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Marisol Gonzalez (mgonzalez, rol Op-impuestos) pide el programa de requisiciones.
 * Mismo alcance que los otros operadores de sector (Finanzas, Marketing, Legales):
 * menú + listar/crear/editar/actualizar, y ver las de su centro de costo.
 * Sin borrar, sin ver todas y sin el alcance de Compras.
 */
return new class extends Migration
{
    private const ROL = 'Op-impuestos';

    private const MENU_URL = 'compras/requisicion';

    private const MENU_PADRE = 'Módulo de Compras';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-requisicion',
        'crear-requisicion',
        'editar-requisicion',
        'actualizar-requisicion',
        'usuario-requisicion-resto',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = $this->resolverRolId();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($rolId <= 0 || $menuId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (count($permisoIds) !== count(self::PERMISO_SLUGS)) {
            throw new \RuntimeException('Faltan permisos de requisición para asignar a Op-impuestos.');
        }

        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        foreach (array_filter([$padreId, $menuId]) as $mid) {
            if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $mid,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach ($permisoIds as $permisoId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCacheRol($rolId);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolId = $this->resolverRolId();
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $permisoIds)->delete();
        }

        $this->invalidarCacheRol($rolId);
    }

    private function invalidarCacheRol(int $rolId): void
    {
        SuitecrmPermiso::flushCachePermisos();
        PermisoCacheSupport::forgetRol($rolId);
    }

    private function resolverRolId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')->where('nombre', 'like', 'Op-impuesto%')->orderBy('id')->value('id') ?? 0);
    }
};
