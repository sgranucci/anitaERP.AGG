<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna crear/editar/eliminar-borrador de slots a los roles que ya listan slots.
 * Al instalar, esos permisos quedaron sin rol (solo administrador vía bypass).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'crear-tesoreria-crown-slot',
        'editar-tesoreria-crown-slot',
        'eliminar-borrador-tesoreria-crown-slot',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $listarId = DB::table('permiso')->where('slug', 'listar-tesoreria-crown-slot')->value('id');
        if (! $listarId) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $listarId)
            ->pluck('rol_id');
        if ($rolIds->isEmpty()) {
            $rolIds = DB::table('rol')->whereIn('nombre', [
                'administrador',
                'Enc-sistemas',
                'Enc-tesorería',
                'enc-Tesoreria Operativa',
                'Ger-Tesoreria',
                'Op-tesoreria',
                'op-Tesoreria Operativa',
                'opflash-tesoreria',
                'Sup-tesoreria',
                'Enc-contaduría',
            ])->pluck('id');
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISOS)->pluck('id', 'slug');
        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) ($permisoIds[$slug] ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISOS)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
