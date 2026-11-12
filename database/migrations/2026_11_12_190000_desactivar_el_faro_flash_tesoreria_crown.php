<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El Faro (EFA) cerró: deja de operar en facturación de juego (Flash).
 * La exclusión en chips/reportes/tesorería vive en TesoreriaCrownSalaSupport::CODIGOS_CERRADAS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('facturacion_juego_sala') || ! Schema::hasTable('sala')) {
            return;
        }

        $salaId = (int) DB::table('sala')->where('codigo', 'EFA')->value('id');
        if ($salaId <= 0) {
            return;
        }

        DB::table('facturacion_juego_sala')
            ->where('sala_id', $salaId)
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('facturacion_juego_sala') || ! Schema::hasTable('sala')) {
            return;
        }

        $salaId = (int) DB::table('sala')->where('codigo', 'EFA')->value('id');
        if ($salaId <= 0) {
            return;
        }

        DB::table('facturacion_juego_sala')
            ->where('sala_id', $salaId)
            ->update([
                'activo' => true,
                'updated_at' => now(),
            ]);
    }
};
