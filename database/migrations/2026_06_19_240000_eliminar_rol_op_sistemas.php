<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROL_ELIMINAR = 'Op-sistemas';

    private const ROL_DESTINO = 'Tecnico de Tecnología';

    /** @var list<string> */
    private const SLUGS_PERMISO = [
        'listar-bien-uso',
        'crear-bien-uso',
        'editar-bien-uso',
        'actualizar-bien-uso',
        'borrar-bien-uso',
        'listar-reporte-movimientos-bien-uso',
        'listar-reporte-transferencias-pendientes',
    ];

    /** @var list<string> */
    private const URLS_MENU = [
        'contable/bien-uso',
        'stock/reporte-movimientos-bien-uso',
        'stock/reporte-transferencias-pendientes',
    ];

    public function up(): void
    {
        $rolOrigenId = (int) (DB::table('rol')->where('nombre', self::ROL_ELIMINAR)->value('id') ?? 0);
        $rolDestinoId = (int) (DB::table('rol')->where('nombre', self::ROL_DESTINO)->value('id') ?? 0);

        if ($rolDestinoId > 0) {
            $this->asignarPermisosRol($rolDestinoId);
            $this->asignarMenusRol($rolDestinoId);
        }

        if ($rolOrigenId > 0) {
            DB::table('permiso_rol')->where('rol_id', $rolOrigenId)->delete();
            DB::table('menu_rol')->where('rol_id', $rolOrigenId)->delete();
            DB::table('usuario_rol')->where('rol_id', $rolOrigenId)->delete();
            DB::table('rol')->where('id', $rolOrigenId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No recrear rol duplicado sobre producción.
    }

    private function asignarPermisosRol(int $rolId): void
    {
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_PERMISO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permisoIds as $permisoId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }
    }

    private function asignarMenusRol(int $rolId): void
    {
        $menuIds = [];

        foreach (self::URLS_MENU as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                $menuIds = array_merge($menuIds, $this->cadenaMenuIds($menuId));
            }
        }

        foreach (array_unique(array_filter($menuIds)) as $menuId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }
    }

    /** @return list<int> */
    private function cadenaMenuIds(int $menuId): array
    {
        $ids = [];
        $actual = $menuId;

        while ($actual > 0) {
            $ids[] = $actual;
            $actual = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
        }

        return $ids;
    }
};
