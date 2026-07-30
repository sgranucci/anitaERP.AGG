<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suss_presentacion_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedSmallInteger('codigo_impuesto')->default(353);
            $table->unsignedSmallInteger('codigo_regimen')->nullable();
            $table->string('frecuencia', 20)->default('quincenal');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('suss_presentacion_config_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('suss_presentacion_config_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->timestamps();

            $table->foreign('suss_presentacion_config_id', 'fk_suss_pres_cuenta_config')
                ->references('id')->on('suss_presentacion_config')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_suss_pres_cuenta_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_suss_pres_cuenta_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unique(
                ['suss_presentacion_config_id', 'empresa_id', 'cuentacontable_id'],
                'uq_suss_pres_config_cuenta'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suss_presentacion_config_cuenta');
        Schema::dropIfExists('suss_presentacion_config');
    }
};
