<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permisoVer = (int) (DB::table('permiso')->where('slug', 'ver-comprobante-cierre-turno-gastronomia')->value('id') ?? 0);
        $permisoListar = (int) (DB::table('permiso')->where('slug', 'listar-cierres-turno-gastronomia')->value('id') ?? 0);
        $permisoGestionar = (int) (DB::table('permiso')->where('slug', 'gestionar-habilitacion-turno-gastronomia')->value('id') ?? 0);
        $permisoFact = (int) (DB::table('permiso')->where('slug', 'usar-proceso-facturacion-gastronomia')->value('id') ?? 0);

        foreach ([$permisoFact, $permisoGestionar] as $refId) {
            if ($refId <= 0) {
                continue;
            }
            foreach (DB::table('permiso_rol')->where('permiso_id', $refId)->pluck('rol_id')->unique() as $rid) {
                $rid = (int) $rid;
                if ($permisoListar > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoListar)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoListar, 'rol_id' => $rid]);
                }
                if ($permisoVer > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoVer)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoVer, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        // Sin reversión: permisos compartidos con otros flujos.
    }
};
