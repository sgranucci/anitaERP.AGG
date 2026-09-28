<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: envío masivo de OP a proveedores, en Cuentas a pagar,
 * visible para los roles de pagos.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor/envio-masivo';

    private const MENU_NOMBRE = 'Envío de OP a proveedores';

    private const PERMISO = 'enviar-op-masivo-proveedor';

    private const PERMISO_NOMBRE = 'Enviar órdenes de pago a proveedores';

    private const PADRE_CXP_NOMBRE = 'Cuentas a pagar';

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

        $padreCxp = $this->resolverPadreCxpId();
        if ($padreCxp <= 0) {
            return;
        }

        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->whereIn('url', ['compras/pagoproveedor', 'compras/propuesta-pago'])
            ->max('orden') ?? 5);
        $orden = $ordenRef + 1;

        $ordenOcupado = DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('orden', $orden)
            ->where('url', '!=', self::MENU_URL)
            ->exists();
        if ($ordenOcupado) {
            DB::table('menu')
                ->where('menu_id', $padreCxp)
                ->where('orden', '>=', $orden)
                ->where('url', '!=', self::MENU_URL)
                ->increment('orden');
        }

        $menuId = (int) (DB::table('menu')
            ->where('menu_id', $padreCxp)
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);

        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-envelope',
                'updated_at' => now(),
            ]);
        } else {
            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreCxp,
                'orden' => $orden,
                'icono' => 'fa-envelope',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
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

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $padreCxp = $this->resolverPadreCxpId();
        $menuId = (int) (DB::table('menu')
            ->when($padreCxp > 0, fn ($q) => $q->where('menu_id', $padreCxp))
            ->where('url', self::MENU_URL)
            ->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverPadreCxpId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->where('menu_id', 0)
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', self::PADRE_CXP_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
