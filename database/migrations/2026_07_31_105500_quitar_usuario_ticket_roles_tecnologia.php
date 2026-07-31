<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles de tecnología no usan perfil usuario-ticket: administran / atienden
 * tickets de terceros (Adm. de Tickets), no el alcance “solo mis emitidos”.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_TECNOLOGIA = [
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
    ];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', 'usuario-ticket')->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TECNOLOGIA)
            ->pluck('id');

        if ($rolIds->isEmpty()) {
            return;
        }

        DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', 'usuario-ticket')->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        foreach (DB::table('rol')->whereIn('nombre', self::ROLES_TECNOLOGIA)->pluck('id') as $rolId) {
            $rolId = (int) $rolId;
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
