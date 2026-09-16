<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: menú + permisos Importar deuda Anita (CAP, junto a Pago a proveedores).
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/importar-deuda-proveedor-anita';

    private const MENU_NOMBRE = 'Importar deuda Anita';

    private const PADRE_CXP_NOMBRE = 'Cuentas a pagar';

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar importar deuda proveedor Anita', 'slug' => 'listar-importar-deuda-proveedor-anita'],
        ['nombre' => 'Ejecutar importar deuda proveedor Anita', 'slug' => 'ejecutar-importar-deuda-proveedor-anita'],
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-pagos',
        'Op-Pagos',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $padreCxp = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($padreCxp <= 0) {
            return;
        }

        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->whereIn('url', ['compras/pagoproveedor', 'compras/propuesta-pago'])
            ->max('orden') ?? 5);
        $orden = $ordenRef + 1;

        DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::MENU_URL)
            ->increment('orden');

        $menuId = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);
        $now = now();

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-download',
                'updated_at' => $now,
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxp,
                'orden' => $orden,
                'icono' => 'fa-download',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $rolIds = DB::table('rol')
            ->where(function ($q) {
                $q->whereIn('nombre', self::ROLES)
                    ->orWhere('nombre', 'like', '%pagos%');
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $rolIds = array_values(array_unique(array_filter($rolIds)));

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreCxp)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $padreCxp,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'nombre' => $permiso['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => $now,
                ]);
            } else {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $permiso['slug'],
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
