<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'corregir-arqueo-cierre-turno-gastronomia';

    private const ROL_SUP_GASTRONOMIA = 'Sup-Gastronomia';

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_SUP_GASTRONOMIA)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_SUP_GASTRONOMIA)->value('id') ?? 0);
        if ($permisoId > 0 && $rolId > 0) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
        }
    }
};
