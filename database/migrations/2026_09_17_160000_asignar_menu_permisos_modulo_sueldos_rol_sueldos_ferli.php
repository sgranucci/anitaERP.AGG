<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: asigna al rol Sueldos todos los menús y permisos
 * del Módulo Sueldos y Jornales (incluye indumentaria y config del módulo).
 *
 * No quita asignaciones previas ajenas al módulo (pago a proveedores, asientos, etc.).
 */
return new class extends Migration
{
    private const ROL = 'Sueldos';

    private const MENU_MODULO = 'Módulo Sueldos y Jornales';

    private const MENU_CONFIG_MODULO = '#config-modulo-sueldos';

    /** Slugs de indumentaria del módulo sin sufijo -sueldos. */
    private const SLUGS_INDUMENTARIA = [
        'ver-configuracion-indumentaria',
        'editar-configuracion-indumentaria',
        'listar-entrega-prenda',
        'entregar-prenda',
        'anular-entrega-prenda',
        'ver-planificacion-indumentaria',
        'listar-solicitud-indumentaria',
        'crear-solicitud-indumentaria',
        'aprobar-solicitud-indumentaria',
        'ver-aprobacion-indumentaria',
        'editar-aprobacion-indumentaria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuIds = $this->menuIdsModuloSueldos();
        foreach ($menuIds as $menuId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach ($this->permisoIdsModuloSueldos() as $permisoId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuIds = $this->menuIdsModuloSueldos();
        if ($menuIds !== []) {
            DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->whereIn('menu_id', $menuIds)
                ->delete();
        }

        $permisoIds = $this->permisoIdsModuloSueldos();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Árbol del módulo + config sueldos + ancestros hasta la raíz.
     *
     * @return list<int>
     */
    private function menuIdsModuloSueldos(): array
    {
        $rootIds = [];

        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where(function ($q) {
                $q->where('url', '#')->orWhere('url', '')->orWhereNull('url');
            })
            ->value('id') ?? 0);
        if ($moduloId > 0) {
            $rootIds[] = $moduloId;
        }

        $configId = (int) (DB::table('menu')->where('url', self::MENU_CONFIG_MODULO)->value('id') ?? 0);
        if ($configId > 0) {
            $rootIds[] = $configId;
        }

        if ($rootIds === []) {
            return [];
        }

        $all = DB::table('menu')->get(['id', 'menu_id']);
        $byParent = [];
        foreach ($all as $m) {
            $byParent[(int) $m->menu_id][] = (int) $m->id;
        }

        $ids = [];
        $stack = $rootIds;
        while ($stack !== []) {
            $id = (int) array_pop($stack);
            if (isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            foreach ($byParent[$id] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        // Ancestros (Configuración / Configuración por módulo) para que el árbol sea visible.
        $byId = [];
        foreach ($all as $m) {
            $byId[(int) $m->id] = (int) $m->menu_id;
        }
        foreach (array_keys($ids) as $id) {
            $cur = $id;
            while ($cur > 0) {
                $padre = $byId[$cur] ?? 0;
                if ($padre <= 0) {
                    break;
                }
                $ids[$padre] = true;
                $cur = $padre;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @return list<int>
     */
    private function permisoIdsModuloSueldos(): array
    {
        $idsSueldos = DB::table('permiso')
            ->where('slug', 'like', '%sueldos%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $idsIndumentaria = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_INDUMENTARIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($idsSueldos, $idsIndumentaria)));
    }
};
