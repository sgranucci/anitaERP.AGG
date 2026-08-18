<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('empresa_id');
            $table->foreign('owner_id', 'rsd_owner_fk')
                ->references('id')->on('usuario')->nullOnDelete();
        });

        Schema::create('reporte_sueldos_definible_envio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('suscripcion_id');
            $table->unsignedBigInteger('ejecucion_id')->nullable();
            $table->string('destinatario');
            $table->string('burst_clave')->nullable();
            $table->string('burst_etiqueta')->nullable();
            $table->string('estado')->default('pendiente')
                ->comment('pendiente|enviado|error');
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->index('suscripcion_id', 'rsd_envio_suscripcion_idx');
            $table->index('estado', 'rsd_envio_estado_idx');
            $table->foreign('suscripcion_id', 'rsd_envio_suscripcion_fk')
                ->references('id')->on('reporte_sueldos_definible_suscripcion')->cascadeOnDelete();
            $table->foreign('ejecucion_id', 'rsd_envio_ejecucion_fk')
                ->references('id')->on('reporte_sueldos_definible_ejecucion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_sueldos_definible_envio');

        Schema::table('reporte_sueldos_definible', function (Blueprint $table) {
            $table->dropForeign('rsd_owner_fk');
            $table->dropColumn('owner_id');
        });
    }
};
