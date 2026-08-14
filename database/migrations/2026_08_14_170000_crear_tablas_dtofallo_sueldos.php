<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de descuentos/sanciones por fallos (Anita: dtofallo + cierrefallo).
 * Origen de negocio: p-dtofallo.c / l-fallo.c.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cierrefallo_sueldos')) {
            Schema::create('cierrefallo_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('nro_cierre');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedInteger('periodo_descuento'); // YYYYMM inicio del plan
                $table->date('fecha_fallo_desde');
                $table->date('fecha_fallo_hasta');
                $table->unsignedInteger('legajo_desde')->default(1);
                $table->unsignedInteger('legajo_hasta')->default(99999999);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->unsignedInteger('empleados_procesados')->default(0);
                $table->unsignedInteger('movimientos_generados')->default(0);
                $table->unsignedInteger('novedades_generadas')->default(0);
                $table->decimal('total_perdida', 18, 2)->default(0);
                $table->decimal('total_descuento', 18, 2)->default(0);
                $table->decimal('total_sancion', 18, 2)->default(0);
                $table->string('estado', 20)->default('generado'); // generado|anulado
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';

                $table->unique('nro_cierre');
                $table->index(['empresa_id', 'periodo_descuento'], 'cierrefallo_emp_per_idx');
                $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('dtofallo_sueldos')) {
            Schema::create('dtofallo_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('empleado_sueldos_id');
                $table->unsignedBigInteger('cierrefallo_id')->nullable();
                $table->date('fecha');
                $table->unsignedInteger('periodo'); // YYYYMM del movimiento
                $table->string('tipo_oper', 1); // D=descuento S=sancion I=ingreso
                $table->decimal('importe', 18, 2)->default(0);
                $table->string('observacion', 80)->nullable();
                $table->unsignedBigInteger('novedad_id')->nullable();
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';

                $table->index(['empleado_sueldos_id', 'fecha'], 'dtofallo_emp_fecha_idx');
                $table->index(['empresa_id', 'periodo', 'tipo_oper'], 'dtofallo_emp_per_tipo_idx');
                $table->index(['cierrefallo_id'], 'dtofallo_cierre_idx');
                $table->foreign('empresa_id')->references('id')->on('empresa')->cascadeOnDelete();
                $table->foreign('empleado_sueldos_id')->references('id')->on('empleado_sueldos')->cascadeOnDelete();
                $table->foreign('cierrefallo_id')->references('id')->on('cierrefallo_sueldos')->nullOnDelete();
            });
        }

        if (Schema::hasTable('novedad_sueldos') && ! Schema::hasColumn('novedad_sueldos', 'dtofallo_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->unsignedBigInteger('dtofallo_id')->nullable()->after('ausencia_id');
                $table->unique('dtofallo_id', 'novedad_dtofallo_uq');
                $table->foreign('dtofallo_id')
                    ->references('id')
                    ->on('dtofallo_sueldos')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('novedad_sueldos', 'dtofallo_id')) {
            Schema::table('novedad_sueldos', function (Blueprint $table) {
                $table->dropForeign(['dtofallo_id']);
                $table->dropUnique('novedad_dtofallo_uq');
                $table->dropColumn('dtofallo_id');
            });
        }

        Schema::dropIfExists('dtofallo_sueldos');
        Schema::dropIfExists('cierrefallo_sueldos');
    }
};
