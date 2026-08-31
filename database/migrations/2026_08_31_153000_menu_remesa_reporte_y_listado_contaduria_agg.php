<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Contaduría ve Remesas (padre) + Carga (solo listar) + Reporte.
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Remesas';

    private const MENU_CARGA_URL = 'caja/remesa';

    private const MENU_REPORTE_URL = 'caja/remesa-reporte';

    /** Solo lectura / consulta / listados (sin alta, edición, anular, configurar, revertir). */
    private const PERMISOS_LECTURA = [
        'listar-remesa',
        'listar-remesa-reporte',
    ];

    /** @var list<string> */
    private const PERMISOS_ESCRITURA = [
        'crear-remesa',
        'editar-remesa',
        'actualizar-remesa',
        'anular-remesa',
        'configurar-remesa',
        'revertir-remesa',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->resolverMenuCajaId();
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
        $cargaId = (int) (DB::table('menu')->where('url', self::MENU_CARGA_URL)->value('id') ?? 0);
        $reporteId = (int) (DB::table('menu')->where('url', self::MENU_REPORTE_URL)->value('id') ?? 0);

        if ($padreId <= 0 || $cargaId <= 0 || $reporteId <= 0) {
            return;
        }

        $rolIds = $this->resolverRolIdsContaduria();
        if ($rolIds === []) {
            return;
        }

        foreach ([$padreId, $cargaId, $reporteId] as $menuId) {
            $this->vincularMenuRoles($menuId, $rolIds);
        }

        foreach (self::PERMISOS_LECTURA as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                $this->vincularPermisoRol($permisoId, $rolId);
            }
        }

        $this->revocarPermisosEscritura($rolIds);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->resolverMenuCajaId();
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
        $cargaId = (int) (DB::table('menu')->where('url', self::MENU_CARGA_URL)->value('id') ?? 0);
        $reporteId = (int) (DB::table('menu')->where('url', self::MENU_REPORTE_URL)->value('id') ?? 0);
        $rolIds = $this->resolverRolIdsContaduria();

        if ($rolIds === []) {
            return;
        }

        foreach (array_filter([$padreId, $cargaId, $reporteId]) as $menuId) {
            DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_LECTURA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsContaduria(): array
    {
        $ids = [];
        foreach (['Enc-contadur%', 'Op-contadur%', 'Sup-contadur%', 'Ger-contadur%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    private function resolverMenuCajaId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Módulo de Caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : 104;
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function vincularMenuRoles(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            $exists = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
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

    /**
     * Contaduría: solo consulta. Si algún Enc tenía alta/edición previa, se quita.
     *
     * @param  list<int>  $rolIds
     */
    private function revocarPermisosEscritura(array $rolIds): void
    {
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_ESCRITURA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds === [] || $rolIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();
    }
};
