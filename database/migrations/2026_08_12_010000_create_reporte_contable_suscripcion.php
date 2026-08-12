<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reporte_contable_suscripcion')) {
            return;
        }

        Schema::create('reporte_contable_suscripcion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->string('nombre', 160);
            $table->boolean('activo')->default(true);

            // Cuándo sale
            $table->string('periodicidad', 20)->default('mensual'); // mensual | semanal | diaria
            $table->unsignedTinyInteger('dia_mes')->default(5);     // 1..28 (mensual)
            $table->unsignedTinyInteger('dia_semana')->default(1);  // 1=lunes .. 7=domingo (semanal)
            $table->string('hora', 5)->default('07:00');

            // Qué sale
            $table->json('filtros');
            $table->string('periodo_relativo', 20)->default('mes_anterior'); // mes_anterior | mes_actual | fijo
            $table->string('formato', 10)->default('pdf');                   // pdf | excel | ambos
            $table->boolean('publicar')->default(false);
            $table->boolean('solo_si_alertas')->default(false);

            // A quién
            $table->text('destinatarios')->nullable();
            $table->json('usuario_ids')->nullable();
            $table->text('mensaje')->nullable();

            // Trazabilidad de la última corrida
            $table->timestamp('ultima_ejecucion')->nullable();
            $table->string('ultimo_estado', 20)->nullable(); // ok | error | omitida
            $table->text('ultimo_mensaje')->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['reporte_contable_id', 'activo'], 'rcs_reporte_activo_idx');
            $table->index(['activo', 'periodicidad'], 'rcs_activo_periodicidad_idx');
        });

        Schema::table('reporte_contable_suscripcion', function (Blueprint $table) {
            if (Schema::hasTable('reporte_contable')) {
                $table->foreign('reporte_contable_id', 'rcs_reporte_fk')
                    ->references('id')->on('reporte_contable')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_suscripcion');
    }
};
