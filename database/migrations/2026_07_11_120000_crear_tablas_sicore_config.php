<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sicore_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedSmallInteger('codigo_impuesto');
            $table->unsignedSmallInteger('codigo_regimen')->nullable();
            $table->string('nombre', 120);
            $table->string('descripcion', 255)->nullable();
            $table->string('criterio', 30);
            $table->unsignedTinyInteger('codigo_operacion')->default(1);
            $table->string('concilia_con', 30)->default('sicore');
            $table->string('frecuencia', 20)->default('quincenal');
            $table->unsignedTinyInteger('quincena_1_desde')->nullable();
            $table->unsignedTinyInteger('quincena_1_hasta')->nullable();
            $table->unsignedTinyInteger('quincena_2_desde')->nullable();
            $table->unsignedTinyInteger('quincena_2_hasta')->nullable();
            $table->unsignedInteger('concepto_retencion_sueldos')->nullable();
            $table->unsignedInteger('concepto_devolucion_sueldos')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('sicore_config_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sicore_config_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('sicore_config_id', 'fk_sicore_config_cuenta_config')
                ->references('id')->on('sicore_config')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_sicore_config_cuenta_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_sicore_config_cuenta_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->unique(
                ['sicore_config_id', 'empresa_id', 'cuentacontable_id'],
                'uq_sicore_config_cuenta'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sicore_config_cuenta');
        Schema::dropIfExists('sicore_config');
    }
};
