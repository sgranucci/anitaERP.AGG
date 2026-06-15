<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CANJES_NOMBRE = 'Canjes';

    private const MENU_LISTADO_URL = 'ventas/gastronomia/canjes/listado-marketing';

    private const PERMISO_SLUG = 'listar-canje-marketing-gastronomia';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $canjesMenuId = $this->resolverMenuCanjesId();
        if ($canjesMenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $canjesMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_LISTADO_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Listado canjes marketing',
                'url' => self::MENU_LISTADO_URL,
                'orden' => $orden,
                'icono' => 'fa-list-alt',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $canjesMenuId,
                'nombre' => 'Listado canjes marketing',
                'orden' => $orden,
                'icono' => 'fa-list-alt',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar canjes marketing gastronomía',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Listar canjes marketing gastronomía',
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
            if (! DB::table('menu_rol')->where('menu_id', $canjesMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $canjesMenuId, 'rol_id' => $rolId]);
            }
        }

        $refPermisoVip = (int) (DB::table('permiso')->where('slug', 'listar-cliente-vip-gastronomia')->value('id') ?? 0);
        if ($refPermisoVip > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $refPermisoVip)->pluck('rol_id')->unique() as $rid) {
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
                if (! DB::table('menu_rol')->where('menu_id', $canjesMenuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $canjesMenuId, 'rol_id' => $rid]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuCanjesId(): int
    {
        $gastronomiaId = (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')
                    ->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($gastronomiaId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $gastronomiaId)
            ->where('nombre', self::MENU_CANJES_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];

        $encGastro = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);
        if ($encGastro <= 0) {
            $encGastro = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->value('id') ?? 0);
        }
        if ($encGastro > 0) {
            $ids[] = $encGastro;
        }

        foreach (['enc-Marketing y CAC', 'Sup-Marketing', 'Op-Marketing'] as $nombre) {
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

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_LISTADO_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
