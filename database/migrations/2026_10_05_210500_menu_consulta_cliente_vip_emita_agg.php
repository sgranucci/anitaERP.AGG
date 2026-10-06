<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_NOMBRE_URL = 'ventas/gastronomia/canjes/cliente-vip-emita/nombre';

    private const MENU_ALIAS_URL = 'ventas/gastronomia/canjes/cliente-vip-emita/alias';

    private const MENU_VIP_ACTUAL_URL = 'ventas/gastronomia/canjes/cliente-vip';

    private const PERMISO_SLUG = 'consultar-cliente-vip-emita';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $canjesMenuId = $this->resolverMenuCanjesId();
        if ($canjesMenuId === 0) {
            return;
        }

        $menuNombreId = $this->asegurarMenu(
            $canjesMenuId,
            self::MENU_NOMBRE_URL,
            'VIP Emita por nombre',
            'fa-user'
        );
        $menuAliasId = $this->asegurarMenu(
            $canjesMenuId,
            self::MENU_ALIAS_URL,
            'VIP Emita por alias',
            'fa-id-badge'
        );

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Consultar clientes VIP Emita',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuNombreId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuNombreId,
                'nombre' => 'Consultar clientes VIP Emita',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')
            ->whereIn('menu_id', function ($q) {
                $q->select('id')->from('menu')->where('url', self::MENU_VIP_ACTUAL_URL);
            })
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
            foreach ([$menuNombreId, $menuAliasId, $canjesMenuId] as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarMenu(int $canjesMenuId, string $url, string $nombre, string $icono): int
    {
        $orden = (int) (DB::table('menu')->where('menu_id', $canjesMenuId)->max('orden') ?? 0) + 1;
        $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $canjesMenuId,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $canjesMenuId,
            'nombre' => $nombre,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    private function resolverMenuCanjesId(): int
    {
        $vipMenuId = (int) (DB::table('menu')->where('url', self::MENU_VIP_ACTUAL_URL)->value('menu_id') ?? 0);
        if ($vipMenuId > 0) {
            return $vipMenuId;
        }

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
            ->where('nombre', 'Canjes')
            ->orderBy('id')
            ->value('id') ?? 0);
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

        $menuIds = DB::table('menu')
            ->whereIn('url', [self::MENU_NOMBRE_URL, self::MENU_ALIAS_URL])
            ->pluck('id');
        foreach ($menuIds as $menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
