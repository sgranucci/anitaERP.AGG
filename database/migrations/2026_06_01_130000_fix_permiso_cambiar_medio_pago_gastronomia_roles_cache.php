<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'cambiar-medio-pago-gastronomia-facturas-dia';

    /** Mismos roles que nota de crédito en facturas del día (Enc-gastronomía + Sup-Gastronomia). */
    private const SLUG_REF_NOTA_CREDITO = 'generar-nota-credito-gastronomia-facturas-dia';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF_NOTA_CREDITO)->value('id') ?? 0);
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        } else {
            $rolIds = DB::table('permiso_rol')
                ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
                ->where('permiso.slug', 'listar-facturas-gastronomia-dia')
                ->pluck('permiso_rol.rol_id')
                ->unique()
                ->all();
            $rolIds = array_values(array_filter($rolIds, function ($rolId) {
                $nombre = (string) (DB::table('rol')->where('id', (int) $rolId)->value('nombre') ?? '');

                return stripos($nombre, 'Op-Gastronomia') === false;
            }));
        }

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rid,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF_NOTA_CREDITO)->value('id') ?? 0);
        if ($refPermisoId <= 0) {
            return;
        }

        $rolIdsRef = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        $encId = (int) (DB::table('rol')->where('nombre', 'Enc-gastronomía')->value('id') ?? 0);

        foreach ($rolIdsRef as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0 || $rid === $encId) {
                continue;
            }
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
