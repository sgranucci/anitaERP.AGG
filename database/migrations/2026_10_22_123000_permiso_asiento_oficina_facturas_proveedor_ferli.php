<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: Oficina carga facturas de proveedor y tiene que abrir el
 * asiento (botón «Abrir asiento») para corregir las cuentas.
 * Sin listar/editar el botón no aparece y el ABM redirige al inicio.
 * Sin actualizar no puede guardar el cambio de cuenta.
 * No otorga alta, baja ni cambio de empresa del asiento.
 */
return new class extends Migration
{
    private const ROL = 'Oficina';

    private const MENU_URL = 'contable/asiento';

    /** @var list<string> */
    private const PERMISOS = [
        'listar-asiento',
        'editar-asiento',
        'actualizar-asiento',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0 && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISOS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->where('rol_id', $rolId)->whereIn('permiso_id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
