<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El menú histórico Turnos (…/turnos) debe ser el ABM, no el listado vacío de cierres.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('menu')
            ->where('url', 'ventas/facturacion-local/turnos')
            ->update(['nombre' => 'Turnos', 'orden' => 3, 'updated_at' => now()]);

        DB::table('menu')
            ->where('url', 'ventas/facturacion-local/turno')
            ->update([
                'url' => 'ventas/facturacion-local/cierres-turno',
                'nombre' => 'Cierres de turno',
                'orden' => 4,
                'updated_at' => now(),
            ]);

        $this->forgetPermisosRoles();
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('menu')
            ->where('url', 'ventas/facturacion-local/cierres-turno')
            ->update([
                'url' => 'ventas/facturacion-local/turno',
                'nombre' => 'Turnos',
                'orden' => 3,
                'updated_at' => now(),
            ]);

        DB::table('menu')
            ->where('url', 'ventas/facturacion-local/turnos')
            ->update(['nombre' => 'Cierres de turno', 'orden' => 4, 'updated_at' => now()]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function forgetPermisosRoles(): void
    {
        $ids = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Admin-ventas', 'Enc-admin', 'Ventas'])
            ->pluck('id');
        foreach ($ids as $id) {
            PermisoCacheSupport::forgetRol((int) $id);
        }
    }
};
