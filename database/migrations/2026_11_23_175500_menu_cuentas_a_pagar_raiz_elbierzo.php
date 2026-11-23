<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EL BIERZO: Cuentas a pagar pasa a módulo raíz (barra lateral),
 * como en Crown/Ferli/AGG. Antes solo estaba bajo Compras y no se veía
 * como módulo operativo. También restaura Precarga junto a Comprobantes.
 */
return new class extends Migration
{
    private const PADRE_NOMBRE = 'Cuentas a pagar';

    private const COMPRAS_NOMBRE = 'Módulo de Compras';

    private const PRECARGA_URL = 'compras/precarga_comprobante_proveedor';

    private const PRECARGA_NOMBRE = 'Precarga';

    private const COMPROBANTE_URL = 'compras/comprobante-proveedor';

    /** @var list<string> */
    private const PRECARGA_PERMISO_SLUGS = [
        'listar-precarga-proveedores',
        'crear-precarga-proveedores',
        'editar-precarga-proveedores',
        'actualizar-precarga-proveedores',
        'borrar-precarga-proveedores',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $capId = $this->idCuentasAPagar();
        if ($capId <= 0) {
            return;
        }

        if ((int) (DB::table('menu')->where('id', $capId)->value('menu_id') ?? -1) !== 0) {
            $ordenCompras = (int) (DB::table('menu')
                ->where('menu_id', 0)
                ->where('nombre', self::COMPRAS_NOMBRE)
                ->value('orden') ?? 0);

            $orden = $ordenCompras > 0 ? $ordenCompras + 1 : ((int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1);

            if ($ordenCompras > 0) {
                DB::table('menu')
                    ->where('menu_id', 0)
                    ->where('orden', '>=', $orden)
                    ->increment('orden');
            }

            DB::table('menu')->where('id', $capId)->update([
                'menu_id' => 0,
                'orden' => $orden,
                'icono' => 'fa-file-invoice-dollar',
                'updated_at' => now(),
            ]);
        }

        $this->asegurarPrecarga($capId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $capId = $this->idCuentasAPagarRaiz();
        $comprasId = $this->idModuloCompras();
        if ($capId <= 0 || $comprasId <= 0) {
            return;
        }

        $precargaId = (int) (DB::table('menu')
            ->where('url', self::PRECARGA_URL)
            ->where('menu_id', $capId)
            ->value('id') ?? 0);

        if ($precargaId > 0) {
            $comprobanteId = (int) (DB::table('menu')->where('url', self::COMPROBANTE_URL)->value('id') ?? 0);
            DB::table('menu_rol')->where('menu_id', $precargaId)->delete();
            DB::table('menu')->where('id', $precargaId)->delete();
            if ($comprobanteId > 0) {
                DB::table('permiso')
                    ->whereIn('slug', self::PRECARGA_PERMISO_SLUGS)
                    ->update([
                        'menu_id' => $comprobanteId,
                        'updated_at' => now(),
                    ]);
            }
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $comprasId)->max('orden') ?? 0) + 1;
        DB::table('menu')->where('id', $capId)->update([
            'menu_id' => $comprasId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarPrecarga(int $capId): void
    {
        $comprobante = DB::table('menu')
            ->where('url', self::COMPROBANTE_URL)
            ->orderBy('id')
            ->first();
        if (! $comprobante) {
            return;
        }

        $ordenComprobante = (int) ($comprobante->orden ?? 2);
        $ordenPrecarga = max(1, $ordenComprobante);

        $menuId = (int) (DB::table('menu')->where('url', self::PRECARGA_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            DB::table('menu')
                ->where('menu_id', $capId)
                ->where('orden', '>=', $ordenPrecarga)
                ->increment('orden');

            $menuId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::PRECARGA_NOMBRE,
                'url' => self::PRECARGA_URL,
                'menu_id' => $capId,
                'orden' => $ordenPrecarga,
                'icono' => 'fa-upload',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::PRECARGA_NOMBRE,
                'menu_id' => $capId,
                'orden' => $ordenPrecarga,
                'icono' => 'fa-upload',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) $comprobante->id)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $capId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $capId, 'rol_id' => $rolId]);
            }
        }

        DB::table('permiso')
            ->whereIn('slug', self::PRECARGA_PERMISO_SLUGS)
            ->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
    }

    private function idCuentasAPagar(): int
    {
        $raiz = $this->idCuentasAPagarRaiz();
        if ($raiz > 0) {
            return $raiz;
        }

        $comprasId = $this->idModuloCompras();
        if ($comprasId > 0) {
            $bajoCompras = (int) (DB::table('menu')
                ->where('menu_id', $comprasId)
                ->where('nombre', self::PADRE_NOMBRE)
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
            if ($bajoCompras > 0) {
                return $bajoCompras;
            }
        }

        return (int) (DB::table('menu')
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function idCuentasAPagarRaiz(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function idModuloCompras(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::COMPRAS_NOMBRE)
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
