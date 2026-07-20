<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Familiares a cargo del empleado para cantidades del plan de Ganancias
 * (cantidad("CONYUGE"), cantidad("HIJOS"), etc.). Independiente de SiRADIG:
 * SiRADIG declara deducciones F572; esta tabla alimenta el motor del plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleado_familiar_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            // CONYUGE | HIJOS | HIJOS_50 | HIJO_INCAP  (clave de cantidad() en el plan)
            $table->string('tipo', 20);
            $table->string('apellido', 60)->nullable();
            $table->string('nombre', 60)->nullable();
            $table->string('documento', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedTinyInteger('porcentaje_deduccion')->default(100); // 50 o 100
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->index(['empleado_id', 'activo', 'tipo'], 'emp_fam_emp_act_tipo_ix');
            $table->foreign('empleado_id')
                ->references('id')->on('empleado_sueldos')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleado_familiar_sueldos');
    }
};
