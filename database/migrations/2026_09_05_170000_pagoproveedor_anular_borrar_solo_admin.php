<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pagos a proveedores: anular físico y borrar quedan solo para administrador
 * (el resto usa Revertir), igual que ingresos/egresos en 2026_08_31_183000.
 *
 * Anular físico borra el asiento y elimina pago/auxpag/tesmov en Anita: es
 * destructivo y no deja rastro. Revertir genera OP compensatoria + contraasiento.
 */
return new class extends Migration
{
    private const SLUG_ANULAR = 'anular-pagoproveedor';

    private const SLUG_BORRAR = 'borrar-pagoproveedor';

    private const ROL_ADMIN = 'administrador';

    /** Roles que tenían el permiso antes (misma lista que 2026_08_11_230200). */
    private const ROLES_PREVIOS = [
        ['nombre' => 'Enc-contaduría', 'like' => 'Enc-contadur%'],
        ['nombre' => 'Op-contaduria', 'like' => 'Op-contadur%'],
        ['nombre' => 'Enc-finanzas', 'like' => 'Enc-finanz%'],
        ['nombre' => 'Op-Finanzas', 'like' => 'Op-Finanz%'],
        ['nombre' => 'Enc-tesorería', 'like' => 'Enc-tesorer%'],
        ['nombre' => 'enc-Tesoreria Operativa', 'like' => 'enc-Tesoreria Operativa%'],
        ['nombre' => 'Ger-Tesoreria', 'like' => 'Ger-Tesorer%'],
        ['nombre' => 'Op-tesoreria', 'like' => 'Op-tesorer%'],
        ['nombre' => 'op-Tesoreria Operativa', 'like' => 'op-Tesoreria Operativa%'],
        ['nombre' => 'Sup-tesoreria', 'like' => 'Sup-tesorer%'],
        ['nombre' => 'Enc-pagos', 'like' => 'Enc-pago%'],
        ['nombre' => 'Op-Pagos', 'like' => 'Op-Pago%'],
    ];

    public function up(): void
    {
        $adminRolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMIN)->value('id') ?? 0);

        $this->restringirSoloAdmin(self::SLUG_ANULAR, $adminRolId);
        $this->restringirSoloAdmin(self::SLUG_BORRAR, $adminRolId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolIds = $this->resolverRolIdsPrevios();

        foreach ([self::SLUG_ANULAR, self::SLUG_BORRAR] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
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

        SuitecrmPermiso::flushCachePermisos();
    }

    private function restringirSoloAdmin(string $slug, int $adminRolId): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->when($adminRolId > 0, fn ($q) => $q->where('rol_id', '!=', $adminRolId))
            ->delete();

        if ($adminRolId > 0
            && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $adminRolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $adminRolId,
            ]);
        }
    }

    /** @return list<int> */
    private function resolverRolIdsPrevios(): array
    {
        $ids = [];
        foreach (self::ROLES_PREVIOS as $rol) {
            $q = DB::table('rol');
            if (! empty($rol['like'])) {
                $q->where('nombre', 'like', $rol['like']);
            } else {
                $q->where('nombre', $rol['nombre']);
            }
            foreach ($q->pluck('id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
};
