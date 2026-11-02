<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Ventas → Transferencias.
 * Celina Chiappero (Enc-contaduría) y Lana Eluney Roldan (Sup-contaduria).
 */
return new class extends Migration
{
    private const PADRE = 'Transferencias';

    /** @var list<array{nombre: string, url: string, icono: string, permiso: string, permiso_nombre: string}> */
    private const HIJOS = [
        [
            'nombre' => 'Ventas de gastronomía',
            'url' => 'ventas/transferencia-gastronomia',
            'icono' => 'fa-cutlery',
            'permiso' => 'transferir-ventas-gastronomia-crown',
            'permiso_nombre' => 'Transferir ventas de gastronomía por sala',
        ],
        [
            'nombre' => 'Facturas ARCA',
            'url' => 'ventas/transferencia-pxsol',
            'icono' => 'fa-file-excel-o',
            'permiso' => 'transferir-facturas-arca-crown',
            'permiso_nombre' => 'Transferir facturas de ARCA (PXSOL)',
        ],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-contaduría',
        'Sup-contaduria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ventasId = $this->idModuloVentas();
        if ($ventasId <= 0) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('menu_id', $ventasId)->where('nombre', self::PADRE)->where('url', '#')->value('id') ?? 0);
        if ($padreId <= 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $ventasId)->max('orden') ?? 0) + 1;
            $padreId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::PADRE,
                'url' => '#',
                'menu_id' => $ventasId,
                'orden' => $orden,
                'icono' => 'fa-exchange',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuIds = [$ventasId, $padreId];
        foreach (self::HIJOS as $hijo) {
            $menuId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($menuId <= 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
                $menuId = (int) DB::table('menu')->insertGetId([
                    'nombre' => $hijo['nombre'],
                    'url' => $hijo['url'],
                    'menu_id' => $padreId,
                    'orden' => $orden,
                    'icono' => $hijo['icono'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('menu')->where('id', $menuId)->update([
                    'nombre' => $hijo['nombre'],
                    'menu_id' => $padreId,
                    'icono' => $hijo['icono'],
                    'updated_at' => now(),
                ]);
            }
            $menuIds[] = $menuId;

            $permisoId = (int) (DB::table('permiso')->where('slug', $hijo['permiso'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $hijo['permiso_nombre'],
                    'slug' => $hijo['permiso'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $hijo['permiso_nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }

            $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
            foreach ($rolIds as $rolId) {
                $rolId = (int) $rolId;
                if ($rolId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
                PermisoCacheSupport::forgetRol($rolId);
            }
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ($menuIds as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $urls = array_column(self::HIJOS, 'url');
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::PADRE)
            ->where('url', '#')
            ->where('menu_id', $this->idModuloVentas())
            ->value('id') ?? 0);

        foreach (self::HIJOS as $hijo) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $hijo['permiso'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
        if ($padreId > 0) {
            DB::table('menu_rol')->where('menu_id', $padreId)->delete();
            DB::table('menu')->where('id', $padreId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloVentas(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->whereIn('nombre', ['Módulo de Ventas', 'Módulo Ventas'])
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
