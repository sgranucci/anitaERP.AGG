<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/auditoria-notas-suitecrm';

    private const PERMISO_SLUG = 'listar-auditoria-notas-suitecrm';

    private const MENU_REPORTES_ID = 191;

    public function up(): void
    {
        $reportesMenuId = (int) (DB::table('menu')->where('id', self::MENU_REPORTES_ID)->value('id') ?? 0);
        if ($reportesMenuId <= 0) {
            $reportesMenuId = (int) (DB::table('menu')
                ->where('nombre', 'Reportes')
                ->where('url', '#')
                ->where('menu_id', 51)
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($reportesMenuId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $reportesMenuId,
                'nombre' => 'Auditoría notas CRM',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-sticky-note',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $reportesMenuId,
                'nombre' => 'Auditoría notas CRM',
                'orden' => $orden,
                'icono' => 'fa-sticky-note',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar auditoría de notas SuiteCRM',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Listar auditoría de notas SuiteCRM',
                'updated_at' => now(),
            ]);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $reportesMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $reportesMenuId, 'rol_id' => $rolId]);
            }
        }

        $refPermiso = (int) (DB::table('permiso')->where('slug', 'listar-notas-suitecrm-cliente')->value('id') ?? 0);
        if ($refPermiso > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $refPermiso)->pluck('rol_id')->unique() as $rid) {
                $rid = (int) $rid;
                if ($rid <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $reportesMenuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $reportesMenuId, 'rol_id' => $rid]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (['administrador', 'Sistemas', 'Ventas', 'Jefe_Ventas', 'Enc-admin'] as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
