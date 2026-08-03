<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Grupos de conceptos (Anita: grupo + emp_grp1/2/3) + elegibilidad + asignación explícita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_concepto_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable(); // null = todas
            $table->unsignedInteger('codigo');
            $table->string('descripcion', 80)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('origen', 20)->default('manual'); // manual|sync_anita
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'grupo_conc_emp_cod_uq');
            $table->index(['codigo'], 'grupo_conc_codigo_idx');
            $table->foreign('empresa_id')->references('id')->on('empresa')->nullOnDelete();
        });

        Schema::create('grupo_concepto_item_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('grupo_concepto_id');
            $table->unsignedBigInteger('concepto_id');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['grupo_concepto_id', 'concepto_id'], 'grupo_item_uq');
            $table->foreign('grupo_concepto_id')->references('id')->on('grupo_concepto_sueldos')->cascadeOnDelete();
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->cascadeOnDelete();
        });

        Schema::create('concepto_elegibilidad_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_id');
            // sindicato_codigo|obrasocial_codigo|categoria_codigo|agrupamiento_codigo|empresa_id|sindicato_id|...
            $table->string('campo', 40);
            $table->string('operador', 20)->default('igual'); // igual|distinto|en|vacio|no_vacio
            $table->string('valor', 255)->nullable(); // para 'en': 1,2,3
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['concepto_id', 'activo'], 'conc_eleg_conc_idx');
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->cascadeOnDelete();
        });

        Schema::create('empleado_concepto_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('concepto_id');
            $table->string('accion', 10); // incluir|excluir
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->string('origen', 20)->default('manual');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'accion'], 'emp_conc_emp_acc_idx');
            $table->unique(['empleado_id', 'concepto_id', 'accion'], 'emp_conc_uq');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->cascadeOnDelete();
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->cascadeOnDelete();
        });

        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->unsignedBigInteger('grupo_concepto_1_id')->nullable()->after('art_id');
            $table->unsignedBigInteger('grupo_concepto_2_id')->nullable()->after('grupo_concepto_1_id');
            $table->unsignedBigInteger('grupo_concepto_3_id')->nullable()->after('grupo_concepto_2_id');
            // Códigos Anita crudos (emp_grp1/2/3) por si el grupo aún no se importó
            $table->unsignedInteger('grupo_concepto_1_codigo')->nullable()->after('grupo_concepto_3_id');
            $table->unsignedInteger('grupo_concepto_2_codigo')->nullable()->after('grupo_concepto_1_codigo');
            $table->unsignedInteger('grupo_concepto_3_codigo')->nullable()->after('grupo_concepto_2_codigo');

            $table->foreign('grupo_concepto_1_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
            $table->foreign('grupo_concepto_2_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
            $table->foreign('grupo_concepto_3_id')->references('id')->on('grupo_concepto_sueldos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('empleado_sueldos', function (Blueprint $table) {
            $table->dropForeign(['grupo_concepto_1_id']);
            $table->dropForeign(['grupo_concepto_2_id']);
            $table->dropForeign(['grupo_concepto_3_id']);
            $table->dropColumn([
                'grupo_concepto_1_id', 'grupo_concepto_2_id', 'grupo_concepto_3_id',
                'grupo_concepto_1_codigo', 'grupo_concepto_2_codigo', 'grupo_concepto_3_codigo',
            ]);
        });
        Schema::dropIfExists('empleado_concepto_sueldos');
        Schema::dropIfExists('concepto_elegibilidad_sueldos');
        Schema::dropIfExists('grupo_concepto_item_sueldos');
        Schema::dropIfExists('grupo_concepto_sueldos');
    }
};
