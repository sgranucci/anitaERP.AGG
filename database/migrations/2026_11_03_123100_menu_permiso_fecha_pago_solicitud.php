<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permiso: programar la fecha de pago de solicitudes autorizadas.
 * Vive en Solicitudes de pago y en Caja (junto a la posición bancaria diaria).
 */
return new class extends Migration
{
    private const MENU_URL = 'solicitudpago/fecha-pago';

    private const MENU_NOMBRE = 'Fecha de pago';

    private const PERMISO_SLUG = 'programar-fecha-pago-solicitud';

    private const PERMISO_NOMBRE = 'Programar fecha de pago de solicitud';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Enc-contaduria',
        'Op-contaduria',
        'Sup-contaduria',
        'Enc-impuestos',
        'Op-impuestos',
    ];

    public function up(): void
    {
        $rolIds = $this->roles();
        $menuIds = [];

        $moduloSp = (int) (DB::table('menu')
            ->where('nombre', 'Módulo Solicitudes de Pago')
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloSp > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $moduloSp)->max('orden') ?? 0) + 1;
            $menuIds[] = $this->upsertMenuEnPadre($moduloSp, self::MENU_URL, self::MENU_NOMBRE, 'fa-calendar', $orden);
        }

        $cajaId = (int) (DB::table('menu')->where('nombre', 'Módulo de Caja')->value('id') ?? 0);
        if ($cajaId > 0) {
            $ordenCaja = (int) (DB::table('menu')->where('menu_id', $cajaId)->max('orden') ?? 0) + 1;
            $menuIds[] = $this->upsertMenuEnPadre($cajaId, self::MENU_URL, self::MENU_NOMBRE, 'fa-calendar', $ordenCaja);
        }

        $permisoMenuId = $menuIds[0] ?? 0;
        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $permisoMenuId);
        foreach ($rolIds as $rolId) {
            foreach ($menuIds as $menuId) {
                $this->vincularMenuRol($menuId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
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

    /** @return list<int> */
    private function roles(): array
    {
        $ids = DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $menuPosicionId = (int) (DB::table('menu')->where('url', 'caja/posicion-bancaria-diaria')->value('id') ?? 0);
        if ($menuPosicionId > 0) {
            $extra = DB::table('menu_rol')->where('menu_id', $menuPosicionId)->pluck('rol_id')->all();
            $ids = array_merge($ids, array_map('intval', $extra));
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
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
