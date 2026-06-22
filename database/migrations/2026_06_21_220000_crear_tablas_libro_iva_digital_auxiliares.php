<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('libro_iva_importacion_bien')) {
            Schema::create('libro_iva_importacion_bien', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->date('fecha_oficializacion');
                $table->string('despacho', 16);
                $table->decimal('importe_total', 15, 2)->default(0);
                $table->decimal('neto_gravado', 15, 2)->default(0);
                $table->string('alicuota_lid', 4)->default('0005');
                $table->decimal('impuesto_liquidado', 15, 2)->default(0);
                $table->string('codigo_operacion', 1)->nullable();
                $table->string('codigo_moneda', 3)->default('PES');
                $table->decimal('tipo_cambio', 12, 6)->default(1);
                $table->string('observacion', 255)->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'fecha_oficializacion']);
            });
        }

        if (! Schema::hasTable('libro_iva_importacion_servicio')) {
            Schema::create('libro_iva_importacion_servicio', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedTinyInteger('tipo_comprobante')->default(1);
                $table->string('descripcion', 20)->nullable();
                $table->string('identificacion_comprobante', 20);
                $table->date('fecha_operacion');
                $table->decimal('monto_moneda_original', 15, 2)->default(0);
                $table->string('codigo_moneda', 3)->default('PES');
                $table->decimal('tipo_cambio', 12, 6)->default(1);
                $table->string('cuit_prestador', 11);
                $table->string('nif_prestador', 20)->nullable();
                $table->string('nombre_prestador', 30);
                $table->string('alicuota_lid', 4)->default('0005');
                $table->date('fecha_ingreso_impuesto');
                $table->decimal('monto_impuesto_ingresado', 15, 2)->default(0);
                $table->decimal('impuesto_computable', 15, 2)->default(0);
                $table->string('identificacion_pago', 20)->nullable();
                $table->string('cuit_entidad_pago', 11)->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'fecha_operacion']);
            });
        }

        if (! Schema::hasTable('libro_iva_ajuste_dj')) {
            Schema::create('libro_iva_ajuste_dj', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedSmallInteger('anio');
                $table->unsignedTinyInteger('mes');
                $table->string('tipo', 50);
                $table->decimal('importe', 15, 2)->default(0);
                $table->decimal('importe_computable', 15, 2)->nullable();
                $table->decimal('neto_gravado', 15, 2)->nullable();
                $table->string('observacion', 255)->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'anio', 'mes']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('libro_iva_ajuste_dj');
        Schema::dropIfExists('libro_iva_importacion_servicio');
        Schema::dropIfExists('libro_iva_importacion_bien');
    }
};
