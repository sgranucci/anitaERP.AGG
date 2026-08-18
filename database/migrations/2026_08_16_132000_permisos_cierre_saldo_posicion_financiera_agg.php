<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISOS = [
        'confirmar-saldo-posicion-financiera' => 'Confirmar saldo de posición financiera',
        'anular-saldo-posicion-financiera' => 'Anular saldo de posición financiera',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $permisoListar = DB::table('permiso')->where('slug', 'listar-posicion-financiera')->first();
        if ($permisoListar === null) {
            return;
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', (int) $permisoListar->id)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => (int) $permisoListar->menu_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIds as $rolId) {
                DB::table('permiso_rol')->updateOrInsert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $ids = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
        DB::table('permiso')->whereIn('id', $ids)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
