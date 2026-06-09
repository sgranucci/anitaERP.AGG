<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROL_GER = 'Ger-Tesoreria';

    /** Consulta de asientos desde cierre Waitry y módulo contable. */
    private const PERMISOS = [
        'listar-asiento',
        'editar-asiento',
    ];

    public function up(): void
    {
        $rolGerId = $this->resolverRolGerId();
        if ($rolGerId <= 0) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolGerId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolGerId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolGerId = $this->resolverRolGerId();
        if ($rolGerId <= 0) {
            return;
        }

        foreach (self::PERMISOS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolGerId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverRolGerId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_GER)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Ger-Tesorer%')->orderBy('id')->value('id') ?? 0);
    }
};
