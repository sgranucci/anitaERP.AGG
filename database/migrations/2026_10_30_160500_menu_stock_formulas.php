<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Submenú Stock → Fórmulas con el ABM y el reporte de recetas.
 * El árbol de menú corre en todos los entornos.
 * La asignación a Ref-gastronomía y Control de gestión corre solo en AGG.
 */
return new class extends Migration
{
    private const URL_ABM = 'stock/formula-articulo';

    private const URL_REPORTE = 'stock/reporte-recetas';

    private const URL_REPORTE_ANTERIOR = 'ventas/gastronomia/recetas-reporte';

    private const PERMISO_SLUG = 'listar-recetas-reporte-gastronomia';

    public function up(): void
    {
        $stockId = $this->menuStockId();
        if ($stockId <= 0) {
            return;
        }

        $abmId = (int) (DB::table('menu')->where('url', self::URL_ABM)->value('id') ?? 0);
        $reporteId = $this->resolverReporteId();
        $ordenPadre = 4;
        if ($abmId > 0) {
            $abm = DB::table('menu')->where('id', $abmId)->first();
            if ($abm && (int) $abm->menu_id === $stockId) {
                $ordenPadre = (int) $abm->orden;
            }
        }

        $padreId = $this->asegurarCarpetaFormulas($stockId, $ordenPadre);

        if ($abmId > 0) {
            DB::table('menu')->where('id', $abmId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Fórmulas de artículos',
                'orden' => 1,
                'icono' => 'fa-flask',
                'updated_at' => now(),
            ]);
        }

        if ($reporteId <= 0) {
            $reporteId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Reporte de recetas',
                'url' => self::URL_REPORTE,
                'orden' => 2,
                'icono' => 'fa-book',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $reporteId)->update([
                'menu_id' => $padreId,
                'nombre' => 'Reporte de recetas',
                'url' => self::URL_REPORTE,
                'orden' => 2,
                'icono' => 'fa-book',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Listar reporte de recetas',
                'menu_id' => $reporteId,
                'updated_at' => now(),
            ]);
        }

        $rolesConHijo = DB::table('menu_rol')
            ->whereIn('menu_id', array_values(array_filter([$abmId, $reporteId])))
            ->pluck('rol_id')
            ->unique();
        foreach ($rolesConHijo as $rolId) {
            $this->asignarMenuRol($padreId, (int) $rolId);
        }

        if (EntornoEmpresaSupport::esAgg()) {
            foreach ($this->rolesAgg() as $rolId) {
                $this->asignarMenuRol($reporteId, $rolId);
                $this->asignarMenuRol($padreId, $rolId);
                $this->asignarMenuRol($stockId, $rolId);
                if ($permisoId > 0) {
                    $this->asignarPermisoRol($permisoId, $rolId);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $stockId = $this->menuStockId();
        $padreId = $stockId > 0
            ? (int) (DB::table('menu')->where('menu_id', $stockId)->where('nombre', 'Fórmulas')->where('url', '#')->value('id') ?? 0)
            : 0;

        $abmId = (int) (DB::table('menu')->where('url', self::URL_ABM)->value('id') ?? 0);
        if ($abmId > 0 && $stockId > 0) {
            DB::table('menu')->where('id', $abmId)->update([
                'menu_id' => $stockId,
                'orden' => 4,
                'updated_at' => now(),
            ]);
        }

        $reporteId = (int) (DB::table('menu')->where('url', self::URL_REPORTE)->value('id') ?? 0);
        $gastroId = (int) (DB::table('menu')->where('url', '#')->where('nombre', 'like', 'Reportes Gastronom%')->orderBy('id')->value('id') ?? 0);
        if ($reporteId > 0 && $gastroId > 0) {
            DB::table('menu')->where('id', $reporteId)->update([
                'menu_id' => $gastroId,
                'nombre' => 'Recetas',
                'url' => self::URL_REPORTE_ANTERIOR,
                'orden' => 9,
                'updated_at' => now(),
            ]);
        }

        if ($padreId > 0 && ! DB::table('menu')->where('menu_id', $padreId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function menuStockId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Módulo de Stock%')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function resolverReporteId(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::URL_REPORTE)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', self::URL_REPORTE_ANTERIOR)->value('id') ?? 0);
    }

    private function asegurarCarpetaFormulas(int $stockId, int $orden): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $stockId)
            ->where('nombre', 'Fórmulas')
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'orden' => $orden,
                'icono' => 'fa-flask',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $stockId,
            'nombre' => 'Fórmulas',
            'url' => '#',
            'orden' => $orden,
            'icono' => 'fa-flask',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function rolesAgg(): array
    {
        $ids = [];
        foreach (['Ref-gastronomía', 'Enc-Control de Gestión', 'Op-Control de Gestión'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0 && str_starts_with($nombre, 'Ref-')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Ref-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id <= 0 && str_contains($nombre, 'Control')) {
                $id = (int) (DB::table('rol')->where('nombre', 'like', $nombre.'%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function asignarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asignarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
