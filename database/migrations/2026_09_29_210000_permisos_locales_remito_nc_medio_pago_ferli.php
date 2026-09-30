<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: el rol Locales (Lugano, Caballito y el resto de locales) consulta
 * remitos internos y facturas, pero no puede generar el remito ni ve los
 * iconos de cambiar medio de pago y nota de crédito.
 */
return new class extends Migration
{
    private const ROL = 'Locales';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'crear-remito-interno-facturacion-local',
        'actualizar-remito-interno-facturacion-local',
        'confirmar-remito-interno-facturacion-local',
        'pdf-remito-interno-facturacion-local',
        'generar-nota-credito-facturacion-local',
        'cambiar-medio-pago-facturacion-local',
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

        $permisos = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->get(['id']);

        foreach ($permisos as $permiso) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permiso->id)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => (int) $permiso->id,
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

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
