<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flash_reporte_suscripcion')) {
            return;
        }

        Schema::create('flash_reporte_suscripcion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 160);
            $table->boolean('activo')->default(true);

            $table->string('periodicidad', 20)->default('diaria');
            $table->unsignedTinyInteger('dia_mes')->default(5);
            $table->unsignedTinyInteger('dia_semana')->default(1);
            $table->string('hora', 5)->default('16:00');

            $table->string('periodo_relativo', 20)->default('mes_actual');
            $table->string('mes_fijo', 7)->nullable();

            $table->text('destinatarios')->nullable();
            $table->text('mensaje')->nullable();

            $table->timestamp('ultima_ejecucion')->nullable();
            $table->string('ultimo_estado', 20)->nullable();
            $table->text('ultimo_mensaje')->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index(['activo', 'periodicidad'], 'frs_activo_periodicidad_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_reporte_suscripcion');
    }
};
