<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seguridad: agrupa catálogos bajo "Tablas de seguridad" y reportes bajo "Reportes".
 * Los procesos (Control de ingreso, Ingreso de proveedores) quedan como hijos directos del módulo.
 */
return new class extends Migration
{
    private const MODULO = 'Seguridad';

    private const SUBMENU_TABLAS = 'Tablas de seguridad';

    private const SUBMENU_REPORTES = 'Reportes';

    /** @var list<string> */
    private const URLS_TABLAS = [
        'seguridad/ingreso-proveedor-punto',
        'seguridad/ingreso-proveedor-area',
        'seguridad/ingreso-proveedor-motivo',
        'seguridad/ingreso-proveedor-sector',
    ];

    /** @var list<string> */
    private const URLS_REPORTES = [
        'seguridad/reporte-tickets-ingreso',
        'seguridad/reporte-ingresos-planta',
    ];

    public function up(): void
    {
        $moduloId = $this->idModulo();
        if ($moduloId <= 0) {
            return;
        }

        $tablasId = $this->asegurarSubmenu($moduloId, self::SUBMENU_TABLAS, 'fa-table', 10);
        $reportesId = $this->asegurarSubmenu($moduloId, self::SUBMENU_REPORTES, 'fa-chart-bar', 20);

        $this->reparentar(self::URLS_TABLAS, $tablasId);
        $this->reparentar(self::URLS_REPORTES, $reportesId);

        $this->asignarRolesPadreDesdeHijos($tablasId, self::URLS_TABLAS);
        $this->asignarRolesPadreDesdeHijos($reportesId, self::URLS_REPORTES);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $moduloId = $this->idModulo();
        if ($moduloId <= 0) {
            return;
        }

        $this->reparentar(self::URLS_TABLAS, $moduloId);
        $this->reparentar(self::URLS_REPORTES, $moduloId);

        $this->borrarSubmenu($moduloId, self::SUBMENU_TABLAS);
        $this->borrarSubmenu($moduloId, self::SUBMENU_REPORTES);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModulo(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::MODULO)
            ->value('id') ?? 0);
    }

    private function asegurarSubmenu(int $moduloId, string $nombre, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $moduloId)
            ->where('nombre', $nombre)
            ->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'url' => '#',
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $moduloId,
            'nombre' => $nombre,
            'url' => '#',
            'orden' => $orden,
            'icono' => $icono,
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
     * El padre con url # solo se ve si el rol tiene menu_rol sobre el padre.
     *
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

    private function borrarSubmenu(int $moduloId, string $nombre): void
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $moduloId)
            ->where('nombre', $nombre)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($id <= 0) {
            return;
        }

        DB::table('menu_rol')->where('menu_id', $id)->delete();
        DB::table('menu')->where('id', $id)->delete();
    }
};
