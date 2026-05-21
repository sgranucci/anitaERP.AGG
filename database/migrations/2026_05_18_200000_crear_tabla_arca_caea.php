<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('arca_caea')) {
            return;
        }

        Schema::create('arca_caea', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('periodo');
            $table->unsignedTinyInteger('orden');
            $table->string('cuit', 11);
            $table->string('nro_caea', 14)->nullable();
            $table->date('fecha_vigencia_desde')->nullable();
            $table->date('fecha_vigencia_hasta')->nullable();
            $table->date('fecha_tope_informe')->nullable();
            $table->dateTime('fecha_proceso')->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('origen', 20)->default('automatico');
            $table->unsignedBigInteger('solicitado_por_usuario_id')->nullable();
            $table->string('codigo_error', 20)->nullable();
            $table->text('mensaje_error')->nullable();
            $table->json('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('empresa_id', 'fk_arca_caea_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('solicitado_por_usuario_id', 'fk_arca_caea_usuario')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');

            $table->unique(['empresa_id', 'periodo', 'orden'], 'uq_arca_caea_empresa_periodo_orden');
            $table->index(['periodo', 'orden'], 'idx_arca_caea_periodo_orden');
            $table->index('estado', 'idx_arca_caea_estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arca_caea');
    }
};
