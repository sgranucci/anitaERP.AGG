<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatos de origen para recibos importados (auxconf/auxconfh)
 * y bitácora de importaciones confidenciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidacion_recibo_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidacion_recibo_sueldos', 'origen')) {
                $table->string('origen', 30)->default('motor_erp')->after('estado');
            }
            if (! Schema::hasColumn('liquidacion_recibo_sueldos', 'confidencial')) {
                $table->boolean('confidencial')->default(false)->after('origen');
            }
            if (! Schema::hasColumn('liquidacion_recibo_sueldos', 'origen_fingerprint')) {
                $table->string('origen_fingerprint', 64)->nullable()->after('confidencial');
            }
        });

        Schema::table('liquidacion_detalle_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('liquidacion_detalle_sueldos', 'origen_tabla')) {
                $table->string('origen_tabla', 20)->nullable()->after('leyenda');
            }
            if (! Schema::hasColumn('liquidacion_detalle_sueldos', 'origen_serial')) {
                $table->integer('origen_serial')->nullable()->after('origen_tabla');
            }
            if (! Schema::hasColumn('liquidacion_detalle_sueldos', 'origen_nro_interno')) {
                $table->integer('origen_nro_interno')->nullable()->after('origen_serial');
            }
            if (! Schema::hasColumn('liquidacion_detalle_sueldos', 'origen_clave')) {
                $table->string('origen_clave', 64)->nullable()->after('origen_nro_interno');
            }
        });

        if (! Schema::hasTable('liquidacion_importacion_sueldos')) {
            Schema::create('liquidacion_importacion_sueldos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('liquidacion_id');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('fuente', 20);
                $table->string('plan_hash', 64);
                $table->unsignedInteger('empresa_anita');
                $table->unsignedInteger('liquidacion_anita');
                $table->unsignedInteger('filas')->default(0);
                $table->unsignedInteger('recibos_creados')->default(0);
                $table->unsignedInteger('recibos_actualizados')->default(0);
                $table->unsignedInteger('recibos_iguales')->default(0);
                $table->unsignedInteger('empleados_marcados')->default(0);
                $table->json('resumen')->nullable();
                $table->timestamps();

                $table->foreign('liquidacion_id')->references('id')->on('liquidacion_sueldos')->cascadeOnDelete();
                $table->index(['liquidacion_id', 'created_at']);
            });
        }

        try {
            Schema::table('liquidacion_recibo_sueldos', function (Blueprint $table) {
                $table->index(['legajo'], 'liqrecibo_legajo_idx');
            });
        } catch (\Throwable) {
            // índice ya existente
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_importacion_sueldos');

        Schema::table('liquidacion_detalle_sueldos', function (Blueprint $table) {
            foreach (['origen_clave', 'origen_nro_interno', 'origen_serial', 'origen_tabla'] as $col) {
                if (Schema::hasColumn('liquidacion_detalle_sueldos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('liquidacion_recibo_sueldos', function (Blueprint $table) {
            foreach (['origen_fingerprint', 'confidencial', 'origen'] as $col) {
                if (Schema::hasColumn('liquidacion_recibo_sueldos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
