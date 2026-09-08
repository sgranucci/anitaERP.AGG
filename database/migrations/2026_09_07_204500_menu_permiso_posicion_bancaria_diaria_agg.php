<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permiso AGG: Posición bancaria diaria
 * - Módulo de Caja (junto a Posición financiera)
 * - Atajo en Contable → Reportes de integración
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/posicion-bancaria-diaria';

    private const MENU_NOMBRE = 'Posición bancaria diaria';

    private const PERMISO_SLUG = 'generar-posicion-bancaria-diaria';

    private const PERMISO_NOMBRE = 'Generar posición bancaria diaria';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $pfMenuId = (int) (DB::table('menu')->where('url', 'caja/posicion-financiera')->value('id') ?? 0);
        if ($pfMenuId > 0) {
            $extra = DB::table('menu_rol')->where('menu_id', $pfMenuId)->pluck('rol_id')->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, array_map('intval', $extra))));
        }

        $menuCajaId = 0;
        $cajaId = (int) (DB::table('menu')->where('nombre', 'Módulo de Caja')->value('id') ?? 0);
        if ($cajaId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 20) + 1;
            $menuCajaId = $this->upsertMenuEnPadre($cajaId, self::MENU_URL, self::MENU_NOMBRE, 'fa-university', $orden);
            foreach ($rolIds as $rolId) {
                $this->vincularMenuRol($menuCajaId, $rolId);
            }
        }

        $integracionId = (int) (DB::table('menu')
            ->where('url', '#reportes-integracion-contable')
            ->value('id') ?? 0);
        if ($integracionId > 0) {
            $ordenI = (int) (DB::table('menu')->where('menu_id', $integracionId)->max('orden') ?? 0) + 1;
            $menuIntId = $this->upsertMenuEnPadre(
                $integracionId,
                self::MENU_URL,
                self::MENU_NOMBRE,
                'fa-university',
                $ordenI
            );
            foreach ($rolIds as $rolId) {
                $this->vincularMenuRol($menuIntId, $rolId);
            }
        }

        $permisoMenuId = $menuCajaId > 0
            ? $menuCajaId
            : (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $permisoMenuId);
        foreach ($rolIds as $rolId) {
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $ids = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        foreach ($ids as $id) {
            DB::table('menu_rol')->where('menu_id', $id)->delete();
            DB::table('menu')->where('id', $id)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuEnPadre(int $padreId, string $url, string $nombre, string $icono, int $orden): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('url', $url)
            ->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => $nombre,
                'orden' => $orden,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
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
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        DB::table('menu_rol')->updateOrInsert(
            ['menu_id' => $menuId, 'rol_id' => $rolId],
            []
        );
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        DB::table('permiso_rol')->updateOrInsert(
            ['permiso_id' => $permisoId, 'rol_id' => $rolId],
            []
        );
    }
};
