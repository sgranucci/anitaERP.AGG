<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iibb_presentacion_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provincia_id');
            $table->string('tipo', 20);
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->unsignedTinyInteger('codigo_actividad_arba')->nullable();
            $table->string('frecuencia', 20)->default('quincenal');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('provincia_id', 'fk_iibb_pres_config_provincia')
                ->references('id')->on('provincia')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->unique(['provincia_id', 'tipo'], 'uq_iibb_pres_config_provincia_tipo');
        });

        Schema::create('iibb_presentacion_config_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('iibb_presentacion_config_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('iibb_presentacion_config_id', 'fk_iibb_pres_cuenta_config')
                ->references('id')->on('iibb_presentacion_config')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_iibb_pres_cuenta_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_iibb_pres_cuenta_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unique(
                ['iibb_presentacion_config_id', 'empresa_id', 'cuentacontable_id'],
                'uq_iibb_pres_config_cuenta'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iibb_presentacion_config_cuenta');
        Schema::dropIfExists('iibb_presentacion_config');
    }
};
