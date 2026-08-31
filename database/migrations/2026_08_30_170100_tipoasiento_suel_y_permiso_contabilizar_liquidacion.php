<?php

use App\Support\Sueldos\SueldosAsientoSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipoasiento SUEL + permiso contabilizar-liquidacion-sueldos (CH + Contaduría).
 */
return new class extends Migration
{
    public function up(): void
    {
        $abrev = SueldosAsientoSupport::ABREV_TIPOASIENTO;
        $tipoId = (int) (DB::table('tipoasiento')->where('abreviatura', $abrev)->value('id') ?? 0);
        if ($tipoId === 0) {
            DB::table('tipoasiento')->insert([
                'nombre' => 'Sueldos',
                'abreviatura' => $abrev,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuId = (int) (DB::table('menu')->where('url', 'sueldos/liquidacion')->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', SueldosAsientoSupport::PERMISO_CONTABILIZAR)->value('id') ?? 0);
        $payload = [
            'nombre' => 'Contabilizar liquidacion sueldos',
            'slug' => SueldosAsientoSupport::PERMISO_CONTABILIZAR,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update($payload);
        } else {
            $payload['created_at'] = now();
            $permisoId = (int) DB::table('permiso')->insertGetId($payload);
        }

        foreach ($this->resolverRolIds() as $rolId) {
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
        $permisoId = (int) (DB::table('permiso')->where('slug', SueldosAsientoSupport::PERMISO_CONTABILIZAR)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->orWhere('nombre', 'like', '%ontadur%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }
};
