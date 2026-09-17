<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: slug varchar(50) truncaba can() largos; ampliar y consolidar duplicados.
 */
return new class extends Migration
{
    /** @var array<string, string> truncated => full */
    private const SLUGS_FULL = [
        'actualizar-configuracion-puntoventa-estacionamient' => 'actualizar-configuracion-puntoventa-estacionamiento',
        'ver-comprobante-maquinavending-rendicion-gastronom' => 'ver-comprobante-maquinavending-rendicion-gastronomia',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::table('permiso', function (Blueprint $table) {
            $table->string('slug', 80)->change();
        });

        foreach (self::SLUGS_FULL as $trunc => $full) {
            $ids = DB::table('permiso')
                ->where('slug', $trunc)
                ->orWhere('slug', $full)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($ids === []) {
                continue;
            }

            $keepId = $ids[0];
            $extraIds = array_slice($ids, 1);

            DB::table('permiso')->where('id', $keepId)->update([
                'slug' => $full,
                'updated_at' => now(),
            ]);

            foreach ($extraIds as $extraId) {
                $rolIds = DB::table('permiso_rol')->where('permiso_id', $extraId)->pluck('rol_id');
                foreach ($rolIds as $rolId) {
                    $existe = DB::table('permiso_rol')
                        ->where('permiso_id', $keepId)
                        ->where('rol_id', (int) $rolId)
                        ->exists();
                    if (! $existe) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $keepId,
                            'rol_id' => (int) $rolId,
                        ]);
                    }
                }
                DB::table('permiso_rol')->where('permiso_id', $extraId)->delete();
                DB::table('permiso')->where('id', $extraId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        foreach (self::SLUGS_FULL as $trunc => $full) {
            DB::table('permiso')->where('slug', $full)->update([
                'slug' => $trunc,
                'updated_at' => now(),
            ]);
        }

        Schema::table('permiso', function (Blueprint $table) {
            $table->string('slug', 50)->change();
        });

        SuitecrmPermiso::flushCachePermisos();
    }
};
