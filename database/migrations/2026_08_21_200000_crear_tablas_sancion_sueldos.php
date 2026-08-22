<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expediente disciplinario de sueldos (Anita: sancion / motivosanc / empsanc / empsley).
 * Sin SoftDeletes: baja física + audits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_sancion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 60);
            $table->string('clase', 20)->default('otro');
            $table->boolean('requiere_dias')->default(false);
            $table->unsignedSmallInteger('tope_dias')->nullable();
            $table->string('tipo_dias', 10)->default('corridos');
            $table->boolean('goza_sueldo')->default(false);
            $table->boolean('genera_novedad')->default(false);
            $table->unsignedBigInteger('concepto_id')->nullable();
            $table->unsignedSmallInteger('orden_progresivo')->default(1);
            $table->unsignedSmallInteger('plazo_descargo_dias')->default(2);
            $table->text('plantilla_notificacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['clase', 'activo']);
            $table->foreign('concepto_id')->references('id')->on('concepto_sueldos')->nullOnDelete();
        });

        Schema::create('motivo_sancion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('codigo')->unique();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'nombre']);
        });

        Schema::create('empleado_sancion_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empleado_id');
            $table->unsignedBigInteger('tipo_sancion_id');
            $table->unsignedBigInteger('motivo_sancion_id');
            $table->date('fecha_hecho');
            $table->date('fecha_desde')->nullable();
            $table->date('fecha_hasta')->nullable();
            $table->unsignedSmallInteger('cant_dias')->default(0);
            $table->string('tipo_dias', 10)->default('corridos');
            $table->decimal('importe_perdida', 14, 2)->default(0);
            $table->date('fecha_notificacion')->nullable();
            $table->date('fecha_recepcion')->nullable();
            $table->string('estado', 20)->default('borrador');
            $table->text('comentario');
            $table->text('descargo_texto')->nullable();
            $table->date('descargo_fecha')->nullable();
            $table->text('resolucion_texto')->nullable();
            $table->date('resolucion_fecha')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedInteger('nro_interno')->nullable();
            $table->unsignedInteger('anita_nro_interno')->nullable();
            $table->timestamps();

            $table->index(['empleado_id', 'fecha_hecho']);
            $table->index(['empleado_id', 'estado']);
            $table->index(['tipo_sancion_id', 'motivo_sancion_id']);
            $table->unique('anita_nro_interno', 'empsanc_anita_nro_uq');
            $table->foreign('empleado_id')->references('id')->on('empleado_sueldos')->cascadeOnDelete();
            $table->foreign('tipo_sancion_id')->references('id')->on('tipo_sancion_sueldos');
            $table->foreign('motivo_sancion_id')->references('id')->on('motivo_sancion_sueldos');
            $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();
        });

        Schema::create('empleado_sancion_archivo_sueldos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sancion_id');
            $table->string('nombre_original', 255);
            $table->string('path', 255);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->index('sancion_id');
            $table->foreign('sancion_id')->references('id')->on('empleado_sancion_sueldos')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuario')->nullOnDelete();
        });

        if (Schema::hasTable('novedad_sueldos') && ! Schema::hasColumn('novedad_sueldos', 'sancion_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->unsignedBigInteger('sancion_id')->nullable();
                $table->unique('sancion_id', 'novedad_sancion_uq');
                $table->foreign('sancion_id')
                    ->references('id')
                    ->on('empleado_sancion_sueldos')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('novedad_sueldos') && Schema::hasColumn('novedad_sueldos', 'sancion_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->dropForeign(['sancion_id']);
                $table->dropUnique('novedad_sancion_uq');
                $table->dropColumn('sancion_id');
            });
        }

        Schema::dropIfExists('empleado_sancion_archivo_sueldos');
        Schema::dropIfExists('empleado_sancion_sueldos');
        Schema::dropIfExists('motivo_sancion_sueldos');
        Schema::dropIfExists('tipo_sancion_sueldos');
    }
};
