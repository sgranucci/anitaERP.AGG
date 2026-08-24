<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regularizar cliente (estado R) para roles que ya editan clientes.
 * Oscar y similares no tenían suspender-clientes y el botón no aparecía.
 * Solo EL BIERZO.
 */
return new class extends Migration
{
    private const PERMISO = 'suspender-clientes';

    /** @var list<string> */
    private const SLUGS_EDICION = [
        'editar-clientes',
        'actualizar-clientes',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $refIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_EDICION)
            ->pluck('id')
            ->all();
        if ($refIds === []) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->whereIn('permiso_id', $refIds)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            if ($rolId <= 0) {
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
        // No revierte asignaciones: podría quitar suspender-clientes a roles que ya lo tenían.
    }
};
