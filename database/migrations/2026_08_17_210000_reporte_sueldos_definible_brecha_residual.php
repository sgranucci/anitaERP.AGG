<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brecha residual: acta de certificación de paridad + webhooks RaaS-light.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_sueldos_definible_certificacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->unsignedBigInteger('liquidacion_id');
            $table->unsignedBigInteger('ejecucion_id');
            $table->string('nomina', 20); // normal|confidencial|ambos
            $table->string('estado', 20)->default('certificada'); // certificada|revocada
            $table->decimal('max_diferencia', 20, 4)->default(0);
            $table->unsignedInteger('columnas_ok')->default(0);
            $table->unsignedInteger('columnas_dif')->default(0);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('certificada_at')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_cert_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->foreign('ejecucion_id', 'rsd_cert_ejecucion_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->cascadeOnDelete();
            $table->foreign('liquidacion_id', 'rsd_cert_liquidacion_fk')
                ->references('id')->on('liquidacion_sueldos')->cascadeOnDelete();
            $table->index(
                ['reporte_sueldos_definible_id', 'liquidacion_id', 'nomina', 'estado'],
                'rsd_cert_vigente_idx'
            );
        });

        Schema::create('reporte_sueldos_definible_webhook', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_sueldos_definible_id');
            $table->string('url', 500);
            $table->string('secret', 120);
            $table->json('eventos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('reporte_sueldos_definible_id', 'rsd_webhook_reporte_fk')
                ->references('id')->on('reporte_sueldos_definible')->cascadeOnDelete();
            $table->index(['reporte_sueldos_definible_id', 'activo'], 'rsd_webhook_activo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_sueldos_definible_webhook');
        Schema::dropIfExists('reporte_sueldos_definible_certificacion');
    }
};
