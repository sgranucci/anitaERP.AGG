<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Submenú Ventas → Cuenta corriente + reporte deuda / ficha CC clientes.
 * Genérico para todos los clientes (sin gate por EMPRESA).
 */
return new class extends Migration
{
    private const SUBMENU_URL = '#cuenta-corriente-ventas';

    private const SUBMENU_NOMBRE = 'Cuenta corriente';

    private const MENU_URL = 'ventas/cliente-cuentacorriente-reporte';

    private const MENU_NOMBRE = 'Deuda / ficha de clientes';

    private const PERMISO_SLUG = 'listar-cliente-cuentacorriente-reporte';

    private const PERMISO_NOMBRE = 'Listar reporte deuda / CC clientes';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Ger-administracion',
        'Enc-contaduría',
        'Op-contaduria',
        'Ventas',
        'Admin-ventas',
    ];

    public function up(): void
    {
        $ventasId = $this->idModuloVentas();
        if ($ventasId <= 0) {
            return;
        }

        $submenuId = $this->asegurarSubmenuCuentaCorriente($ventasId);
        $ordenHoja = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, self::MENU_NOMBRE, $submenuId, $ordenHoja, 'fa-file-invoice-dollar');

        $permisoId = $this->upsertPermiso($menuId);
        $rolIds = $this->resolverRolIds();

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($submenuId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            $this->asegurarMenuRol($ventasId, $rolId);
            $this->asegurarPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $submenuId = (int) (DB::table('menu')->where('url', self::SUBMENU_URL)->value('id') ?? 0);
        if ($submenuId > 0 && ! DB::table('menu')->where('menu_id', $submenuId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
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

        return (int) (DB::table('menu')->where('url', 'ventas/cliente')->value('menu_id') ?? 0);
    }

    private function asegurarSubmenuCuentaCorriente(int $ventasId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where(function ($q) {
                $q->where('url', self::SUBMENU_URL)
                    ->orWhere(function ($q2) {
                        $q2->where('nombre', self::SUBMENU_NOMBRE)
                            ->where('url', '#');
                    });
            })
            ->value('id') ?? 0);

        $ordenClientes = (int) (DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where('url', 'ventas/cliente')
            ->value('orden') ?? 0);
        $orden = $ordenClientes > 0 ? $ordenClientes + 1 : ((int) (DB::table('menu')->where('menu_id', $ventasId)->max('orden') ?? 0) + 1);

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $ventasId,
                'nombre' => self::SUBMENU_NOMBRE,
                'url' => self::SUBMENU_URL,
                'icono' => 'fa-folder-open',
                'updated_at' => now(),
            ]);

            return $id;
        }

        DB::table('menu')
            ->where('menu_id', $ventasId)
            ->where('orden', '>=', $orden)
            ->increment('orden');

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $ventasId,
            'nombre' => self::SUBMENU_NOMBRE,
            'url' => self::SUBMENU_URL,
            'orden' => $orden,
            'icono' => 'fa-folder-open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => self::PERMISO_NOMBRE,
            'slug' => self::PERMISO_SLUG,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

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

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
        }
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (['ventas/iva-ventas', 'ventas/cliente'] as $urlRef) {
            $refMenuId = (int) (DB::table('menu')->where('url', $urlRef)->value('id') ?? 0);
            if ($refMenuId <= 0) {
                continue;
            }
            foreach (DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id') as $rolId) {
                $rid = (int) $rolId;
                if ($rid > 0 && ! in_array($rid, $ids, true)) {
                    $ids[] = $rid;
                }
            }
        }

        $permCc = (int) (DB::table('permiso')->where('slug', 'listar-cuentacorriente-cliente')->value('id') ?? 0);
        if ($permCc > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $permCc)->pluck('rol_id') as $rolId) {
                $rid = (int) $rolId;
                if ($rid > 0 && ! in_array($rid, $ids, true)) {
                    $ids[] = $rid;
                }
            }
        }

        return $ids;
    }
};
