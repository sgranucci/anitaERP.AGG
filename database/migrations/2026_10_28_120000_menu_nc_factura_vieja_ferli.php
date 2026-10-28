<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: nota de crédito de facturas anteriores para locales y Myriam (administrador).
 * El neto va a L-NOTAS DE CREDITO LOCAL (113100009).
 */
return new class extends Migration
{
    private const PADRE_URL = '#facturacion-local';

    private const MENU_URL = 'ventas/facturacion-local/nota-credito-factura-vieja';

    private const MENU_NOMBRE = 'Notas de crédito Varias';

    private const PERMISO_SLUG = 'emitir-nc-factura-vieja-local';

    private const CUENTA_CODIGO = '113100009';

    /** @var list<string> */
    private const ROLES = [
        'Locales',
        'administrador',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->menu();
        $this->cuenta();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            DB::table('contabilidad_cuenta_automatica')
                ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_NOTAS_CREDITO)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function menu(): void
    {
        $padreId = (int) (DB::table('menu')->where('url', self::PADRE_URL)->orderBy('id')->value('id') ?? 0);
        if ($padreId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => 2,
                'icono' => 'fa-undo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Emitir notas de crédito varias',
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->rolIds() as $rolId) {
            foreach ([$padreId, $menuId] as $menuParaRol) {
                if (! DB::table('menu_rol')->where('menu_id', $menuParaRol)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuParaRol,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    private function cuenta(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('cuentacontable')
            ->where('codigo', self::CUENTA_CODIGO)
            ->distinct()
            ->pluck('empresa_id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', self::CUENTA_CODIGO)
                ->value('id') ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $existe = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_NOTAS_CREDITO)
                ->exists();

            if ($existe) {
                DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_NOTAS_CREDITO)
                    ->where(function ($q) {
                        $q->whereNull('cuentacontable_id')->orWhere('cuentacontable_id', 0);
                    })
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('contabilidad_cuenta_automatica')->insert([
                'empresa_id' => $empresaId,
                'clave' => CuentaAutomaticaClaves::FACTURACION_LOCAL_NOTAS_CREDITO,
                'cuentacontable_id' => $cuentaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function rolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
};
