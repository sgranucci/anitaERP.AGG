<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Persiste el SI pozo acumulado bingo (cierre de cada día con actividad).
 * Semilla inicial Biyemas: cierre 31/07/2026 = encabezado Anita p-vtabingo ago/2026 (132024.04).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bingo_pozo_acumulado')) {
            Schema::create('bingo_pozo_acumulado', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha');
                $table->decimal('importe', 16, 2);
                $table->string('origen', 30)->default('cierre');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'fecha'], 'bingo_pozo_acum_empresa_fecha_uk');
                $table->index(['empresa_id', 'fecha'], 'bingo_pozo_acum_empresa_fecha_idx');
                $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            });
        }

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! DB::table('empresa')->where('id', 1)->exists()) {
            return;
        }

        $existe = DB::table('bingo_pozo_acumulado')
            ->where('empresa_id', 1)
            ->whereDate('fecha', '2026-07-31')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('bingo_pozo_acumulado')->insert([
            'empresa_id' => 1,
            'fecha' => '2026-07-31',
            'importe' => 132024.04,
            'origen' => 'semilla_anita',
            'usuario_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bingo_pozo_acumulado');
    }
};
