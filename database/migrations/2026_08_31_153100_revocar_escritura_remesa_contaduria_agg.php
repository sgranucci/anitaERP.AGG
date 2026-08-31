<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: asegura solo lectura de remesas en contaduría (revoca escritura residual).
 * Complemento de 2026_08_31_153000 si esa corrida no incluía el revoke.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS_ESCRITURA = [
        'crear-remesa',
        'editar-remesa',
        'actualizar-remesa',
        'anular-remesa',
        'configurar-remesa',
        'revertir-remesa',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = [];
        foreach (['Enc-contadur%', 'Op-contadur%', 'Sup-contadur%', 'Ger-contadur%'] as $like) {
            foreach (DB::table('rol')->where('nombre', 'like', $like)->pluck('id') as $id) {
                $rolIds[] = (int) $id;
            }
        }
        $rolIds = array_values(array_unique(array_filter($rolIds)));

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS_ESCRITURA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($rolIds === [] || $permisoIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No reponer escritura: el estado previo no es el deseado para contaduría.
    }
};
