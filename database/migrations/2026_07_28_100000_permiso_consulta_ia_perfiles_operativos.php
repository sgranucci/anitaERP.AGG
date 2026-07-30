<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el panel FAB (ejecutar-consulta-ia) a Contaduría, Impuestos y Logística.
 * Impuestos también recibe consulta-ia-contable para mayor/saldo/asiento vía IA.
 * Contaduría ya tenía consulta-ia-contable; Logística usa intents de stock/OC/pedido.
 */
return new class extends Migration
{
    private const PERMISO_FAB = 'ejecutar-consulta-ia';

    private const PERMISO_CONTABLE = 'consulta-ia-contable';

    /** @var list<string> */
    private const ROLES_FAB = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Enc-impuestos',
        'Op-impuestos',
        'Enc-logistica',
        'op-Logistica',
    ];

    /** @var list<string> */
    private const ROLES_CONTABLE = [
        'administrador',
        'Enc-contaduría',
        'Op-contaduria',
        'Enc-impuestos',
        'Op-impuestos',
    ];

    public function up(): void
    {
        $this->asignarPermisoARoles(self::PERMISO_FAB, self::ROLES_FAB);
        $this->asignarPermisoARoles(self::PERMISO_CONTABLE, self::ROLES_CONTABLE);
        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @param  list<string>  $roles
     */
    private function asignarPermisoARoles(string $slug, array $roles): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach ($this->resolverRolIds($roles) as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function down(): void
    {
        // No revoca: otros entornos pueden haber asignado los mismos roles a mano.
        SuitecrmPermiso::flushCachePermisos();
    }
};
