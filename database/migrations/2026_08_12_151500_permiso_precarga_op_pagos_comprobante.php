<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Op-Pagos (frodriguez) y roles con listar-precarga: completar permisos de precarga
 * (crear/actualizar/borrar) que quedaron sin asignar a ningún rol.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PRECARGA_SLUGS = [
        'crear-precarga-proveedores',
        'actualizar-precarga-proveedores',
        'borrar-precarga-proveedores',
        'editar-precarga-proveedores',
        'listar-precarga-proveedores',
    ];

    public function up(): void
    {
        $rolIds = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso.slug', 'listar-precarga-proveedores')
            ->pluck('permiso_rol.rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $opPagosId = (int) (DB::table('rol')->where('nombre', 'Op-Pagos')->value('id') ?? 0);
        if ($opPagosId > 0 && ! in_array($opPagosId, $rolIds, true)) {
            $rolIds[] = $opPagosId;
        }

        // Roles que ya operan comprobante proveedor también necesitan precarga completa.
        $rolIdsCp = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso.slug', 'contabilizar-comprobante-proveedor')
            ->pluck('permiso_rol.rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsCp)));

        foreach (self::PRECARGA_SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
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
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No revierte asignaciones (podrían existir de forma legítima previa).
    }
};
