<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Padre Reportes de ventas bajo el módulo. Agrupa IVA ventas y Ventas por concepto.
 * En AGG no existía ese submenu y Ventas por concepto había quedado bajo UIF.
 */
return new class extends Migration
{
    private const SUBMENU = 'Reportes de ventas';

    /** @var list<string> */
    private const HIJOS = [
        'ventas/iva-ventas',
        'ventas/ventas-por-concepto',
    ];

    public function up(): void
    {
        $ventasId = $this->idModuloVentas();
        if ($ventasId <= 0) {
            return;
        }

        $ordenPadre = $this->ordenDondeEstabaIva($ventasId);
        $reportesId = $this->asegurarSubmenu($ventasId, $ordenPadre);
        $this->reparentar(self::HIJOS, $reportesId);
        $this->asignarRolesPadreDesdeHijos($reportesId, self::HIJOS);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $ventasId = $this->idModuloVentas();
        if ($ventasId <= 0) {
            return;
        }

        $this->reparentar(self::HIJOS, $ventasId);

        $reportesId = $this->idSubmenuReportes($ventasId);
        if ($reportesId > 0 && ! DB::table('menu')->where('menu_id', $reportesId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $reportesId)->delete();
            DB::table('menu')->where('id', $reportesId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloVentas(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->whereIn('nombre', ['Módulo de Ventas', 'Módulo Ventas'])
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'ventas/factura')->value('menu_id') ?? 0);
    }

    private function ordenDondeEstabaIva(int $ventasId): int
    {
        $ordenIva = (int) (DB::table('menu')
            ->where('url', 'ventas/iva-ventas')
            ->where('menu_id', $ventasId)
            ->value('orden') ?? 0);

        if ($ordenIva > 0) {
            return $ordenIva;
        }

        return (int) (DB::table('menu')->where('menu_id', $ventasId)->max('orden') ?? 0) + 1;
    }

    private function idSubmenuReportes(int $ventasId): int
    {
        foreach ([self::SUBMENU, 'Reportes'] as $nombre) {
            $id = (int) (DB::table('menu')
                ->where('menu_id', $ventasId)
                ->where('url', '#')
                ->where('nombre', $nombre)
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function asegurarSubmenu(int $ventasId, int $orden): int
    {
        $id = $this->idSubmenuReportes($ventasId);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => self::SUBMENU,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa-chart-bar',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $ventasId,
            'nombre' => self::SUBMENU,
            'url' => '#',
            'orden' => $orden,
            'icono' => 'fa-chart-bar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $urls
     */
    private function reparentar(array $urls, int $padreId): void
    {
        $orden = 1;
        foreach ($urls as $url) {
            $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($id <= 0) {
                continue;
            }
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
            $orden++;
        }
    }

    /**
     * @param  list<string>  $urlsHijos
     */
    private function asignarRolesPadreDesdeHijos(int $padreId, array $urlsHijos): void
    {
        $hijoIds = DB::table('menu')->whereIn('url', $urlsHijos)->pluck('id');
        if ($hijoIds->isEmpty()) {
            return;
        }

        $rolIds = DB::table('menu_rol')
            ->whereIn('menu_id', $hijoIds)
            ->distinct()
            ->pluck('rol_id');

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
