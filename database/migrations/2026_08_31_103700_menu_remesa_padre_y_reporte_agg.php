<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Remesas (padre) + Carga + Reporte por cuenta de caja. Solo AGG (Biyemas).
 */
return new class extends Migration
{
    private const MENU_CARGA_URL = 'caja/remesa';

    private const MENU_REPORTE_URL = 'caja/remesa-reporte';

    private const MENU_PADRE_NOMBRE = 'Remesas';

    private const PERMISO_REPORTE = [
        'nombre' => 'Listar reporte remesas por cuenta de caja',
        'slug' => 'listar-remesa-reporte',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->resolverMenuCajaId();
        $carga = DB::table('menu')->where('url', self::MENU_CARGA_URL)->first();
        if ($carga === null || $cajaId <= 0) {
            return;
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $carga->id)
            ->pluck('rol_id')
            ->unique()
            ->all();

        $padreId = $this->upsertMenuPadre($cajaId, (int) $carga->orden, (string) $carga->icono);
        $this->vincularRoles($padreId, $rolIds);

        DB::table('menu')->where('id', $carga->id)->update([
            'menu_id' => $padreId,
            'nombre' => 'Carga de remesas',
            'orden' => 1,
            'icono' => 'fa-university',
            'updated_at' => now(),
        ]);

        $reporteId = $this->upsertMenu(
            self::MENU_REPORTE_URL,
            'Reporte',
            $padreId,
            2,
            'fa-file-alt'
        );
        $this->vincularRoles($reporteId, $rolIds);

        $permisoId = $this->upsertPermiso(self::PERMISO_REPORTE['nombre'], self::PERMISO_REPORTE['slug'], $reporteId);
        foreach ($rolIds as $rolId) {
            $this->vincularPermisoRol($permisoId, (int) $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaId = $this->resolverMenuCajaId();
        $carga = DB::table('menu')->where('url', self::MENU_CARGA_URL)->first();
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_REPORTE['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $reporteId = (int) (DB::table('menu')->where('url', self::MENU_REPORTE_URL)->value('id') ?? 0);
        if ($reporteId > 0) {
            DB::table('menu_rol')->where('menu_id', $reporteId)->delete();
            DB::table('menu')->where('id', $reporteId)->delete();
        }

        if ($carga !== null && $cajaId > 0) {
            DB::table('menu')->where('id', $carga->id)->update([
                'menu_id' => $cajaId,
                'nombre' => self::MENU_PADRE_NOMBRE,
                'orden' => $padreId > 0
                    ? (int) (DB::table('menu')->where('id', $padreId)->value('orden') ?? $carga->orden)
                    : $carga->orden,
                'updated_at' => now(),
            ]);
        }

        if ($padreId > 0) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
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

    private function upsertMenuPadre(int $cajaId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $cajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'orden' => $orden,
                'icono' => $icono !== '' ? $icono : 'fa-university',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $cajaId,
            'nombre' => self::MENU_PADRE_NOMBRE,
            'url' => '#',
            'orden' => $orden,
            'icono' => $icono !== '' ? $icono : 'fa-university',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

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

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<int|string>  $rolIds
     */
    private function vincularRoles(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
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
};
