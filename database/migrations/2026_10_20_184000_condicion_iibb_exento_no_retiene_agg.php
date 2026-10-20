<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: la condición IIBB Exento no retiene, igual que No percibe.
 * El resto de las instalaciones conserva Exento en Retiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('condicionIIBB')) {
            return;
        }

        DB::table('condicionIIBB')
            ->where('id', 3)
            ->where('nombre', 'Exento')
            ->where('formacalculo', 'R')
            ->update([
                'formacalculo' => 'N',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! Schema::hasTable('condicionIIBB')) {
            return;
        }

        DB::table('condicionIIBB')
            ->where('id', 3)
            ->where('nombre', 'Exento')
            ->where('formacalculo', 'N')
            ->update([
                'formacalculo' => 'R',
                'updated_at' => now(),
            ]);
    }
};
