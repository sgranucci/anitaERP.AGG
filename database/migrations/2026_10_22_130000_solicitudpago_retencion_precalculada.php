<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones opcionales de una solicitud anticipada.
 * Se calculan en la SP y la OPA de ingresos/egresos las toma al pagar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudpago', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitudpago', 'retencion_activa')) {
                $table->boolean('retencion_activa')->default(false)->after('monto');
                $table->string('retencion_modo', 20)->nullable()->after('retencion_activa');
                $table->decimal('retencion_neto_gravado', 18, 2)->nullable()->after('retencion_modo');
                $table->decimal('retencion_tasa_iva', 8, 4)->nullable()->after('retencion_neto_gravado');
                $table->decimal('retencion_importe_iva', 18, 2)->nullable()->after('retencion_tasa_iva');
                $table->dateTime('retencion_calculada_en')->nullable()->after('retencion_importe_iva');
            }
        });

        if (! Schema::hasTable('solicitudpago_retencion')) {
            Schema::create('solicitudpago_retencion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('solicitudpago_id');
                $table->string('tiporetencion', 1);
                $table->unsignedBigInteger('retencionganancia_id')->nullable();
                $table->unsignedBigInteger('retencioniva_id')->nullable();
                $table->unsignedBigInteger('retencionsuss_id')->nullable();
                $table->unsignedBigInteger('provincia_id')->nullable();
                $table->string('codigo_regimen', 20)->nullable();
                $table->string('codigo_retencion', 20)->nullable();
                $table->decimal('base_calculo', 18, 2)->default(0);
                $table->decimal('alicuota', 12, 4)->default(0);
                $table->decimal('importe', 18, 2)->default(0);
                $table->json('detalle_calculo')->nullable();
                $table->string('motivo', 80)->nullable();
                $table->timestamps();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';

                $table->foreign('solicitudpago_id', 'fk_sp_ret_sp')
                    ->references('id')->on('solicitudpago')->cascadeOnDelete()->cascadeOnUpdate();
                $table->index('solicitudpago_id', 'sp_ret_sp_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudpago_retencion');

        Schema::table('solicitudpago', function (Blueprint $table) {
            foreach ([
                'retencion_calculada_en',
                'retencion_importe_iva',
                'retencion_tasa_iva',
                'retencion_neto_gravado',
                'retencion_modo',
                'retencion_activa',
            ] as $columna) {
                if (Schema::hasColumn('solicitudpago', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
