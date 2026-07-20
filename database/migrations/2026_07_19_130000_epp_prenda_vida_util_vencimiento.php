<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EPP / ciclo de vida de la indumentaria:
 *  - prenda_sueldos.vida_util_meses: ventana de reposición (meses). Si está definida,
 *    el cupo se controla por ventana móvil y la entrega calcula la fecha de vencimiento.
 *  - prenda_sueldos.requiere_certificacion / norma: cumplimiento EPP (ej. norma IRAM).
 *  - entrega_prenda_articulo_sueldos.vence_el: vencimiento de la prenda entregada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenda_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('prenda_sueldos', 'vida_util_meses')) {
                $table->unsignedSmallInteger('vida_util_meses')->nullable()->after('es_seguridad');
            }
            if (! Schema::hasColumn('prenda_sueldos', 'requiere_certificacion')) {
                $table->boolean('requiere_certificacion')->default(false)->after('vida_util_meses');
            }
            if (! Schema::hasColumn('prenda_sueldos', 'norma')) {
                $table->string('norma', 80)->nullable()->after('requiere_certificacion');
            }
        });

        Schema::table('entrega_prenda_articulo_sueldos', function (Blueprint $table) {
            if (! Schema::hasColumn('entrega_prenda_articulo_sueldos', 'vence_el')) {
                $table->date('vence_el')->nullable()->after('cantidad');
                $table->index('vence_el');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entrega_prenda_articulo_sueldos', function (Blueprint $table) {
            if (Schema::hasColumn('entrega_prenda_articulo_sueldos', 'vence_el')) {
                $table->dropIndex(['vence_el']);
                $table->dropColumn('vence_el');
            }
        });

        Schema::table('prenda_sueldos', function (Blueprint $table) {
            foreach (['norma', 'requiere_certificacion', 'vida_util_meses'] as $col) {
                if (Schema::hasColumn('prenda_sueldos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
