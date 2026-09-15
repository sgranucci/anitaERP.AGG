<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Op-Pagos (frodriguez, igongora y resto CxP): listar-ordencompra para abrir
 * la OC en modo consulta desde "Ver OC" en carga de factura.
 * No asigna el menú de OC (solo el permiso de lectura).
 */
return new class extends Migration
{
    private const ROL = 'Op-Pagos';

    private const SLUG = 'listar-ordencompra';

    /** @var list<string> */
    private const USUARIOS = [
        'frodriguez',
        'igongora',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->where('nombre', self::ROL)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Por si el rol se llama distinto: roles de los usuarios pedidos.
        $rolIdsUsuarios = DB::table('usuario_rol')
            ->join('usuario', 'usuario.id', '=', 'usuario_rol.usuario_id')
            ->whereIn('usuario.usuario', self::USUARIOS)
            ->pluck('usuario_rol.rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsUsuarios)));

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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = DB::table('usuario_rol')
            ->join('usuario', 'usuario.id', '=', 'usuario_rol.usuario_id')
            ->whereIn('usuario.usuario', self::USUARIOS)
            ->pluck('usuario_rol.rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $opPagosId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($opPagosId > 0) {
            $rolIds[] = $opPagosId;
        }
        $rolIds = array_values(array_unique($rolIds));

        if ($rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
