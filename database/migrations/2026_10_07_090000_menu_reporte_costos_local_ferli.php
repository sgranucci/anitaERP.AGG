<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Reporte de costos del local — solo Calzados Ferli.
 * Mismo permiso que Reportes Local (reportes-facturacion-local).
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    private const URL = 'ventas/facturacion-local/reporte-costos';

    private const MENU_REPORTES = 'ventas/facturacion-local/reportes';

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

        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->orderBy('id')->value('id') ?? 0);
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::URL, 'Costos del local', $padreId, $orden, 'fa-calculator');

        $rolIds = $this->rolesDelMenuReportes();
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }
        $rolIds = array_values(array_unique($rolIds));
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($padreId, $rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuIds = DB::table('menu')->where('url', self::URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
    }

    /**
     * @return list<int>
     */
    private function rolesDelMenuReportes(): array
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_REPORTES)->orderBy('id')->value('id') ?? 0);
        if ($menuId <= 0) {
            return [];
        }

        return DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->orderBy('id')->value('id') ?? 0);
        }

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param  list<int>  $rolIds */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0 || $rolIds === []) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
