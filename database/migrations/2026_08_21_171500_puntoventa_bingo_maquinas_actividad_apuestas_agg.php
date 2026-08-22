<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PV de cierre FBI/FSL: actividad ARCA 920009 (Servicios de apuestas)
 * para que IVA Simple no los mezcle con gastronomía.
 */
return new class extends Migration
{
    /** @var list<array{empresa_id: int, codigo: string}> */
    private const PVS = [
        ['empresa_id' => 1, 'codigo' => '00039'], // Biyemas
        ['empresa_id' => 2, 'codigo' => '00026'], // Kandiko
        ['empresa_id' => 3, 'codigo' => '00014'], // Rebisco
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('puntoventa') || ! Schema::hasTable('actividad_arca')) {
            return;
        }

        $actividadId = (int) (DB::table('actividad_arca')
            ->where('codigoarca', '920009')
            ->value('id') ?? 0);

        if ($actividadId <= 0) {
            return;
        }

        foreach (self::PVS as $pv) {
            DB::table('puntoventa')
                ->where('empresa_id', $pv['empresa_id'])
                ->where('codigo', $pv['codigo'])
                ->whereNull('deleted_at')
                ->update([
                    'actividad_arca_id' => $actividadId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        foreach (self::PVS as $pv) {
            DB::table('puntoventa')
                ->where('empresa_id', $pv['empresa_id'])
                ->where('codigo', $pv['codigo'])
                ->whereNull('deleted_at')
                ->update([
                    'actividad_arca_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }
};
