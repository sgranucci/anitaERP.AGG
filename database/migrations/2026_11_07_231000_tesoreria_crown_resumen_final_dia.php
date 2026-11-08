<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot diario del resumen final del Anexo VIII (pre-depósito, cajas chicas, etc.).
 * Evita depender del historial completo de bóveda para el acumulado ≤ fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_resumen_final_dia')) {
            Schema::create('tesoreria_crown_resumen_final_dia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->date('fecha');
                $table->unsignedInteger('codigo'); // grupo: 1 predep, 3 fondo, …
                $table->decimal('monto', 16, 2)->default(0);
                $table->string('fuente', 16)->default('anita');
                $table->timestamps();
                $table->unique(['sala_id', 'fecha', 'codigo'], 'tc_resumen_final_dia_uq');
                $table->index(['sala_id', 'fecha'], 'tc_resumen_final_dia_idx');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_resumen_final_dia');
    }
};
