<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Agrega Bonif." en el ABM cliente queda bajo modificar-descuento-cliente.
 * Debe estar en todos los roles que crean o editan clientes.
 */
return new class extends Migration
{
    private const PERMISO = 'modificar-descuento-cliente';

    /** @var list<string> */
    private const SLUGS_CARGA_CLIENTE = [
        'crear-clientes',
        'editar-clientes',
        'actualizar-clientes',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $refPermisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_CARGA_CLIENTE)
            ->pluck('id')
            ->all();

        if ($refPermisoIds === []) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->whereIn('permiso_id', $refPermisoIds)
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
        // Solo revierte roles que históricamente no tenían el permiso (no quitar Enc-admin / Enc-contaduría / administrador).
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['Despacho', 'Facturacion', 'Vendedor'])
            ->pluck('id')
            ->all();

        if ($rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
