<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: Precarga de facturas de proveedor bajo Cuentas a pagar,
 * junto a Comprobantes. Los permisos ya existían colgados del menú de comprobantes.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/precarga_comprobante_proveedor';

    private const MENU_NOMBRE = 'Precarga';

    private const PADRE_NOMBRE = 'Cuentas a pagar';

    private const HERMANO_URL = 'compras/comprobante-proveedor';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'listar-precarga-proveedores',
        'crear-precarga-proveedores',
        'editar-precarga-proveedores',
        'actualizar-precarga-proveedores',
        'borrar-precarga-proveedores',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $padreId = $this->padreCuentasAPagarId();
        if ($padreId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('url', self::HERMANO_URL)->where('menu_id', $padreId)->value('orden') ?? 2);

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            DB::table('menu')
                ->where('menu_id', $padreId)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-upload',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $padreId,
                'orden' => $orden,
                'icono' => 'fa-upload',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) (DB::table('menu')->where('url', self::HERMANO_URL)->value('id') ?? 0))
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rolId]);
            }
        }

        DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $padreId = $this->padreCuentasAPagarId();
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $comprobanteMenuId = (int) (DB::table('menu')->where('url', self::HERMANO_URL)->value('id') ?? 0);

        if ($menuId > 0) {
            $orden = (int) (DB::table('menu')->where('id', $menuId)->value('orden') ?? 0);
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();

            if ($padreId > 0 && $orden > 0) {
                DB::table('menu')
                    ->where('menu_id', $padreId)
                    ->where('orden', '>', $orden)
                    ->decrement('orden');
            }
        }

        if ($comprobanteMenuId > 0) {
            DB::table('permiso')
                ->whereIn('slug', self::PERMISO_SLUGS)
                ->update([
                    'menu_id' => $comprobanteMenuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function padreCuentasAPagarId(): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
    }
};
