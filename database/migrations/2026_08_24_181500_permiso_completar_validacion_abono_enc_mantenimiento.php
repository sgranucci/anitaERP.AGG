<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El área que recibe el servicio (mantenimiento) completa la validación de abono.
 * El permiso había quedado solo en administrador; Enc-mantenimiento ya opera las COM.
 * Completa el responsable vacío de la OC 223191 con el de su OC gemela 223190.
 */
return new class extends Migration
{
    private const PERMISO = 'completar-validacion-abono';

    private const ROL = 'enc-mantenimiento';

    private const OC_SIN_RESPONSABLE = 223191;

    private const OC_GEMELA = 223190;

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->whereRaw('LOWER(nombre) = ?', [self::ROL])->value('id') ?? 0);
        if ($permisoId > 0 && $rolId > 0
            && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()
        ) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $responsableGemela = (int) (DB::table('ordencompra')
            ->where('numeroordencompra', self::OC_GEMELA)
            ->value('contrato_responsable_id') ?? 0);
        if ($responsableGemela > 0) {
            DB::table('ordencompra')
                ->where('numeroordencompra', self::OC_SIN_RESPONSABLE)
                ->whereNull('contrato_responsable_id')
                ->update(['contrato_responsable_id' => $responsableGemela]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->whereRaw('LOWER(nombre) = ?', [self::ROL])->value('id') ?? 0);
        if ($permisoId > 0 && $rolId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $responsableGemela = (int) (DB::table('ordencompra')
            ->where('numeroordencompra', self::OC_GEMELA)
            ->value('contrato_responsable_id') ?? 0);
        if ($responsableGemela > 0) {
            DB::table('ordencompra')
                ->where('numeroordencompra', self::OC_SIN_RESPONSABLE)
                ->where('contrato_responsable_id', $responsableGemela)
                ->update(['contrato_responsable_id' => null]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
