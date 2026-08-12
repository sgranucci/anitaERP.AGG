<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reportes contables definibles (Anita infomae/infomov/infocta/infoccos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reporte_contable', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique()->comment('Nro. informe Anita / código ERP');
            $table->string('nombre', 80);
            $table->string('titulo1', 80)->nullable();
            $table->string('titulo2', 80)->nullable();
            $table->string('tipo', 20)->default('otro')->comment('balance|resultado|otro');
            $table->string('origen', 20)->default('manual')->comment('manual|anita');
            $table->unsignedInteger('anita_informe')->nullable()->index();
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        Schema::create('reporte_contable_rubro', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_id');
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('codigo_linea', 20)->nullable();
            $table->string('nombre', 80);
            $table->unsignedTinyInteger('nivel')->default(1);
            $table->unsignedInteger('orden')->default(0);
            $table->string('tipo', 20)->default('cuentas')->comment('cuentas|total|formula|texto');
            $table->string('formula', 255)->nullable();
            $table->boolean('estilo_negrita')->default(false);
            $table->boolean('estilo_subrayado')->default(false);
            $table->boolean('mostrar_total')->default(true);
            $table->unsignedInteger('anita_rubro')->nullable();
            $table->timestamps();

            $table->foreign('reporte_contable_id')
                ->references('id')->on('reporte_contable')->cascadeOnDelete();
            $table->foreign('parent_id')
                ->references('id')->on('reporte_contable_rubro')->nullOnDelete();
            $table->index(['reporte_contable_id', 'orden']);
        });

        Schema::create('reporte_contable_cuenta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_rubro_id');
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->unsignedBigInteger('cuentacontable_id')->nullable()->index();
            $table->unsignedBigInteger('codigo_cuenta')->comment('Código Anita/ERP numérico');
            $table->char('origen', 1)->default('R')->comment('R=real P=presupuesto');
            $table->smallInteger('signo')->default(1)->comment('1 o -1');
            $table->char('carga_ccosto', 1)->default('S')->comment('S=sin R=rango P=particular');
            $table->unsignedInteger('sucursal')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('reporte_contable_rubro_id')
                ->references('id')->on('reporte_contable_rubro')->cascadeOnDelete();
            $table->index(['reporte_contable_rubro_id', 'codigo_cuenta'], 'rd_cta_rubro_codigo_idx');
        });

        Schema::create('reporte_contable_ccosto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reporte_contable_cuenta_id');
            $table->unsignedInteger('ccosto_desde')->default(0);
            $table->unsignedInteger('ccosto_hasta')->default(0);
            $table->unsignedBigInteger('centrocosto_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('reporte_contable_cuenta_id')
                ->references('id')->on('reporte_contable_cuenta')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reporte_contable_ccosto');
        Schema::dropIfExists('reporte_contable_cuenta');
        Schema::dropIfExists('reporte_contable_rubro');
        Schema::dropIfExists('reporte_contable');
    }
};
