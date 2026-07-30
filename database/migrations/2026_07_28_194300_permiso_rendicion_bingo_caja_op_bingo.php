<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Operadores de bingo cargan las rendiciones: crear, listar e imprimir.
 */
return new class extends Migration
{
    private const ROL = 'op-Bingo';

    /** @var list<string> */
    private const PERMISOS = [
        'crear-rendicion-bingo-caja',
        'listar-rendicion-bingo-caja',
        'imprimir-rendicion-bingo-caja',
    ];

    public function up(): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
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
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISOS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->whereIn('permiso_id', $permisoIds)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
